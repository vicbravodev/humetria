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
