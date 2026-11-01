<?php

namespace App\Ai\Exports;

use Carbon\CarbonPeriod;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * يبني جداول يومية مجمّعة (من نفس الـ queries بتاعة الـ tools، مفيش بيانات شخصية)
 * ويكتبها CSV أو XLSX في storage/app/exports ويرجّع لينك تحميل موقّع ومؤقت.
 */
class ReportExporter
{
    public const REPORTS = ['nfc_topups', 'new_users', 'notifications', 'complaints'];

    private const NFC_STATUSES       = [0 => 'new', 1 => 'paid', 2 => 'charged', 3 => 'canceled'];
    private const COMPLAINT_STATUSES = [
        0 => 'pending', 1 => 'resolved', 2 => 'declined',
        3 => 'flagged', 4 => 'customer_replied', 5 => 'in_progress',
    ];
    private const COMPLAINT_TYPES = ['request' => 0, 'complain' => 1, 'orion' => 2];

    /**
     * @param  array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface}  $range  حدود الفترة بتوقيت الداتابيز (من AiTool::range)
     * @return array{headers: string[], rows: array<int, array<int, string|int|float>>}
     */
    public function table(ConnectionInterface $db, string $report, string $fromDate, string $toDate, array $range, array $options = []): array
    {
        $table = match ($report) {
            'nfc_topups'    => $this->nfc($db, $range, $options['session_type'] ?? 'all'),
            'new_users'     => $this->newUsers($db, $range),
            'notifications' => $this->notifications($db, $range),
            'complaints'    => $this->complaints($db, $range, $options['type'] ?? 'complain'),
            default         => throw new InvalidArgumentException('report must be one of: ' . implode(', ', self::REPORTS) . '.'),
        };

        // صف لكل يوم في الفترة (حتى الأيام اللي مفيهاش بيانات = أصفار)
        $zeros = array_fill(0, count($table['headers']) - 1, 0);
        $rows  = [];
        foreach (CarbonPeriod::create($fromDate, $toDate) as $day) {
            $key    = $day->toDateString();
            $rows[] = [$key, ...($table['byDay'][$key] ?? $zeros)];
        }

        return ['headers' => $table['headers'], 'rows' => $rows];
    }

    /** يكتب الملف ويرجّع بيانات اللينك. @return array{url: string, file_name: string, format: string, rows: int, expires_at: string} */
    public function write(array $headers, array $rows, string $format, string $slug): array
    {
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw new InvalidArgumentException('format must be csv or xlsx.');
        }

        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->prune($dir);

        $name = (string) Str::uuid() . '-' . Str::slug($slug, '_') . '.' . $format;
        $path = $dir . '/' . $name;

        $format === 'csv' ? $this->writeCsv($path, $headers, $rows) : $this->writeXlsx($path, $headers, $rows);

        $expires = now()->addMinutes((int) config('ai.export_ttl_minutes'));

        // اللينك لازم يشاور على العنوان العام اللي الأدمن بيفتحه، مش على host.docker.internal اللي جه منه الطلب
        URL::forceRootUrl((string) config('ai.public_url'));
        try {
            $url = URL::temporarySignedRoute('exports.download', $expires, ['file' => $name]);
        } finally {
            URL::forceRootUrl(null);
        }

        return [
            'url'        => $url,
            'file_name'  => substr($name, 37),
            'format'     => $format,
            'rows'       => count($rows),
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $f = fopen($path, 'w');
        fwrite($f, "\xEF\xBB\xBF"); // BOM عشان Excel يقرا UTF-8 صح
        fputcsv($f, $headers, ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($f, $row, ',', '"', '');
        }
        fclose($f);
    }

    private function writeXlsx(string $path, array $headers, array $rows): void
    {
        $writer = new XlsxWriter();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();
    }

