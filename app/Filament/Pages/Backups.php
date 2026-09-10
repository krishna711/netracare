<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Services\BackupService;
use Filament\Notifications\Notification;

class Backups extends Page
{
    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-circle-stack';
    }
    
    protected string $view = 'filament.pages.backups';
    
    protected static ?int $navigationSort = 1000;
    
    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function canAccess(): bool
    {
        return auth()->user() && auth()->user()->hasPermissionTo('manage_backups');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateBackup')
                ->label('Generate & Download Backup')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $zipPath = BackupService::backup();
                        
                        Notification::make()
                            ->title('Backup generated successfully')
                            ->success()
                            ->send();
                            
                        return response()->download($zipPath)->deleteFileAfterSend(true);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Backup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
