<?php

namespace App\Reports;

use Illuminate\Support\Collection;

class PdfReportGenerator extends AbstractReportGenerator
{
    protected function formatContent(Collection $orders): string
    {
        return '%PDF-1.4 Report with ' . $orders->count() . ' orders';
    }

    protected function fileExtension(): string
    {
        return 'pdf';
    }

    protected function logLabel(): string
    {
        return 'PDF';
    }
}
