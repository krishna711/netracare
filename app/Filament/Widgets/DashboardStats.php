<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user() && auth()->user()->hasPermissionTo('view_dashboard_cards');
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Appointments', Appointment::count())
                ->description('Total Appointments')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('New Patients', Patient::count())
                ->description('Total Registered Patients')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning'),
            Stat::make('Payments', '₹' . Payment::sum('amount'))
                ->description('Total Revenue Collected')
                ->descriptionIcon('heroicon-m-currency-rupee')
                ->color('info'),
        ];
    }
}
