<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class MetaWebhookController
{
    public function verify(Request $request): Response
    {
        abort(404);
    }

    public function receive(Request $request): Response
    {
        abort(404);
    }
}
