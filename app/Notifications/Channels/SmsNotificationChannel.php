<?php

namespace App\Notifications\Channels;

use App\Models\Notification;
use App\Services\SMSService;

class SmsNotificationChannel implements NotificationChannel
{
    public function send(Notification $notification, string $content): void
    {
        $recipient = $notification->resolveRecipient();
        (new SMSService())->send($recipient?->user?->phone ?? '', $content);
    }
}
