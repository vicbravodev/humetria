export type Inline =
    | { kind: 'text'; text: string }
    | { kind: 'strong'; text: string }
    | { kind: 'code'; text: string }
    | { kind: 'link'; text: string; href: string };

export type Block =
    | { kind: 'paragraph'; inline: Inline[] }
    | { kind: 'list'; ordered: boolean; items: Inline[][] };

const MARKS = /(\*\*[^*\n]+\*\*|`[^`\n]+`|\[[^\]\n]+\]\([^)\n]+\))/;
const LINK = /^\[([^\]]+)\]\(([^)]+)\)$/;
const BULLET = /^\s*[-*]\s+(.*)$/;
const NUMBERED = /^\s*\d+[.)]\s+(.*)$/;
const HEADING = /^#{1,6}\s+(.*)$/;

/** Only the pages of the panel may be linked: anything else is shown as its text. */
function isPanelPath(href: string): boolean {
    return href.startsWith('/admin/') && !href.startsWith('//');
}

function mark(token: string): Inline {
    if (token.startsWith('**')) {
        return { kind: 'strong', text: token.slice(2, -2) };
    }

    if (token.startsWith('`')) {
        return { kind: 'code', text: token.slice(1, -1) };
    }

    const link = LINK.exec(token);
    const text = link?.[1] ?? token;
    const href = link?.[2] ?? '';

    return isPanelPath(href)
        ? { kind: 'link', text, href }
        : { kind: 'text', text };
}

/** The marks of a line of text, never HTML: a tag or an unclosed mark stays as text. */
function inlineOf(line: string): Inline[] {
    const parts = line.split(MARKS).filter((part) => part !== '');
    const marked = parts.map((part) =>
        MARKS.test(part) ? mark(part) : { kind: 'text' as const, text: part },
    );

    // Neighboring plain pieces (a plain link text beside its sentence) read as one.
    return marked.reduce<Inline[]>((joined, piece) => {
        const last = joined.at(-1);

        if (last?.kind === 'text' && piece.kind === 'text') {
            return [
                ...joined.slice(0, -1),
                { kind: 'text', text: last.text + piece.text },
            ];
        }

        return [...joined, piece];
    }, []);
}

function listOf(lines: readonly string[]): Block | null {
    const first = lines[0] ?? '';
    const ordered = NUMBERED.test(first);
    const pattern = ordered ? NUMBERED : BULLET;

    if (!pattern.test(first) || !lines.every((line) => pattern.test(line))) {
        return null;
    }

    return {
        kind: 'list',
        ordered,
        items: lines.map((line) => inlineOf(pattern.exec(line)?.[1] ?? '')),
    };
}

function blockOf(chunk: string): Block | null {
    const lines = chunk.split('\n').filter((line) => line.trim() !== '');
    const heading = lines.length === 1 ? HEADING.exec(lines[0] ?? '') : null;

    if (lines.length === 0) {
        return null;
    }

    if (heading !== null) {
        return {
            kind: 'paragraph',
            inline: [{ kind: 'strong', text: heading[1] ?? '' }],
        };
    }

    return (
        listOf(lines) ?? {
            kind: 'paragraph',
            inline: inlineOf(lines.map((line) => line.trim()).join(' ')),
        }
    );
}

/**
 * The blocks of what Humi wrote: paragraphs, bullet and numbered lists, bold, inline code and links to the panel. Enough
 * for its answers and safe by construction: it produces nodes for the page to draw, never HTML.
 */
export function parseMarkdown(source: string): Block[] {
    return source.split(/\n\s*\n/).flatMap((chunk) => blockOf(chunk) ?? []);
}
