<?php

namespace Tests\Integration;

use App\Actions\AssignTrip;
use App\Actions\SubmitTripRequest;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SchedulingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_assignments_cannot_double_book_one_vehicle(): void
    {
        $this->assertResourceConflict(false);
    }

    public function test_simultaneous_assignments_with_different_vehicles_cannot_double_book_one_driver(): void
    {
        $this->assertResourceConflict(true);
    }

    private function assertResourceConflict(bool $sharedDriver): void
    {
        $this->assertSame('vehiserve_test', DB::connection()->getDatabaseName());
        $staff = User::factory()->create(['role' => Role::MotorPool]);
        $vehicle = Vehicle::create(['plate' => 'CONC-1', 'name' => 'Demo van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available']);
        $driver = Driver::create(['name' => 'Sample Driver', 'status' => 'available']);
        $secondVehicle = $sharedDriver ? Vehicle::create(['plate' => 'CONC-2', 'name' => 'Second van', 'type' => 'Van', 'passenger_capacity' => 12, 'status' => 'available']) : $vehicle;
        $tripA = $this->readyRequest();
        $tripB = $this->readyRequest();

        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-scheduling.php'), (string) $staff->id, (string) $tripA->id, (string) $vehicle->id, (string) $driver->id], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'vehiserve_test', 'DB_URL' => '',
        ], timeout: 20);
        DB::beginTransaction();
        try {
            if ($sharedDriver) {
                Driver::query()->lockForUpdate()->findOrFail($driver->id);
            } else {
                Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
            }
            $process->start();
            $process->waitUntil(fn ($type, $output) => str_contains($output, 'ready'));

            $deadline = microtime(true) + 8;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::table('pg_stat_activity')->where('application_name', 'vehiserve-scheduling-test')
                    ->where('wait_event_type', 'Lock')->exists();
                if ($blocked) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($blocked, 'The concurrent worker must actually wait on the resource row lock.');

            app(AssignTrip::class)->handle($staff, $tripB->id, $secondVehicle->id, $driver->id);
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('conflict', $process->getOutput());
            $this->assertStringNotContainsString('scheduled', $process->getOutput());
            $this->assertSame(RequestState::Scheduled, $tripB->fresh()->state);
            $this->assertSame(RequestState::ReadyToSchedule, $tripA->fresh()->state);
            $this->assertSame(1, Booking::query()->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }

    private function readyRequest(): TripRequest
    {
        $trip = app(SubmitTripRequest::class)->handle(User::factory()->create(), [
            'office' => 'Demo', 'purpose' => 'Concurrency check', 'destination' => 'Demo campus',
            'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T10:00', 'passengers' => ['Sample Passenger'],
        ]);
        $trip->state = RequestState::ReadyToSchedule;
        $trip->save();

        return $trip;
    }
}
