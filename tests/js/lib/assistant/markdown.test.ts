import { describe, expect, it } from 'vite-plus/test';
import { leadOf, parseMarkdown, plainText } from '@/lib/assistant/markdown';

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

describe('parseMarkdown: what a model writes without blank lines', () => {
    it('reads a heading that is followed by its text on the next line', () => {
        expect(
            parseMarkdown('### ¿A quién aplica?\nA quienes teletrabajan.'),
        ).toEqual([
            {
                kind: 'paragraph',
                inline: [{ kind: 'strong', text: '¿A quién aplica?' }],
            },
            {
                kind: 'paragraph',
                inline: [{ kind: 'text', text: 'A quienes teletrabajan.' }],
            },
        ]);
    });

    it('reads a list that comes right after a sentence, and the sentence that follows a list', () => {
        expect(parseMarkdown('Puntos:\n* uno\n* dos\nCierre.')).toEqual([
            { kind: 'paragraph', inline: [{ kind: 'text', text: 'Puntos:' }] },
            {
                kind: 'list',
                ordered: false,
                items: [
                    [{ kind: 'text', text: 'uno' }],
                    [{ kind: 'text', text: 'dos' }],
                ],
            },
            { kind: 'paragraph', inline: [{ kind: 'text', text: 'Cierre.' }] },
        ]);
    });

    it('marks italics, and leaves a lone asterisk and a product as text', () => {
        expect(
            parseMarkdown('*Fuente: criterio C-01.* Son 5 * 3 * 2 horas.'),
        ).toEqual([
            {
                kind: 'paragraph',
                inline: [
                    { kind: 'em', text: 'Fuente: criterio C-01.' },
                    { kind: 'text', text: ' Son 5 * 3 * 2 horas.' },
                ],
            },
        ]);
    });
});

describe('leadOf', () => {
    it('is the first paragraph of a criterion without its label', () => {
        expect(
            leadOf(
                '**Qué hacemos.** Una persona entra al padrón con **más de 40 %**.\n\n**Qué dice la ley.** Otra cosa.',
            ),
        ).toEqual([
            { kind: 'text', text: 'Una persona entra al padrón con ' },
            { kind: 'strong', text: 'más de 40 %' },
            { kind: 'text', text: '.' },
        ]);
    });

    it('keeps a first paragraph that has no label, and has nothing when the text starts with a list', () => {
        expect(leadOf('Solo texto.')).toEqual([
            { kind: 'text', text: 'Solo texto.' },
        ]);
        expect(leadOf('- uno\n- dos')).toEqual([]);
        expect(leadOf('')).toEqual([]);
    });
});

describe('plainText', () => {
    it('is what a screen reader should say: the words, without the marks', () => {
        expect(
            plainText(
                '### ¿A quién aplica?\nCon **más de 40 %** y `C-01`.\n* Una política\n* *Una* comisión\n[Ver](/admin/acme)',
            ),
        ).toBe(
            '¿A quién aplica?\nCon más de 40 % y C-01.\nUna política\nUna comisión\nVer',
        );
        expect(plainText('')).toBe('');
    });
});
