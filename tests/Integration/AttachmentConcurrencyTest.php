<?php

namespace Tests\Integration;

use App\Actions\StoreAttachment;
use App\Actions\SubmitTripRequest;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AttachmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_uploads_cannot_exceed_three_attachments(): void
    {
        $this->assertSame('vehiserve_test', DB::connection()->getDatabaseName());
        Storage::fake(Attachment::DISK);
        $user = User::factory()->create();
        $trip = app(SubmitTripRequest::class)->handle($user, [
            'office' => 'Demo', 'purpose' => 'Concurrency check', 'destination' => 'Demo campus',
            'departure_at' => '2026-12-01T08:00', 'return_at' => '2026-12-01T10:00', 'passengers' => ['Sample Passenger'],
        ]);
        $upload = fn () => app(StoreAttachment::class)->handle($user, $trip->id, UploadedFile::fake()->createWithContent('sample.pdf', "%PDF-1.4\n%%EOF"), AttachmentCategory::Supporting);
        $upload();
        $upload();
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-attachment.php'), (string) $user->id, (string) $trip->id], base_path(), [
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
                $blocked = DB::table('pg_stat_activity')->where('application_name', 'vehiserve-attachment-test')
                    ->where('wait_event_type', 'Lock')->exists();
                if ($blocked) {
                    break;
                }
                usleep(50000);
            } while (microtime(true) < $deadline);
            $this->assertTrue($blocked, 'The upload must wait for the request row lock.');
            $upload();
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertStringContainsString('limit reached', $process->getOutput());
            $this->assertSame(3, $trip->attachments()->count());
            $this->assertCount(3, Storage::disk(Attachment::DISK)->allFiles());
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
