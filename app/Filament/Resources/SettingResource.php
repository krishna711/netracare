<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?int $navigationSort = 100;

    public static function canViewAny(): bool
    {
        return auth()->user() && auth()->user()->hasPermissionTo('manage_settings');
    }

    public static function getNavigationIcon(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return 'heroicon-o-cog-8-tooth';
    }

    public static function getNavigationLabel(): string
    {
        return 'Settings';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->disabled()
                    ->required(),
                \Filament\Schemas\Components\Group::make()
                    ->schema(function (?Setting $record) {
                        if (!$record) {
                            return [\Filament\Forms\Components\Textarea::make('value')->label('Value')->rows(6)->required()];
                        }
                        
                        if (in_array($record->key, ['banner_1', 'banner_2', 'banner_3'])) {
                            return [
                                \Filament\Forms\Components\FileUpload::make('value')
                                    ->label('Upload Banner Image')
                                    ->disk('public')
                                    ->image()
                                    ->imageEditor()
                                    ->directory('banners')
                                    ->required()
                            ];
                        }
                        
                        if (in_array($record->key, ['title_english', 'title_hindi', 'phone_1', 'phone_2', 'phone_3', 'registration_number', 'timings_english', 'timings_hindi'])) {
                            return [
                                \Filament\Forms\Components\TextInput::make('value')
                                    ->label('Value')
                                    ->required()
                            ];
                        }

                        // Default fallback mapping (for old dropdowns and address/descriptions)
                        return [
                            \Filament\Forms\Components\Textarea::make('value')
                                ->label('Value')
                                ->rows(6)
                                ->required()
                        ];
                    })->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
