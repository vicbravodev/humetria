<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http;

use App\Modules\Compensation\Enums\ScenarioSource;
use Illuminate\Http\Request;

/**
 * Which kind of scenario a page of Compensación is for: the routes under /compensation/scenarios are the ones costed on the
 * roster and the ones under /compensation/quick the ones costed on an average payroll («Cotizador rápido»). The route carries
 * it as its `source` default.
 */
final class ScenarioRoute
{
    public static function source(Request $request): ScenarioSource
    {
        $source = $request->route('source');

        return ScenarioSource::tryFrom(is_string($source) ? $source : '') ?? ScenarioSource::Roster;
    }

    /** The name of a page of that kind: `tenant.compensation.quick.edit`. */
    public static function name(ScenarioSource $source, string $page): string
    {
        return 'tenant.compensation.'.($source === ScenarioSource::Average ? 'quick' : 'scenarios').'.'.$page;
    }
}
