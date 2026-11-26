import { Check, Minus } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { moduleLabel } from '@/lib/billing-catalog';
import type { CatalogView } from '@/types';

/** Which module each plan brings, one row per module of the catalog. */
export default function PlanModulesTable({
    catalog,
}: {
    catalog: CatalogView;
}) {
    return (
        <section
            aria-labelledby="plan-modules"
            className="overflow-hidden card p-0 [&_[data-slot=table-container]]:rounded-none [&_[data-slot=table-container]]:border-0"
        >
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-b px-6 py-4">
                <h2 id="plan-modules" className="flex-1 font-semibold">
                    Qué módulo trae cada plan
                </h2>
                <p className="text-sm text-muted-foreground">
                    Cada cruce puede anularse por empresa
                </p>
            </div>
            <div>
                <Table className="min-w-[640px]">
                    <TableCaption className="sr-only">
                        Módulos del catálogo {catalog.version} por plan
                    </TableCaption>
                    <TableHeader>
                        <TableRow className="bg-muted hover:bg-muted">
                            <TableHead>Módulo</TableHead>
                            {catalog.plans.map((plan) => (
                                <TableHead key={plan.key}>
                                    {plan.label}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {catalog.modules.map((module) => (
                            <TableRow key={module}>
                                <TableCell className="font-medium">
                                    {moduleLabel(module)}
                                </TableCell>
                                {catalog.plans.map((plan) => (
                                    <TableCell key={plan.key}>
                                        {plan.modules.includes(module) ? (
                                            <Check
                                                aria-label="Incluido"
                                                className="size-4 text-primary"
                                            />
                                        ) : (
                                            <Minus
                                                aria-label="No incluido"
                                                className="size-4 text-muted-foreground"
                                            />
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
        </section>
    );
}
