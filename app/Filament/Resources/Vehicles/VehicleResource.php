<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Vehicles';

    protected static ?string $recordTitleAttribute = 'plate';

    /** @return Builder<Vehicle> */
    public static function getEloquentQuery(): Builder
    {
        return Vehicle::query()->when(Auth::user()?->is_demo, fn (Builder $query) => $query->where('is_demo', true));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('plate')->required()->maxLength(50)->unique(ignoreRecord: true),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('type')->required()->maxLength(255),
                TextInput::make('passenger_capacity')->label('Passenger capacity')->required()->integer()->minValue(1)->maxValue(32767),
                Select::make('status')->required()->default('available')
                    ->options(['available' => 'Available', 'unavailable' => 'Unavailable', 'maintenance' => 'Maintenance']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('plate')
            ->columns([
                TextColumn::make('plate')->label('Plate')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('type')->searchable(),
                TextColumn::make('passenger_capacity')->label('Seats')->sortable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => match ($state) {
                        'available' => 'success', 'maintenance' => 'warning', default => 'danger',
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No vehicles yet')
            ->emptyStateDescription('Motor Pool Staff can add vehicles here. Records with bookings are kept for history.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }
}
