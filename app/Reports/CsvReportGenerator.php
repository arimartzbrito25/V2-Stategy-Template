<?php

namespace App\Reports;

use Illuminate\Support\Collection;

class CsvReportGenerator extends AbstractReportGenerator
{
    protected function formatContent(Collection $orders): string
    {
        $lines = ['id,customer,vendor,total,status'];

        foreach ($orders as $order) {
            $lines[] = implode(',', [
                $order->id,
                $order->customer->user->name ?? '',
                $order->vendor->business_name ?? '',
                $order->total,
                $order->status,
            ]);
        }

        return implode("\n", $lines);
    }

    protected function fileExtension(): string
    {
        return 'csv';
    }

    protected function logLabel(): string
    {
        return 'CSV';
    }
}
