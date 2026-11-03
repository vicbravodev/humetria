import { describe, expect, it } from 'vitest';
import {
    joinLines,
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
