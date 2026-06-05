<?php

namespace App\Notifications\Channels;

use App\Models\Notification;
use App\Services\WhatsAppService;

class WhatsAppNotificationChannel implements NotificationChannel
{
    public function send(Notification $notification, string $content): void
    {
        $recipient = $notification->resolveRecipient();
        (new WhatsAppService())->send($recipient?->user?->phone ?? '', $content);
    }
}
