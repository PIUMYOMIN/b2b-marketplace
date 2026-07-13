<?php

namespace App\Services\Messaging;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    public function __construct(
        private readonly ConversationContextService $contextService,
        private readonly MessageSanitizer $sanitizer,
        private readonly ExpoPushService $pushService,
    ) {}

    /**
     * @param  array{context_type: string, context_id: int, seller_id?: int|null, message?: string|null}  $input
     */
    public function startOrGet(User $actor, array $input): Conversation
    {
        $resolved = $this->contextService->resolveParticipants(
            $input['context_type'],
            (int) $input['context_id'],
            $actor,
            isset($input['seller_id']) ? (int) $input['seller_id'] : null,
        );

        $existing = $this->contextService->findExisting(
            $input['context_type'],
            (int) $input['context_id'],
            $resolved['buyer']->id,
            $resolved['seller']->id,
        );

        if ($existing) {
            if (!empty($input['message'])) {
                $this->sendMessage($existing, $actor, (string) $input['message']);
                $existing->refresh();
            }

            return $this->loadConversation($existing->id);
        }

        return DB::transaction(function () use ($input, $resolved, $actor) {
            $conversation = Conversation::create([
                'conversation_number' => Conversation::generateConversationNumber(),
                'context_type' => $input['context_type'],
                'context_id' => (int) $input['context_id'],
                'subject' => $resolved['subject'],
                'status' => Conversation::STATUS_OPEN,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $resolved['buyer']->id,
                'role' => ConversationParticipant::ROLE_BUYER,
                'last_read_at' => $resolved['buyer']->id === $actor->id ? now() : null,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $resolved['seller']->id,
                'role' => ConversationParticipant::ROLE_SELLER,
                'last_read_at' => $resolved['seller']->id === $actor->id ? now() : null,
            ]);

            if (!empty($input['message'])) {
                $this->sendMessage($conversation, $actor, (string) $input['message'], skipReload: true);
            }

            return $this->loadConversation($conversation->id);
        });
    }

    public function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        array $uploadedFiles = [],
        bool $skipReload = false,
    ): Message {
        $this->contextService->authorizeParticipant($conversation, $sender);

        if ($conversation->status !== Conversation::STATUS_OPEN) {
            abort(422, 'This conversation is closed.');
        }

        $clean = $this->sanitizer->sanitize($body);
        if ($clean['blocked'] && empty($uploadedFiles)) {
            abort(422, 'Messages must stay on Pyonea. Remove phone numbers, emails, and third-party app links.');
        }

        return DB::transaction(function () use ($conversation, $sender, $clean, $uploadedFiles) {
            $type = empty($uploadedFiles) ? Message::TYPE_TEXT : Message::TYPE_ATTACHMENT;

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => $type,
                'body' => $clean['body'],
                'metadata' => !empty($clean['flags']) ? ['policy_flags' => $clean['flags']] : null,
            ]);

            foreach ($uploadedFiles as $file) {
                $this->storeAttachment($message, $file);
            }

            $preview = Str::limit(strip_tags($message->body), 500, '…');
            $conversation->update([
                'last_message_at' => $message->created_at,
                'last_message_preview' => $preview,
                'last_message_sender_id' => $sender->id,
            ]);

            $recipient = $conversation->otherParticipant($sender->id);
            if ($recipient) {
                $this->notifyRecipient($conversation, $message, $sender, $recipient);
            }

            $message->load(['sender:id,name,email', 'attachments']);
            broadcast(new MessageSent($message))->toOthers();

            return $message;
        });
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $participant = $this->contextService->authorizeParticipant($conversation, $user);
        $participant->markRead();
    }

    public function loadConversation(int $id): Conversation
    {
        return Conversation::query()
            ->with([
                'participants.user:id,name,email',
                'participants.user.sellerProfile:user_id,store_name',
                'lastMessageSender:id,name',
            ])
            ->findOrFail($id);
    }

    private function storeAttachment(Message $message, UploadedFile $file): MessageAttachment
    {
        $path = $file->store('message-attachments/' . $message->conversation_id, 'public');

        return MessageAttachment::create([
            'message_id' => $message->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }

    private function notifyRecipient(
        Conversation $conversation,
        Message $message,
        User $sender,
        ConversationParticipant $recipient,
    ): void {
        $context = $conversation->contextSummary();
        $label = $context['label'] ?? $conversation->subject ?? 'New message';

        $body = Str::limit(trim(strip_tags((string) $message->body)), 120);
        if ($body === '') {
            $body = $message->type === Message::TYPE_ATTACHMENT ? 'Sent an attachment' : 'Sent you a message';
        }

        $this->pushService->sendToUser($recipient->user_id, [
            'title' => $sender->name,
            'body' => $body,
            'channelId' => 'messages',
            'data' => [
                'type' => 'message_received',
                'conversation_id' => $conversation->id,
                'context_type' => $conversation->context_type,
                'context_id' => $conversation->context_id,
                'context_label' => $label,
            ],
        ]);
    }
}
