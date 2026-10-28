<?php

namespace App\Ai\Tools;

class ComplaintsCount extends AiTool
{
    public function name(): string
    {
        return 'get_complaints_count';
    }

    private const TYPES    = ['request' => 0, 'complain' => 1, 'orion' => 2];
    private const STATUSES = [
        0 => 'pending', 1 => 'resolved', 2 => 'declined',
        3 => 'flagged', 4 => 'customer_replied', 5 => 'in_progress',
    ];

    public function description(): string
    {
        return 'Returns the number of contact forms (complaints, requests) created in a date range, '
             . 'broken down by status (pending, resolved, declined, flagged, customer_replied, in_progress). '
             . 'Defaults to complaints only; use type to count requests or orion forms.';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties() + [
                'type' => [
                    'type'        => 'string',
                    'enum'        => ['complain', 'request', 'orion', 'all'],
                    'description' => 'Form type. Default complain.',
                ],
            ],
            'required' => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);
        $type = $input['type'] ?? 'complain';

        $query = $this->db()->table('contact_forms')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$from, $to]);

        if ($type !== 'all') {
            if (! isset(self::TYPES[$type])) {
                throw new \InvalidArgumentException('type must be complain, request, orion or all.');
            }
            $query->where('type', (string) self::TYPES[$type]);
        }

        $byStatus = [];
        foreach ($query->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $n) {
            $byStatus[self::STATUSES[$status] ?? "status_$status"] = (int) $n;
        }

        return [
            'from'      => $input['from_date'],
            'to'        => $input['to_date'],
            'type'      => $type,
            'total'     => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }
}
