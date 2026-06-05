<?php

namespace App\Notifications\Channels;

use App\Models\Notification;
use App\Services\EmailService;

class EmailNotificationChannel implements NotificationChannel
{
    public function send(Notification $notification, string $content): void
    {
        $recipient = $notification->resolveRecipient();
        (new EmailService())->send(
            $recipient?->user?->email ?? '',
            $notification->subject,
            $content
        );
    }
}
