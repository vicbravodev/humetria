<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Models\Benefit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** The active benefits of the company that have what was typed, for the field that picks the benefit a scenario changes. */
final class BenefitOptionsController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @param  mixed  $tenant  The company, already resolved by Tenancy from the URL; only routed through.
     * @return list<array{value: string, label: string, unit: string}>
     */
    public function __invoke(Request $request, mixed $tenant): array
    {
        Gate::authorize('view-compensation');

        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $options = [];

        foreach (Benefit::query()->whereNull('archived_at')->matching(['name'], $search)->orderBy('name')->limit(self::LIMIT)->get() as $benefit) {
            $options[] = ['value' => $benefit->id, 'label' => $benefit->name, 'unit' => $benefit->kind->unit()];
        }

        return $options;
    }
}
