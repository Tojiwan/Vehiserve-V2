<?php

use App\Actions\AssignTrip;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.pgsql.database') !== 'vehiserve_test') {
    throw new RuntimeException('Concurrency checks require the disposable test database.');
}
DB::statement("SET application_name = 'vehiserve-scheduling-test'");
DB::statement("SET lock_timeout = '10s'");
echo "ready\n";
flush();
try {
    app(AssignTrip::class)->handle(User::findOrFail((int) $argv[1]), (int) $argv[2], (int) $argv[3], (int) $argv[4]);
    echo 'scheduled';
} catch (ValidationException) {
    echo 'conflict';
}
