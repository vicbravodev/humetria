<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\RequestPages;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RH's queue of vacation and leave requests: owner, hr and viewer read it, owner and hr answer, correct and record. A work
 * center that is not one in use of the company is a 404.
 */
final readonly class RequestController
{
    public function __construct(private WorkCenters $centers) {}

    public function index(Request $request, LeaveDefaults $defaults, RequestPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/requests', $pages->index(
            $request->string('tab')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
            $this->uuid($request, 'center', fn (string $id): bool => $this->centers->findActive($id) !== null),
            $this->uuid($request, 'area', fn (): bool => true),
            Gate::allows('manage-vacations'),
        ));
    }

    /**
     * The id a filter names: none when it is not asked for, a 404 when it is not a uuid or is not one `valid` accepts.
     *
     * @param  callable(string): bool  $valid
     */
    private function uuid(Request $request, string $key, callable $valid): ?string
    {
        $id = $request->string($key)->toString();

        if ($id === '') {
            return null;
        }

        return Str::isUuid($id) && $valid($id) ? $id : abort(404);
    }
}
