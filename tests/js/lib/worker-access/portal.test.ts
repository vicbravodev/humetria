import { describe, expect, it } from 'vite-plus/test';

import {
    initials,
    pendingTotal,
    splitSections,
} from '@/lib/worker-access/portal';
import type { WorkerSection } from '@/types';

const section = (key: string, pending: number | null): WorkerSection => ({
    key,
    label: key,
    description: '',
    href: `/me/acme/${key}`,
    pending,
});

describe('splitSections', () => {
    it('puts what waits for the person first, and the rest as their tools, each in its order', () => {
        const { waiting, tools } = splitSections([
            section('attendance', null),
            section('survey', 1),
            section('vacations', 0),
            section('engagement', 2),
        ]);

        expect(waiting.map((s) => s.key)).toEqual(['survey', 'engagement']);
        expect(tools.map((s) => s.key)).toEqual(['attendance', 'vacations']);
    });

    it('has nothing waiting when no tool counts anything', () => {
        expect(splitSections([section('team', null)]).waiting).toEqual([]);
        expect(splitSections([])).toEqual({ waiting: [], tools: [] });
    });
});

describe('pendingTotal', () => {
    it('adds what waits in every tool', () => {
        expect(
            pendingTotal([
                section('a', 1),
                section('b', null),
                section('c', 2),
            ]),
        ).toBe(3);
        expect(pendingTotal([])).toBe(0);
    });
});

describe('initials', () => {
    it('takes the first letter of the given name and of the first surname', () => {
        expect(initials('Adriana Moreno Álvarez')).toBe('AM');
        expect(initials('Ana')).toBe('A');
        expect(initials('  lucía   herrera ')).toBe('LH');
        expect(initials('')).toBe('');
    });
});
