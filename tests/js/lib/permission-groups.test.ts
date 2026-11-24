import { describe, expect, it } from 'vitest';
import {
    groupCount,
    heldBy,
    sensitiveAdded,
    switchLook,
    toggled,
} from '@/lib/permission-groups';
import type { PermissionGroup, PermissionToggle } from '@/types';

const toggle = (
    name: string,
    extra: Partial<PermissionToggle> = {},
): PermissionToggle => ({
    name,
    label: `Etiqueta ${name}`,
    description: '',
    sensitive: false,
    enabled: false,
    unavailableReason: null,
    source: null,
    ...extra,
});

const groups: PermissionGroup[] = [
    {
        module: 'nom035',
        label: 'NOM-035',
        permissions: [
            toggle('view-nom035-route', { enabled: true, source: 'role' }),
            toggle('view-trauma-screenings', { sensitive: true }),
            toggle('manage-campaigns', { enabled: true, source: 'direct' }),
        ],
    },
    {
        module: 'vacations',
        label: 'Vacaciones',
        permissions: [
            toggle('view-vacations', {
                unavailableReason: 'Tu plan no incluye Vacaciones.',
            }),
        ],
    },
];

describe('heldBy', () => {
    it('lists the permissions held by the role, or by the person besides it', () => {
        expect(heldBy(groups, 'role')).toEqual(['view-nom035-route']);
        expect(heldBy(groups, 'direct')).toEqual(['manage-campaigns']);
    });
});

describe('groupCount', () => {
    it('counts the ones on, chosen or locked, out of the ones the plan lets switch on', () => {
        const [nom035, vacations] = groups;

        expect(nom035 && groupCount(nom035, ['manage-campaigns'])).toEqual({
            on: 1,
            total: 3,
        });
        expect(
            nom035 &&
                groupCount(nom035, ['manage-campaigns'], ['view-nom035-route']),
        ).toEqual({ on: 2, total: 3 });
        // A permission of a tool the plan lacks neither counts as on nor as one to switch on.
        expect(vacations && groupCount(vacations, ['view-vacations'])).toEqual({
            on: 0,
            total: 0,
        });
    });
});

describe('sensitiveAdded', () => {
    it('names the sensitive permissions switched on that were not there before', () => {
        expect(sensitiveAdded(groups, [], ['view-trauma-screenings'])).toEqual([
            'Etiqueta view-trauma-screenings',
        ]);
    });

    it('says nothing for a sensitive one already held, nor for one that is not sensitive', () => {
        expect(
            sensitiveAdded(
                groups,
                ['view-trauma-screenings'],
                ['view-trauma-screenings', 'manage-campaigns'],
            ),
        ).toEqual([]);
    });
});

describe('toggled', () => {
    it('switches one on or off, once', () => {
        expect(toggled(['a'], 'b', true)).toEqual(['a', 'b']);
        expect(toggled(['a', 'b'], 'b', true)).toEqual(['a', 'b']);
        expect(toggled(['a', 'b'], 'a', false)).toEqual(['b']);
    });
});

describe('switchLook', () => {
    it('keeps one of a tool the plan lacks off and still, with its reason', () => {
        expect(
            switchLook(
                toggle('view-vacations', {
                    unavailableReason: 'Tu plan no incluye Vacaciones.',
                }),
                { on: true, locked: false },
                false,
            ),
        ).toEqual({
            on: false,
            still: true,
            hint: 'Tu plan no incluye Vacaciones.',
        });
    });

    it('keeps one the role brings on and still, saying so', () => {
        expect(
            switchLook(
                toggle('view-dashboard'),
                { on: true, locked: true },
                false,
            ),
        ).toEqual({ on: true, still: true, hint: 'Viene de su rol' });
    });

    it('lets the others change, unless the screen is only read', () => {
        expect(
            switchLook(
                toggle('view-dashboard'),
                { on: false, locked: false },
                false,
            ),
        ).toEqual({ on: false, still: false, hint: null });
        expect(
            switchLook(
                toggle('view-dashboard'),
                { on: true, locked: false },
                true,
            ),
        ).toEqual({ on: true, still: true, hint: null });
    });
});
