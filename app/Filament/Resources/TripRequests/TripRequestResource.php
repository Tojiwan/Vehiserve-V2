<?php

namespace App\Filament\Resources\TripRequests;

use App\Actions\AssignTrip;
use App\Actions\CompleteTrip;
use App\Enums\BookingState;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Filament\Resources\TripRequests\Pages\ListTripRequests;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TripRequestResource extends Resource
{
    protected static ?string $model = TripRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Request queue';

    protected static ?string $modelLabel = 'transport request';

    /** @return Builder<TripRequest> */
    public static function getEloquentQuery(): Builder
    {
        return TripRequest::query()->visibleTo(User::query()->findOrFail(Auth::id()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'asc')
            ->columns([
                TextColumn::make('request_number')->label('Request')->searchable()->wrap(),
                TextColumn::make('requester.name')->label('Requester')->searchable(),
                TextColumn::make('destination')->searchable()->wrap(),
                TextColumn::make('departure_at')->label('Departure (Manila)')->dateTime('M j, Y g:i A', timezone: 'Asia/Manila')->sortable(),
                TextColumn::make('state')->formatStateUsing(fn (RequestState $state) => $state->label())->badge()->wrap(),
            ])
            ->filters([
                Filter::make('awaiting_me')->label('Awaiting my decision')
                    ->default(fn () => in_array(Auth::user()?->role, [Role::MotorPool, Role::Dean, Role::VicePresident, Role::President], true))
                    ->query(fn (Builder $query) => $query->whereIn('state', array_map(
                        fn (RequestState $state) => $state->value,
                        array_filter(RequestState::cases(), fn (RequestState $state) => $state->reviewer() === Auth::user()?->role
                            || (Auth::user()?->role === Role::MotorPool && in_array($state, [RequestState::ReadyToSchedule, RequestState::Scheduled], true))),
                    ))),
                SelectFilter::make('state')->label('Status')->options(collect(RequestState::cases())->mapWithKeys(fn (RequestState $state) => [$state->value => $state->label()])->all()),
            ])
            ->recordActions([
                Action::make('schedule')->label('Schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (TripRequest $record) => Auth::user()?->can('schedule', $record) ?? false)
                    ->modalHeading(fn (TripRequest $record) => 'Schedule '.$record->request_number)
                    ->modalDescription(fn (TripRequest $record) => $record->destination.' · '.$record->departure_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y g:i A').' – '.$record->return_at->setTimezone(config('vehiserve.display_timezone'))->format('M j, Y g:i A').' Manila · '.$record->passengers()->count().' passenger(s)')
                    ->schema([
                        Select::make('vehicle_id')->label('Vehicle')->required()->searchable()
                            ->options(fn (TripRequest $record) => self::usableVehicles($record)),
                        Select::make('driver_id')->label('Driver')->required()->searchable()
                            ->options(fn (TripRequest $record) => self::usableDrivers($record)),
                    ])
                    ->action(function (TripRequest $record, array $data): void {
                        app(AssignTrip::class)->handle(User::query()->findOrFail(Auth::id()), $record->id, (int) $data['vehicle_id'], (int) $data['driver_id']);
                    })
                    ->successNotificationTitle('Trip scheduled'),
                Action::make('complete')->label('Complete trip')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (TripRequest $record) => Auth::user()?->can('complete', $record) ?? false)
                    ->requiresConfirmation()
                    ->modalHeading(fn (TripRequest $record) => 'Complete '.$record->request_number)
                    ->modalDescription('This closes the trip, releases its vehicle and driver, and records the actual completion time in history.')
                    ->action(fn (TripRequest $record) => app(CompleteTrip::class)->handle(User::query()->findOrFail(Auth::id()), $record->id))
                    ->successNotificationTitle('Trip completed'),
                Action::make('review')->label('View request')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TripRequest $record) => route('requests.show', $record)),
            ])
            ->emptyStateHeading('No requests in this queue')
            ->emptyStateDescription('Requests appear here when they reach your approval stage. Clear the filters to view other permitted requests.');
    }

    /** @return array<int, string> */
    private static function usableVehicles(TripRequest $trip): array
    {
        return Vehicle::query()
            ->where('status', 'available')
            ->where('passenger_capacity', '>=', $trip->passengers()->count())
            ->when(Auth::user()?->is_demo, fn (Builder $query) => $query->where('is_demo', true))
            ->whereNotExists(fn ($bookings) => $bookings->selectRaw('1')->from('bookings')
                ->whereColumn('vehicle_id', 'vehicles.id')->where('state', BookingState::Active->value)
                ->where('departure_at', '<', $trip->return_at)->where('return_at', '>', $trip->departure_at))
            ->orderBy('plate')
            ->get()
            ->mapWithKeys(fn (Vehicle $vehicle) => [$vehicle->id => "{$vehicle->plate} · {$vehicle->name} ({$vehicle->passenger_capacity} seats)"])
            ->all();
    }

    /** @return array<int, string> */
    private static function usableDrivers(TripRequest $trip): array
    {
        return Driver::query()
            ->where('status', 'available')
            ->when(Auth::user()?->is_demo, fn (Builder $query) => $query->where('is_demo', true))
            ->whereNotExists(fn ($bookings) => $bookings->selectRaw('1')->from('bookings')
                ->whereColumn('driver_id', 'drivers.id')->where('state', BookingState::Active->value)
                ->where('departure_at', '<', $trip->return_at)->where('return_at', '>', $trip->departure_at))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getPages(): array
    {
        return ['index' => ListTripRequests::route('/')];
    }
}
