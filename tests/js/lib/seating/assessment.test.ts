import { describe, expect, it } from 'vite-plus/test';

import {
    conditionSource,
    scoreSummary,
    whoAssesses,
} from '@/lib/seating/assessment';

const keys = [
    'standing_time',
    'posture_change',
    'surface',
    'footwear',
    'movement_space',
    'discomfort',
    'pauses',
];

describe('scoreSummary', () => {
    it('adds the points chosen so far, out of the 7 to 21 of the seven conditions', () => {
        expect(
            scoreSummary(
                {
                    standing_time: 3,
                    posture_change: 2,
                    surface: 3,
                    footwear: 1,
                    movement_space: 2,
                    discomfort: 1,
                    pauses: 2,
                },
                keys,
            ),
        ).toEqual({ total: 14, answered: 7, min: 7, max: 21 });
        expect(scoreSummary({ standing_time: 3 }, keys)).toEqual({
            total: 3,
            answered: 1,
            min: 7,
            max: 21,
        });
    });
});

describe('conditionSource', () => {
    it('says where each condition comes from: the position, or what the person answered', () => {
        expect(conditionSource('surface', true)).toEqual({
            label: 'Del puesto',
            unanswered: false,
        });
        expect(conditionSource('footwear', true)).toEqual({
            label: 'Contestó la persona',
            unanswered: false,
        });
        expect(conditionSource('discomfort', false)).toEqual({
            label: 'Sin respuesta de la persona',
            unanswered: true,
        });
        expect(conditionSource('footwear', false)).toEqual({
            label: 'Sin respuesta de la persona',
            unanswered: false,
        });
    });
});

describe('whoAssesses', () => {
    it('says the Comisión does the assessments the person who may only read cannot start', () => {
        expect(whoAssesses('none', false)).toBe('La hace la Comisión');
        expect(whoAssesses('due', false)).toBe('La hace la Comisión');
    });

    it('says nothing to who may assess, nor where an assessment is already open or signed', () => {
        expect(whoAssesses('none', true)).toBeNull();
        expect(whoAssesses('draft', false)).toBeNull();
        expect(whoAssesses('signed', false)).toBeNull();
    });
});
