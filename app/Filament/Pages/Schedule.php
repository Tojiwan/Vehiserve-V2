<?php

namespace App\Filament\Pages;

use App\Enums\BookingState;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Schedule extends Page
{
    protected string $view = 'filament.pages.schedule';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Schedule';

    #[Url]
    public string $month = '';

    public function mount(): void
    {
        $this->month = $this->monthStart()->format('Y-m');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->hasVerifiedEmail() && $user->role !== Role::User;
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
    }

    public function currentMonth(): void
    {
        $this->month = CarbonImmutable::now($this->timezone())->format('Y-m');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Transport schedule';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $timezone = $this->timezone();
        $monthStart = $this->monthStart();
        $monthEnd = $monthStart->addMonth();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $user = User::query()->findOrFail(Auth::id());

        $bookings = Booking::query()
            ->with(['tripRequest.requester', 'vehicle', 'driver'])
            ->whereIn('state', [BookingState::Active, BookingState::Completed])
            ->where('departure_at', '<', $monthEnd->utc())
            ->where('return_at', '>', $monthStart->utc())
            ->when($user->is_demo, fn ($query) => $query->whereHas('tripRequest.requester', fn ($query) => $query->where('is_demo', true)))
            ->orderBy('departure_at')
            ->get();

        $days = [];
        $lastGridDay = $monthEnd->subDay()->endOfWeek(CarbonInterface::SUNDAY);
        for ($day = $monthStart->startOfWeek(CarbonInterface::MONDAY); $day <= $lastGridDay; $day = $day->addDay()) {
            $days[] = [
                'date' => $day,
                'inMonth' => $day->isSameMonth($monthStart),
                'isToday' => $day->isSameDay($today),
                'bookings' => $bookings->filter(fn (Booking $booking) => $booking->departure_at < $day->addDay() && $booking->return_at > $day),
            ];
        }

        return [
            'timezone' => $timezone,
            'monthLabel' => $monthStart->format('F Y'),
            'month' => $this->month,
            'days' => $days,
            'bookings' => $bookings,
        ];
    }

    private function timezone(): string
    {
        return config('vehiserve.display_timezone');
    }

    private function monthStart(): CarbonImmutable
    {
        if (! preg_match('/\A[1-9][0-9]{3}-(0[1-9]|1[0-2])\z/', $this->month)) {
            return CarbonImmutable::now($this->timezone())->startOfMonth();
        }

        return CarbonImmutable::parse($this->month.'-01', $this->timezone())->startOfDay();
    }
}
