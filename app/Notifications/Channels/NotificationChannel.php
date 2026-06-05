<?php

namespace App\Notifications\Channels;

use App\Models\Notification;

interface NotificationChannel
{
    public function send(Notification $notification, string $content): void;
}