    /** يمسح الملفات الأقدم من مدة صلاحية اللينك. */
    private function prune(string $dir): void
    {
        $limit = time() - ((int) config('ai.export_ttl_minutes') + 5) * 60;
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $limit) {
                @unlink($file);
            }
        }
    }

    /** تعبير SQL لتاريخ اليوم بتوقيت الشغل (مش UTC)، عشان حدود اليوم تطابق اللي الأدمن بيقصده. */
    private function day(ConnectionInterface $db): string
    {
        if ($db->getDriverName() === 'pgsql') {
            $pdo = $db->getPdo();

            return '((created_at AT TIME ZONE ' . $pdo->quote(config('ai.db_timezone')) . ') AT TIME ZONE '
                 . $pdo->quote(config('ai.timezone')) . ')::date';
        }

        return 'DATE(created_at)';
    }

    private function nfc(ConnectionInterface $db, array $range, string $sessionType): array
    {
        $q = $db->table('recharge_session')->whereBetween('created_at', $range);
        if ($sessionType !== 'all') {
            $types = ['electricity' => 0, 'water' => 1];
            if (! isset($types[$sessionType])) {
                throw new InvalidArgumentException('session_type must be all, electricity or water.');
            }
            $q->where('session_type', $types[$sessionType]);
        }

        $byDay = [];
        foreach ($q->selectRaw($this->day($db) . ' as day, status, COUNT(*) as n, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('day', 'status')->get() as $r) {
            $d = (string) $r->day;
            $byDay[$d] ??= [0, 0, 0, 0, 0, 0];
            $byDay[$d][(int) $r->status] = (int) $r->n; // new, paid, charged, canceled
            if (in_array((int) $r->status, [1, 2], true)) {
                $byDay[$d][4] += (int) $r->n;
                $byDay[$d][5] += (float) $r->amount;
            }
        }

        return [
            'headers' => ['date', ...array_values(self::NFC_STATUSES), 'paid_or_charged', 'paid_or_charged_amount'],
            'byDay'   => $byDay,
        ];
    }

    private function newUsers(ConnectionInterface $db, array $range): array
    {
        $byDay = [];
        foreach ($db->table('users')->whereNull('deleted_at')->whereBetween('created_at', $range)
            ->selectRaw($this->day($db) . ' as day, COUNT(*) as n')->groupBy('day')->get() as $r) {
            $byDay[(string) $r->day] = [(int) $r->n];
        }

        return ['headers' => ['date', 'new_users'], 'byDay' => $byDay];
    }

    private function notifications(ConnectionInterface $db, array $range): array
    {
        $byDay = [];
        foreach ($db->table('notifications')->whereNull('deleted_at')->whereBetween('created_at', $range)
            ->selectRaw($this->day($db) . ' as day, COUNT(*) as created, '
                . 'SUM(CASE WHEN sent_at IS NOT NULL THEN 1 ELSE 0 END) as sent, '
                . 'SUM(CASE WHEN is_important THEN 1 ELSE 0 END) as important')
            ->groupBy('day')->get() as $r) {
            $byDay[(string) $r->day] = [(int) $r->created, (int) $r->sent, (int) $r->important];
        }

        return ['headers' => ['date', 'created', 'sent', 'important'], 'byDay' => $byDay];
    }

    private function complaints(ConnectionInterface $db, array $range, string $type): array
    {
        $q = $db->table('contact_forms')->whereNull('deleted_at')->whereBetween('created_at', $range);
        if ($type !== 'all') {
            if (! isset(self::COMPLAINT_TYPES[$type])) {
                throw new InvalidArgumentException('type must be complain, request, orion or all.');
            }
            $q->where('type', (string) self::COMPLAINT_TYPES[$type]);
        }

        $byDay = [];
        foreach ($q->selectRaw($this->day($db) . ' as day, status, COUNT(*) as n')->groupBy('day', 'status')->get() as $r) {
            $d = (string) $r->day;
            $byDay[$d] ??= [0, 0, 0, 0, 0, 0, 0];
            $byDay[$d][(int) $r->status] = (int) $r->n;
            $byDay[$d][6] += (int) $r->n;
        }

        return ['headers' => ['date', ...array_values(self::COMPLAINT_STATUSES), 'total'], 'byDay' => $byDay];
    }
}
