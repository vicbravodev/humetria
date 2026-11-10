import { center } from '@/routes/tenant/attendance';
import { index as devices } from '@/routes/tenant/attendance/devices';
import { show as policy } from '@/routes/tenant/attendance/policy';
import { index as schedules } from '@/routes/tenant/attendance/schedules';
import type { AttendanceSetup, SetupCenterNeed } from '@/types';

/** The four things a company does before its people can mark: its centers, schedules, phones and policy. */
export type SetupStep = {
    key: 'centers' | 'schedules' | 'devices' | 'policy';
    title: string;
    done: number;
    total: number;
    complete: boolean;
    text: string;
    /** The button's label; null once the step is complete. */
    action: string | null;
    /** Where the button goes: a page of the registro (a center, by id). */
    target:
        | { page: 'center'; id: string }
        | { page: 'schedules' | 'devices' | 'policy' };
};

/** «Ana», «Ana y Bob», «Ana, Bob y Carla». */
export function joinNames(names: readonly string[]): string {
    const last = names.at(-1);

    if (last === undefined) {
        return '';
    }

    return names.length === 1
        ? last
        : `${names.slice(0, -1).join(', ')} y ${last}`;
}

/** What is left to 1 January 2027, the day the registro de jornada is mandatory (LFT art. 132-XXXIV). */
export function deadlineText(daysLeft: number): string {
    if (daysLeft === 0) {
        return 'Ya es obligatorio';
    }

    return daysLeft === 1 ? 'Falta 1 día' : `Faltan ${daysLeft} días`;
}

/** «necesita activar su pantalla QR», «necesita una zona», «necesita zona y pantalla». */
function needText(needs: readonly SetupCenterNeed[]): string {
    if (needs.length === 2) {
        return 'necesita zona y pantalla';
    }

    return needs[0] === 'screen'
        ? 'necesita activar su pantalla QR'
        : 'necesita una zona';
}

const plural = (count: number, one: string, many: string): string =>
    count === 1 ? one : many;

/** «Ana y Bob no tienen horario», «Ana, Bob, Carla y 2 más no tienen horario»; one person has no «n más». */
function lackText(names: readonly string[], left: number): string {
    const more = left - names.length;

    return more > 0
        ? `${names.join(', ')} y ${more} más no tienen horario`
        : `${joinNames(names)} ${plural(left, 'no tiene', 'no tienen')} horario`;
}

function centersStep(setup: AttendanceSetup): SetupStep {
    const { done, total, pending } = setup.centers;
    const first = pending[0];

    return {
        key: 'centers',
        title: 'Centros listos',
        done,
        total,
        complete: first === undefined,
        text:
            first === undefined
                ? 'Todos tus centros pueden recibir marcas.'
                : pending
                      .map(
                          (center) =>
                              `${center.name} ${needText(center.needs)}.`,
                      )
                      .join(' '),
        action: first === undefined ? null : `Terminar ${first.name}`,
        target:
            first === undefined
                ? { page: 'policy' }
                : { page: 'center', id: first.id },
    };
}

function schedulesStep(setup: AttendanceSetup): SetupStep {
    const { done, total, pending } = setup.schedules;
    const left = total - done;

    return {
        key: 'schedules',
        title: 'Horarios fijados',
        done,
        total,
        complete: left === 0,
        text:
            left === 0
                ? 'Todas las personas tienen horario.'
                : `${lackText(pending, left)}: sus horas extra pueden salir de más.`,
        action:
            left === 0
                ? null
                : `Fijar ${left} ${plural(left, 'horario', 'horarios')}`,
        target: { page: 'schedules' },
    };
}

function devicesStep(setup: AttendanceSetup): SetupStep {
    const { done, total } = setup.devices;
    const left = total - done;

    return {
        key: 'devices',
        title: 'Teléfonos vinculados',
        done,
        total,
        complete: left === 0,
        text:
            left === 0
                ? 'Todas las personas vincularon su teléfono.'
                : `${plural(left, 'Falta 1 persona', `Faltan ${left} personas`)}. Cada una recibe un enlace de alta por correo; sirve una sola vez.`,
        action:
            left === 0
                ? null
                : `Vincular ${left} ${plural(left, 'teléfono', 'teléfonos')}`,
        target: { page: 'devices' },
    };
}

function policyStep(setup: AttendanceSetup): SetupStep {
    const { done, total } = setup.policy;

    return {
        key: 'policy',
        title: 'Política revisada',
        done,
        total,
        complete: done >= total,
        text:
            done >= total
                ? 'Ya revisaste tu política.'
                : 'Hoy usas los valores de inicio. Revísalos antes de que alguien marque.',
        action: done >= total ? null : 'Revisar política',
        target: { page: 'policy' },
    };
}

/** The four steps with their count, what each needs and where to do it. */
export function setupSteps(setup: AttendanceSetup): SetupStep[] {
    return [
        centersStep(setup),
        schedulesStep(setup),
        devicesStep(setup),
        policyStep(setup),
    ];
}

/** How many of the four steps are complete. */
export function setupProgress(setup: AttendanceSetup): {
    done: number;
    total: number;
} {
    const steps = setupSteps(setup);

    return {
        done: steps.filter((step) => step.complete).length,
        total: steps.length,
    };
}

/** The page where a step is done, already narrowed to the people who still lack it. */
export function setupHref(
    tenantSlug: string,
    target: SetupStep['target'],
): string {
    switch (target.page) {
        case 'center':
            return center({ tenant: tenantSlug, workCenter: target.id }).url;
        case 'schedules':
            return schedules(tenantSlug, { query: { status: 'unscheduled' } })
                .url;
        case 'devices':
            return devices(tenantSlug, { query: { status: 'unlinked' } }).url;
        case 'policy':
            return policy(tenantSlug).url;
    }
}
