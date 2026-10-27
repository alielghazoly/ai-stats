<?php

// المشروع الأساسي: app/Filament/Pages/StatsAssistantPage.php
// (الـ blade view: resources/views/filament/pages/stats-assistant.blade.php - موجود جنبه)

namespace App\Filament\Pages;

use App\Services\AiStatsClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Throwable;

// Filament v3. في v4 غيّر $view لـ `protected string $view` (مش static).
class StatsAssistantPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'مساعد الإحصائيات';
    protected static ?string $title           = 'مساعد الإحصائيات';
    protected static string $view             = 'filament.pages.stats-assistant';

    public string $question = '';

    /** @var array<int, array{question: string, answer: string, tools: array}> */
    public array $history = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // عدّل حسب نظام الصلاحيات عندك (مثال spatie):
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function ask(AiStatsClient $client): void
    {
        abort_unless(static::canAccess(), 403);

        $question = trim($this->question);

        if ($question === '' || mb_strlen($question) > 500) {
            Notification::make()->title('اكتب سؤال (أقصى 500 حرف)')->warning()->send();
            return;
        }

        try {
            $result = $client->ask($question, (string) Auth::id());
        } catch (Throwable $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            return;
        }

        array_unshift($this->history, [
            'question' => $question,
            'answer'   => $result['answer'],
            'tools'    => $result['tools'],
        ]);

        $this->question = '';
    }
}
