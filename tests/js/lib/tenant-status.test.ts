import { describe, expect, it } from 'vite-plus/test';

import { tenantStatusVariant } from '@/lib/tenant-status';
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
