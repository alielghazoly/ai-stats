<?php

// Main project: app/Filament/Pages/StatsAssistantPage.php
// (the blade view: resources/views/filament/pages/stats-assistant.blade.php - next to it)

namespace App\Filament\Pages;

use App\Services\AiStatsClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Throwable;

// Filament v3. In v4 change $view to `protected string $view` (not static).
class StatsAssistantPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Stats Assistant';
    protected static ?string $title           = 'Stats Assistant';
    protected static string $view             = 'filament.pages.stats-assistant';

    public string $question = '';

    /** @var array<int, array{question: string, answer: string, tools: array}> */
    public array $history = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // Adjust to your permission system (e.g. spatie):
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['super_admin', 'admin']);
    }

    public function ask(AiStatsClient $client): void
    {
        abort_unless(static::canAccess(), 403);

        $question = trim($this->question);

        if ($question === '' || mb_strlen($question) > 500) {
            Notification::make()->title('Enter a question (max 500 characters)')->warning()->send();
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
