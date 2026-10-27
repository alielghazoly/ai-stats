<x-filament-panels::page>
    <form wire:submit="ask" class="flex items-start gap-3">
        <div class="flex-1">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="question"
                    placeholder="مثال: كام شحنة NFC اتعملت الشهر ده؟"
                    maxlength="500"
                    dir="auto"
                />
            </x-filament::input.wrapper>
        </div>

        <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="ask">
            <span wire:loading.remove wire:target="ask">اسأل</span>
            <span wire:loading wire:target="ask">بيحسب...</span>
        </x-filament::button>
    </form>

    <div class="space-y-4">
        @foreach ($history as $item)
            <x-filament::section>
                <div class="text-sm text-gray-500 dark:text-gray-400" dir="auto">
                    {{ $item['question'] }}
                </div>

                <div class="mt-2 whitespace-pre-line text-base" dir="auto">
                    {{ $item['answer'] }}
                </div>

                @if (! empty($item['tools']))
                    <details class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        <summary class="cursor-pointer">إزاي وصلت للرقم؟</summary>
                        <ul class="mt-1 list-disc ps-5">
                            @foreach ($item['tools'] as $tool)
                                <li>
                                    <code>{{ $tool['name'] }}</code>
                                    <code>{{ json_encode($tool['input'], JSON_UNESCAPED_UNICODE) }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
