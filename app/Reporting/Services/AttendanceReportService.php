<?php

namespace App\Reporting\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class AttendanceReportService
{
    public function aggregate(int $organizationId, array $filters): array
    {
        $query = $this->query($organizationId, $filters);
        $sessions = (clone $query)->distinct()->count('event_occurrences.id');
        $attendances = (clone $query)->where('event_occurrence_user.status', 'attended')->count();
        $absences = (clone $query)->where('event_occurrence_user.status', 'no_show')->count();
        $decided = $attendances + $absences;

        return [
            'sessions' => $sessions,
            'attendances' => $attendances,
            'absences' => $absences,
            'participation_rate' => $decided === 0 ? 0.0 : round($attendances / $decided * 100, 2),
        ];
    }

    public function export(int $organizationId, array $filters, string $format): string
    {
        $summary = $this->aggregate($organizationId, $filters);
        $headers = ['sessions', 'attendances', 'absences', 'participation_rate'];
        $rows = [array_values($summary)];

        return $format === 'csv' ? $this->csv($headers, $rows) : $this->xlsx($headers, $rows);
    }

    private function query(int $organizationId, array $filters): Builder
    {
        $query = DB::table('event_occurrences')
            ->join('events', 'events.id', '=', 'event_occurrences.event_id')
            ->leftJoin('event_occurrence_user', 'event_occurrence_user.event_occurrence_id', '=', 'event_occurrences.id')
            ->where('event_occurrences.organization_id', $organizationId)
            ->whereNull('events.deleted_at');

        if (isset($filters['from'])) {
            $query->whereDate('event_occurrences.occurrence_date', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('event_occurrences.occurrence_date', '<=', $filters['to']);
        }
        foreach (['location_id', 'category_id', 'instructor_id', 'group_id'] as $filter) {
            if (isset($filters[$filter])) {
                $query->where("events.{$filter}", $filters[$filter]);
            }
        }
        if (isset($filters['member_id'])) {
            $query->where('event_occurrence_user.user_id', $filters['member_id']);
        }

        return $query;
    }

    private function csv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private function xlsx(array $headers, array $rows): string
    {
        $xmlRows = collect(array_merge([$headers], $rows))->values()->map(function (array $row, int $rowIndex): string {
            $cells = collect($row)->values()->map(fn ($value, int $columnIndex): string =>
                '<c r="'.$this->columnName($columnIndex).($rowIndex + 1).'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>'
            )->implode('');

            return '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        })->implode('');

        $temporary = tempnam(sys_get_temp_dir(), 'attendance-xlsx-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create the XLSX export.');
        }
        $zip = new ZipArchive();
        if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the XLSX archive.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Attendance" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
        $zip->close();
        $content = file_get_contents($temporary);
        unlink($temporary);

        return (string) $content;
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
