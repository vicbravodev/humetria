import { useRef, useState } from 'react';
import type { OrgEditor } from '@/hooks/use-org-editor';
import type { ChartState } from '@/lib/org-chart/chart-state';
import type { ViewNode } from '@/lib/org-chart/chart-view';
import type { DropTone } from '@/lib/org-chart/card-style';
import { dropMessage, dropState, targetAt } from '@/lib/org-chart/drag';
import type { DropState, Rect } from '@/lib/org-chart/drag';
import { asOrgNode } from '@/lib/org-chart/tray';
import type { OrgNode, UnassignedPerson } from '@/types';

type Point = { x: number; y: number };
type Over = { id: string; tone: DropTone; state: DropState };

/** The cards that can receive a person, where the layout put them. */
function targetRects(
    nodes: ViewNode[],
    positions: ReadonlyMap<string, Point>,
): Rect[] {
    return nodes.flatMap((view) => {
        const at = positions.get(view.id);

        return view.data.kind === 'person' && at !== undefined
            ? [{ id: view.id, ...at, width: view.width, height: view.height }]
            : [];
    });
}

/**
 * Dragging in the editing mode: a person of the chart (by their card) or of the tray (by its row) over the cards of
 * the canvas. Whoever is under the pointer lights as a valid or invalid manager; letting go on a valid one saves it,
 * on an invalid one says why, calmly.
 */
export function useOrgDrag({
    state,
    view,
    editor,
}: {
    state: ChartState;
    view: { nodes: ViewNode[]; positions: ReadonlyMap<string, Point> };
    editor: OrgEditor;
}) {
    const [dragging, setDragging] = useState<(Point & { id: string }) | null>(
        null,
    );
    const [over, setOver] = useState<Over | null>(null);
    const tray = useRef<UnassignedPerson | null>(null);

    const probe = (draggedId: string, at: Point): Over | null => {
        const id = targetAt(
            at,
            targetRects(view.nodes, view.positions),
            draggedId,
        );

        if (id === null) {
            return null;
        }

        const verdict = dropState(state, draggedId, id);

        return {
            id,
            tone: verdict === 'valid' ? 'valid' : 'invalid',
            state: verdict,
        };
    };

    const settle = (person: OrgNode, found: Over | null) => {
        setOver(null);
        setDragging(null);
        tray.current = null;

        const target = found === null ? undefined : state.people.get(found.id);

        if (found === null || target === undefined) {
            return;
        }

        const message = dropMessage(found.state, {
            dragged: person.name,
            target: target.name,
        });

        if (message === null) {
            void editor.assign(person, target);
        } else {
            editor.setMessage(message);
        }
    };

    const centre = (card: Point) => ({ x: card.x + 108, y: card.y + 36 });

    return {
        dragging,
        over,
        /** What the chart draws as the card being dragged and as the one under it. */
        cardDrag: {
            onDrag: (id: string, at: Point) => {
                setDragging({ id, ...at });
                setOver(probe(id, centre(at)));
            },
            onStop: (id: string, at: Point) => {
                const person = state.people.get(id);

                if (person !== undefined) {
                    settle(person, probe(id, centre(at)));
                }
            },
        },
        trayDrag: {
            onStart: (person: UnassignedPerson) => {
                tray.current = person;
            },
            onOver: (point: Point) => {
                if (tray.current !== null) {
                    setOver(probe(tray.current.id, point));
                }
            },
            onDrop: (point: Point) => {
                if (tray.current !== null) {
                    settle(
                        asOrgNode(tray.current),
                        probe(tray.current.id, point),
                    );
                }
            },
            onLeave: () => setOver(null),
        },
    };
}
