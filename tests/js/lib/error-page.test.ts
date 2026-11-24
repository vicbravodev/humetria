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
        expect(errorMessage(429)).toEqual({
            caption: 'Error 429',
            title: 'Demasiados intentos',
            body: 'Espera un minuto y vuelve a intentarlo.',
        });
    });

    it('speaks "usted" to the person answering a survey', () => {
        expect(errorMessage(429, true)).toEqual({
            caption: 'Error 429',
            title: 'Demasiados intentos',
            body: 'Espere un minuto y vuelva a intentarlo.',
        });
        expect(errorMessage(403, true).title).toBe(
            'No tiene acceso a esta página',
        );
        expect(errorMessage(404, true).body).toBe(
            'Puede que el enlace esté incompleto. Ábralo de nuevo desde el mensaje que recibió.',
        );
        expect(errorMessage(419, true).body).toBe(
            'Por seguridad, la página expiró. Vuelva a abrirla: sus respuestas siguen guardadas en este dispositivo.',
        );
        expect(errorMessage(500, true).body).toBe(
            'Ya quedó registrado. Vuelva a intentarlo en unos minutos.',
        );
        expect(errorMessage(503, true).body).toBe(
            'Volvemos en unos minutos. Gracias por su paciencia.',
        );
        expect(errorMessage(502, true)).toEqual({
            ...errorMessage(500, true),
            caption: 'Error 502',
        });
    });

    it('tells the time clock screen not activated or revoked to ask RH for a new link, not of a message nobody received (hallazgo 24)', () => {
        expect(errorMessage(404, true, true)).toEqual({
            caption: 'Error 404',
            title: 'Esta pantalla no está activa',
            body: 'Pida a Recursos Humanos un enlace nuevo para activarla.',
        });
        expect(errorMessage(429, true, true)).toEqual(errorMessage(429, true));
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

    it("names a role of the company's own by its label, and by a neutral «Rol propio» when the page carries only its key", () => {
        const own = { ...tenant, role: 'custom_3f2a-9c' as const };

        expect(roleLimit(403, { ...own, role_label: 'Auditoría' })).toEqual({
            company: 'Autopartes Demo',
            role: 'Auditoría',
            summary: 'entra con los permisos que se le asignaron',
        });
        expect(
            roleLimit(403, { ...own, role_label: 'custom_3f2a-9c' })?.role,
        ).toBe('Rol propio');
        expect(roleLimit(403, { ...own, role_label: '' })?.role).toBe(
            'Rol propio',
        );
    });

    it('has nothing to say outside a company, for the owner, or for other errors', () => {
        expect(roleLimit(403, null)).toBeNull();
        expect(roleLimit(403, { ...tenant, role: 'owner' })).toBeNull();
        expect(roleLimit(404, tenant)).toBeNull();
    });
});
