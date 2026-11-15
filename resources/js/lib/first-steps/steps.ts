import type {
    FirstStep,
    FirstStepKey,
    FirstStepTool,
} from '@/types/first-steps';

/** One tour bubble; `target` is the value of the control's `data-tour` attribute. */
export type TourBubble = { target: string; title: string; text: string };

type Copy = { title: string; text: string };

const COPY: Record<Exclude<FirstStepKey, 'evaluation'>, Copy> = {
    work_centers: {
        title: 'Revisa tus centros de trabajo',
        text: 'Su domicilio y su actividad salen en tus informes.',
    },
    people: {
        title: 'Carga a tu gente',
        text: 'Importa tu plantilla con un CSV o agrégala a mano.',
    },
    team: {
        title: 'Invita a alguien de tu equipo',
        text: 'RH o quien te ayude con las rutas.',
    },
    humi: { title: 'Conoce a Humi', text: 'Hazle tu primera pregunta.' },
};

const EVALUATION: Record<FirstStepTool, Copy> = {
    nom035: {
        title: 'Lanza tu primera evaluación NOM-035',
        text: 'Elige un centro y envía las guías.',
    },
    nom037: {
        title: 'Dinos a qué centros les aplica NOM-037',
        text: 'Dos preguntas por centro.',
    },
    seating: {
        title: 'Dinos a qué centros les aplica Ley Silla',
        text: 'Dos preguntas por centro.',
    },
    attendance: {
        title: 'Configura el registro de jornada',
        text: 'Zonas, horarios y política para la primera marca.',
    },
};

const QUESTIONS: TourBubble = {
    target: 'applicability-questions',
    title: 'Dos preguntas por centro',
    text: 'Con tus respuestas armamos las rutas y las constancias de lo que no aplica.',
};

const EVALUATION_BUBBLES: Record<FirstStepTool, TourBubble> = {
    nom035: {
        target: 'new-evaluation',
        title: 'Tu primera evaluación',
        text: 'Elige el centro; Humetria decide qué guías tocan según su número de personas.',
    },
    nom037: QUESTIONS,
    seating: QUESTIONS,
    attendance: {
        target: 'attendance-setup',
        title: 'Puesta en marcha',
        text: 'Aquí ves qué le falta a cada centro, horario y teléfono para la primera marca.',
    },
};

const BUBBLES: Record<Exclude<FirstStepKey, 'evaluation'>, TourBubble[]> = {
    work_centers: [
        {
            target: 'add-work-center',
            title: 'Tus centros de trabajo',
            text: 'Agrega cada lugar donde trabaja tu gente. Las normas se aplican por centro.',
        },
        {
            target: 'work-centers-list',
            title: 'Revisa la ficha',
            text: 'El domicilio y la actividad principal salen tal cual en tus informes.',
        },
    ],
    people: [
        {
            target: 'import-employees',
            title: 'Importa tu plantilla',
            text: 'Sube un CSV con tu gente; te decimos qué filas corregir antes de guardar.',
        },
        {
            target: 'add-employee',
            title: 'O una por una',
            text: 'Agrega a una persona a mano.',
        },
    ],
    team: [
        {
            target: 'invite-member',
            title: 'Invita a tu equipo',
            text: 'Elige qué puede hacer cada quien: RH, solo consulta o la Comisión.',
        },
    ],
    humi: [
        {
            target: 'humi-launcher',
            title: 'Este es Humi',
            text: 'Pregúntale por tus pendientes, tus plazos o qué dice la norma.',
        },
    ],
};

const OTHER_LABELS: Record<FirstStepTool, string> = {
    nom035: 'NOM-035',
    nom037: 'NOM-037',
    seating: 'Ley Silla',
    attendance: 'Registro de jornada',
};

/** An evaluation step without its tool is not shown, so it has no copy of its own. */
export function stepCopy(step: FirstStep): Copy {
    if (step.key !== 'evaluation') {
        return COPY[step.key];
    }

    return EVALUATION[step.tool ?? 'nom035'];
}

export function bubblesOf(step: FirstStep): TourBubble[] {
    if (step.key !== 'evaluation') {
        return BUBBLES[step.key];
    }

    return step.tool === null ? [] : [EVALUATION_BUBBLES[step.tool]];
}

export function progress(steps: FirstStep[]): {
    done: number;
    total: number;
    label: string;
} {
    const done = steps.filter((step) => step.done).length;

    return { done, total: steps.length, label: `${done} de ${steps.length}` };
}

export function progressText(steps: FirstStep[]): string {
    return `${progress(steps).label} pasos`;
}

export function welcomeText(total: number): string {
    return `Son ${total} pasos y unos 15 minutos. Puedes dejarlo cuando quieras y retomarlo desde el botón de abajo.`;
}

export function otherToolLabel(key: FirstStepTool): string {
    return OTHER_LABELS[key];
}

export function humiQuestion(modules: string[] | undefined): string {
    return modules?.includes('nom035')
        ? '¿Qué me falta para cumplir con la NOM-035?'
        : '¿Qué pendientes tengo primero?';
}
