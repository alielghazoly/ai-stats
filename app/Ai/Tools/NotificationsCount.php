<?php

namespace App\Ai\Tools;

class NotificationsCount extends AiTool
{
    public function name(): string
    {
        return 'get_notifications_count';
    }

    public function description(): string
    {
        return 'Returns how many notifications were sent in a date range. '
             . 'Optionally filter by channel (database, mail, sms, push).';
    }

    public function schema(): array
    {
        return [
            'type'       => 'object',
            'properties' => $this->dateRangeProperties() + [
                'channel' => [
                    'type'        => 'string',
                    'enum'        => ['database', 'mail', 'sms', 'push'],
                    'description' => 'Optional channel filter',
                ],
            ],
            'required' => ['from_date', 'to_date'],
        ];
    }

    public function handle(array $input): array
    {
        [$from, $to] = $this->range($input);

        // مثال: عدّل اسم الجدول/الأعمدة حسب الـ schema عندك
        $query = $this->db()->table('notifications')
            ->whereBetween('created_at', [$from, $to]);

        if (! empty($input['channel'])) {
            $query->where('channel', $input['channel']);
        }

        return [
            'from'  => $input['from_date'],
            'to'    => $input['to_date'],
            'count' => $query->count(),
        ];
    }
}
