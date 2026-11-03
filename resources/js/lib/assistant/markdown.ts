export type Inline =
    | { kind: 'text'; text: string }
    | { kind: 'strong'; text: string }
    | { kind: 'em'; text: string }
    | { kind: 'code'; text: string }
    | { kind: 'link'; text: string; href: string };

export type Block =
    | { kind: 'paragraph'; inline: Inline[] }
    | { kind: 'list'; ordered: boolean; items: Inline[][] };

const MARKS =
    /(\*\*[^*\n]+\*\*|\*[^*\s](?:[^*\n]*[^*\s])?\*|`[^`\n]+`|\[[^\]\n]+\]\([^)\n]+\))/;
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

    if (token.startsWith('*')) {
        return { kind: 'em', text: token.slice(1, -1) };
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

type Line = {
    kind: 'blank' | 'heading' | 'bullet' | 'numbered' | 'text';
    text: string;
};

function lineOf(raw: string): Line {
    const matches = [
        { kind: 'heading' as const, found: HEADING.exec(raw) },
        { kind: 'numbered' as const, found: NUMBERED.exec(raw) },
        { kind: 'bullet' as const, found: BULLET.exec(raw) },
    ];
    const hit = matches.find((match) => match.found !== null);

    if (hit?.found) {
        return { kind: hit.kind, text: hit.found[1] ?? '' };
    }

    return raw.trim() === ''
        ? { kind: 'blank', text: '' }
        : { kind: 'text', text: raw.trim() };
}

/** Neighboring lines of the same kind make one block; a heading is always alone and a blank line closes the block. */
function groupsOf(lines: readonly Line[]): Line[][] {
    return lines.reduce<Line[][]>((groups, line) => {
        const last = groups.at(-1);

        if (last?.[0]?.kind === line.kind && line.kind !== 'heading') {
            last.push(line);

            return groups;
        }

        return [...groups, [line]];
    }, []);
}

function blockOf(group: readonly Line[]): Block | null {
    const kind = group[0]?.kind;
    const texts = group.map((line) => line.text);

    switch (kind) {
        case 'heading':
            return {
                kind: 'paragraph',
                inline: [{ kind: 'strong', text: texts.join(' ') }],
            };
        case 'text':
            return { kind: 'paragraph', inline: inlineOf(texts.join(' ')) };
        case 'bullet':
        case 'numbered':
            return {
                kind: 'list',
                ordered: kind === 'numbered',
                items: texts.map(inlineOf),
            };
        default:
            return null;
    }
}

/**
 * The blocks of what Humi wrote: paragraphs, headings, bullet and numbered lists, bold, italics, inline code and links to
 * the panel. Line by line, so a list or a heading right after a sentence is still one. Enough for its answers and safe by
 * construction: it produces nodes for the page to draw, never HTML.
 */
export function parseMarkdown(source: string): Block[] {
    return groupsOf(source.split('\n').map(lineOf)).flatMap(
        (group) => blockOf(group) ?? [],
    );
}

/** The first paragraph of a criterion of a norm, without its label («Qué hacemos.»): what a card shows of it. */
export function leadOf(text: string): Inline[] {
    const first = parseMarkdown(text)[0];

    if (first?.kind !== 'paragraph') {
        return [];
    }

    const [label, ...rest] = first.inline;
    const labeled = label?.kind === 'strong' && label.text.endsWith('.');
    const [head, ...tail] = labeled ? rest : first.inline;

    return head?.kind === 'text'
        ? [{ kind: 'text', text: head.text.trimStart() }, ...tail]
        : [...(head === undefined ? [] : [head]), ...tail];
}

function wordsOf(nodes: readonly Inline[]): string {
    return nodes.map((node) => node.text).join('');
}

/** What Humi wrote as words alone, a block to a line: what a screen reader should say, with no marks to spell out. */
export function plainText(source: string): string {
    return parseMarkdown(source)
        .flatMap((block) =>
            block.kind === 'paragraph'
                ? [wordsOf(block.inline)]
                : block.items.map(wordsOf),
        )
        .join('\n');
}
