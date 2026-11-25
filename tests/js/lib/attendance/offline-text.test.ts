import { describe, expect, it } from 'vitest';
import {
    FEW_TICKETS,
    LOSS_WARNING,
    OPEN_ONLINE,
    OPEN_ONLINE_SEND,
    SAVED_TITLE,
    alreadyRecordedText,
    queueCount,
    receivedText,
    refusalNote,
    refusedText,
    savedText,
    unsavedOnServer,
} from '@/lib/attendance/offline-text';

const REASONS = [
    'ticket_expired',
    'person_inactive',
    'agreement_not_accepted',
    'sequence_conflict',
    'wrong_center',
    'pass_expired',
    'ticket_invalid',
    'signature_invalid',
    'ticket_reused',
    'malformed',
];

describe('the texts of a mark made without a connection', () => {
    it('says each state in the words the design settles', () => {
        expect(SAVED_TITLE).toBe('Guardada sin conexión');
        expect(savedText('08:02')).toBe(
            'Usted indicó 08:02. Aún no la recibe Recursos Humanos.',
        );
        expect(receivedText()).toBe(
            'Recibida. Cuenta en sus horas. Recursos Humanos la revisará.',
        );
        expect(alreadyRecordedText()).toBe('Ya estaba registrada.');
        expect(OPEN_ONLINE_SEND).toBe(
            'Abra Humetría con internet para enviar su marca.',
        );
        expect(OPEN_ONLINE).toBe('Abra Humetría con internet');
        expect(LOSS_WARNING).toBe(
            'Si borra los datos de la app antes de que se envíe, esta marca se pierde.',
        );
        expect(FEW_TICKETS).toBe(
            'Pocos boletos: abra Humetría con internet para recibir más.',
        );
    });

    it('counts the marks that wait in the right number', () => {
        expect(queueCount(0)).toBe('0 marcas sin enviar');
        expect(queueCount(1)).toBe('1 marca sin enviar');
        expect(queueCount(2)).toBe('2 marcas sin enviar');
    });

    it('gives every reason its note, and an unknown one the general one', () => {
        for (const reason of REASONS) {
            expect(refusalNote(reason), reason).not.toBe(
                'Recursos Humanos la revisará',
            );
        }

        expect(refusalNote('wrong_center')).toBe('el centro no es el suyo');
        expect(refusalNote('ticket_expired')).toBe(
            'la app llevaba más de 35 días sin internet',
        );
        expect(refusalNote('something_new')).toBe(
            'Recursos Humanos la revisará',
        );
        expect(refusalNote(null)).toBe('Recursos Humanos la revisará');
    });

    it('says the mark was not accepted, why, and that a correction can be asked', () => {
        expect(refusedText('wrong_center')).toBe(
            'No aceptada: el centro no es el suyo. Puede pedir una corrección.',
        );
    });

    it('knows the four reasons that leave nothing at the server', () => {
        const unsaved = REASONS.filter((reason) => unsavedOnServer(reason));

        expect(unsaved).toEqual([
            'ticket_invalid',
            'signature_invalid',
            'ticket_reused',
            'malformed',
        ]);
        expect(unsavedOnServer(null)).toBe(false);
    });

    it('speaks to the person as «usted», never «tú»', () => {
        const all = [
            SAVED_TITLE,
            savedText('08:02'),
            receivedText(),
            alreadyRecordedText(),
            OPEN_ONLINE,
            OPEN_ONLINE_SEND,
            LOSS_WARNING,
            FEW_TICKETS,
            queueCount(3),
            ...REASONS.map((reason) => refusedText(reason)),
        ].join(' ');

        expect(all).not.toMatch(/\b(tu|tienes|puedes|abre)\b/i);
    });
});
