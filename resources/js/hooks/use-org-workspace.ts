import { useMemo, useState } from 'react';
import { useChartTree } from '@/hooks/use-chart-tree';
import { useOrgDrag } from '@/hooks/use-org-drag';
import { useOrgEditor } from '@/hooks/use-org-editor';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useUnassigned } from '@/hooks/use-unassigned';
import { boundsOf } from '@/lib/org-chart/camera';
import { ancestorsOf } from '@/lib/org-chart/chart-state';
import type { ChartInit } from '@/lib/org-chart/chart-state';
import { chartGraph } from '@/lib/org-chart/chart-view';
import { applyDrag, openingRequest, toFlow } from '@/lib/org-chart/flow';
import type { CameraRequest } from '@/lib/org-chart/flow';
import { orgChartQuery } from '@/lib/org-chart/labels';
import { layoutGraph } from '@/lib/org-chart/layout';
import { panelOf } from '@/lib/org-chart/panel';
import { reports } from '@/routes/tenant/org-chart';
import type { OrgChartFilters } from '@/types';

export type OrgWorkspace = ReturnType<typeof useOrgWorkspace>;

/**
 * Everything the desktop chart needs, assembled: the tree and who is open, the person selected and their panel, the
 * graph the canvas draws with its layout, the editing mode with its drag and its tray, and where the camera goes.
 */
export function useOrgWorkspace({
    init,
    company,
    filters,
}: {
    init: ChartInit;
    company: { name: string; people: number };
    filters: OrgChartFilters;
}) {
    const tenantSlug = useTenantSlug();
    const tree = useChartTree({
        init,
        reportsUrl: (employee, page) =>
            reports.url(
                { tenant: tenantSlug, employee },
                { query: { ...orgChartQuery(filters), page: String(page) } },
            ),
    });
    const editor = useOrgEditor(tree);
    const focusId = init.chain.at(-1)?.id ?? null;
    const [selected, setSelected] = useState<string | null>(focusId);
    const [camera, setCamera] = useState<CameraRequest>(() =>
        openingRequest(init),
    );
    const graph = useMemo(
        () =>
            chartGraph(tree.state, {
                company: company.name,
                people: company.people,
                selected,
                highlight: focusId,
                filtered: filters.work_center !== null || filters.area !== null,
            }),
        [tree.state, company, selected, focusId, filters],
    );
    const positions = useMemo(
        () => layoutGraph(graph.nodes, graph.edges),
        [graph],
    );
    const drag = useOrgDrag({
        state: tree.state,
        view: { nodes: graph.nodes, positions },
        editor,
    });
    const tray = useUnassigned({
        enabled: editor.editing,
        version: editor.version,
    });
    const base = useMemo(
        () => toFlow(graph, { positions, dragging: null, drop: null }),
        [graph, positions],
    );
    const flow = useMemo(
        () => ({
            nodes: applyDrag(base.nodes, {
                dragging: drag.dragging,
                drop: drag.over,
            }),
            edges: base.edges,
        }),
        [base, drag.dragging, drag.over],
    );

    return {
        tree,
        editor,
        drag,
        tray,
        flow,
        camera: {
            request: camera,
            boundsOf: (ids: string[] | null) =>
                boundsOf(ids, graph.nodes, positions),
        },
        selected,
        panel: selected === null ? null : panelOf(tree.state, selected),
        select: (id: string | null) => {
            setSelected(id);

            if (id !== null) {
                tree.ensure(id);
            }
        },
        /** Selects a person and brings them (and the way to them) into view. */
        reveal: (id: string) => {
            setSelected(id);
            tree.ensure(id);
            setCamera({ ids: [...ancestorsOf(tree.state, id), id] });
        },
        showLevels: (levels: number) => {
            tree.showLevels(levels);
            setCamera({ ids: null });
        },
    };
}
