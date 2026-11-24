import { AREAS } from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';
import type { PermissionGroup, PermissionToggle } from '@/types';

/** An area by key, for the tests of the roles screen. */
export const area = (key: string): PermissionArea => {
    const found = AREAS.find((candidate) => candidate.key === key);

    if (found === undefined) {
        throw new Error(`No area ${key}`);
    }

    return found;
};

/** One permission of the roles screen, off and available unless said otherwise. */
export const toggle = (
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

/** Some tools of an account: one the plan lacks (Asistencia), with sensitive permissions in NOM-035 and Quejas. */
export const groups: PermissionGroup[] = [
    {
        module: 'general',
        label: 'General',
        permissions: [toggle('view-dashboard'), toggle('use-assistant')],
    },
    {
        module: 'nom035',
        label: 'NOM-035',
        permissions: [
            'view-nom035-route',
            'view-interventions',
            'view-disclosure',
            'manage-campaigns',
            'manage-interventions',
            'manage-disclosure',
        ]
            .map((name) => toggle(name))
            .concat(toggle('view-trauma-screenings', { sensitive: true })),
    },
    {
        module: 'complaints',
        label: 'Quejas',
        permissions: [
            toggle('manage-complaint-channel', { sensitive: true }),
            toggle('handle-complaints', { sensitive: true }),
        ],
    },
    {
        module: 'vacations',
        label: 'Vacaciones',
        permissions: [toggle('view-vacations'), toggle('manage-vacations')],
    },
    {
        module: 'attendance',
        label: 'Asistencia',
        permissions: [
            toggle('view-attendance', {
                unavailableReason: 'Tu plan no incluye Asistencia.',
            }),
            toggle('manage-attendance', {
                unavailableReason: 'Tu plan no incluye Asistencia.',
            }),
        ],
    },
];
