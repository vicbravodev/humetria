import { router } from '@inertiajs/react';
import { Archive, ChartColumn, Copy, Ellipsis, Pencil } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import {
    archive,
    duplicate,
    edit,
    report,
} from '@/routes/tenant/compensation/scenarios';
import type { ScenarioRow } from '@/types';

type Action = 'duplicate' | 'archive';

/** Edit a scenario, make a copy to try a variation, or archive it (never delete: a scenario is kept). */
export default function ScenarioRowActions({
    scenario,
}: {
    scenario: ScenarioRow;
}) {
    const tenantSlug = useTenantSlug();
    const [pending, setPending] = useState<Action | null>(null);
    const ids = { tenant: tenantSlug, scenario: scenario.id };

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Acciones de ${scenario.name}`}
                    data-test="scenario-actions"
                    className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                >
                    <Ellipsis className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="min-w-48"
                // Sent once the menu has closed: a page redrawn mid-animation leaves the menu unable to open again.
                onCloseAutoFocus={() => {
                    if (pending !== null) {
                        const send =
                            pending === 'archive' ? archive : duplicate;

                        setPending(null);
                        router.post(
                            send.url(ids),
                            {},
                            { preserveScroll: true },
                        );
                    }
                }}
            >
                <DropdownMenuItem
                    onSelect={() => router.visit(report.url(ids))}
                >
                    <ChartColumn />
                    Ver reporte
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => router.visit(edit.url(ids))}>
                    <Pencil />
                    Editar
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={() => setPending('duplicate')}>
                    <Copy />
                    Duplicar
                </DropdownMenuItem>
                {!scenario.archived && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() => setPending('archive')}
                        >
                            <Archive />
                            Archivar
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
