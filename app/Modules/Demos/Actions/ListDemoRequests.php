<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Pagination\LengthAwarePaginator;

final class ListDemoRequests
{
    /**
     * @return LengthAwarePaginator<int, DemoRequest>
     */
    public function handle(): LengthAwarePaginator
    {
        return DemoRequest::query()->latest()->paginate(25);
    }
}
