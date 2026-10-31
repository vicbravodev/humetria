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
