<?php

namespace App\Services;

use App\Models\InboxMessage;
use App\Models\SocialCredential;
use App\Models\User;
use Illuminate\Support\Str;

class InboxSyncService
{
    public function ingestForUser(User $user): int
    {
        $credential = SocialCredential::query()
            ->where('user_id', $user->id)
            ->where('provider', 'twitter')
            ->where('token_health', 'healthy')
            ->first();

        if (! $credential) {
            return 0;
        }

        $count = 0;
        $mentions = [
            [
                'external_id' => 'stub-'.Str::uuid(),
                'sender' => '@mention_sample',
                'body' => 'Sample mention — connect inbox sync to platform APIs for live data.',
                'platform' => 'twitter',
            ],
        ];

        foreach ($mentions as $mention) {
            $exists = InboxMessage::query()
                ->where('user_id', $user->id)
                ->where('external_id', $mention['external_id'])
                ->exists();

            if ($exists) {
                continue;
            }

            InboxMessage::create([
                'user_id' => $user->id,
                'social_credential_id' => $credential->id,
                'platform' => $mention['platform'],
                'message_type' => 'mention',
                'external_id' => $mention['external_id'],
                'author_name' => $mention['sender'],
                'body' => $mention['body'],
                'received_at' => now(),
            ]);
            $count++;
        }

        return $count;
    }
}
