<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\ConversationContextService;
use App\Services\Messaging\ConversationService;
use App\Events\ConversationTyping;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly ConversationContextService $contextService,
    ) {}

    /** GET /conversations — inbox */
    public function index(Request $request)
    {
        $user = $request->user();
        $rows = $this->contextService->inboxFor($user)
            ->sortByDesc(fn ($row) => $row['conversation']->last_message_at ?? $row['conversation']->created_at)
            ->values();

        if ($request->filled('context_type')) {
            $rows = $rows->filter(
                fn ($row) => $row['conversation']->context_type === $request->string('context_type')->toString()
            )->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $total = $rows->count();
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($row) => $this->transformInboxRow($row, $user->id)),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    /** POST /conversations — start or reopen contextual thread */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'context_type' => 'required|in:product,rfq,order',
            'context_id' => 'required|integer|min:1',
            'seller_id' => 'nullable|integer|exists:users,id',
            'message' => 'nullable|string|max:5000',
        ]);

        try {
            $conversation = $this->conversationService->startOrGet(
                $request->user(),
                $validated,
            );
        } catch (AuthorizationException|InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Context not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformConversation($conversation, $request->user()->id),
        ], 201);
    }

    /** GET /conversations/{id} */
    public function show(Request $request, int $id)
    {
        try {
            $conversation = $this->conversationService->loadConversation($id);
            $this->contextService->authorizeParticipant($conversation, $request->user());
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformConversation($conversation, $request->user()->id),
        ]);
    }

    /** GET /conversations/{id}/messages */
    public function messages(Request $request, int $id)
    {
        try {
            $conversation = $this->conversationService->loadConversation($id);
            $this->contextService->authorizeParticipant($conversation, $request->user());
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->whereHas('conversation', function ($query) use ($request) {
                $query->whereHas(
                    'participants',
                    fn ($participant) => $participant->where('user_id', $request->user()->id)
                );
            })
            ->with(['sender:id,name,email', 'attachments'])
            ->orderByDesc('created_at')
            ->paginate(min(50, max(1, (int) $request->query('per_page', 30))));

        $messages->getCollection()->transform(
            fn (Message $message) => $this->transformMessage($message)
        );

        return response()->json(['success' => true, 'data' => $messages]);
    }

    /** POST /conversations/{id}/messages */
    public function sendMessage(Request $request, int $id)
    {
        $validated = $request->validate([
            'body' => 'required_without:attachments|nullable|string|max:5000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:5120|mimes:jpg,jpeg,png,webp,pdf',
        ]);

        try {
            $conversation = $this->conversationService->loadConversation($id);
            $message = $this->conversationService->sendMessage(
                $conversation,
                $request->user(),
                (string) ($validated['body'] ?? ''),
                $request->file('attachments', []),
            );
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformMessage($message),
        ], 201);
    }

    /** PATCH /conversations/{id}/read */
    public function markRead(Request $request, int $id)
    {
        try {
            $conversation = $this->conversationService->loadConversation($id);
            $this->conversationService->markRead($conversation, $request->user());
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Conversation marked as read.']);
    }

    /** POST /conversations/{id}/typing — broadcast typing indicator via Reverb */
    public function typing(Request $request, int $id)
    {
        $validated = $request->validate([
            'is_typing' => 'required|boolean',
        ]);

        try {
            $conversation = $this->conversationService->loadConversation($id);
            $this->contextService->authorizeParticipant($conversation, $request->user());
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        $user = $request->user();
        try {
            broadcast(new ConversationTyping(
                $conversation->id,
                $user->id,
                $user->name,
                (bool) $validated['is_typing'],
            ))->toOthers();
        } catch (Throwable $e) {
            Log::warning('Typing broadcast failed.', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    /** PATCH /conversations/{id}/close */
    public function close(Request $request, int $id)
    {
        try {
            $conversation = $this->conversationService->loadConversation($id);
            $this->contextService->authorizeParticipant($conversation, $request->user());
            $conversation->update(['status' => Conversation::STATUS_CLOSED]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Conversation not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformConversation($conversation->fresh(), $request->user()->id),
        ]);
    }

    private function transformInboxRow(array $row, int $userId): array
    {
        /** @var Conversation $conversation */
        $conversation = $row['conversation'];

        return [
            'id' => $conversation->id,
            'conversation_number' => $conversation->conversation_number,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'context' => $row['context'],
            'last_message_at' => $conversation->last_message_at,
            'last_message_preview' => $conversation->last_message_preview,
            'unread_count' => $row['unread_count'],
            'other_party' => $this->transformOtherParty($row['other_user'] ?? null, $row['other_role'] ?? null),
            'my_role' => $conversation->participantFor($userId)?->role,
        ];
    }

    private function transformConversation(Conversation $conversation, int $userId): array
    {
        $other = $conversation->otherParticipant($userId);

        return [
            'id' => $conversation->id,
            'conversation_number' => $conversation->conversation_number,
            'subject' => $conversation->subject,
            'status' => $conversation->status,
            'context_type' => $conversation->context_type,
            'context_id' => $conversation->context_id,
            'context' => $conversation->contextSummary(),
            'last_message_at' => $conversation->last_message_at,
            'last_message_preview' => $conversation->last_message_preview,
            'unread_count' => $conversation->unreadCountFor($userId),
            'my_role' => $conversation->participantFor($userId)?->role,
            'other_party' => $this->transformOtherParty($other?->user, $other?->role),
            'participants' => $conversation->participants->map(fn ($p) => [
                'user_id' => $p->user_id,
                'role' => $p->role,
                'name' => $p->user?->name,
                'store_name' => $p->user?->sellerProfile?->store_name,
                'last_read_at' => $p->last_read_at,
            ]),
        ];
    }

    private function transformOtherParty($user, ?string $role): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $role,
            'store_name' => $user->sellerProfile?->store_name,
        ];
    }

    private function transformMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ] : null,
            'type' => $message->type,
            'body' => $message->body,
            'metadata' => $message->metadata,
            'attachments' => $message->attachments->map(fn ($file) => [
                'id' => $file->id,
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'size_bytes' => $file->size_bytes,
                'url' => $file->url(),
            ]),
            'created_at' => $message->created_at,
        ];
    }
}
