import { describe, expect, it } from 'vite-plus/test';

import {
    accidentBadge,
    accidentStage,
    accidentTimeline,
    shortDay,
} from '@/lib/telework/accident';
import type { AccidentNotice } from '@/types';

const open: AccidentNotice = {
    id: 'n1',
    person: 'Hugo Saiz',
    jobTitle: 'Almacenista',
    percentage: 50,
    personHref: null,
    workCenter: { id: 'c1', name: 'Planta Querétaro' },
    description: 'Me lastimé la espalda al levantar una caja.',
    writtenOn: '2026-02-27',
    occurredOn: '2026-02-25',
    waiting: 'hace 8 meses',
    reviewedOn: null,
    actions: [],
    note: null,
    closedOn: null,
    evidenceHref: null,
    placeReviewOn: null,
    canAskPlaceReview: false,
};
const answered = { ...open, reviewedOn: '2026-03-01', actions: ['leave'] };
const closed = { ...answered, closedOn: '2026-03-04' };

describe('shortDay', () => {
    it('writes the day and the short month, without a period', () => {
        expect(shortDay('2026-02-27')).toBe('27 feb');
    });
});

describe('accidentStage and accidentBadge', () => {
    it('says how long nobody answered it', () => {
        expect(accidentStage(open)).toBe('open');
        expect(accidentBadge(open)).toEqual({
            text: 'Sin revisar desde hace 8 meses',
            tone: 'destructive',
        });
    });

    it('follows it once what was done is recorded, and closes it', () => {
        expect(accidentStage(answered)).toBe('answered');
        expect(accidentBadge(answered)).toEqual({
            text: 'En seguimiento',
            tone: 'success',
        });
        expect(accidentStage(closed)).toBe('closed');
        expect(accidentBadge(closed)).toEqual({
            text: 'Cerrado',
            tone: 'success',
        });
    });
});

describe('accidentTimeline', () => {
    it('leaves what RH has to do as pending', () => {
        expect(accidentTimeline(open, 'Hugo Saiz')).toEqual([
            { key: 'occurred', title: '25 feb · ocurrió', tone: 'person' },
            {
                key: 'written',
                title: '27 feb · Hugo Saiz avisó',
                detail: 'Por su portal',
                tone: 'person',
            },
            {
                key: 'answered',
                title: 'Pendiente · registrar lo que se hizo',
                tone: 'pending',
            },
            {
                key: 'closed',
                title: 'Pendiente · cerrar con evidencia',
                tone: 'pending',
            },
        ]);
    });

    it('dates what was done and the closing', () => {
        const [, , done, end] = accidentTimeline(closed, 'Hugo Saiz');

        expect(done).toEqual({
            key: 'answered',
            title: '1 mar · se registró lo que se hizo',
            tone: 'done',
        });
        expect(end).toEqual({
            key: 'closed',
            title: '4 mar · aviso cerrado',
            tone: 'done',
        });
    });
});
