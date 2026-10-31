import { describe, expect, it } from 'vitest';
import { errorMessage, roleLimit } from '@/lib/error-page';

describe('errorMessage', () => {
    it('names the error and says what happened, calmly', () => {
        expect(errorMessage(404)).toEqual({
            caption: 'Error 404',
            title: 'No encontramos esta página',
            body: 'Puede que el enlace esté mal escrito o que la página se haya movido.',
        });
        expect(errorMessage(403).title).toBe('No tienes acceso a esta página');
        expect(errorMessage(419).title).toBe('La página expiró');
        expect(errorMessage(500).title).toBe('Algo salió mal de nuestro lado');
        expect(errorMessage(503).title).toBe('Estamos en mantenimiento');
    });

    it('treats any other status as a server error', () => {
        expect(errorMessage(502)).toEqual({
            ...errorMessage(500),
            caption: 'Error 502',
        });
    });
});

describe('roleLimit', () => {
    const tenant = {
        trade_name: 'Autopartes Demo',
        role: 'viewer' as const,
        role_label: 'Consulta',
    };

    it('explains a 403 by the role the person has in the company', () => {
        expect(roleLimit(403, tenant)).toEqual({
            company: 'Autopartes Demo',
            role: 'Consulta',
            summary: 've resultados e informes',
        });
    });

    it('has nothing to say outside a company, for the owner, or for other errors', () => {
        expect(roleLimit(403, null)).toBeNull();
        expect(roleLimit(403, { ...tenant, role: 'owner' })).toBeNull();
        expect(roleLimit(404, tenant)).toBeNull();
    });
});
