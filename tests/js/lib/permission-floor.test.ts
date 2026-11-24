import { describe, expect, it } from 'vite-plus/test';
import { AREAS } from '@/lib/permission-areas';
import { area } from '../support/permission-areas';
import {
    ownLevels,
    ownPermissions,
    roleMix,
    summarizeBeyond,
} from '@/lib/permission-floor';

const viewer = [
    'view-vacations',
    'view-nom035-route',
    'view-interventions',
    'view-disclosure',
];

describe('what a person chose besides their role', () => {
    it('keeps the whole level of an area raised past the role, so another role never leaves it half', () => {
        const chosen = ownLevels({
            areas: AREAS,
            held: [...viewer, 'manage-vacations'],
            base: viewer,
        });

        expect(chosen).toEqual(['view-vacations', 'manage-vacations']);
    });

    it("keeps an extra past the role and leaves the role's level of that area out", () => {
        expect(
            ownLevels({
                areas: AREAS,
                held: [...viewer, 'view-trauma-screenings'],
                base: viewer,
            }),
        ).toEqual(['view-trauma-screenings']);
    });

    it('keeps nothing when the person has only their role', () => {
        expect(ownLevels({ areas: AREAS, held: viewer, base: viewer })).toEqual(
            [],
        );
    });
});

describe('the summary of what a person has besides their role', () => {
    it('speaks only of the levels past the role and the extras it does not bring', () => {
        expect(
            summarizeBeyond({
                areas: AREAS,
                held: [...viewer, 'manage-vacations', 'view-trauma-screenings'],
                base: viewer,
                subject: 'Además de lo que trae su rol, Vera',
            }),
        ).toBe(
            'Además de lo que trae su rol, Vera administra Vacaciones. También ve los tamizajes de la Guía I.',
        );
    });

    it("does not credit the person with a level only the role gives, nor a role's extra", () => {
        expect(
            summarizeBeyond({
                areas: AREAS,
                held: [
                    ...viewer,
                    'view-trauma-screenings',
                    'handle-complaints',
                ],
                base: [...viewer, 'view-trauma-screenings'],
                subject: 'Vera',
            }),
        ).toBe('Vera atiende quejas asignadas.');
    });
});

describe('an area the role holds as a mix no level names', () => {
    const vacations = area('vacations');

    it('is one where the role holds permissions of the area that are not a level, such as managing without seeing', () => {
        expect(roleMix(vacations, ['manage-vacations'])).toBe(true);
    });

    it('is not one when the role holds a whole level, or nothing of the area', () => {
        expect(roleMix(vacations, ['view-vacations'])).toBe(false);
        expect(roleMix(vacations, ['view-vacations', 'manage-vacations'])).toBe(
            false,
        );
        expect(roleMix(vacations, ['view-dashboard'])).toBe(false);
        expect(roleMix(vacations, [])).toBe(false);
    });
});

describe('what is sent for a person', () => {
    it('is what goes past the role, as before', () => {
        expect(
            ownPermissions({
                held: [...viewer, 'manage-vacations'],
                base: viewer,
                stored: [],
            }),
        ).toEqual(['manage-vacations']);
    });

    it('keeps what was stored for the person even when their role now gives it too', () => {
        expect(
            ownPermissions({
                held: [...viewer, 'manage-vacations'],
                base: viewer,
                stored: ['view-vacations'],
            }),
        ).toEqual(['manage-vacations', 'view-vacations']);
    });

    it('does not repeat a stored permission that is also past the role', () => {
        expect(
            ownPermissions({
                held: [...viewer, 'manage-vacations'],
                base: viewer,
                stored: ['manage-vacations'],
            }),
        ).toEqual(['manage-vacations']);
    });
});
