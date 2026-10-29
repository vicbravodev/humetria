import { describe, expect, it } from 'vite-plus/test';

import {
    activationCopy,
    ownerAction,
    tenantStatusVariant,
    trialLabel,
} from '@/lib/tenant-status';
import type { TenantRow } from '@/types';

const variants: [
    TenantRow['status'],
    'default' | 'secondary' | 'outline' | 'destructive',
][] = [
    ['active', 'default'],
    ['trial', 'secondary'],
    ['not_started', 'outline'],
    ['trial_ended', 'destructive'],
    ['expired', 'destructive'],
    ['deactivated', 'destructive'],
];

describe('tenantStatusVariant', () => {
    it.each(variants)('shows %s as %s', (status, variant) => {
        expect(tenantStatusVariant(status)).toBe(variant);
    });
});

const activations: {
    status: TenantRow['status'];
    action: string;
    title: string;
    submit: string;
}[] = [
    {
        status: 'not_started',
        action: 'Activar',
        title: 'Activar empresa',
        submit: 'Activar',
    },
    {
        status: 'trial',
        action: 'Activar',
        title: 'Activar empresa',
        submit: 'Activar',
    },
    {
        status: 'active',
        action: 'Cambiar vigencia',
        title: 'Cambiar vigencia',
        submit: 'Guardar',
    },
    {
        status: 'trial_ended',
        action: 'Reactivar',
        title: 'Reactivar empresa',
        submit: 'Reactivar',
    },
    {
        status: 'expired',
        action: 'Reactivar',
        title: 'Reactivar empresa',
        submit: 'Reactivar',
    },
    {
        status: 'deactivated',
        action: 'Reactivar',
        title: 'Reactivar empresa',
        submit: 'Reactivar',
    },
];

describe('activationCopy', () => {
    it.each(activations)(
        'offers $status companies "$action"',
        ({ status, ...copy }) => {
            expect(activationCopy(status)).toEqual(copy);
        },
    );
});

describe('ownerAction', () => {
    it('invites an owner when the company has none, and changes it otherwise', () => {
        expect(ownerAction(null)).toBe('Invitar owner');
        expect(ownerAction({ name: 'Ana Pérez', email: 'ana@acme.mx' })).toBe(
            'Cambiar owner',
        );
    });
});

describe('trialLabel', () => {
    it.each([
        [1, 'Prueba · último día'],
        [2, 'Prueba · quedan 2 días'],
        [14, 'Prueba · quedan 14 días'],
    ])('says %i days left as "%s"', (daysLeft, label) => {
        expect(trialLabel(daysLeft)).toBe(label);
    });
});
