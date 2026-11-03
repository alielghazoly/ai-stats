<?php

namespace Tests\Feature;

use App\Ai\Exports\ReportExporter;
use App\Ai\Tools\ExportReport;
use Tests\TestCase;
use ZipArchive;

class ExportDownloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The signed link must match the host the test requests from
        config(['ai.public_url' => 'http://localhost', 'ai.export_ttl_minutes' => 60]);
    }

    private function export(string $format): array
    {
        return app(ReportExporter::class)->write(['date', 'n'], [['2026-09-01', 3], ['2026-09-02', 0]], $format, 'stats-test');
    }

    public function test_csv_link_downloads_with_bom_and_rows(): void
    {
        $file = $this->export('csv');

        $response = $this->get($file['url'])->assertOk();
        $body     = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith("\xEF\xBB\xBF" . "date,n\n2026-09-01,3\n2026-09-02,0", $body);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_xlsx_link_downloads_a_valid_workbook(): void
    {
        $file = $this->export('xlsx');

        $response = $this->get($file['url'])->assertOk();
        $zip      = new ZipArchive();

        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
    }

    public function test_unsigned_tampered_or_expired_links_are_rejected(): void
    {
        $file = $this->export('csv');
        $path = parse_url($file['url'], PHP_URL_PATH);

        $this->get($path)->assertForbidden();
        $this->get($file['url'] . 'x')->assertForbidden();

        $this->travel(2)->hours();
        $this->get($file['url'])->assertForbidden();
    }

    public function test_unknown_file_name_returns_404_even_when_signed(): void
    {
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('exports.download', now()->addMinute(), ['file' => 'passwd']);

        $this->get($url)->assertNotFound();
    }

    public function test_export_tool_validates_input(): void
    {
        $tool = app(ExportReport::class);

        $this->expectException(\InvalidArgumentException::class);
        $tool->handle(['report' => 'drop_tables', 'from_date' => '2026-09-01', 'to_date' => '2026-09-30']);
    }
}
