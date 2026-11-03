import { describe, expect, it } from 'vitest';
import {
    addCenter,
    boardHeadline,
    INCISOS,
    joinLines,
    removeCenter,
    requirementVariant,
    sectionPayload,
    splitLines,
    withChange,
    withoutSection,
} from '@/lib/disclosure';
import type { PolicySectionData } from '@/types';

const section = (
    key: string,
    removable = true,
    text = 'texto',
): PolicySectionData => ({
    key,
    heading: key,
    text,
    items: ['uno', 'dos'],
    listStyle: 'numbers',
    removable,
    required: false,
});

describe('items as lines', () => {
    it('joins the items one per line and splits them back', () => {
        expect(joinLines(['uno', 'dos'])).toBe('uno\ndos');
        expect(splitLines('uno\ndos')).toEqual(['uno', 'dos']);
        expect(splitLines('')).toEqual(['']);
        expect(splitLines(joinLines([]))).toEqual(['']);
    });
});

describe('sectionPayload', () => {
    it('sends the key, the words and the items, and nothing the server owns', () => {
        expect(sectionPayload(section('a'))).toEqual({
            key: 'a',
            text: 'texto',
            items: ['uno', 'dos'],
        });
    });
});

describe('withoutSection', () => {
    it('takes out a section that may go', () => {
        expect(
            withoutSection([section('a'), section('b')], 'a').map((s) => s.key),
        ).toEqual(['b']);
    });

    it('keeps a section that may not go', () => {
        expect(
            withoutSection([section('a', false), section('b')], 'a').map(
                (s) => s.key,
            ),
        ).toEqual(['a', 'b']);
    });
});

describe('withChange', () => {
    it('changes only the section asked for', () => {
        const changed = withChange([section('a'), section('b')], 'b', {
            text: 'nuevo',
        });

        expect(changed.map((s) => s.text)).toEqual(['texto', 'nuevo']);
        expect(changed[1]?.items).toEqual(['uno', 'dos']);
    });
});

describe('requirementVariant', () => {
    it('reads each state of the board as a badge', () => {
        expect(requirementVariant('met')).toBe('success');
        expect(requirementVariant('notice')).toBe('warning');
        expect(requirementVariant('missing')).toBe('destructive');
        expect(requirementVariant('not_applicable')).toBe('outline');
    });
});

describe('boardHeadline', () => {
    it('says there is nothing to judge without centers', () => {
        expect(boardHeadline({ met: 0, notice: 0, missing: 0 })).toBe(
            'Aún no hay centros de trabajo.',
        );
    });

    it('says everything is recorded when nothing is missing or warned', () => {
        expect(boardHeadline({ met: 3, notice: 0, missing: 0 })).toBe(
            'Los 3 centros tienen registrado todo lo que revisamos.',
        );
        expect(boardHeadline({ met: 1, notice: 0, missing: 0 })).toBe(
            'Tu centro tiene registrado todo lo que revisamos.',
        );
    });

    it('counts what is missing first, then the warnings, then what is in order', () => {
        expect(boardHeadline({ met: 1, notice: 2, missing: 1 })).toBe(
            '1 centro con faltantes, 2 con avisos y 1 al día.',
        );
        expect(boardHeadline({ met: 0, notice: 0, missing: 4 })).toBe(
            '4 centros con faltantes.',
        );
        expect(boardHeadline({ met: 0, notice: 2, missing: 0 })).toBe(
            '2 centros con avisos.',
        );
    });
});

describe('the centers chosen to publish to', () => {
    const north = { value: 'n', label: 'Norte' };
    const south = { value: 's', label: 'Sur' };

    it('adds a center once, in the order added', () => {
        expect(addCenter([north], south)).toEqual([north, south]);
        expect(addCenter([north, south], north)).toEqual([north, south]);
    });

    it('takes one out', () => {
        expect(removeCenter([north, south], 'n')).toEqual([south]);
        expect(removeCenter([north], 'x')).toEqual([north]);
    });
});

describe('INCISOS', () => {
    it('are the three incisos of numeral 5.1', () => {
        expect(INCISOS.map(({ value }) => value)).toEqual(['a', 'b', 'c']);
    });
});
