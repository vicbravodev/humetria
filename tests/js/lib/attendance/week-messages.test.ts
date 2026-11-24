import { describe, expect, it } from 'vitest';
import { weekMessage } from '@/lib/attendance/week-messages';

describe('weekMessage', () => {
    it.each([
        ['not_verified', 'No se pudo verificar su dispositivo'],
        ['cancelled', 'No se confirmó su huella'],
        ['throttled', 'demasiados intentos'],
        ['week_not_over', 'cuando termine el domingo'],
        ['week_disputed', 'Recursos Humanos la revisará'],
        ['already_disputed', 'Ya objetó esta semana'],
        ['invalid', 'al menos 10 caracteres'],
        ['week_changed', 'Su semana cambió'],
        ['day_open', 'cuando marque su salida'],
        ['no_marks', 'No estoy de acuerdo'],
        ['uncovered_days', 'Pida una corrección'],
        [
            'uncovered_days',
            'pida a Recursos Humanos que registre su ausencia como permiso',
        ],
        ['unsupported', 'llaves de acceso'],
        ['anything', 'Intente de nuevo'],
    ])('words %s', (reason, text) => {
        expect(weekMessage(reason)).toContain(text);
    });
});
