<?php

namespace App\Filament\Widgets;

use App\Models\Consultation;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingFollowups extends BaseWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Upcoming Followups';

    public static function canView(): bool
    {
        return auth()->user() && auth()->user()->hasPermissionTo('view_dashboard_followups');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Consultation::query()
                    ->whereNotNull('followup_date')
                    ->whereDate('followup_date', '>=', now()->toDateString())
                    ->orderBy('followup_date', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('followup_date')
                    ->label('Followup Date')
                    ->date()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('appointment.appointment_date')
                    ->label('Last Visit')
                    ->date()
            ]);
    }
}
