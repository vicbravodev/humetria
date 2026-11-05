import { describe, expect, it } from 'vite-plus/test';

import {
    figureOne,
    POSITION_STEPS,
    stepWithError,
} from '@/lib/seating/position-steps';

describe('figureOne', () => {
    it('gives A as soon as the person can work seated and there is space, without the third question', () => {
        expect(
            figureOne({
                canWorkSeated: true,
                hasSpaceForSeat: true,
                needsToBeNearby: null,
            }),
        ).toEqual({ result: 'A', thirdNeeded: false });
    });

    it('asks the third question otherwise: B when the person needs to be near, C when not', () => {
        expect(
            figureOne({
                canWorkSeated: true,
                hasSpaceForSeat: false,
                needsToBeNearby: null,
            }),
        ).toEqual({ result: null, thirdNeeded: true });
        expect(
            figureOne({
                canWorkSeated: false,
                hasSpaceForSeat: null,
                needsToBeNearby: true,
            }),
        ).toEqual({ result: 'B', thirdNeeded: true });
        expect(
            figureOne({
                canWorkSeated: false,
                hasSpaceForSeat: true,
                needsToBeNearby: false,
            }),
        ).toEqual({ result: 'C', thirdNeeded: true });
    });

    it('has no result before the first two questions are answered', () => {
        expect(
            figureOne({
                canWorkSeated: true,
                hasSpaceForSeat: null,
                needsToBeNearby: true,
            }),
        ).toEqual({ result: null, thirdNeeded: false });
        expect(
            figureOne({
                canWorkSeated: null,
                hasSpaceForSeat: true,
                needsToBeNearby: true,
            }),
        ).toEqual({ result: null, thirdNeeded: false });
    });
});

describe('stepWithError', () => {
    it('opens the first step with a field the server rejected', () => {
        expect(POSITION_STEPS.map((step) => step.title)).toEqual([
            'El puesto',
            'Figura 1: dónde va el asiento',
            'Condiciones del área',
            'Condiciones de las personas',
            'Quién lo ocupa',
        ]);
        expect(
            stepWithError({ default_pauses: 'x', has_space_for_seat: 'y' }),
        ).toBe(2);
        expect(stepWithError({ 'area_conditions.noise': 'x' })).toBe(3);
        expect(stepWithError({ default_surface: 'x' })).toBe(4);
        expect(stepWithError({ name: 'x' })).toBe(1);
        expect(stepWithError({})).toBeNull();
    });
});
