<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Models\User;
use App\Modules\Demos\Data\DemoRequestData;
use App\Modules\Demos\Data\DemoTouch;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Models\DemoRequest;
use App\Modules\Demos\Notifications\DemoRequested;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class SubmitDemoRequest
{
    public function handle(DemoRequestData $data): DemoRequest
    {
        $credited = $data->attribution?->credited() ?? new DemoTouch;
        $first = $data->attribution->first ?? new DemoTouch;

        $demoRequest = DemoRequest::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'whatsapp' => $data->whatsapp,
            'company' => $data->company,
            'profile' => $data->profile,
            'workforce' => $data->workforce,
            'tools' => $data->tools,
            'message' => $data->message,
            'utm_source' => $credited->source,
            'utm_medium' => $credited->medium,
            'utm_campaign' => $credited->campaign,
            'referrer_host' => $credited->referrerHost,
            'first_utm_source' => $first->source,
            'first_utm_medium' => $first->medium,
            'first_utm_campaign' => $first->campaign,
            'first_referrer_host' => $first->referrerHost,
            'ad_click_type' => $data->attribution?->adClick?->type,
            'ad_click_id' => $data->attribution?->adClick?->id,
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

        return $demoRequest;
    }
}
