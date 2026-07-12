<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    public const CONTEXT_PRODUCT = 'product';
    public const CONTEXT_RFQ = 'rfq';
    public const CONTEXT_ORDER = 'order';

    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'conversation_number',
        'context_type',
        'context_id',
        'subject',
        'status',
        'last_message_at',
        'last_message_preview',
        'last_message_sender_id',
    ];

    protected $casts = [
        'context_id' => 'integer',
        'last_message_sender_id' => 'integer',
        'last_message_at' => 'datetime',
    ];

    public static function generateConversationNumber(): string
    {
        $year = date('Y');
        $prefix = "MSG-{$year}-";
        $last = static::where('conversation_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('conversation_number');
        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessageSender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_message_sender_id');
    }

    public function participantFor(int $userId): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $userId)
            ?? $this->participants()->where('user_id', $userId)->first();
    }

    public function otherParticipant(int $userId): ?ConversationParticipant
    {
        return $this->participants->first(fn ($p) => $p->user_id !== $userId)
            ?? $this->participants()->where('user_id', '!=', $userId)->first();
    }

    public function unreadCountFor(int $userId): int
    {
        $participant = $this->participantFor($userId);
        if (!$participant) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->when(
                $participant->last_read_at,
                fn ($q) => $q->where('created_at', '>', $participant->last_read_at)
            )
            ->count();
    }

    public function contextSummary(): array
    {
        return match ($this->context_type) {
            self::CONTEXT_PRODUCT => $this->productContext(),
            self::CONTEXT_RFQ => $this->rfqContext(),
            self::CONTEXT_ORDER => $this->orderContext(),
            default => ['type' => $this->context_type, 'id' => $this->context_id],
        };
    }

    private function productContext(): array
    {
        $product = Product::query()
            ->select(['id', 'name_en', 'name_mm', 'slug_en', 'seller_id', 'images'])
            ->find($this->context_id);

        return [
            'type' => self::CONTEXT_PRODUCT,
            'id' => $this->context_id,
            'label' => $product?->name_en,
            'product' => $product,
        ];
    }

    private function rfqContext(): array
    {
        $rfq = Rfq::query()
            ->select(['id', 'rfq_number', 'product_name', 'status'])
            ->find($this->context_id);

        return [
            'type' => self::CONTEXT_RFQ,
            'id' => $this->context_id,
            'label' => $rfq?->rfq_number ?? $rfq?->product_name,
            'rfq' => $rfq,
        ];
    }

    private function orderContext(): array
    {
        $order = Order::query()
            ->select(['id', 'order_number', 'status'])
            ->find($this->context_id);

        return [
            'type' => self::CONTEXT_ORDER,
            'id' => $this->context_id,
            'label' => $order?->order_number,
            'order' => $order,
        ];
    }
}
