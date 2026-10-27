<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Facades\Log;

final class SubmitDemoRequest
{
    public function handle(DemoRequestData $data): void
    {
        $demoRequest = DemoRequest::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'whatsapp' => $data->whatsapp,
            'company' => $data->company,
            'profile' => $data->profile,
            'workforce' => $data->workforce,
            'message' => $data->message,
        ]);

        // Only what the console needs to spot a trend: never who asked or how to reach them.
        Log::info('demo_request.submitted', [
            'demo_request_id' => $demoRequest->id,
            'profile' => $data->profile->value,
            'workforce' => $data->workforce->value,
        ]);
    }
}
