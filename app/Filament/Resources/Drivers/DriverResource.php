<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\Driver;
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

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Drivers';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return Builder<Driver> */
    public static function getEloquentQuery(): Builder
    {
        return Driver::query()->when(Auth::user()?->is_demo, fn (Builder $query) => $query->where('is_demo', true));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('contact')->maxLength(255),
                TextInput::make('license_number')->label('License number')->maxLength(255),
                Select::make('status')->required()->default('available')
                    ->options(['available' => 'Available', 'unavailable' => 'Unavailable']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->wrap(),
                TextColumn::make('contact')->searchable(),
                TextColumn::make('license_number')->label('License')->searchable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'available' ? 'success' : 'danger'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading('No drivers yet')
            ->emptyStateDescription('Motor Pool Staff can add drivers here. Records with bookings are kept for history.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDrivers::route('/'),
        ];
    }
}
