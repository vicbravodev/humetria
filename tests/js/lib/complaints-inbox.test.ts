import { describe, expect, it } from 'vitest';
import { firstToAcknowledge, inboxDue } from '@/lib/complaints/inbox';
import type { CaseRow } from '@/types';

const row: CaseRow = {
    id: 'c1',
    reference: 'NQ4MVZN',
    type: 'workplace_violence',
    typeLabel: 'Denuncia de violencia laboral',
    status: 'new',
    statusLabel: 'Nueva',
    resolution: null,
    receivedOn: '2026-11-02',
    center: 'Planta Querétaro',
    kindLabel: 'Compañera o compañero',
    identity: 'anonymous',
    overdue: false,
    acknowledged: false,
    dueOn: '2026-11-05',
};

describe('inboxDue', () => {
    it('says which deadline counts now, and whether it is close', () => {
        expect(inboxDue(row, '2026-11-04')).toEqual({
            text: 'Acuse vence mañana',
            urgent: true,
            share: 2 / 3,
        });
        expect(
            inboxDue(
                { ...row, acknowledged: true, dueOn: '2026-12-02' },
                '2026-11-14',
            ),
        ).toEqual({
            text: 'Respuesta vence en 18 días',
            urgent: false,
            share: 12 / 30,
        });
    });
});

describe('firstToAcknowledge', () => {
    it('is the first case waiting for its acknowledgement, told in words for the handler', () => {
        expect(firstToAcknowledge([row], '2026-11-04', 3)).toEqual({
            id: 'c1',
            title: 'Una persona espera saber que alguien leyó su denuncia',
            text: 'NQ4MVZN llegó el lun 2 nov. Te propusiste confirmar en 3 días y vence mañana. Ya te dejamos el mensaje listo.',
        });
    });

    it('names a complaint and a notice, and a deadline already passed', () => {
        expect(
            firstToAcknowledge(
                [{ ...row, type: 'unfavorable_practice' }],
                '2026-11-08',
                1,
            )?.title,
        ).toBe('Una persona espera saber que alguien leyó su queja');
        expect(
            firstToAcknowledge(
                [{ ...row, type: 'traumatic_event_notice' }],
                '2026-11-08',
                3,
            ),
        ).toMatchObject({
            title: 'Una persona espera saber que alguien leyó su aviso',
            text: 'NQ4MVZN llegó el lun 2 nov. Te propusiste confirmar en 3 días y venció hace 3 días. Ya te dejamos el mensaje listo.',
        });
    });

    it('says a day in the singular', () => {
        expect(firstToAcknowledge([row], '2026-11-02', 1)?.text).toContain(
            'confirmar en 1 día y',
        );
    });

    it('is nothing when every case was acknowledged', () => {
        expect(
            firstToAcknowledge(
                [{ ...row, acknowledged: true }],
                '2026-11-04',
                3,
            ),
        ).toBeNull();
        expect(
            firstToAcknowledge([{ ...row, status: 'closed' }], '2026-11-04', 3),
        ).toBeNull();
    });
});
