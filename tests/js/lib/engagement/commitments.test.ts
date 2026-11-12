import { describe, expect, it } from 'vite-plus/test';
import {
    groupChoice,
    menuSteps,
    nextStatuses,
    primaryStep,
    statusVariant,
} from '@/lib/engagement/commitments';

describe('primaryStep', () => {
    it('offers the next state of the cycle as the one button of a card', () => {
        expect(primaryStep('open')).toBe('in_progress');
        expect(primaryStep('in_progress')).toBe('done');
        expect(primaryStep('done')).toBeNull();
        expect(primaryStep('dropped')).toBeNull();
    });
});

describe('menuSteps', () => {
    it('leaves every other state to the menu of the card', () => {
        expect(menuSteps('open')).toEqual(['done', 'dropped']);
        expect(menuSteps('in_progress')).toEqual(['open', 'dropped']);
        expect(menuSteps('done')).toEqual(['open', 'in_progress', 'dropped']);
        expect(menuSteps('dropped')).toEqual(['open', 'in_progress', 'done']);
    });
});

describe('statusVariant', () => {
    it('shows an overdue commitment as a problem, whatever it was', () => {
        expect(statusVariant('open', true)).toBe('destructive');
        expect(statusVariant('in_progress', true)).toBe('destructive');
    });

    it('gives each state its own tone', () => {
        expect(statusVariant('open', false)).toBe('warning');
        expect(statusVariant('in_progress', false)).toBe('default');
        expect(statusVariant('done', false)).toBe('success');
        expect(statusVariant('dropped', false)).toBe('secondary');
    });
});

describe('nextStatuses', () => {
    it('offers every other state', () => {
        expect(nextStatuses('open')).toEqual([
            'in_progress',
            'done',
            'dropped',
        ]);
        expect(nextStatuses('done')).toEqual([
            'open',
            'in_progress',
            'dropped',
        ]);
        expect(nextStatuses('dropped')).toEqual([
            'open',
            'in_progress',
            'done',
        ]);
    });
});

describe('groupChoice', () => {
    it('keeps the company out of the id and joins the others with their type', () => {
        expect(groupChoice({ type: 'company', id: null })).toBe('company');
        expect(groupChoice({ type: 'area', id: 'a-1' })).toBe('area:a-1');
    });
});
