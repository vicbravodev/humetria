import { createContext, useContext } from 'react';

/** What the cards of the canvas can do, given by the canvas: they live inside React Flow, out of reach of props. */
export type OrgCanvasActions = {
    selected: string | null;
    editing: boolean;
    onSelect: (id: string) => void;
    onToggle: (id: string) => void;
    /** Loads the next page of the reports of a person, or tries again after a failure. */
    onMore: (parentId: string) => void;
};

export const OrgCanvasContext = createContext<OrgCanvasActions>({
    selected: null,
    editing: false,
    onSelect: () => undefined,
    onToggle: () => undefined,
    onMore: () => undefined,
});

export function useOrgCanvasActions(): OrgCanvasActions {
    return useContext(OrgCanvasContext);
}
