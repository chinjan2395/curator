<?php

namespace App\Services;

use App\Mail\CuratorNotificationMail;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notify(User $user, string $type, string $title, string $body, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $this->sendEmailIfEnabled($user, $type, $title, $body);

        return $notification;
    }

    private function sendEmailIfEnabled(User $user, string $type, string $title, string $body): void
    {
        $pref = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_type', $type)
            ->first();

        if ($pref && ! $pref->email) {
            return;
        }

        if (! $pref) {
            NotificationPreference::create([
                'user_id' => $user->id,
                'event_type' => $type,
                'in_app' => true,
                'email' => in_array($type, ['post_failed', 'post_published'], true),
                'push' => false,
            ]);
        }

        Mail::to($user->email)->queue(new CuratorNotificationMail($title, $body));
    }

    public function listForUser(User $user, int $limit = 50): array
    {
        return Notification::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function markRead(User $user, int $notificationId): void
    {
        Notification::where('user_id', $user->id)
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);
    }

    public function markAllRead(User $user): void
    {
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
