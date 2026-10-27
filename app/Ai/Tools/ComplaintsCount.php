<?php

namespace App\Ai\Tools;

class ComplaintsCount extends AiTool
{
    public function name(): string
    {
        return 'get_complaints_count';
    }

    public function description(): string
    {
        return 'Returns the number of complaints created in a date range, '
             . 'broken down by status (e.g. open, in_progress, resolved).';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties(),
            'required'   => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);

        $byStatus = $this->db()->table('complaints')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        return [
            'from'      => $input['from_date'],
            'to'        => $input['to_date'],
            'total'     => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }
}
