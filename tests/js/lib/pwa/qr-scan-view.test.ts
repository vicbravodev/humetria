import { describe, expect, it } from 'vitest';
import { scanView } from '@/lib/pwa/qr-scan-view';

describe('scanView', () => {
    it('looks while the camera opens and while it reads', () => {
        expect(scanView('opening', false)).toMatchObject({
            seeing: true,
            state: 'looking',
            problem: false,
            text: 'Abriendo la cámara…',
        });
        expect(scanView('scanning', false)).toMatchObject({
            seeing: true,
            state: 'looking',
            problem: false,
            text: 'Apunte al código de la pantalla de su centro.',
        });
    });

    it.each(['unreadable', 'unrecognized', 'other-company'] as const)(
        "asks for the person's hand when it is %s, with the camera still on",
        (status) => {
            expect(scanView(status, false)).toMatchObject({
                seeing: true,
                state: 'problem',
                problem: true,
            });
        },
    );

    it('says what each problem is, in «usted»', () => {
        expect(scanView('unreadable', false).text).toContain(
            'No logro leer el código',
        );
        expect(scanView('unrecognized', false).text).toBe(
            'Ese código no es el de la pantalla de su centro. Apunte al de la pantalla.',
        );
        expect(scanView('other-company', false).text).toBe(
            'Ese código es de otra empresa.',
        );
    });

    it('shows no camera and no window when it cannot open', () => {
        expect(scanView('denied', false)).toMatchObject({
            seeing: false,
            problem: false,
            text: 'Permita el uso de la cámara para leer el código.',
        });
        expect(scanView('unavailable', false).text).toBe(
            'Este teléfono no pudo abrir la cámara.',
        );
        expect(scanView('idle', false)).toMatchObject({
            seeing: false,
            text: '',
        });
    });

    it('shows the check once the code was read, whatever the camera says after', () => {
        expect(scanView('idle', true)).toMatchObject({
            seeing: false,
            state: 'found',
            problem: false,
            text: 'Código leído.',
        });
        expect(scanView('unrecognized', true).state).toBe('found');
    });
});
