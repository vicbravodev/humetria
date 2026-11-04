import { Link } from '@inertiajs/react';
import ScenarioRowActions from '@/components/compensation/scenario-row-actions';
import { Checkbox } from '@/components/ui/checkbox';
import { TableCell, TableRow } from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { raiseSummary } from '@/lib/compensation/scenarios';
import { countOf } from '@/lib/plural';
import { edit } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioRow } from '@/types';

/** A scenario of the list: its name and when it starts, what it raises and changes, and what it costs in its first year. */
export default function ScenarioTableRow({
    scenario,
    checked,
    disabled,
    onChoose,
}: {
    scenario: ScenarioRow;
    checked: boolean;
    disabled: boolean;
    onChoose: (id: string, checked: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <TableRow>
            <TableCell className="w-px">
                <Checkbox
                    aria-label={`Comparar ${scenario.name}`}
                    checked={checked}
                    disabled={disabled}
                    onCheckedChange={(value) =>
                        onChoose(scenario.id, value === true)
                    }
                />
            </TableCell>
            <TableCell className="whitespace-normal">
                <Link
                    href={edit({ tenant: tenantSlug, scenario: scenario.id })}
                    className="inline-flex min-h-11 items-center font-semibold hover:underline"
                >
                    {scenario.name}
                </Link>
                <span className="block text-sm text-muted-foreground">
                    Desde el {describeDate(scenario.effective_on)} ·{' '}
                    {countOf(scenario.horizon_years, 'año', 'años')} ·{' '}
                    {countOf(
                        scenario.people,
                        'persona costeada',
                        'personas costeadas',
                    )}
                </span>
            </TableCell>
            <TableCell className="whitespace-normal">
                {raiseSummary(scenario.unionized_raise, scenario.trusted_raise)}
                {scenario.changed_benefits > 0 && (
                    <span className="block text-sm text-muted-foreground">
                        {countOf(
                            scenario.changed_benefits,
                            'prestación cambia',
                            'prestaciones cambian',
                        )}
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right font-medium tabular-nums">
                {scenario.first_year_text}
                {scenario.retroactive_months > 0 && (
                    <span className="block text-sm font-normal text-muted-foreground">
                        con{' '}
                        {countOf(scenario.retroactive_months, 'mes', 'meses')}{' '}
                        de retroactivo
                    </span>
                )}
            </TableCell>
            <TableCell className="text-right tabular-nums">
                {scenario.recurring_text}
            </TableCell>
            <TableCell className="w-px text-right">
                <ScenarioRowActions scenario={scenario} />
            </TableCell>
        </TableRow>
    );
}
