<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use App\Services\LicenseService;
use Filament\Notifications\Notification;

class SoftwareActivation extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-key';
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
    
    protected string $view = 'filament.pages.software-activation';
    
    protected static ?int $navigationSort = 2000;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                TextInput::make('license_key')
                    ->label('Enter Software License Key')
                    ->required()
                    ->password()
                    ->revealable(),
            ])
            ->statePath('data');
    }

    public function activate(): void
    {
        $key = $this->form->getState()['license_key'] ?? '';
        
        if (LicenseService::activate($key)) {
            Notification::make()
                ->title('Software Activated Successfully')
                ->success()
                ->send();
                
            $this->redirect('/admin');
        } else {
            Notification::make()
                ->title('Invalid License Key')
                ->body('The key provided does not match this hardware.')
                ->danger()
                ->send();
        }
    }
}
