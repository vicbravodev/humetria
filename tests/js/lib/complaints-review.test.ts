import { describe, expect, it } from 'vitest';
import { emptyDraft } from '@/lib/complaints/form';
import { reviewRows } from '@/lib/complaints/review';

const types = [
    { value: 'workplace_violence', label: 'Denuncia', hint: '' },
    { value: 'traumatic_event_notice', label: 'Aviso', hint: '' },
];
const kinds = [{ value: 'coworker', label: 'Un compañero', available: true }];

describe('reviewRows', () => {
    it('lists what a complaint says, as the person will send it', () => {
        const rows = reviewRows(
            {
                ...emptyDraft,
                type: 'workplace_violence',
                description: '  Me gritan  ',
                respondentKind: 'coworker',
            },
            types,
            kinds,
        );

        expect(rows).toEqual([
            ['Qué informa', 'Denuncia'],
            ['Lo que pasó', 'Me gritan'],
            ['Centro de trabajo', 'Sin indicar'],
            ['De quién se trata', 'Un compañero'],
            ['Cómo va', 'Anónima: sin nombre ni contacto'],
            ['Aviso por correo', 'No'],
        ]);
    });

    it('names the work center and the name of who goes with their complaint', () => {
        const rows = reviewRows(
            {
                ...emptyDraft,
                type: 'workplace_violence',
                workCenter: { value: 'c1', label: 'Planta Querétaro' },
                identityMode: 'confidential',
                reporterName: ' Rosa ',
            },
            types,
            kinds,
        );

        expect(rows).toContainEqual(['Centro de trabajo', 'Planta Querétaro']);
        expect(rows).toContainEqual([
            'Cómo va',
            'Confidencial, con su nombre (Rosa)',
        ]);
    });

    it('shows the day and the people involved, not «of whom», for the notice of an event', () => {
        const rows = reviewRows(
            {
                ...emptyDraft,
                type: 'traumatic_event_notice',
                occurredOn: '2026-11-10',
                involvedText: ' Luis ',
                identityMode: 'confidential',
                reporterName: 'Rosa',
            },
            types,
            kinds,
        );

        expect(rows.map(([label]) => label)).toEqual([
            'Qué informa',
            'Lo que pasó',
            'Fecha en que ocurrió',
            'Centro de trabajo',
            'Personas involucradas',
            'Cómo va',
            'Aviso por correo',
        ]);
        expect(rows).toContainEqual(['Personas involucradas', 'Luis']);
        expect(rows).toContainEqual([
            'Fecha en que ocurrió',
            '10 de noviembre de 2026',
        ]);
    });

    it('says whether the person will be told of news by email, without showing the address', () => {
        const without = reviewRows(
            { ...emptyDraft, type: 'workplace_violence' },
            types,
            kinds,
        );
        const withEmail = reviewRows(
            {
                ...emptyDraft,
                type: 'workplace_violence',
                notifyEmail: 'rosa@ejemplo.mx',
            },
            types,
            kinds,
        );

        expect(without).toContainEqual(['Aviso por correo', 'No']);
        expect(withEmail).toContainEqual([
            'Aviso por correo',
            'Sí: le avisaremos a su correo. Nadie lo verá.',
        ]);
        expect(JSON.stringify(withEmail)).not.toContain('rosa@ejemplo.mx');
    });

    it('says nobody was named when the notice names nobody', () => {
        const rows = reviewRows(
            { ...emptyDraft, type: 'traumatic_event_notice' },
            types,
            kinds,
        );

        expect(rows).toContainEqual(['Personas involucradas', 'Sin indicar']);
    });
});
