<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    private array $logs = [];

    public function log(string $message, string $level = 'info'): void
    {
        $entry = [
            'timestamp' => now()->toIso8601String(),
            'level'     => $level,
            'message'   => $message,
        ];
        $this->logs[] = $entry;

        Log::channel('legacy')->{$level}("[Legacy] {$message}");
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }
}
