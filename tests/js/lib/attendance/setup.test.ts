import { describe, expect, it } from 'vitest';
import {
    deadlineText,
    joinNames,
    setupHref,
    setupProgress,
    setupSteps,
} from '@/lib/attendance/setup';
import type { AttendanceSetup } from '@/types';

const base: AttendanceSetup = {
    deadline: '2027-01-01',
    daysLeft: 53,
    requiresQr: true,
    centers: {
        done: 0,
        total: 2,
        pending: [
            { id: 'c1', name: 'Planta Norte', needs: ['screen'] },
            { id: 'c2', name: 'Oficinas Centro', needs: ['zone', 'screen'] },
        ],
    },
    schedules: { done: 4, total: 6, pending: ['Bob Díaz', 'Elena Vega'] },
    devices: { done: 2, total: 6 },
    policy: { done: 0, total: 1 },
    findings: [],
};

const done: AttendanceSetup = {
    ...base,
    centers: { done: 2, total: 2, pending: [] },
    schedules: { done: 6, total: 6, pending: [] },
    devices: { done: 6, total: 6 },
    policy: { done: 1, total: 1 },
};

describe('joinNames', () => {
    it('reads a list as a sentence', () => {
        expect(joinNames([])).toBe('');
        expect(joinNames(['Ana'])).toBe('Ana');
        expect(joinNames(['Ana', 'Bob'])).toBe('Ana y Bob');
        expect(joinNames(['Ana', 'Bob', 'Carla'])).toBe('Ana, Bob y Carla');
    });
});

describe('deadlineText', () => {
    it('counts the days left to 1 January 2027', () => {
        expect(deadlineText(53)).toBe('Faltan 53 días');
        expect(deadlineText(1)).toBe('Falta 1 día');
        expect(deadlineText(0)).toBe('Ya es obligatorio');
    });
});

describe('setupProgress', () => {
    it('counts the steps that are complete, out of four', () => {
        expect(setupProgress(base)).toEqual({ done: 0, total: 4 });
        expect(
            setupProgress({
                ...base,
                devices: { done: 6, total: 6 },
                policy: { done: 1, total: 1 },
            }),
        ).toEqual({ done: 2, total: 4 });
        expect(setupProgress(done)).toEqual({ done: 4, total: 4 });
    });

    it('counts a step with nobody to count as complete: there is nothing left to do', () => {
        expect(
            setupProgress({
                ...done,
                schedules: { done: 0, total: 0, pending: [] },
                devices: { done: 0, total: 0 },
            }),
        ).toEqual({ done: 4, total: 4 });
    });
});

describe('setupSteps', () => {
    it('says what each pending step needs and where to do it', () => {
        const [centers, schedules, devices, policy] = setupSteps(base);

        expect(centers).toMatchObject({
            key: 'centers',
            title: 'Centros listos',
            done: 0,
            total: 2,
            complete: false,
            text: 'Planta Norte necesita activar su pantalla QR. Oficinas Centro necesita zona y pantalla.',
            action: 'Terminar Planta Norte',
            target: { page: 'center', id: 'c1' },
        });
        expect(schedules).toMatchObject({
            key: 'schedules',
            title: 'Horarios fijados',
            done: 4,
            total: 6,
            text: 'Bob Díaz y Elena Vega no tienen horario: sus horas extra pueden salir de más.',
            action: 'Fijar 2 horarios',
            target: { page: 'schedules' },
        });
        expect(devices).toMatchObject({
            key: 'devices',
            title: 'Teléfonos vinculados',
            text: 'Faltan 4 personas. Cada una recibe un enlace de alta por correo; sirve una sola vez.',
            action: 'Vincular 4 teléfonos',
            target: { page: 'devices' },
        });
        expect(policy).toMatchObject({
            key: 'policy',
            title: 'Política revisada',
            text: 'Hoy usas los valores de inicio. Revísalos antes de que alguien marque.',
            action: 'Revisar política',
            target: { page: 'policy' },
        });
    });

    it('names the need of a center in the singular and the plural', () => {
        const [centers] = setupSteps({
            ...base,
            centers: {
                done: 1,
                total: 2,
                pending: [{ id: 'c2', name: 'Oficinas', needs: ['zone'] }],
            },
        });

        expect(centers?.text).toBe('Oficinas necesita una zona.');
        expect(centers?.action).toBe('Terminar Oficinas');
    });

    it('speaks of one person in the singular', () => {
        const [, schedules, devices] = setupSteps({
            ...base,
            schedules: { done: 5, total: 6, pending: ['Bob Díaz'] },
            devices: { done: 5, total: 6 },
        });

        expect(schedules?.text).toBe(
            'Bob Díaz no tiene horario: sus horas extra pueden salir de más.',
        );
        expect(schedules?.action).toBe('Fijar 1 horario');
        expect(devices?.text).toBe(
            'Falta 1 persona. Cada una recibe un enlace de alta por correo; sirve una sola vez.',
        );
        expect(devices?.action).toBe('Vincular 1 teléfono');
    });

    it('says a step is done, with no action', () => {
        const steps = setupSteps(done);

        expect(steps.map((step) => step.complete)).toEqual([
            true,
            true,
            true,
            true,
        ]);
        expect(steps.map((step) => step.action)).toEqual([
            null,
            null,
            null,
            null,
        ]);
        expect(steps[0]?.text).toBe('Todos tus centros pueden recibir marcas.');
        expect(steps[1]?.text).toBe('Todas las personas tienen horario.');
        expect(steps[2]?.text).toBe(
            'Todas las personas vincularon su teléfono.',
        );
        expect(steps[3]?.text).toBe('Ya revisaste tu política.');
    });

    it('adds how many more lack a schedule when only some are named', () => {
        const [, schedules] = setupSteps({
            ...base,
            schedules: {
                done: 1,
                total: 6,
                pending: ['Ana', 'Bob', 'Carla'],
            },
        });

        expect(schedules?.text).toBe(
            'Ana, Bob, Carla y 2 más no tienen horario: sus horas extra pueden salir de más.',
        );
    });
});

describe('setupHref', () => {
    it('sends each step to the page where it is done, already narrowed to who lacks it', () => {
        expect(setupHref('acme', { page: 'center', id: 'c1' })).toBe(
            '/admin/acme/attendance/centers/c1',
        );
        expect(setupHref('acme', { page: 'schedules' })).toBe(
            '/admin/acme/attendance/schedules?status=unscheduled',
        );
        expect(setupHref('acme', { page: 'devices' })).toBe(
            '/admin/acme/attendance/devices?status=unlinked',
        );
        expect(setupHref('acme', { page: 'policy' })).toBe(
            '/admin/acme/attendance/policy',
        );
    });
});
