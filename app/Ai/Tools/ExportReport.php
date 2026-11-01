<?php

namespace App\Ai\Tools;

use App\Ai\Exports\ReportExporter;
use InvalidArgumentException;

class ExportReport extends AiTool
{
    public function __construct(private ReportExporter $exporter)
    {
    }

    public function name(): string
    {
        return 'export_report';
    }

    public function description(): string
    {
        return 'Creates a downloadable Excel (xlsx) or CSV file with a DAILY breakdown for a date range and returns a '
             . 'temporary download link (download_url). Use it whenever the user asks to export, download, or get the data '
             . 'as Excel/CSV/a file. Reports: nfc_topups (sessions per status + amounts), new_users, notifications '
             . '(created/sent/important), complaints (contact forms per status). Aggregated numbers only.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties() + [
                'report' => [
                    'type'        => 'string',
                    'enum'        => ReportExporter::REPORTS,
                    'description' => 'Which data to export.',
                ],
                'format' => [
                    'type'        => 'string',
                    'enum'        => ['xlsx', 'csv'],
                    'description' => 'File format. Default xlsx (Excel).',
                ],
                'session_type' => [
                    'type'        => 'string',
                    'enum'        => ['all', 'electricity', 'water'],
                    'description' => 'nfc_topups only. Default all.',
                ],
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['complain', 'request', 'orion', 'all'],
                    'description' => 'complaints only. Default complain.',
                ],
            ],
            'required' => ['report', 'from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        $report = $input['report'] ?? null;
        if (! in_array($report, ReportExporter::REPORTS, true)) {
            throw new InvalidArgumentException('report must be one of: ' . implode(', ', ReportExporter::REPORTS) . '.');
        }

        $range  = $this->range($input);
        $format = $input['format'] ?? 'xlsx';
        $table  = $this->exporter->table($this->db(), $report, $input['from_date'], $input['to_date'], $range, $input);
        $file   = $this->exporter->write(
            $table['headers'], $table['rows'], $format,
            "stats-{$report}-{$input['from_date']}-{$input['to_date']}"
        );

        return [
            'report'       => $report,
            'from'         => $input['from_date'],
            'to'           => $input['to_date'],
            'download_url' => $file['url'],
            'file_name'    => $file['file_name'],
            'format'       => $file['format'],
            'rows'         => $file['rows'],
            'expires_at'   => $file['expires_at'],
        ];
    }
}
