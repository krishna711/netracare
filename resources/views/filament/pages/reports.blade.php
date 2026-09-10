<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::card>
            <form wire:submit="submit">
                <div style="display: flex; flex-direction: row; align-items: end; gap: 16px;">
                    <div style="flex-grow: 1;">
                        {{ $this->form }}
                    </div>
                    <div style="margin-bottom: 2px;">
                        <x-filament::button type="submit" color="success">
                            Submit
                        </x-filament::button>
                    </div>
                </div>
            </form>
        </x-filament::card>

        @if(!empty($reportData))
            <x-filament::card>
                <div style="font-size: 1rem; font-weight: 600; margin-bottom: 24px; padding-bottom: 12px;" class="dark:text-gray-200">
                    Date Range: {{ \Carbon\Carbon::parse($data['date_from'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($data['date_to'])->format('d M Y') }}
                </div>
                
                <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                    <table style="width: 100%; text-align: left; border-collapse: collapse;">
                        <tbody>
                            @foreach($reportData as $key => $value)
                                <tr>
                                    <td style="width: 250px; padding: 12px 16px; @if(!$loop->last) border-bottom: 1px solid rgba(156, 163, 175, 0.4); @endif font-size: 0.875rem; font-weight: 500;">
                                        {{ $key }}:
                                    </td>
                                    <td style="padding: 12px 16px; @if(!$loop->last) border-bottom: 1px solid rgba(156, 163, 175, 0.4); @endif font-size: 0.875rem;">
                                        {{ $value }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
