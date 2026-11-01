import { describe, expect, it } from 'vite-plus/test';

import { addChoice, removeChoice } from '@/lib/telework/choices';

const ana = { value: 'a', label: 'Ana' };
const beto = { value: 'b', label: 'Beto' };

describe('addChoice', () => {
    it('adds each choice once and never the empty "all" choice', () => {
        expect(addChoice([ana], beto)).toEqual([ana, beto]);
        expect(addChoice([ana], ana)).toEqual([ana]);
        expect(addChoice([ana], { value: '', label: 'Todos' })).toEqual([ana]);
    });
});

describe('removeChoice', () => {
    it('takes one choice out by value', () => {
        expect(removeChoice([ana, beto], 'a')).toEqual([beto]);
    });
});
