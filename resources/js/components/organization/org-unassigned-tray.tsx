import SearchField from '@/components/humetria/search-field';
import OrgTrayPerson from '@/components/organization/org-tray-person';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ManagerRef } from '@/hooks/use-org-editor';
import type { useUnassigned } from '@/hooks/use-unassigned';
import type { OrgNode, UnassignedPerson } from '@/types';

/**
 * «Sin jefe» of the editing mode: whoever has no manager and leads nobody, so they are not on the chart yet. Each row
 * is dragged onto their manager in the canvas, or given one with «Asignar jefe» (keyboard and touch).
 */
export default function OrgUnassignedTray({
    tray,
    onStart,
    editor,
}: {
    tray: ReturnType<typeof useUnassigned>;
    onStart: (person: UnassignedPerson) => void;
    editor: {
        message: string | null;
        processing: boolean;
        assign: (
            person: OrgNode,
            manager: ManagerRef | null,
        ) => Promise<boolean>;
    };
}) {
    return (
        <aside
            aria-label="Personas sin jefe"
            className="flex w-full shrink-0 flex-col gap-3 overflow-x-hidden overflow-y-auto border-b bg-card p-4 md:w-[280px] md:border-r md:border-b-0"
        >
            <div className="flex items-center gap-2">
                <h2 className="text-lg font-semibold">Sin jefe</h2>
                <Badge variant="secondary">{tray.total}</Badge>
            </div>
            <p className="text-sm text-muted-foreground">
                Arrástralas al lienzo sobre su jefe.
            </p>
            <SearchField
                label="Busca en esta lista"
                value={tray.query}
                onChange={tray.setQuery}
            />
            {tray.status === 'error' && (
                <p role="alert" className="text-sm text-muted-foreground">
                    No se pudo cargar la lista.
                </p>
            )}
            {tray.status === 'ready' && tray.people.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {tray.query === ''
                        ? 'Nadie sin jefe: todas las personas tienen su lugar en el organigrama.'
                        : 'Nadie coincide con lo que escribiste.'}
                </p>
            )}
            <ul className="grid gap-2">
                {tray.people.map((person) => (
                    <OrgTrayPerson
                        key={person.id}
                        person={person}
                        onStart={onStart}
                        editor={editor}
                    />
                ))}
            </ul>
            {tray.nextPage !== null && (
                <Button variant="outline" onClick={tray.more}>
                    Ver más
                </Button>
            )}
            <p className="text-sm text-muted-foreground">
                Solo aparecen quienes no tienen jefe y todavía no están en el
                árbol. Al soltarlas sobre alguien, salen de aquí.
            </p>
        </aside>
    );
}
