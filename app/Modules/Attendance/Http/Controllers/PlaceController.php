<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Contracts\PlaceSearch;
use App\Modules\Attendance\Http\Requests\PlaceRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/** The places that match an address, for the map of the zone dialog: owner and hr only, as they change the zones. */
final readonly class PlaceController
{
    public function __invoke(PlaceRequest $request, PlaceSearch $places): JsonResponse
    {
        try {
            return response()->json(['places' => $places->find($request->address())]);
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('attendance.place_search_failed', ['error' => $exception::class]);

            return response()->json(['message' => 'No pudimos buscar la dirección. Toca el mapa o usa tu ubicación.'], 503);
        }
    }
}
