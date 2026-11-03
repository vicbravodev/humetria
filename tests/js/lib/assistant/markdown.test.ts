import { describe, expect, it } from 'vite-plus/test';
import { parseMarkdown } from '@/lib/assistant/markdown';

describe('parseMarkdown: paragraphs and inline marks', () => {
    it('splits paragraphs at blank lines and keeps a line break inside one as a space', () => {
        expect(parseMarkdown('Primero\ncontinúa\n\nSegundo')).toEqual([
            {
                kind: 'paragraph',
                inline: [{ kind: 'text', text: 'Primero continúa' }],
            },
            { kind: 'paragraph', inline: [{ kind: 'text', text: 'Segundo' }] },
        ]);
    });

    it('marks bold and inline code', () => {
        expect(parseMarkdown('Está en **liderazgo** y `C-23`.')).toEqual([
            {
                kind: 'paragraph',
                inline: [
                    { kind: 'text', text: 'Está en ' },
                    { kind: 'strong', text: 'liderazgo' },
                    { kind: 'text', text: ' y ' },
                    { kind: 'code', text: 'C-23' },
                    { kind: 'text', text: '.' },
                ],
            },
        ]);
    });

    it('turns a link to a page of the panel into a link, and any other into plain text', () => {
        const [block] = parseMarkdown(
            '[Ver en el panel](/admin/acme/campaigns/1) o [otro](https://malo.example) o [js](javascript:alert(1))',
        );

        expect(block).toEqual({
            kind: 'paragraph',
            inline: [
                {
                    kind: 'link',
                    text: 'Ver en el panel',
                    href: '/admin/acme/campaigns/1',
                },
                { kind: 'text', text: ' o otro o js)' },
            ],
        });
    });

    it('never produces markup: a tag is text', () => {
        expect(parseMarkdown('<script>alert(1)</script>')).toEqual([
            {
                kind: 'paragraph',
                inline: [{ kind: 'text', text: '<script>alert(1)</script>' }],
            },
        ]);
    });
});

describe('parseMarkdown: lists and headings', () => {
    it('reads bullet and numbered lists, each item with its marks', () => {
        expect(parseMarkdown('- uno\n- **dos**\n\n1. a\n2. b')).toEqual([
            {
                kind: 'list',
                ordered: false,
                items: [
                    [{ kind: 'text', text: 'uno' }],
                    [{ kind: 'strong', text: 'dos' }],
                ],
            },
            {
                kind: 'list',
                ordered: true,
                items: [
                    [{ kind: 'text', text: 'a' }],
                    [{ kind: 'text', text: 'b' }],
                ],
            },
        ]);
    });

    it('reads a heading as a bold paragraph', () => {
        expect(parseMarkdown('## Resultados')).toEqual([
            {
                kind: 'paragraph',
                inline: [{ kind: 'strong', text: 'Resultados' }],
            },
        ]);
    });

    it('has no blocks for no text, and keeps an unclosed mark as text while it streams', () => {
        expect(parseMarkdown('')).toEqual([]);
        expect(parseMarkdown('   \n\n ')).toEqual([]);
        expect(parseMarkdown('Está en **liderazg')).toEqual([
            {
                kind: 'paragraph',
                inline: [{ kind: 'text', text: 'Está en **liderazg' }],
            },
        ]);
    });
});
