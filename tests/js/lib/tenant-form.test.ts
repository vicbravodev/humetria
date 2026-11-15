import { describe, expect, it } from 'vite-plus/test';

import { tenantDefaults } from '@/lib/tenant-form';
import type { TenantPrefill } from '@/types';

const prefill: TenantPrefill = {
    demo_request_id: 'd1',
    company: 'Empresa Ejemplo',
    contact: 'Ana',
    email: 'ana@empresa.mx',
};

describe('tenantDefaults', () => {
    it('starts empty for a new company with no request behind it', () => {
        expect(tenantDefaults(undefined, null)).toEqual({
            legalName: '',
            tradeName: '',
            slug: '',
        });
    });

    it('fills both names from the demo request, with an address that follows', () => {
        expect(tenantDefaults(undefined, prefill)).toEqual({
            legalName: 'Empresa Ejemplo',
            tradeName: 'Empresa Ejemplo',
            slug: 'empresa-ejemplo',
        });
    });

    it('keeps what the company already has when it is edited', () => {
        const tenant = {
            legal_name: 'Acme S.A.',
            trade_name: 'Acme',
            slug: 'acme',
        };

        expect(tenantDefaults(tenant, prefill)).toEqual({
            legalName: 'Acme S.A.',
            tradeName: 'Acme',
            slug: 'acme',
        });
    });
});
