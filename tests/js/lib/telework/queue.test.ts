import { describe, expect, it } from 'vite-plus/test';

import {
    greeting,
    namesLine,
    pendingLine,
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
