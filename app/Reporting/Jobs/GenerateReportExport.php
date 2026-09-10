<?php

namespace App\Reporting\Jobs;

use App\Reporting\Models\ReportExport;
use App\Reporting\Services\FinancialReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $exportId) {}

    public function handle(FinancialReportService $reports): void
    {
        $export = ReportExport::query()->findOrFail($this->exportId);
        $export->update(['status' => 'processing']);
        try {
            $headers = ['id', 'paid_at', 'first_name', 'last_name', 'amount', 'status', 'method', 'model_type', 'model_id', 'operator_id', 'location_id', 'bank_reference', 'reconciled_at'];
            $rows = $reports->rows($export->organization_id, $export->filters);
            $content = $export->format === 'csv' ? $this->csv($headers, $rows) : $this->xlsx($headers, $rows);
            $path = "reports/{$export->organization_id}/{$export->id}.{$export->format}";
            Storage::disk('local')->put($path, $content);
            $export->update(['status' => 'completed', 'path' => $path, 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $export->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) fputcsv($stream, $row);
        rewind($stream);
        return stream_get_contents($stream);
    }

    private function xlsx(array $headers, array $rows): string
    {
        $lines = array_merge([$headers], $rows);
        $xmlRows = collect($lines)->values()->map(fn ($row, $index) => '<row r="'.($index + 1).'">'.collect($row)->values()->map(
            fn ($value, $column) => '<c r="'.$this->columnName($column).($index + 1).'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>'
        )->implode('').'</row>')->implode('');
        $temporary = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        $zip = new ZipArchive();
        $zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Payments" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($temporary);
        unlink($temporary);
        return $content;
    }

    private function columnName(int $index): string
    {
        $name = '';
        do {
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $name;
    }
}
