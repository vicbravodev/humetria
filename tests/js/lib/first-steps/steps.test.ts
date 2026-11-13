import { describe, expect, it } from 'vite-plus/test';
import {
    bubblesOf,
    humiQuestion,
    otherToolLabel,
    progress,
    progressText,
    stepCopy,
    welcomeText,
} from '@/lib/first-steps/steps';
import type {
    FirstStep,
    FirstStepKey,
    FirstStepTool,
} from '@/types/first-steps';

const step = (
    key: FirstStepKey,
    tool: FirstStepTool | null = null,
    done = false,
): FirstStep => ({
    key,
    done,
    href: null,
    tool,
});

describe('stepCopy', () => {
    it.each([
        [
            step('work_centers'),
            'Revisa tus centros de trabajo',
            'Su domicilio y su actividad salen en tus informes.',
        ],
        [
            step('people'),
            'Carga a tu gente',
            'Importa tu plantilla con un CSV o agrégala a mano.',
        ],
        [
            step('evaluation', 'nom035'),
            'Lanza tu primera evaluación NOM-035',
            'Elige un centro y envía las guías.',
        ],
        [
            step('evaluation', 'nom037'),
            'Dinos a qué centros les aplica NOM-037',
            'Dos preguntas por centro.',
        ],
        [
            step('evaluation', 'seating'),
            'Dinos a qué centros les aplica Ley Silla',
            'Dos preguntas por centro.',
        ],
        [
            step('evaluation', 'attendance'),
            'Configura el registro de jornada',
            'Zonas, horarios y política para la primera marca.',
        ],
        [
            step('team'),
            'Invita a alguien de tu equipo',
            'RH o quien te ayude con las rutas.',
        ],
        [step('humi'), 'Conoce a Humi', 'Hazle tu primera pregunta.'],
    ])('gives the copy of %j', (s, title, text) => {
        expect(stepCopy(s)).toEqual({ title, text });
    });
});

describe('bubblesOf', () => {
    it('points the work centers step at its two controls', () => {
        expect(bubblesOf(step('work_centers'))).toEqual([
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
        ]);
    });

    it('points the people step at its two controls', () => {
        expect(bubblesOf(step('people'))).toEqual([
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
        ]);
    });

    it('points NOM-035 at the new evaluation button', () => {
        expect(bubblesOf(step('evaluation', 'nom035'))).toEqual([
            {
                target: 'new-evaluation',
                title: 'Tu primera evaluación',
                text: 'Elige el centro; Humetria decide qué guías tocan según su número de personas.',
            },
        ]);
    });

    it.each<FirstStepTool>(['nom037', 'seating'])(
        'points %s at the applicability questions',
        (tool) => {
            expect(bubblesOf(step('evaluation', tool))).toEqual([
                {
                    target: 'applicability-questions',
                    title: 'Dos preguntas por centro',
                    text: 'Con tus respuestas armamos las rutas y las constancias de lo que no aplica.',
                },
            ]);
        },
    );

    it('points attendance at its setup', () => {
        expect(bubblesOf(step('evaluation', 'attendance'))).toEqual([
            {
                target: 'attendance-setup',
                title: 'Puesta en marcha',
                text: 'Aquí ves qué le falta a cada centro, horario y teléfono para la primera marca.',
            },
        ]);
    });

    it('points the team step at the invite button', () => {
        expect(bubblesOf(step('team'))).toEqual([
            {
                target: 'invite-member',
                title: 'Invita a tu equipo',
                text: 'Elige qué puede hacer cada quien: RH, solo consulta o la Comisión.',
            },
        ]);
    });

    it('points the humi step at the launcher', () => {
        expect(bubblesOf(step('humi'))).toEqual([
            {
                target: 'humi-launcher',
                title: 'Este es Humi',
                text: 'Pregúntale por tus pendientes, tus plazos o qué dice la norma.',
            },
        ]);
    });

    it('gives no bubble to an evaluation without tool', () => {
        expect(bubblesOf(step('evaluation'))).toEqual([]);
    });
});

describe('progress', () => {
    const steps = [
        step('work_centers', null, true),
        step('people', null, true),
        step('evaluation'),
        step('team'),
        step('humi'),
    ];

    it('counts the steps done', () =>
        expect(progress(steps).label).toBe('2 de 5'));
    it('gives the numbers', () =>
        expect(progress(steps)).toMatchObject({ done: 2, total: 5 }));
    it('says the steps in a sentence', () =>
        expect(progressText(steps)).toBe('2 de 5 pasos'));
});

describe('welcomeText', () => {
    it('says the real number of steps in the welcome', () => {
        expect(welcomeText(3)).toMatch(/^Son 3 pasos/);
        expect(welcomeText(5)).toBe(
            'Son 5 pasos y unos 15 minutos. Puedes dejarlo cuando quieras y retomarlo desde el botón de abajo.',
        );
    });
});

describe('otherToolLabel', () => {
    it.each<[FirstStepTool, string]>([
        ['nom035', 'NOM-035'],
        ['nom037', 'NOM-037'],
        ['seating', 'Ley Silla'],
        ['attendance', 'Registro de jornada'],
    ])('names %s', (key, label) => expect(otherToolLabel(key)).toBe(label));
});

describe('humiQuestion', () => {
    it('asks about NOM-035 when the company has it', () => {
        expect(humiQuestion(['nom037', 'nom035'])).toBe(
            '¿Qué me falta para cumplir con la NOM-035?',
        );
    });

    it('asks about the pending things otherwise', () => {
        expect(humiQuestion(['nom037'])).toBe('¿Qué pendientes tengo primero?');
        expect(humiQuestion(undefined)).toBe('¿Qué pendientes tengo primero?');
    });
});
