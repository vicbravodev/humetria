import { describe, expect, it } from 'vitest';
import {
    heldBy,
    keepsAcknowledgement,
    sensitiveAdded,
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

describe('keepsAcknowledgement', () => {
    const more: PermissionGroup[] = [
        ...groups,
        {
            module: 'complaints',
            label: 'Quejas',
            permissions: [toggle('handle-complaints', { sensitive: true })],
        },
    ];

    it('keeps the confirmation while the sensitive permissions added stay the same', () => {
        expect(
            keepsAcknowledgement(more, [], {
                previous: ['view-trauma-screenings'],
                next: ['view-trauma-screenings', 'manage-campaigns'],
            }),
        ).toBe(true);
    });

    it('drops it when one sensitive is switched off and another on, or one more is added', () => {
        expect(
            keepsAcknowledgement(more, [], {
                previous: ['view-trauma-screenings'],
                next: ['handle-complaints'],
            }),
        ).toBe(false);
        expect(
            keepsAcknowledgement(more, [], {
                previous: ['view-trauma-screenings'],
                next: ['view-trauma-screenings', 'handle-complaints'],
            }),
        ).toBe(false);
    });
});
