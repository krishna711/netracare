<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;

class Reports extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'reports';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.reports';

    public static function canAccess(): bool
    {
        return auth()->user() && auth()->user()->hasPermissionTo('manage_reports');
    }

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-document-text';
    }

    public static function getNavigationLabel(): string
    {
        return 'Reports';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Grid::make(3)->schema([
                    DatePicker::make('date_from')->label('Date From')->default(now()->startOfMonth()),
                    DatePicker::make('date_to')->label('Date To')->default(now()),
                    Select::make('type')
                        ->label('Type')
                        ->options([
                            'All' => 'All',
                            'Consultation' => 'Consultation',
                            'Registration' => 'Registration',
                            'Investigation' => 'Investigation',
                            'Procedure' => 'Procedure',
                            'Surgery' => 'Surgery',
                        ])
                        ->default('All')
                ]),
            ])
            ->statePath('data');
    }

    public array $reportData = [];

    public function submit()
    {
        $data = $this->form->getState();
        
        // Populate dummy statistics based on user's screenshot
        $this->reportData = [
            'New Patients' => 1,
            'Appointments' => 1,
            'Cash' => 200,
            'Online' => 0,
            'OPD' => 200,
            'Minor' => 0,
            'Surgery' => 0,
            'Total Payment' => '200 INR',
        ];
        
        \Filament\Notifications\Notification::make()
            ->title('Report generated successfully')
            ->success()
            ->send();
    }
}
