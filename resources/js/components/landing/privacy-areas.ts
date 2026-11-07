import type { RiskLevelValue } from '@/types';

export type PrivacyAreaKey = 'planta' | 'produccion' | 'almacen';

export type PrivacyArea = {
    key: PrivacyAreaKey;
    name: string;
    answers: number;
    /** By category; empty when the group is too small to show. */
    categories: readonly {
        name: string;
        level: RiskLevelValue;
        label: string;
    }[];
};

/** The example plant: the whole of it, a big area and one too small to show (fewer than 5 answers). */
export const PRIVACY_AREAS: readonly PrivacyArea[] = [
    {
        key: 'planta',
        name: 'Toda la planta',
        answers: 89,
        categories: [
            { name: 'Ambiente de trabajo', level: 'low', label: 'Bajo' },
            {
                name: 'Factores propios de la actividad',
                level: 'medium',
                label: 'Medio',
            },
            {
                name: 'Organización del tiempo de trabajo',
                level: 'low',
                label: 'Bajo',
            },
            {
                name: 'Liderazgo y relaciones en el trabajo',
                level: 'null',
                label: 'Nulo',
            },
        ],
    },
    {
        key: 'produccion',
        name: 'Producción',
        answers: 58,
        categories: [
            { name: 'Ambiente de trabajo', level: 'medium', label: 'Medio' },
            {
                name: 'Factores propios de la actividad',
                level: 'medium',
                label: 'Medio',
            },
            {
                name: 'Organización del tiempo de trabajo',
                level: 'low',
                label: 'Bajo',
            },
            {
                name: 'Liderazgo y relaciones en el trabajo',
                level: 'low',
                label: 'Bajo',
            },
        ],
    },
    { key: 'almacen', name: 'Almacén', answers: 4, categories: [] },
];
