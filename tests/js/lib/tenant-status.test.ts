import { describe, expect, it } from 'vite-plus/test';

import { activationCopy, tenantStatusVariant } from '@/lib/tenant-status';
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

const activations: [TenantRow['status'], string, string, string][] = [
    ['not_started', 'Activar', 'Activar empresa', 'Activar'],
    ['trial', 'Activar', 'Activar empresa', 'Activar'],
    ['active', 'Cambiar vigencia', 'Cambiar vigencia', 'Guardar'],
    ['trial_ended', 'Reactivar', 'Reactivar empresa', 'Reactivar'],
    ['expired', 'Reactivar', 'Reactivar empresa', 'Reactivar'],
    ['deactivated', 'Reactivar', 'Reactivar empresa', 'Reactivar'],
];

describe('activationCopy', () => {
    it.each(activations)(
        'offers %s companies "%s"',
        (status, action, title, submit) => {
            expect(activationCopy(status)).toEqual({ action, title, submit });
        },
    );
});
