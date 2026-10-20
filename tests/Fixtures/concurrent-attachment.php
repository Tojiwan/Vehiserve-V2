<?php

use App\Actions\StoreAttachment;
use App\Enums\AttachmentCategory;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'vehiserve_test') {
    throw new RuntimeException('Concurrency checks require the disposable test database.');
}
Storage::persistentFake(Attachment::DISK);
DB::statement("SET application_name = 'vehiserve-attachment-test'");
DB::statement("SET lock_timeout = '10s'");
echo "ready\n";
flush();
try {
    app(StoreAttachment::class)->handle(User::findOrFail((int) $argv[1]), (int) $argv[2], UploadedFile::fake()->createWithContent('worker.pdf', "%PDF-1.4\n%%EOF"), AttachmentCategory::Supporting);
    echo 'uploaded';
} catch (ValidationException) {
    echo 'limit reached';
}
