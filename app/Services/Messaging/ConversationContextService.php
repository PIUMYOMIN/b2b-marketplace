<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ConversationContextService
{
    /**
     * Resolve buyer/seller participants for a contextual thread.
     *
     * @return array{buyer: User, seller: User, subject: ?string}
     */
    public function resolveParticipants(
        string $contextType,
        int $contextId,
        User $actor,
        ?int $sellerId = null,
    ): array {
        return match ($contextType) {
            Conversation::CONTEXT_PRODUCT => $this->fromProduct($contextId, $actor),
            Conversation::CONTEXT_RFQ => $this->fromRfq($contextId, $actor, $sellerId),
            Conversation::CONTEXT_ORDER => $this->fromOrder($contextId, $actor),
            default => throw new InvalidArgumentException('Invalid conversation context type.'),
        };
    }

    public function authorizeParticipant(Conversation $conversation, User $user): ConversationParticipant
    {
        if ($user->hasRole('admin')) {
            throw new AuthorizationException('Admins must use moderation tools, not buyer/seller threads.');
        }

        $participant = $conversation->participantFor($user->id);
        if (!$participant) {
            throw new AuthorizationException('You are not a participant in this conversation.');
        }

        return $participant;
    }

    /**
     * Find an existing thread for the same context + buyer/seller pair.
     */
    public function findExisting(
        string $contextType,
        int $contextId,
        int $buyerId,
        int $sellerId,
    ): ?Conversation {
        return Conversation::query()
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->whereHas('participants', fn ($q) => $q->where('user_id', $buyerId)->where('role', ConversationParticipant::ROLE_BUYER))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $sellerId)->where('role', ConversationParticipant::ROLE_SELLER))
            ->first();
    }

    /**
     * @return array{buyer: User, seller: User, subject: ?string}
     */
    private function fromProduct(int $productId, User $actor): array
    {
        if (!$actor->hasRole('buyer')) {
            throw new AuthorizationException('Only buyers can start product conversations.');
        }

        $product = Product::query()->find($productId);
        if (!$product) {
            throw (new ModelNotFoundException())->setModel(Product::class, [$productId]);
        }

        if ((int) $product->seller_id === (int) $actor->id) {
            throw new AuthorizationException('You cannot message your own listing.');
        }

        $seller = User::query()->findOrFail($product->seller_id);

        return [
            'buyer' => $actor,
            'seller' => $seller,
            'subject' => $product->name_en ?: $product->name_mm,
        ];
    }

    /**
     * @return array{buyer: User, seller: User, subject: ?string}
     */
    private function fromRfq(int $rfqId, User $actor, ?int $sellerId): array
    {
        $rfq = Rfq::query()->find($rfqId);
        if (!$rfq) {
            throw (new ModelNotFoundException())->setModel(Rfq::class, [$rfqId]);
        }

        if ($actor->hasRole('buyer') && (int) $rfq->buyer_id === (int) $actor->id) {
            if (!$sellerId) {
                throw new InvalidArgumentException('seller_id is required for RFQ conversations.');
            }

            $seller = User::query()->findOrFail($sellerId);
            if (!$seller->hasRole('seller')) {
                throw new AuthorizationException('Target user is not a seller.');
            }

            $visible = Rfq::query()
                ->whereKey($rfq->id)
                ->visibleTo($seller->id)
                ->exists();

            if (!$visible) {
                throw new AuthorizationException('This seller is not linked to the RFQ.');
            }

            return [
                'buyer' => $actor,
                'seller' => $seller,
                'subject' => $rfq->rfq_number . ' — ' . $rfq->product_name,
            ];
        }

        if ($actor->hasRole('seller')) {
            $visible = Rfq::query()
                ->whereKey($rfq->id)
                ->visibleTo($actor->id)
                ->exists();

            if (!$visible) {
                throw new AuthorizationException('You cannot access this RFQ.');
            }

            $buyer = User::query()->findOrFail($rfq->buyer_id);

            return [
                'buyer' => $buyer,
                'seller' => $actor,
                'subject' => $rfq->rfq_number . ' — ' . $rfq->product_name,
            ];
        }

        throw new AuthorizationException('You cannot start a conversation for this RFQ.');
    }

    /**
     * @return array{buyer: User, seller: User, subject: ?string}
     */
    private function fromOrder(int $orderId, User $actor): array
    {
        $order = Order::query()->find($orderId);
        if (!$order) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$orderId]);
        }

        if ((int) $actor->id === (int) $order->buyer_id) {
            return [
                'buyer' => $actor,
                'seller' => User::query()->findOrFail($order->seller_id),
                'subject' => 'Order ' . $order->order_number,
            ];
        }

        if ($actor->hasRole('seller') && (int) $actor->id === (int) $order->seller_id) {
            return [
                'buyer' => User::query()->findOrFail($order->buyer_id),
                'seller' => $actor,
                'subject' => 'Order ' . $order->order_number,
            ];
        }

        throw new AuthorizationException('You cannot access this order conversation.');
    }

    public function inboxFor(User $user): Collection
    {
        return ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->with([
                'conversation.participants.user:id,name,email',
                'conversation.lastMessageSender:id,name',
            ])
            ->whereHas('conversation')
            ->get()
            ->map(function (ConversationParticipant $row) use ($user) {
                $conversation = $row->conversation;
                $other = $conversation->otherParticipant($user->id);

                return [
                    'conversation' => $conversation,
                    'participant' => $row,
                    'other_user' => $other?->user,
                    'other_role' => $other?->role,
                    'unread_count' => $conversation->unreadCountFor($user->id),
                    'context' => $conversation->contextSummary(),
                ];
            });
    }
}
