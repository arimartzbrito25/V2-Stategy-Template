<?php

namespace App\Reports;

use App\Models\Order;
use App\Support\Logger;
use Illuminate\Support\Collection;

abstract class AbstractReportGenerator
{
    final public function generate(array $params): string
    {
        $this->validateParams($params);

        $orders = $this->fetchOrders($params['from'], $params['to']);
        $content = $this->formatContent($orders);
        $path = $this->persist($content);

        $this->notify($path);

        return $path;
    }

    protected function validateParams(array $params): void
    {
        if (empty($params['from']) || empty($params['to'])) {
            throw new \InvalidArgumentException('Date range required.');
        }
    }

    protected function fetchOrders(string $from, string $to): Collection
    {
        return Order::whereBetween('created_at', [$from, $to])
            ->with(['customer.user', 'vendor', 'items'])
            ->get();
    }

    abstract protected function formatContent(Collection $orders): string;

    abstract protected function fileExtension(): string;

    abstract protected function logLabel(): string;

    protected function persist(string $content): string
    {
        $filename = 'report_' . now()->format('Ymd_His') . '.' . $this->fileExtension();
        $path = storage_path("app/reports/{$filename}");
        file_put_contents($path, $content);

        return $path;
    }

    protected function notify(string $path): void
    {
        app(Logger::class)->log("{$this->logLabel()} report generated: " . basename($path));
    }
}
