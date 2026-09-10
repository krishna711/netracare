<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Abdm\AbdmBridgeService;
use App\Services\Abdm\AbdmClient;
use Filament\Actions\Action;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class AbdmSettings extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'ABDM Settings';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'ABDM / Ayushman Bharat';
    }

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.abdm-settings';

    public ?array $data = [];
    public ?array $testResult = null;
    public ?array $bridgeServices = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function mount(): void
    {
        $db = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $db = Setting::where('key', 'like', 'abdm_%')->pluck('value', 'key')->toArray();
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        $this->form->fill([
            'client_id' => $db['abdm_client_id'] ?? config('abdm.client_id', ''),
            'client_secret' => $db['abdm_client_secret'] ?? config('abdm.client_secret', ''),
            'env' => $db['abdm_env'] ?? config('abdm.env', 'sandbox'),
            'cm_id' => $db['abdm_cm_id'] ?? config('abdm.cm_id', 'sbx'),
            'hip_id' => $db['abdm_hip_id'] ?? config('abdm.hip_id', ''),
            'facility_name' => $db['abdm_facility_name'] ?? config('abdm.facility_name', 'Netrika Netralaya'),
            'counter_id' => $db['abdm_counter_id'] ?? config('abdm.counter_id', '1'),
            'public_url' => $db['abdm_public_url'] ?? config('abdm.public_callback_url', url('/')),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('ABDM V3 Gateway Credentials')
                    ->description('Enter your ABDM Bridge ID (Client ID) and Client Secret to authenticate with NHA Gateway.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('client_id')
                                ->label('Client ID / Bridge ID')
                                ->placeholder('e.g. SBX_001234')
                                ->required(),
                            TextInput::make('client_secret')
                                ->label('Client Secret')
                                ->password()
                                ->revealable()
                                ->required(),
                        ]),
                        Grid::make(2)->schema([
                            Select::make('env')
                                ->label('Environment')
                                ->options([
                                    'sandbox' => 'Sandbox (dev.abdm.gov.in)',
                                    'production' => 'Production (gateway.abdm.gov.in)',
                                ])
                                ->required(),
                            Select::make('cm_id')
                                ->label('CM ID (Consent Manager)')
                                ->options([
                                    'sbx' => 'sbx (Sandbox)',
                                    'abdm' => 'abdm (Production)',
                                ])
                                ->required(),
                        ]),
                    ]),

                Section::make('Facility & HIP Configuration')
                    ->description('Configure your clinic details for Milestone 1 (M1) Fast-Track Registration and Mock Facility Registry.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('hip_id')
                                ->label('Facility ID / HIP ID')
                                ->placeholder('e.g. IN2310001895')
                                ->helperText('Your official ABDM Facility / HIP ID registered in the Mock Facility Registry.'),
                            TextInput::make('facility_name')
                                ->label('Facility / Hospital Name')
                                ->default('Netrika Netralaya')
                                ->required(),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('counter_id')
                                ->label('Reception Counter ID')
                                ->default('1')
                                ->required(),
                            TextInput::make('public_url')
                                ->label('Public Callback / Webhook URL (HTTPS)')
                                ->placeholder('https://yourdomain.com')
                                ->helperText('Required for ABDM to push Scan & Share demographic data.')
                                ->required(),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $mappings = [
            'abdm_client_id' => $state['client_id'] ?? '',
            'abdm_client_secret' => $state['client_secret'] ?? '',
            'abdm_env' => $state['env'] ?? 'sandbox',
            'abdm_cm_id' => $state['cm_id'] ?? 'sbx',
            'abdm_hip_id' => $state['hip_id'] ?? '',
            'abdm_facility_name' => $state['facility_name'] ?? 'Netrika Netralaya',
            'abdm_counter_id' => $state['counter_id'] ?? '1',
            'abdm_public_url' => $state['public_url'] ?? '',
        ];

        foreach ($mappings as $key => $val) {
            Setting::updateOrCreate(['key' => $key], ['value' => $val, 'name' => strtoupper(str_replace('_', ' ', $key))]);
        }

        Notification::make()
            ->title('ABDM Settings Saved Successfully')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test Gateway Connection')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->action(function (AbdmClient $client) {
                    $this->save();
                    $client->loadConfig();
                    $this->testResult = $client->testConnection();

                    if (($this->testResult['status'] ?? '') === 'success') {
                        Notification::make()
                            ->title('ABDM Gateway Connected Successfully!')
                            ->body("Session token and public encryption cert retrieved in {$this->testResult['session_latency_ms']}ms.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('ABDM Connection Failed')
                            ->body($this->testResult['message'] ?? 'Unknown error.')
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('updateBridgeUrl')
                ->label('1. Update Bridge URL')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Update ABDM Bridge URL (NHA Step 1)')
                ->modalDescription('This registers your public HTTPS endpoint with the ABDM Gateway for callbacks and Scan & Share.')
                ->action(function (AbdmBridgeService $bridgeService, AbdmClient $client) {
                    try {
                        $this->save();
                        $client->loadConfig();

                        $url = $this->data['public_url'] ?? config('abdm.public_callback_url');
                        if (empty($url) || !str_starts_with($url, 'https://')) {
                            throw new \Exception("A valid HTTPS URL is required by ABDM Gateway (e.g. https://netracare.netrikanetralaya.com).");
                        }

                        $res = $bridgeService->updateBridgeUrl($url);
                        Notification::make()
                            ->title('Bridge URL Updated!')
                            ->body($res['message'] ?? 'Successfully updated in ABDM Gateway V3.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to Update Bridge URL')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('registerHip')
                ->label('2. Register HIP Service')
                ->icon('heroicon-o-building-office-2')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Register Facility HIP Service (NHA Step 2)')
                ->modalDescription('This adds Netrika Netralaya as an active HIP (Health Information Provider) in the ABDM Mock Facility Registry.')
                ->action(function (AbdmBridgeService $bridgeService, AbdmClient $client) {
                    try {
                        $this->save();
                        $client->loadConfig();

                        $res = $bridgeService->addUpdateServices();
                        Notification::make()
                            ->title('HIP Service Registered!')
                            ->body($res['message'] ?? 'Successfully registered in registry.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to Register HIP Service')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('viewServices')
                ->label('3. View Registered Services')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(function (AbdmBridgeService $bridgeService) {
                    try {
                        $this->bridgeServices = $bridgeService->getServices();
                        Notification::make()
                            ->title('Fetched Bridge Services')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Failed to Fetch Services')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
