<?php

namespace App\Notifications\Channels;

use App\Models\Notification;
use App\Services\PushService;

class PushNotificationChannel implements NotificationChannel
{
    public function send(Notification $notification, string $content): void
    {
        (new PushService())->send(
            $notification->recipient_id,
            $notification->subject,
            $content
        );
    }
}
