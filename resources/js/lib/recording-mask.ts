/** What the panel's recording hides: the company's data, never the interface (lib/session-recording.ts). */

export function isObject(value: unknown): value is object {
    return typeof value === 'object' && value !== null;
}

export function prop(value: unknown, name: string): unknown {
    return isObject(value) ? Reflect.get(value, name) : undefined;
}

/** Attributes that say how the page looks, not who or what is on it: everything else is masked. */
const KEPT_ATTRIBUTES = new Set([
    'class',
    'style',
    'id',
    'role',
    'type',
    'rel',
    'tabindex',
    'disabled',
    'hidden',
    'width',
    'height',
    'viewbox',
    'd',
    'fill',
    'stroke',
    'xmlns',
    'points',
    'cx',
    'cy',
    'r',
    'x',
    'y',
    'rx',
    'ry',
    'x1',
    'x2',
    'y1',
    'y2',
    'transform',
    'colspan',
    'rowspan',
]);

/**
 * The rest that only says how the page looks: rrweb's own (`_cssText` is the page's CSS inlined, without which the player
 * paints it unstyled; `rr_*` keeps scroll, sizes and media), the ARIA states (not its labels) and Radix's data states.
 */
const LOOK_ATTRIBUTE =
    /^(_csstext|rr_[a-z]+|aria-(?!label|description|valuetext)[a-z]+|data-(state|slot|side|align|orientation|disabled|selected|highlighted))$/;

/** Our own static files (styles, the logo, images of the product): the player needs them to paint the page as it was. */
const OWN_ASSET = /^\/(build|images|fonts|brand)\//;

/**
 * What is hidden even if the page data does not carry it: what a person or a company is (an email, a phone or an id,
 * a CURP or an RFC, an amount), and anything inside what is fetched apart (a search's options) or marked `ph-mask`.
 */
const SENSITIVE_SHAPE =
    /\S+@\S+\.\S+|\$\s?\d|\d[\d\s.,-]{3,}\d|\b[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{0,8}\b/;
const SENSITIVE_PLACE =
    '[role=option], [role=listbox], [role=combobox], .ph-mask';

/** A datum shorter than this is not looked for in the texts: it would hide words of the interface too. */
const SHORTEST = 3;

let data: string[] = [];

/** Every attribute that could carry a name, an address or a value becomes `*`; the look stays. */
export function maskAttribute(name: string, value: string): string {
    const lower = name.toLowerCase();
    const kept =
        KEPT_ATTRIBUTES.has(lower) ||
        LOOK_ATTRIBUTE.test(lower) ||
        ((lower === 'href' || lower === 'src') && OWN_ASSET.test(value));

    return kept ? value : '*';
}

function strings(value: unknown, found: string[]): string[] {
    if (typeof value === 'string') {
        const text = value.trim();

        if (text.length >= SHORTEST) {
            found.push(text);
        }
    } else if (isObject(value)) {
        Object.values(value).forEach((entry) => strings(entry, found));
    }

    return found;
}

/** The texts of the page's data (its props: the people, the company, who signed in): what the recording hides. */
export function rememberData(page: unknown): void {
    data = strings(prop(page, 'props'), []);
}

/**
 * Only what is the company's is hidden: a text that holds a datum of the page (as a name, alone or inside a sentence), has the shape of a personal datum or sits where data is fetched apart. The interface (the menu, the
 * titles, the buttons, the messages: written in the code, the same for every company) is shown as it is.
 */
export function maskText(
    text: string,
    element?: Pick<Element, 'closest'> | null,
): string {
    const trimmed = text.trim();
    const hidden =
        element?.closest(SENSITIVE_PLACE) != null ||
        SENSITIVE_SHAPE.test(trimmed) ||
        data.some((value) => trimmed.includes(value));

    return hidden ? text.replace(/\S/g, '*') : text;
}
