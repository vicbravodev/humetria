import { instrumentsText } from '@/lib/campaigns';
import { highRisk, percentText } from '@/lib/results';
import type {
    CycleRow,
    DashboardCounts,
    DashboardProgress,
    LevelCounts,
    NextStep,
} from '@/types';

export type Kpi = { value: string; caption: string };

export type BadgeVariant = 'default' | 'success' | 'warning' | 'destructive';

const plural = (count: number, one: string, many: string): string =>
    count === 1 ? one : many;

/** "Hola, Rita": the first name only. */
export function greeting(name: string): string {
    const first = name.trim().split(/\s+/)[0] ?? '';

    return first === '' ? 'Hola' : `Hola, ${first}`;
}

/** The sentence under the greeting: what is in course and what is ready to launch. */
export function panelSummary({ active, drafts }: DashboardCounts): string {
    const parts = [
        active > 0 &&
            plural(
                active,
                'una evaluación en curso',
                `${active} evaluaciones en curso`,
            ),
        drafts > 0 &&
            plural(
                drafts,
                'un borrador listo para lanzar',
                `${drafts} borradores listos para lanzar`,
            ),
    ].filter((part) => part !== false);

    return parts.length === 0
        ? 'No tienes evaluaciones en curso.'
        : `Tienes ${parts.join(' y ')}.`;
}

/** The days left to the evaluation's last one. */
export function closingKpi(daysLeft: number): Kpi {
    if (daysLeft <= 0) {
        return { value: 'Hoy', caption: 'es el último día' };
    }

    return {
        value: plural(daysLeft, '1 día', `${daysLeft} días`),
        caption: 'para el cierre',
    };
}

/** How many answers are still missing to reach the target. */
export function missingKpi({ completed, target }: DashboardProgress): Kpi {
    const missing = Math.max(0, target - completed);

    return {
        value: String(missing),
        caption: plural(missing, 'falta por contestar', 'faltan por contestar'),
    };
}

/** A step of "Lo que sigue", written: what to do, where and its button. */
export function nextStepTexts(step: NextStep): {
    title: string;
    detail: string;
    action: string;
} {
    if (step.kind === 'launch') {
        const people =
            step.workers === null
                ? []
                : [
                      plural(
                          step.workers,
                          '1 persona',
                          `${step.workers} personas`,
                      ),
                  ];

        return {
            title: `Lanza la evaluación de ${step.workCenter}`,
            detail: [
                'Borrador listo',
                ...people,
                instrumentsText(step.instruments),
            ].join(' · '),
            action: 'Continuar',
        };
    }

    if (step.kind === 'report') {
        return {
            title: `Completa el informe 7.7 de ${step.workCenter}`,
            detail: 'Faltan conclusiones, recomendaciones y responsable',
            action: 'Completar',
        };
    }

    const people = step.people ?? 0;

    return {
        title: plural(
            people,
            '1 persona requiere valoración clínica',
            `${people} personas requieren valoración clínica`,
        ),
        detail: `Guía I · ${step.workCenter} · ${plural(people, 'canalízala', 'canalízalas')} a atención`,
        action: 'Revisar',
    };
}

export function pendingText(count: number): string {
    return plural(count, '1 pendiente', `${count} pendientes`);
}

const STANDINGS: Record<
    CycleRow['standing'],
    {
        from: string;
        until: string;
        badge: { label: string; variant: BadgeVariant };
    }
> = {
    in_course: {
        from: 'Lanzada el',
        until: 'Cierra el',
        badge: { label: 'En curso', variant: 'default' },
    },
    up_to_date: {
        from: 'Evaluada el',
        until: 'Siguiente antes del',
        badge: { label: 'Al día', variant: 'success' },
    },
    overdue: {
        from: 'Evaluada el',
        until: 'Debía evaluarse antes del',
        badge: { label: 'Vencida', variant: 'destructive' },
    },
    never: {
        from: 'Aún sin evaluar',
        until: '',
        badge: { label: 'Sin evaluar', variant: 'warning' },
    },
};

/** A work center's place in its cycle, written: where its span starts and ends, and its badge. */
export function cycleTexts(row: CycleRow): {
    from: string;
    until: string;
    badge: { label: string; variant: BadgeVariant };
} {
    const standing = STANDINGS[row.standing];
    const dated = (prefix: string, day: string | null) =>
        day === null ? prefix : `${prefix} ${day}`;

    return {
        from: dated(standing.from, row.from),
        until: dated(standing.until, row.until),
        badge: standing.badge,
    };
}

/** "29 de 30 · 97 %". */
export function participationShare({
    completed,
    target,
}: DashboardProgress): string {
    return `${completed} de ${target} · ${percentText(completed, target)}`;
}

/** The share of questionnaires in Alto or Muy alto: "62 %". */
export function highRiskShare(levels: LevelCounts): string {
    return percentText(
        highRisk(levels),
        levels.reduce((sum, count) => sum + count, 0),
    );
}

/**
 * What the panel shows below its header: the way to the work centers until there is one, the wait for the first
 * campaign until anything is on the go or closed, and the panel itself after that.
 */
export function dashboardView({
    hasWorkCenters,
    hasActive,
    steps,
    closed,
}: {
    hasWorkCenters: boolean;
    hasActive: boolean;
    steps: number;
    closed: number;
}): 'no-work-centers' | 'no-campaigns' | 'panel' {
    if (!hasWorkCenters) {
        return 'no-work-centers';
    }

    return hasActive || steps > 0 || closed > 0 ? 'panel' : 'no-campaigns';
}
