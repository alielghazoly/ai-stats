<?php

namespace App\Ai\Tools;

class NfcTopupsCount extends AiTool
{
    public function name(): string
    {
        return 'get_nfc_topups';
    }

    private const STATUSES = [0 => 'new', 1 => 'paid', 2 => 'charged', 3 => 'canceled'];
    private const TYPES    = ['electricity' => 0, 'water' => 1];

    public function description(): string
    {
        return 'Returns NFC recharge sessions (electricity/water meter top-ups) created in a date range: '
             . 'counts per status (new, paid, charged, canceled), and for paid+charged sessions the number of '
             . 'sessions, distinct users and total amount. A session counts as a successful top-up once it is paid or charged. '
             . 'Can filter by session_type and group results by day.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties() + [
                'session_type' => [
                    'type'        => 'string',
                    'enum'        => ['all', 'electricity', 'water'],
                    'description' => 'Optional meter type filter. Default all.',
                ],
                'group_by' => [
                    'type'        => 'string',
                    'enum'        => ['none', 'day'],
                    'description' => 'Use "day" to get a per-day breakdown of paid+charged sessions. Default none.',
                ],
            ],
            'required' => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);

        $base = function () use ($from, $to, $input) {
            $q = $this->db()->table('recharge_session')->whereBetween('created_at', [$from, $to]);

            $type = $input['session_type'] ?? 'all';
            if ($type !== 'all') {
                if (! isset(self::TYPES[$type])) {
                    throw new \InvalidArgumentException('session_type must be all, electricity or water.');
                }
                $q->where('session_type', self::TYPES[$type]);
            }

            return $q;
        };

        $byStatus = [];
        foreach (self::STATUSES as $name) {
            $byStatus[$name] = 0;
        }
        foreach ($base()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $n) {
            $byStatus[self::STATUSES[$status] ?? "status_$status"] = (int) $n;
        }

        $paid = fn () => $base()->whereIn('status', [1, 2]);

        $totals = $paid()
            ->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT user_id) as users, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        $result = [
            'from'                  => $input['from_date'],
            'to'                    => $input['to_date'],
            'session_type'          => $input['session_type'] ?? 'all',
            'by_status'             => $byStatus,
            'paid_or_charged'       => (int) $totals->sessions,
            'unique_users'          => (int) $totals->users,
            'total_amount'          => (float) $totals->total_amount,
        ];

        if (($input['group_by'] ?? 'none') === 'day') {
            $result['by_day'] = $paid()
                ->selectRaw('DATE(created_at) as day, COUNT(*) as sessions, COALESCE(SUM(amount), 0) as total_amount')
                ->groupBy('day')
                ->orderBy('day')
                ->limit(400)
                ->get()
                ->map(fn ($r) => [
                    'day'          => (string) $r->day,
                    'sessions'     => (int) $r->sessions,
                    'total_amount' => (float) $r->total_amount,
                ])
                ->all();
        }

        return $result;
    }
}
