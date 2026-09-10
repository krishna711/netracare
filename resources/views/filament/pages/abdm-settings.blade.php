<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Live Connection Test Result Alert --}}
        @if ($testResult)
            @if (($testResult['status'] ?? '') === 'success')
                <div class="p-4 rounded-xl border border-emerald-500/30 bg-emerald-50/50 dark:bg-emerald-950/20 text-emerald-900 dark:text-emerald-200">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-emerald-500 text-white rounded-lg">
                            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-emerald-800 dark:text-emerald-300">
                                ABDM Gateway Live Connection Active
                            </h3>
                            <p class="text-sm text-emerald-700 dark:text-emerald-400 mt-1">
                                {{ $testResult['message'] }}
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 text-xs">
                                <div class="bg-white/80 dark:bg-gray-800/80 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    <span class="text-gray-500 dark:text-gray-400 block font-medium">Session Latency</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300 text-sm">{{ $testResult['session_latency_ms'] }} ms</span>
                                </div>
                                <div class="bg-white/80 dark:bg-gray-800/80 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    <span class="text-gray-500 dark:text-gray-400 block font-medium">Encryption Cert</span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-300 text-sm">{{ $testResult['cert_retrieved'] ? 'Verified (RSA OAEP)' : 'Failed' }}</span>
                                </div>
                                <div class="bg-white/80 dark:bg-gray-800/80 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    <span class="text-gray-500 dark:text-gray-400 block font-medium">CM-ID Environment</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200 text-sm uppercase">{{ $testResult['cm_id'] }}</span>
                                </div>
                                <div class="bg-white/80 dark:bg-gray-800/80 p-2.5 rounded-lg border border-emerald-200 dark:border-emerald-800">
                                    <span class="text-gray-500 dark:text-gray-400 block font-medium">Last Verified</span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $testResult['timestamp'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="p-4 rounded-xl border border-rose-500/30 bg-rose-50/50 dark:bg-rose-950/20 text-rose-900 dark:text-rose-200">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-rose-500 text-white rounded-lg">
                            <x-filament::icon icon="heroicon-o-x-circle" class="h-6 w-6" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-rose-800 dark:text-rose-300">
                                ABDM Gateway Connection Failed
                            </h3>
                            <p class="text-sm text-rose-700 dark:text-rose-400 mt-1 font-mono">
                                {{ $testResult['message'] }}
                            </p>
                            <p class="text-xs text-rose-600 dark:text-rose-400 mt-2">
                                Please check that your Client ID (Bridge ID) and Client Secret are typed correctly.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif

        {{-- Registered Bridge Services List (from Step 3 of NHA email) --}}
        @if ($bridgeServices)
            <div class="p-5 rounded-xl border border-sky-500/30 bg-white dark:bg-gray-900 shadow-sm">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-building-office" class="h-5 w-5 text-sky-500" />
                        <h3 class="font-semibold text-gray-900 dark:text-white">Active Bridge Services (Mock Facility Registry)</h3>
                    </div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">NHA Verification Step 3</span>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <pre class="bg-gray-50 dark:bg-gray-950 p-3 rounded-lg text-xs font-mono text-gray-800 dark:text-gray-200 max-h-60 overflow-y-auto">{{ json_encode($bridgeServices, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif

        {{-- Form card --}}
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" size="lg">
                    Save ABDM Settings
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
