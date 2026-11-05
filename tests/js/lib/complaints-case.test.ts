import { describe, expect, it } from 'vitest';
import { caseLead, receivedLine } from '@/lib/complaints/case';
import type { CaseDetail } from '@/types';

const detail: CaseDetail = {
    id: 'c1',
    reference: 'NQ4MVZN',
    type: 'workplace_violence',
    typeLabel: 'Denuncia de violencia laboral',
    categoryLabel: null,
    center: 'Planta Querétaro',
    kindLabel: 'Compañera o compañero',
    identity: 'anonymous',
    description: 'Algo pasó',
    respondentText: null,
    involvedText: null,
    receivedOn: '2026-11-02',
    occurredOn: null,
    status: 'new',
    statusLabel: 'Nueva',
    onHold: false,
    resolution: null,
};

describe('caseLead', () => {
    it('says where, how it was presented and the day, never an hour', () => {
        expect(caseLead(detail, '2 de noviembre de 2026')).toBe(
            'Planta Querétaro · anónima · recibida el 2 de noviembre de 2026',
        );
    });

    it('says when no center was named, and a confidential case', () => {
        expect(
            caseLead(
                { ...detail, center: null, identity: 'confidential' },
                'hoy',
            ),
        ).toBe('Sin centro indicado · confidencial · recibida el hoy');
    });
});

describe('receivedLine', () => {
    it('names the case by its kind, with its reference', () => {
        expect(receivedLine(detail)).toBe(
            'Se recibió la denuncia y se dio a la persona la referencia NQ4MVZN.',
        );
        expect(
            receivedLine({ ...detail, type: 'traumatic_event_notice' }),
        ).toBe(
            'Se recibió el aviso y se dio a la persona la referencia NQ4MVZN.',
        );
        expect(receivedLine({ ...detail, type: 'unfavorable_practice' })).toBe(
            'Se recibió la queja y se dio a la persona la referencia NQ4MVZN.',
        );
        expect(receivedLine({ ...detail, type: 'otro' })).toBe(
            'Se recibió la queja y se dio a la persona la referencia NQ4MVZN.',
        );
    });
});
