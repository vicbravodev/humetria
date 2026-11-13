import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import Initials from '@/components/humetria/initials';
import OrgPanelFlow from '@/components/organization/org-panel-flow';
import OrgPanelManager from '@/components/organization/org-panel-manager';
import OrgPanelTeam from '@/components/organization/org-panel-team';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { placeText } from '@/lib/org-chart/labels';
import type { panelOf } from '@/lib/org-chart/panel';
import { countOf } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { index as employees } from '@/routes/tenant/employees';

type Panel = NonNullable<ReturnType<typeof panelOf>>;

/**
 * The person selected on the canvas, on the right (360 px): who they are, their manager, their direct team, what
 * comes out of the chart for them and, when whoever looks may edit, the way to change their manager.
 */
export default function OrgPersonPanel({
    panel,
    actions,
    footer,
    docked = false,
}: {
    panel: Panel;
    actions: {
        onSelect: (id: string) => void;
        onClose: () => void;
        onMore: () => void;
    };
    /** «Cambiar jefe», only while editing. */
    footer: ReactNode;
    /** Beside the canvas instead of over it: while editing, the canvas and its bar stay clear. */
    docked?: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { node, manager, reports, status, more } = panel;

    return (
        <aside
            aria-label={`Panel de ${node.name}`}
            className={cn(
                'flex flex-col gap-5 overflow-y-auto p-5',
                docked
                    ? 'w-[320px] shrink-0 border-l bg-card'
                    : 'absolute inset-y-3 right-3 z-20 w-[360px] max-w-[calc(100%-1.5rem)] card shadow-float',
            )}
        >
            <header className="flex items-start gap-3">
                <Initials name={node.name} className="size-12 text-base" />
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg leading-6 font-semibold text-balance">
                        {node.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {[node.job_title, placeText(node)]
                            .filter((part) => part !== '')
                            .join(' · ')}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Cerrar el panel"
                    onClick={actions.onClose}
                >
                    <X />
                </Button>
            </header>
            <OrgPanelManager manager={manager} onSelect={actions.onSelect} />
            {node.direct_reports > 0 ? (
                <OrgPanelTeam
                    directReports={node.direct_reports}
                    reports={reports}
                    state={{
                        status,
                        more,
                        onSelect: actions.onSelect,
                        onMore: actions.onMore,
                    }}
                />
            ) : (
                <p className="text-sm text-muted-foreground">
                    No tiene un equipo directo.
                </p>
            )}
            {node.total_reports > 0 && (
                <p className="text-sm">
                    <span className="font-semibold">Equipo total:</span>{' '}
                    {countOf(node.total_reports, 'persona', 'personas')}
                </p>
            )}
            <OrgPanelFlow />
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" asChild>
                    <Link
                        href={employees(tenantSlug, {
                            query: { q: node.name },
                        })}
                    >
                        Ver su ficha de empleado
                    </Link>
                </Button>
                {footer}
            </div>
        </aside>
    );
}
