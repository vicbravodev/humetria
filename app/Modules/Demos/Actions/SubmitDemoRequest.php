<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Models\User;
use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Notifications\DemoRequested;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

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
            'tools' => $data->tools,
            'message' => $data->message,
        ]);

        // Only what the console needs to spot a trend: never who asked or how to reach them.
        Log::info('demo_request.submitted', [
            'demo_request_id' => $demoRequest->id,
            'profile' => $data->profile->value,
            'workforce' => $data->workforce->value,
            'tools' => array_map(fn (Tool $tool): string => $tool->value, $data->tools),
        ]);

        // Whoever runs the platform finds out when it happens, not when they next open the console.
        Notification::send(User::role(User::SUPERADMIN)->get(), new DemoRequested($data->company, $data->name, $demoRequest->id));
    }
}
