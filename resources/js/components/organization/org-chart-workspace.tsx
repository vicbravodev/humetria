import OrgChangeManager from '@/components/organization/org-change-manager';
import OrgChartCanvas from '@/components/organization/org-chart-canvas';
import OrgChartToolbar from '@/components/organization/org-chart-toolbar';
import OrgEditBand from '@/components/organization/org-edit-band';
import OrgEditMessage from '@/components/organization/org-edit-message';
import OrgPersonPanel from '@/components/organization/org-person-panel';
import OrgUnassignedTray from '@/components/organization/org-unassigned-tray';
import { Button } from '@/components/ui/button';
import type { OrgWorkspace } from '@/hooks/use-org-workspace';
import { Pencil } from 'lucide-react';
import type { OrgChartFilters, OrgNode } from '@/types';

/**
 * The chart for a wide screen: the canvas with its toolbar and the panel of the person selected and, while whoever may
 * edit is editing, the band that says how and the tray of people with no manager beside it.
 */
export default function OrgChartWorkspace({
    ws,
    filters,
    person,
}: {
    ws: OrgWorkspace;
    filters: OrgChartFilters;
    /** The focused person, found by the search. */
    person: OrgNode | undefined;
}) {
    const { editor, tree, panel } = ws;

    return (
        <section aria-label="Organigrama" className="grid gap-3">
            <div className="overflow-hidden card">
                {editor.editing && <OrgEditBand />}
                <div className="flex flex-col md:flex-row">
                    {editor.editing && (
                        <OrgUnassignedTray
                            tray={ws.tray}
                            onStart={ws.drag.trayDrag.onStart}
                            editor={editor}
                        />
                    )}
                    <div className="relative h-[clamp(540px,72vh,780px)] min-w-0 flex-1">
                        <OrgChartToolbar
                            filters={filters}
                            person={person}
                            reserve={panel !== null}
                            editing={editor.editing}
                            depth={{
                                levels: tree.levels,
                                onChange: ws.showLevels,
                            }}
                        />
                        <OrgChartCanvas
                            nodes={ws.flow.nodes}
                            edges={ws.flow.edges}
                            editing={editor.editing}
                            camera={ws.camera}
                            actions={{
                                selected: ws.selected,
                                editing: editor.editing,
                                onSelect: ws.select,
                                onToggle: tree.toggle,
                                onMore: tree.loadMore,
                            }}
                            drag={{
                                ...ws.drag.cardDrag,
                                onTrayOver: ws.drag.trayDrag.onOver,
                                onTrayDrop: ws.drag.trayDrag.onDrop,
                                onTrayLeave: ws.drag.trayDrag.onLeave,
                            }}
                        />
                        {panel !== null && (
                            <OrgPersonPanel
                                panel={panel}
                                actions={{
                                    onSelect: ws.reveal,
                                    onClose: () => ws.select(null),
                                    onMore: () => tree.loadMore(panel.node.id),
                                }}
                                footer={
                                    editor.editing ? (
                                        <OrgChangeManager
                                            person={panel.node}
                                            manager={panel.manager}
                                            editor={editor}
                                            trigger={
                                                <Button variant="outline">
                                                    <Pencil />
                                                    Cambiar jefe
                                                </Button>
                                            }
                                        />
                                    ) : null
                                }
                            />
                        )}
                        {editor.message !== null && (
                            <OrgEditMessage
                                message={editor.message}
                                className="absolute bottom-4 left-1/2 z-30 max-w-[calc(100%-2rem)] -translate-x-1/2 shadow-float"
                            />
                        )}
                    </div>
                </div>
            </div>
            <p className="text-meta text-muted-foreground print:hidden">
                {editor.editing
                    ? 'Arrastra el lienzo para moverlo · Rueda para acercar · Suelta una persona sobre su jefe nuevo · También puedes elegir a la persona y usar «Cambiar jefe»'
                    : 'Arrastra para mover · Rueda para acercar · Clic en una persona para ver su equipo'}
            </p>
        </section>
    );
}
