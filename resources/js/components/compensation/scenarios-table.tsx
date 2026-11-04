import { Link } from '@inertiajs/react';
import ScenarioRowActions from '@/components/compensation/scenario-row-actions';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { raiseSummary } from '@/lib/compensation/scenarios';
import { countOf } from '@/lib/plural';
import { edit } from '@/routes/tenant/compensation/scenarios';
import type { ScenarioRow } from '@/types';

/** The scenarios, the newest first: what each proposes and what it costs in its first year, with the months of retroactivity in it. */
export default function ScenariosTable({
    scenarios,
}: {
    scenarios: ScenarioRow[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Table>
            <TableCaption className="sr-only">
                Escenarios, los más recientes primero
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Escenario</TableHead>
                    <TableHead>Aumento al salario</TableHead>
                    <TableHead className="text-right">
                        Costo del primer año
                    </TableHead>
                    <TableHead className="text-right">
                        Costo anual recurrente
                    </TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {scenarios.map((scenario) => (
                    <TableRow key={scenario.id}>
                        <TableCell className="whitespace-normal">
                            <Link
                                href={edit({
                                    tenant: tenantSlug,
                                    scenario: scenario.id,
                                })}
                                className="inline-flex min-h-11 items-center font-semibold hover:underline"
                            >
                                {scenario.name}
                            </Link>
                            <span className="block text-sm text-muted-foreground">
                                Desde el {describeDate(scenario.effective_on)} ·{' '}
                                {countOf(scenario.horizon_years, 'año', 'años')}{' '}
                                ·{' '}
                                {countOf(
                                    scenario.people,
                                    'persona costeada',
                                    'personas costeadas',
                                )}
                            </span>
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {raiseSummary(
                                scenario.unionized_raise,
                                scenario.trusted_raise,
                            )}
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
                                    {countOf(
                                        scenario.retroactive_months,
                                        'mes',
                                        'meses',
                                    )}{' '}
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
                ))}
            </TableBody>
        </Table>
    );
}
