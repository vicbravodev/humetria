import { describe, expect, it } from 'vite-plus/test';

import {
    figureOne,
    missingInStep,
    POSITION_STEPS,
    stepWithError,
    textValues,
    withMissing,
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

describe('missingInStep', () => {
    it('lists the questions of Figura 1 nobody answered, even the «No» that is "0"', () => {
        expect(missingInStep(2, {})).toEqual([
            'can_work_seated',
            'has_space_for_seat',
            'needs_to_be_nearby',
        ]);
        expect(
            missingInStep(2, {
                can_work_seated: '0',
                has_space_for_seat: '1',
            }),
        ).toEqual(['needs_to_be_nearby']);
        expect(
            missingInStep(2, {
                can_work_seated: '0',
                has_space_for_seat: '0',
                needs_to_be_nearby: '1',
            }),
        ).toEqual([]);
    });

    // R2-compliance-12: «El puesto» is validated before moving on too, not only at the end.
    it('lists what «El puesto» still lacks before moving on', () => {
        expect(missingInStep(1, {})).toEqual([
            'name',
            'tasks',
            'bipedestation_type',
            'continuous_minutes',
            'max_people_at_once',
        ]);
        expect(
            missingInStep(1, {
                name: 'Cajera',
                tasks: ' ',
                bipedestation_type: 'fixed',
                continuous_minutes: '0',
                max_people_at_once: '2',
            }),
        ).toEqual(['tasks']);
    });

    it('asks nothing of the other steps', () => {
        expect(missingInStep(3, {})).toEqual([]);
        expect(missingInStep(4, {})).toEqual([]);
    });
});

describe('textValues', () => {
    it('keeps the text the form holds, and leaves out files', () => {
        const data = new FormData();
        data.append('name', 'Cajera');
        data.append('file', new Blob(['x']), 'x.txt');

        expect(textValues(data)).toEqual({ name: 'Cajera' });
    });
});

describe('withMissing', () => {
    it('marks the empty fields as required and lets what the server said win', () => {
        expect(
            withMissing({ tasks: 'El campo tasks es obligatorio.' }, [
                'name',
                'tasks',
            ]),
        ).toEqual({
            name: 'Este dato es obligatorio.',
            tasks: 'El campo tasks es obligatorio.',
        });
    });
});
