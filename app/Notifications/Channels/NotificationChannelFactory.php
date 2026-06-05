<?php

namespace App\Notifications\Channels;

class NotificationChannelFactory
{
    private const MAP = [
        'email'    => EmailNotificationChannel::class,
        'sms'      => SmsNotificationChannel::class,
        'push'     => PushNotificationChannel::class,
        'whatsapp' => WhatsAppNotificationChannel::class,
    ];

    public static function create(string $channel): NotificationChannel
    {
        if (!isset(self::MAP[$channel])) {
            throw new \InvalidArgumentException("Unknown notification channel: {$channel}");
        }

        $class = self::MAP[$channel];

        return new $class();
    }
}
