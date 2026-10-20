<?php

namespace App\Http\Controllers;

use App\Actions\GenerateTripDocument;
use App\Enums\TripDocument;
use App\Models\TripRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TripDocumentController extends Controller
{
    public function __invoke(TripRequest $trip, string $type, GenerateTripDocument $generator): Response
    {
        Gate::authorize('view', $trip);
        $document = TripDocument::from($type);

        return $generator->handle($trip, $document)->download($document->filename($trip));
    }
}
