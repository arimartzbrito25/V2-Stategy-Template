<?php

namespace App\Reports;

use Illuminate\Support\Collection;

class ExcelReportGenerator extends AbstractReportGenerator
{
    protected function formatContent(Collection $orders): string
    {
        return "PK\x03\x04Excel stub with " . $orders->count() . ' orders';
    }

    protected function fileExtension(): string
    {
        return 'xlsx';
    }

    protected function logLabel(): string
    {
        return 'Excel';
    }
}
