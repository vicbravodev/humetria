import { describe, expect, it } from 'vite-plus/test';

import {
    greeting,
    namesLine,
    pendingLine,
    taskNorm,
    taskText,
} from '@/lib/telework/queue';
import type { CommitteeTask } from '@/types';

const lists: CommitteeTask = {
    kind: 'lists',
    workCenter: 'Planta Querétaro',
    count: 2,
    people: ['Hugo Saiz', 'Manuel Castellano'],
    href: '/r',
};
const notices: CommitteeTask = {
    kind: 'notices',
    workCenter: 'Planta Querétaro',
    count: 1,
    people: [],
    href: '/n',
};

describe('namesLine', () => {
    it('joins the names in Spanish, and counts the rest after two', () => {
        expect(namesLine(['Hugo'])).toBe('Hugo');
        expect(namesLine(['Hugo', 'Ana'])).toBe('Hugo y Ana');
        expect(namesLine(['Hugo', 'Ana', 'Luis'])).toBe('Hugo, Ana y Luis');
        expect(namesLine(['Hugo', 'Ana', 'Luis', 'Eva'])).toBe(
            'Hugo, Ana y 2 más',
        );
    });
});

describe('greeting', () => {
    it('greets by the first name', () => {
        expect(greeting('Rubén Ortiz')).toBe('Hola, Rubén');
        expect(greeting(' ')).toBe('Hola');
    });
});

describe('taskText', () => {
    it('asks to validate the places whose lists wait', () => {
        expect(taskText(lists)).toEqual({
            title: 'Valida 2 lugares de teletrabajo · Planta Querétaro',
            text: 'Hugo Saiz y Manuel Castellano contestaron la lista de su lugar. Revisa y decide.',
            estimate: '≈ 5 min cada uno',
            action: 'Empezar',
        });
        expect(
            taskText({ ...lists, count: 1, people: ['Hugo Saiz'] }).title,
        ).toBe('Valida 1 lugar de teletrabajo · Planta Querétaro');
        expect(
            taskText({ ...lists, count: 1, people: ['Hugo Saiz'] }).text,
        ).toContain('Hugo Saiz contestó');
    });

    it('asks to review the notices', () => {
        expect(taskText(notices).title).toBe(
            'Revisa 1 aviso · Planta Querétaro',
        );
        expect(taskText({ ...notices, count: 3 }).title).toBe(
            'Revisa 3 avisos · Planta Querétaro',
        );
    });

    it('asks to sign the Ley Silla assessments and to record them in the minutes', () => {
        const signatures: CommitteeTask = {
            kind: 'signatures',
            workCenter: 'Planta Querétaro',
            count: 3,
            people: ['José Antonio', 'Óscar', 'Santiago'],
            href: '/s',
        };

        expect(taskText(signatures)).toEqual({
            title: 'Firma 3 evaluaciones de trabajo de pie · Planta Querétaro',
            text: 'José Antonio, Óscar y Santiago. Ya tienen los datos: solo confirmas y firmas.',
            estimate: '≈ 3 min cada una',
            action: 'Revisar y firmar',
        });
        expect(
            taskText({ ...signatures, count: 1, people: ['Óscar'] }).title,
        ).toBe('Firma 1 evaluación de trabajo de pie · Planta Querétaro');
        expect(
            taskText({ ...signatures, kind: 'minutes', count: 2, people: [] }),
        ).toEqual({
            title: 'Asienta 2 evaluaciones en el acta de recorrido · Planta Querétaro',
            text: 'Las evaluaciones firmadas que aún no están en un acta de la Comisión.',
            estimate: '≈ 5 min',
            action: 'Abrir acta',
        });
        expect(
            taskText({ ...signatures, kind: 'minutes', count: 1, people: [] })
                .title,
        ).toBe(
            'Asienta 1 evaluación en el acta de recorrido · Planta Querétaro',
        );
        expect(taskNorm(signatures)).toBe('Ley Silla');
        expect(taskNorm({ ...signatures, kind: 'minutes' })).toBe('Ley Silla');
        expect(taskNorm(lists)).toBe('NOM-037');
    });
});

describe('pendingLine', () => {
    it('counts what waits', () => {
        expect(pendingLine([lists, notices])).toBe(
            'La Comisión tiene 3 cosas pendientes.',
        );
        expect(pendingLine([notices])).toBe(
            'La Comisión tiene 1 cosa pendiente.',
        );
        expect(pendingLine([])).toBe(
            'No tienes pendientes como integrante de la Comisión.',
        );
    });
});
