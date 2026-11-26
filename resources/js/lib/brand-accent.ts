type Existing = {
    textContent: string | null;
    remove(): void;
};

type Created = Existing & {
    id: string;
    nonce?: string | undefined;
};

type Host<Node extends Created> = {
    getElementById(id: string): Existing | null;
    createElement(tag: 'style'): Node;
    head: { append(node: Node): void };
};

const ID = 'brand-accent';

/**
 * Puts the accent of the company the page belongs to in the page, or takes it off (null). The server paints the first
 * load; an Inertia visit changes the page without loading it again, so what the last page left in the head would stay:
 * the landing after a logout in the colors of the company, and a company in the colors of the one before.
 */
export function applyBrandAccent<Node extends Created>(
    host: Host<Node>,
    css: string | null,
    nonce: string | undefined,
): void {
    const current = host.getElementById(ID);

    if (css === null) {
        current?.remove();

        return;
    }

    if (current !== null) {
        current.textContent = css;

        return;
    }

    const style = host.createElement('style');
    style.id = ID;
    style.nonce = nonce ?? '';
    style.textContent = css;
    host.head.append(style);
}

/** The accent the server shared with a page (`brand.css`); null when the page is not a company's. */
export function brandCssOf(props: unknown): string | null {
    if (typeof props !== 'object' || props === null || !('brand' in props)) {
        return null;
    }

    const brand = props.brand;

    if (typeof brand !== 'object' || brand === null || !('css' in brand)) {
        return null;
    }

    return typeof brand.css === 'string' ? brand.css : null;
}
