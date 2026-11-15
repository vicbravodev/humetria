import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { ChartTree } from '@/hooks/use-chart-tree';
import { useManagerChange } from '@/hooks/use-manager-change';
import { undoText } from '@/lib/org-chart/canvas-labels';
import { parentsOf } from '@/lib/org-chart/chart-state';
import { isEditing } from '@/lib/org-chart/labels';
import type { OrgNode } from '@/types';

export type OrgEditor = ReturnType<typeof useOrgEditor>;

/** Who a person will report to: enough to save it and to say it (the manager may not be on the chart). */
export type ManagerRef = Pick<OrgNode, 'id' | 'name'>;

/** The person of the chart with that id; nobody for a person at the top or one who is not there. */
function personOf(tree: ChartTree, id: string | null): OrgNode | null {
    return id === null ? null : (tree.state.people.get(id) ?? null);
}

/** Says what changed; with `before` it offers «Deshacer» (the same change back to them), without it the change was an undo. */
function announce(
    {
        person,
        manager,
        before,
    }: {
        person: OrgNode;
        manager: ManagerRef | null;
        before: OrgNode | null | undefined;
    },
    back: (to: OrgNode | null) => Promise<boolean>,
): void {
    if (before === undefined) {
        toast('Cambio deshecho');

        return;
    }

    toast.success(undoText(person.name, manager?.name ?? null), {
        action: { label: 'Deshacer', onClick: () => void back(before) },
        // Sonner's own colors do not reach the contrast of the design system: its primary does.
        actionButtonStyle: {
            background: 'var(--primary)',
            color: 'var(--primary-foreground)',
        },
    });
}

/**
 * The editing mode: it saves who a person reports to, then moves them in the chart and offers «Deshacer» (another
 * assignment, to whoever they reported to before). Nothing moves on screen until the server accepted the change.
 */
export function useOrgEditor(tree: ChartTree) {
    const { url } = usePage();
    // An address with `edit=1` opens in the editing mode: finding someone reloads the page and must not end it.
    const [editing, setEditing] = useState(() => isEditing(url));
    const [message, setMessage] = useState<string | null>(null);
    // Counts every change saved, so whatever lists people outside the chart (the tray) asks again.
    const [version, setVersion] = useState(0);
    const { change, processing } = useManagerChange();

    const assign = async (
        person: OrgNode,
        manager: ManagerRef | null,
        undoing = false,
    ): Promise<boolean> => {
        const before = personOf(
            tree,
            parentsOf(tree.state).get(person.id) ?? null,
        );
        const result = await change(person.id, manager?.id ?? '');

        setMessage(result.ok ? null : result.message);

        if (result.ok) {
            setVersion((current) => current + 1);
            tree.move(person, manager?.id ?? null);
            announce(
                { person, manager, before: undoing ? undefined : before },
                (to) => assign(person, to, true),
            );
        }

        return result.ok;
    };

    return {
        editing,
        setEditing: (next: boolean) => {
            setEditing(next);
            setMessage(null);
        },
        message,
        setMessage,
        version,
        processing,
        assign,
    };
}
