import { describe, expect, it } from 'vitest';
import {
    answered,
    minutesLeft,
    paragraphs,
    reached,
    toggled,
    topicStates,
    unanswered,
} from '@/lib/question-flow';
import type { FlowQuestion, FlowSection } from '@/lib/question-flow';

const question = (key: string, multiple = false): FlowQuestion => ({
    key,
    topic: key,
    title: key,
    hint: null,
    fields: [
        {
            key: `${key}-field`,
            legend: key,
            shown: false,
            multiple,
            columns: 1,
            options: [{ value: 'x', label: 'X' }],
        },
    ],
});

const section = (
    key: string,
    text: string | null,
    base = false,
): FlowSection => ({
    key,
    heading: key,
    text,
    base,
    edited: false,
});

describe('minutesLeft', () => {
    it('spreads the minutes of a document over its questions, and never says less than one', () => {
        expect(minutesLeft(12, 8, 0)).toBe(12);
        expect(minutesLeft(12, 8, 4)).toBe(6);
        expect(minutesLeft(5, 4, 1)).toBe(4);
        expect(minutesLeft(12, 8, 7)).toBe(2);
        expect(minutesLeft(3, 3, 3)).toBe(1);
    });
});

describe('toggled', () => {
    it('adds a choice that is not there and takes off one that is', () => {
        expect(toggled(['a'], 'b')).toEqual(['a', 'b']);
        expect(toggled(['a', 'b'], 'a')).toEqual(['b']);
    });
});

describe('answered', () => {
    it('keeps only what was answered, so nothing empty reaches the server', () => {
        expect(answered({ a: 'x', b: null, c: [], d: ['y'] })).toEqual({
            a: 'x',
            c: [],
            d: ['y'],
        });
    });
});

describe('unanswered', () => {
    it('lists the questions with a single choice still open or a list with nothing chosen', () => {
        const questions = [question('a'), question('b', true), question('c')];

        expect(
            unanswered(questions, {
                'a-field': 'x',
                'b-field': [],
                'c-field': null,
            }).map(({ key }) => key),
        ).toEqual(['b', 'c']);
        expect(
            unanswered(questions, {
                'a-field': 'x',
                'b-field': ['x'],
                'c-field': 'x',
            }),
        ).toEqual([]);
    });
});

describe('topicStates', () => {
    const topics = [
        { key: 'a', label: 'A' },
        { key: 'b', label: 'B' },
        { key: 'c', label: 'C' },
    ];
    const sections = [
        section('a', 'Texto'),
        section('b', null),
        section('c', 'Base', true),
    ];

    it('marks a topic done once its text is written, and the base texts apart until the review', () => {
        expect(topicStates(topics, sections, false)).toEqual([
            { key: 'a', label: 'A', state: 'done' },
            { key: 'b', label: 'B', state: 'open' },
            { key: 'c', label: 'C', state: 'base' },
        ]);
        expect(
            topicStates(topics, sections, true).map(({ state }) => state),
        ).toEqual(['done', 'open', 'done']);
    });

    it('leaves open a topic no section covers', () => {
        expect(topicStates([{ key: 'z', label: 'Z' }], sections, true)).toEqual(
            [{ key: 'z', label: 'Z', state: 'open' }],
        );
    });
});

describe('paragraphs', () => {
    it('splits a text on its blank lines and drops the empty ones', () => {
        expect(paragraphs('Uno.\n\nDos.\n\n\n')).toEqual(['Uno.', 'Dos.']);
    });
});

describe('reached', () => {
    it('keeps the text of the questions up to the one on screen, and the base texts, but not of those ahead', () => {
        const questions = [question('a'), question('b'), question('c')];
        const sections = [
            section('a', 'A'),
            section('b', 'B'),
            section('c', 'C'),
            section('z', 'Base', true),
        ];

        expect(reached(sections, questions, 1).map(({ text }) => text)).toEqual(
            ['A', 'B', null, 'Base'],
        );
        expect(
            reached(sections, questions, -1).map(({ text }) => text),
        ).toEqual([null, null, null, 'Base']);
        expect(reached(sections, questions, 3).map(({ text }) => text)).toEqual(
            ['A', 'B', 'C', 'Base'],
        );
    });
});
