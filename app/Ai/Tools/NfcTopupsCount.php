<?php

namespace App\Ai\Tools;

class NfcTopupsCount extends AiTool
{
    public function name(): string
    {
        return 'get_nfc_topups';
    }

    public function description(): string
    {
        return 'Returns the number of successful NFC top-ups (charges), the number of distinct users '
             . 'who topped up, and the total amount, in a date range. '
             . 'Can group results by day.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties() + [
                'group_by' => [
                    'type'        => 'string',
                    'enum'        => ['none', 'day'],
                    'description' => 'Use "day" to get a per-day breakdown. Default none.',
                ],
            ],
            'required' => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);

        // مثال: عدّل اسم الجدول/الأعمدة/القيم حسب الـ schema عندك
        $base = fn () => $this->db()->table('payments')
            ->where('method', 'nfc')
            ->where('status', 'success')
            ->whereBetween('created_at', [$from, $to]);

        $totals = $base()
            ->selectRaw('COUNT(*) as topups, COUNT(DISTINCT user_id) as users, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        $result = [
            'from'         => $input['from_date'],
            'to'           => $input['to_date'],
            'topups'       => (int) $totals->topups,
            'unique_users' => (int) $totals->users,
            'total_amount' => (float) $totals->total_amount,
        ];

        if (($input['group_by'] ?? 'none') === 'day') {
            $result['by_day'] = $base()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as topups, COALESCE(SUM(amount), 0) as total_amount')
                ->groupBy('day')
                ->orderBy('day')
                ->limit(400)
                ->get()
                ->map(fn ($r) => [
                    'day'          => (string) $r->day,
                    'topups'       => (int) $r->topups,
                    'total_amount' => (float) $r->total_amount,
                ])
                ->all();
        }

        return $result;
    }
}
