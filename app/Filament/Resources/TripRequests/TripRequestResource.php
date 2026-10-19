<?php

namespace App\Filament\Resources\TripRequests;

use App\Enums\RequestState;
use App\Enums\Role;
use App\Filament\Resources\TripRequests\Pages\ListTripRequests;
use App\Models\TripRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
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
                        array_filter(RequestState::cases(), fn (RequestState $state) => $state->reviewer() === Auth::user()?->role),
                    ))),
                SelectFilter::make('state')->label('Status')->options(collect(RequestState::cases())->mapWithKeys(fn (RequestState $state) => [$state->value => $state->label()])->all()),
            ])
            ->recordActions([
                Action::make('review')->label('View request')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TripRequest $record) => route('requests.show', $record)),
            ])
            ->emptyStateHeading('No requests in this queue')
            ->emptyStateDescription('Requests appear here when they reach your approval stage. Clear the filters to view other permitted requests.');
    }

    public static function getPages(): array
    {
        return ['index' => ListTripRequests::route('/')];
    }
}
