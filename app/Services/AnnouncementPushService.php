<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnnouncementPushService
{
    public function __construct(
        private readonly ExpoPushService $expoPush,
    ) {}

    /**
     * @return array{sent: int, skipped: bool, reason?: string}
     */
    public function broadcast(Announcement $announcement): array
    {
        if (!$announcement->is_active) {
            return ['sent' => 0, 'skipped' => true, 'reason' => 'inactive'];
        }

        $audience = $announcement->target_audience ?? 'all';
        if ($audience === 'guests') {
            return ['sent' => 0, 'skipped' => true, 'reason' => 'guests_audience'];
        }

        $userIds = $this->resolveAudienceUserIds($audience);
        $tokens = $this->resolveTokens($userIds);

        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'skipped' => true, 'reason' => 'no_tokens'];
        }

        $body = Str::limit(trim(strip_tags((string) ($announcement->content ?: $announcement->title))), 120);
        if ($body === '') {
            $body = (string) $announcement->title;
        }

        $message = [
            'title' => (string) $announcement->title,
            'body' => $body,
            'channelId' => 'announcements',
            'sound' => 'default',
            'priority' => 'default',
            'data' => [
                'type' => 'announcement',
                'announcement_id' => (string) $announcement->id,
                'url' => $this->resolveDeepLink($announcement),
            ],
        ];

        $this->expoPush->sendToTokens($tokens, $message);

        Log::info('Announcement push broadcast dispatched.', [
            'announcement_id' => $announcement->id,
            'audience' => $audience,
            'token_count' => $tokens->count(),
        ]);

        return ['sent' => $tokens->count(), 'skipped' => false];
    }

    /** @return Collection<int, int> */
    private function resolveAudienceUserIds(string $audience): Collection
    {
        $userQuery = User::query()->select('id', 'notification_preferences', 'type');

        if ($audience === 'buyers') {
            $userQuery->where('type', 'buyer');
        } elseif ($audience === 'sellers') {
            $userQuery->where('type', 'seller');
        }

        return $userQuery
            ->get()
            ->filter(fn (User $user) => $this->announcementPushEnabled($user))
            ->pluck('id');
    }

    /** @return Collection<int, PushToken> */
    private function resolveTokens(Collection $allowedUserIds): Collection
    {
        if ($allowedUserIds->isEmpty()) {
            return collect();
        }

        return PushToken::query()
            ->whereIn('user_id', $allowedUserIds)
            ->where('provider', 'expo')
            ->where('token', 'like', 'ExponentPushToken[%')
            ->get();
    }

    private function announcementPushEnabled(User $user): bool
    {
        $prefs = $user->notification_preferences ?? [];
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true) ?: [];
        }

        return (bool) (
            (is_array($prefs) ? ($prefs['announcement_notifications'] ?? $prefs['marketing_notifications'] ?? true) : true)
        );
    }

    private function resolveDeepLink(Announcement $announcement): string
    {
        $candidate = trim((string) ($announcement->cta_url ?: $announcement->banner_link_url ?: ''));

        if ($candidate === '') {
            return '/';
        }

        if (str_starts_with($candidate, '/')) {
            return $candidate;
        }

        $appHost = rtrim((string) config('app.frontend_url', 'https://pyonea.com'), '/');
        if (str_starts_with($candidate, $appHost)) {
            $path = parse_url($candidate, PHP_URL_PATH) ?: '/';
            $query = parse_url($candidate, PHP_URL_QUERY);

            return $query ? "{$path}?{$query}" : $path;
        }

        return $candidate;
    }
}
