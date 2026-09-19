<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Hospital Reception Counter QR Display Card --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- QR Code Display --}}
            <div class="md:col-span-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 rounded-full text-xs font-semibold uppercase tracking-wider mb-3">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    Counter {{ $qrPayload['counter_id'] ?? '1' }} Active
                </div>

                {{-- HIP ID Toggle --}}
                <div class="mb-4 w-full">
                    <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1.5 text-center">Select Facility HIP ID</p>
                    <div class="flex items-center justify-center p-1 bg-gray-100 dark:bg-gray-800 rounded-xl text-xs gap-1 border border-gray-200/60 dark:border-gray-700">
                        <button 
                            type="button"
                            wire:click="switchHip('IN2310001444')" 
                            class="flex-1 py-1 px-2 rounded-lg transition-all {{ ($selectedHipId ?? '') === 'IN2310001444' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-sm font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                            IN2310001444
                        </button>
                        <button 
                            type="button"
                            wire:click="switchHip('IN2310014055')" 
                            class="flex-1 py-1 px-2 rounded-lg transition-all {{ ($selectedHipId ?? '') === 'IN2310014055' ? 'bg-white dark:bg-gray-700 text-primary-600 shadow-sm font-bold' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                            IN2310014055
                        </button>
                    </div>
                </div>

                {{-- Render QR Code via SVG API or data --}}
                @php
                    $encodedData = urlencode($qrPayload['qr_string'] ?? '');
                    $qrApiUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={$encodedData}&color=1e293b";
                @endphp

                <div class="p-3 bg-white border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-2xl shadow-inner">
                    <img src="{{ $qrApiUrl }}" alt="ABDM Counter QR Code" class="w-48 h-48 rounded-lg object-contain" />
                </div>

                <h4 class="mt-4 font-bold text-gray-900 dark:text-white text-base">
                    {{ $qrPayload['facility_name'] ?? 'Netrika Netralaya' }}
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Active HIP ID: <span class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ $qrPayload['hip_id'] ?? 'N/A' }}</span>
                </p>
                <p class="text-[11px] text-gray-400 dark:text-gray-500 font-mono mt-1 break-all bg-gray-50 dark:bg-gray-800/60 p-1.5 rounded-lg select-all border border-gray-100 dark:border-gray-800">
                    {{ $qrPayload['qr_string'] ?? '' }}
                </p>

                <div class="mt-4 w-full flex items-center justify-center gap-2">
                    <a href="{{ $qrApiUrl }}" target="_blank" download="Counter_QR_{{ $qrPayload['hip_id'] ?? 'hip' }}.png" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-4 h-4" />
                        Download QR for Printing
                    </a>
                </div>
            </div>

            {{-- Counter Workflow Instructions & Stats --}}
            <div class="md:col-span-2 bg-gradient-to-br from-primary-500/5 via-primary-500/10 to-transparent border border-primary-500/20 rounded-2xl p-6 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 text-primary-600 dark:text-primary-400 font-semibold text-sm">
                        <x-filament::icon icon="heroicon-o-information-circle" class="w-5 h-5" />
                        ABDM Milestone 1 (M1) Fast-Track OPD Registration
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mt-2">
                        Scan & Share Counter Workflow
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                        Patients scan this QR code using the <strong>ABHA App</strong> or <strong>Aarogya Setu</strong> to share their demographic profile instantly with the hospital counter.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                        <div class="bg-white/80 dark:bg-gray-800/80 p-4 rounded-xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block uppercase">Step 1</span>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mt-1">Patient scans QR on their smartphone</p>
                        </div>
                        <div class="bg-white/80 dark:bg-gray-800/80 p-4 rounded-xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block uppercase">Step 2</span>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mt-1">ABDM Gateway assigns a Token Number</p>
                        </div>
                        <div class="bg-white/80 dark:bg-gray-800/80 p-4 rounded-xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 block uppercase">Step 3</span>
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mt-1">Click "Register & Book OPD" in 1 click below</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-primary-500/10 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>Webhook: <code class="font-mono text-primary-600 dark:text-primary-400">/api/v3/hip/patient/share</code></span>
                    <span>Status: <strong class="text-emerald-600 dark:text-emerald-400">Active & Listening</strong></span>
                </div>
            </div>
        </div>

        {{-- Scanned Patient Queue Table --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-filament::icon icon="heroicon-o-queue-list" class="w-5 h-5 text-primary-500" />
                    Incoming Patient Check-In Queue
                </h3>
            </div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
