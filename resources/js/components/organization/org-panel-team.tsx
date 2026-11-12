import { useState } from 'react';
import OrgMiniPerson from '@/components/organization/org-mini-person';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { teamPreview } from '@/lib/org-chart/panel';
import type { OrgNode } from '@/types';

/** The direct team of a person in the panel: the first few, «Ver los 17» for the rest, and the next page when it is needed. */
export default function OrgPanelTeam({
    directReports,
    reports,
    state,
}: {
    directReports: number;
    reports: OrgNode[];
    state: {
        status: 'loading' | 'ready' | 'error' | undefined;
        more: boolean;
        onSelect: (id: string) => void;
        onMore: () => void;
    };
}) {
    const [all, setAll] = useState(false);
    const { shown, seeAll } = teamPreview(reports, directReports, all);

    return (
        <section aria-labelledby="org-panel-team" className="grid gap-2">
            <h3 id="org-panel-team" className="text-sm font-semibold">
                Su equipo directo ({directReports})
            </h3>
            {state.status === 'loading' && reports.length === 0 && (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Spinner className="size-4" />
                    Cargando a su equipo…
                </p>
            )}
            {state.status === 'error' && (
                <p role="alert" className="text-sm text-muted-foreground">
                    No se pudo cargar a su equipo.{' '}
                    <button
                        type="button"
                        className="font-medium text-primary underline-offset-4 hover:underline"
                        onClick={state.onMore}
                    >
                        Reintentar
                    </button>
                </p>
            )}
            <ul className="grid gap-2">
                {shown.map((person) => (
                    <li key={person.id}>
                        <OrgMiniPerson
                            node={person}
                            onSelect={state.onSelect}
                        />
                    </li>
                ))}
            </ul>
            {seeAll !== null && (
                <Button
                    variant="ghost"
                    className="justify-start px-2 text-primary"
                    onClick={() => setAll(true)}
                >
                    {seeAll}
                </Button>
            )}
            {all && state.more && (
                <Button variant="outline" onClick={state.onMore}>
                    Ver más personas
                </Button>
            )}
        </section>
    );
}
