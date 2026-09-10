<x-filament-panels::page>
    <div class="fi-page-content">
        @if(\App\Services\LicenseService::isValid())
            <x-filament::card>
                <div class="flex items-center gap-4 text-success-600 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-check-badge" class="h-10 w-10" />
                    <div>
                        <h2 class="text-xl font-bold m-0">Software is Activated</h2>
                        <p class="m-0 mt-1 opacity-80">This installation of NetraCare is fully licensed for this machine.</p>
                    </div>
                </div>
            </x-filament::card>

            @php
                $licenseInfo = \App\Services\LicenseService::getLicenseInfo();
            @endphp
            @if($licenseInfo)
                <x-filament::card class="mt-6">
                    <h3 class="text-lg font-bold mb-4">License Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 m-0">Licensed To</p>
                            <p class="font-bold text-lg m-0 mt-1">{{ $licenseInfo['license_to'] }}</p>
                        </div>
                        <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                            <p class="text-sm text-gray-500 dark:text-gray-400 m-0">Expiry Date</p>
                            <p class="font-bold text-lg m-0 mt-1 {{ \Carbon\Carbon::parse($licenseInfo['expiry_date'])->diffInDays(now()) < 30 ? 'text-warning-600' : '' }}">
                                {{ \Carbon\Carbon::parse($licenseInfo['expiry_date'])->format('d M, Y') }}
                            </p>
                        </div>
                    </div>
                </x-filament::card>
            @endif

        @else
            <x-filament::card class="mb-6 ring-1 ring-danger-500 bg-danger-50 dark:bg-danger-900/20">
                <div class="flex items-start gap-4 text-danger-600 dark:text-danger-400">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-8 w-8 mt-1" />
                    <div>
                        <h2 class="text-xl font-bold m-0">Software Not Activated</h2>
                        <p class="mt-2 mb-0">Your application is currently locked. To unlock it, provide your Hardware ID to the developer to receive your Activation Key.</p>
                    </div>
                </div>
            </x-filament::card>
        @endif

        <x-filament::card class="mt-6">
            <h3 class="text-lg font-bold mb-4">Hardware Details</h3>
            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 m-0">Hardware ID (MAC / UUID)</p>
                    <p class="font-mono text-lg font-bold m-0 mt-1">{{ \App\Services\LicenseService::getHardwareId() }}</p>
                </div>
                <x-filament::button 
                    color="gray" 
                    icon="heroicon-m-clipboard" 
                    x-on:click="navigator.clipboard.writeText('{{ \App\Services\LicenseService::getHardwareId() }}'); $tooltip('Copied to clipboard')">
                    Copy ID
                </x-filament::button>
            </div>

            <form wire:submit="activate" class="mt-8 space-y-6">
                {{ $this->form }}

                <x-filament::button type="submit" color="primary" size="lg">
                    Activate Software
                </x-filament::button>
            </form>
        </x-filament::card>
    </div>
</x-filament-panels::page>
