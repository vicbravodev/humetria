import { describe, expect, it } from 'vitest';
import {
    approvalNote,
    daysLine,
    emptyQueue,
    orderLine,
    queueQuery,
    requestDetails,
    sinceLabel,
    teamLine,
} from '@/lib/leave/queue';
import type { LeaveQueueFilters } from '@/types';

const plain: LeaveQueueFilters = {
    q: '',
    center: null,
    area: null,
    tab: 'attend',
};

describe('queueQuery', () => {
    it('leaves a plain queue with a plain address', () => {
        expect(queueQuery(plain)).toEqual({});
    });

    it('keeps only what narrows it', () => {
        expect(
            queueQuery({
                q: '  ana ',
                center: { value: 'c1', label: 'Planta' },
                area: { value: 'a1', label: 'Producción' },
                tab: 'rejected',
            }),
        ).toEqual({ tab: 'rejected', q: 'ana', center: 'c1', area: 'a1' });
    });
});

describe('daysLine', () => {
    it('says a leave uses no days', () => {
        expect(daysLine({ hadBalance: null, periods: [], shortBy: 0 })).toBe(
            'No usa días de vacaciones',
        );
    });

    it('says whether the person had the days and from which periods', () => {
        expect(daysLine({ hadBalance: true, periods: [], shortBy: 0 })).toBe(
            'Tenía días',
        );
        expect(
            daysLine({ hadBalance: true, periods: [2025, 2026], shortBy: 0 }),
        ).toBe('Tenía días · del periodo 2025 y 2026');
    });

    it('says how many days were missing', () => {
        expect(daysLine({ hadBalance: false, periods: [], shortBy: 1 })).toBe(
            'No tenía días · le faltaban 1 día',
        );
        expect(daysLine({ hadBalance: false, periods: [], shortBy: 0 })).toBe(
            'No tenía días · sin saldo al pedirla',
        );
    });
});

describe('sinceLabel', () => {
    it('writes the day it was asked, short', () => {
        expect(sinceLabel('2026-11-03')).toBe('3 nov');
        expect(sinceLabel('2026-12-21')).toBe('21 dic');
    });

    it('is empty for a text that is not a date', () => {
        expect(sinceLabel('nope')).toBe('');
    });
});

describe('teamLine', () => {
    it('says nobody else of the team is out', () => {
        expect(teamLine({ days: [], names: [] })).toBe(
            'Nadie más de su equipo está fuera',
        );
    });

    it('names who is out, only that they are away', () => {
        expect(teamLine({ days: [], names: ['Caro Luz'] })).toBe(
            'Fuera esos días: Caro Luz',
        );
        expect(teamLine({ days: [], names: ['Caro Luz', 'Dan Gil'] })).toBe(
            'Fuera esos días: Caro Luz y Dan Gil',
        );
    });

    it('says nothing when there is no team data', () => {
        expect(teamLine(null)).toBe('');
    });
});

describe('orderLine', () => {
    it('says what a tab that waits is sorted by, and nothing for the answered ones', () => {
        expect(orderLine('attend')).toBe(
            'Ordenado por fecha de inicio: lo más próximo arriba',
        );
        expect(orderLine('waiting')).toBe(
            'Ordenado por fecha de inicio: lo más próximo arriba',
        );
        expect(orderLine('approved')).toBe(
            'Ordenado por fecha de inicio: lo más reciente arriba',
        );
    });
});

describe('requestDetails', () => {
    const base = {
        status: 'requested' as const,
        manager: 'Beto Ruiz',
        recordedByHr: false,
        note: null,
        rejectionReason: null,
    };

    it('has nothing to say of a request that waits, with no note', () => {
        expect(requestDetails(base)).toEqual([]);
    });

    it('says whose it was to answer once it was answered, and what was written', () => {
        expect(
            requestDetails({
                ...base,
                status: 'rejected',
                recordedByHr: true,
                note: 'Boda',
                rejectionReason: 'Cierre de mes',
            }),
        ).toEqual([
            ['Le tocaba a', 'Beto Ruiz'],
            ['Registro', 'Lo registró RH a nombre de la persona'],
            ['Nota', 'Boda'],
            ['Motivo del rechazo', 'Cierre de mes'],
        ]);
    });

    it('does not name a manager that was not there', () => {
        expect(
            requestDetails({ ...base, status: 'approved', manager: null }),
        ).toEqual([]);
    });
});

describe('emptyQueue', () => {
    it('says what each tab holds when it has nothing, with what makes something appear', () => {
        expect(emptyQueue('attend')).toEqual({
            title: 'No hay solicitudes por atender.',
            text: 'Las que su jefe no responda a tiempo llegarán aquí.',
        });
        expect(emptyQueue('waiting').title).toBe(
            'Ninguna solicitud espera a un jefe.',
        );
        expect(emptyQueue('approved').title).toBe(
            'Aún no hay solicitudes aprobadas.',
        );
        expect(emptyQueue('rejected').title).toBe(
            'Aún no hay solicitudes rechazadas.',
        );
        expect(emptyQueue('cancelled').title).toBe(
            'Aún no hay solicitudes canceladas.',
        );
        expect(emptyQueue('taken').title).toBe(
            'Aún no hay solicitudes disfrutadas.',
        );
    });
});

describe('approvalNote', () => {
    const note =
        'No tiene días y su política no permite adelantarlos: recházala o ajusta su saldo.';

    it('says why «Aprobar» is missing when RH could answer but the server would refuse', () => {
        expect(approvalNote({ canDecide: true, canApprove: false })).toBe(note);
    });

    it('says nothing when it can be approved or there is nothing to answer', () => {
        expect(approvalNote({ canDecide: true, canApprove: true })).toBeNull();
        expect(
            approvalNote({ canDecide: false, canApprove: false }),
        ).toBeNull();
    });
});
