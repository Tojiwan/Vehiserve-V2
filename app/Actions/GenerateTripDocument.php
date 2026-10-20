<?php

namespace App\Actions;

use App\Enums\TripDocument;
use App\Models\TripRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\File;

class GenerateTripDocument
{
    public function handle(TripRequest $trip, TripDocument $type): DomPdf
    {
        $temporaryDirectory = storage_path('app/private/dompdf');
        File::ensureDirectoryExists($temporaryDirectory);

        return Pdf::loadView('pdf.'.$type->value, [
            'trip' => $trip,
            'document' => $type,
            'timezone' => config('vehiserve.display_timezone'),
        ])->setOptions([
            // Templates are trusted and local: no remote fetches, and file reads stay inside public/.
            'isRemoteEnabled' => false,
            'chroot' => str_replace('\\', '/', public_path()),
            'defaultFont' => 'DejaVu Sans',
            'tempDir' => $temporaryDirectory,
        ]);
    }
}
