<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ResendWebhookController
{
    public function receive(Request $request): Response
    {
        abort(404);
    }
}
