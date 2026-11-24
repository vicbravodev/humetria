/** One link inside the group «Recursos»: a page of its own or a section of the landing, with its icon or picture. */
export type ResourceLink = {
    id: string;
    label: string;
    hint: string;
    kind: string;
    path?: string | undefined;
    section?: string | undefined;
    test: string;
    paths: readonly string[];
    /** A picture instead of the line icon (Humi's own face): `/brand/{image}.svg` and `-oscuro`. */
    image?: string | undefined;
};

/** One link of the bar: a section of the landing, or a group that holds the rest (Recursos). */
export type NavLink = {
    id: string;
    label: string;
    kind: 'section' | 'group';
    section?: string;
    test: string;
    items?: readonly ResourceLink[];
};
