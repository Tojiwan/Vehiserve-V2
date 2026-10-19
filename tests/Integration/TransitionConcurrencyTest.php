<?php

namespace Tests\Integration;

use App\Actions\SubmitTripRequest;
use App\Actions\TransitionTripRequest;
use App\Enums\RequestState;
use App\Enums\Role;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TransitionConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_decisions_wait_for_the_lock_and_reject_the_stale_state(): void
    {
        $this->assertSame('vehiserve_test', DB::connection()->getDatabaseName());
        $owner = User::factory()->create();
        $dean = User::factory()->create(['role' => Role::Dean]);
        $trip = app(SubmitTripRequest::class)->handle($owner, [
            'office' => 'Demo', 'purpose' => 'Concurrency check', 'destination' => 'Demo campus',
            'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T10:00',
            'passengers' => ['Sample Passenger'],
        ]);
        $trip->state = RequestState::AwaitingDean;
        $trip->save();

        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-approval.php'), (string) $dean->id, (string) $trip->id], base_path(), [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'vehiserve_test', 'DB_URL' => '',
        ], timeout: 20);
        DB::beginTransaction();
        try {
            TripRequest::query()->lockForUpdate()->findOrFail($trip->id);
            $process->start();
            $process->waitUntil(fn ($type, $output) => str_contains($output, 'ready'));

            $deadline = microtime(true) + 8;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::table('pg_stat_activity')->where('application_name', 'vehiserve-concurrency-test')
                    ->where('wait_event_type', 'Lock')->exists();
                if ($blocked) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($blocked, 'The concurrent worker must actually wait on the request row lock.');

            app(TransitionTripRequest::class)->handle($dean, $trip->id, RequestState::AwaitingDean, 'approve');
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('stale', $process->getOutput());
            $this->assertSame(RequestState::AwaitingVp, $trip->fresh()->state);
            $this->assertSame(2, $trip->events()->count());
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}
