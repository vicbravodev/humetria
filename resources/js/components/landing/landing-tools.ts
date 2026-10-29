import type { LucideIcon } from 'lucide-react';
import { Armchair, BrainCircuit, House } from 'lucide-react';

export type LandingTool = {
    key: 'nom_035' | 'nom_037' | 'ley_silla';
    name: string;
    official: string;
    appliesTo: string;
    points: readonly string[];
    icon: LucideIcon;
};

/** What Humetría does for each tool, as the positioning spec words it (docs/superpowers/specs/2026-10-28-hr-tools-positioning-design.md). */
export const TOOLS: readonly LandingTool[] = [
    {
        key: 'nom_035',
        name: 'NOM-035',
        official: 'NOM-035-STPS-2018 · Factores de riesgo psicosocial',
        appliesTo: 'Todo centro de trabajo',
        icon: BrainCircuit,
        points: [
            'Elige la guía de cada centro de trabajo: hasta 15 trabajadores, solo Guía I; de 16 a 50, Guías I y II; más de 50, Guías I y III.',
            'Calcula la muestra representativa y la sortea en proporción por género.',
            'Invita a cada persona a contestar desde su celular. Las respuestas de Guías II y III son confidenciales y los resultados solo se ven por grupo.',
            'Señala quién requiere valoración clínica según la Guía I y lo muestra solo a roles autorizados.',
            'Genera el informe del numeral 7.7.',
        ],
    },
    {
        key: 'nom_037',
        name: 'NOM-037',
        official: 'NOM-037-STPS-2023 · Teletrabajo',
        appliesTo:
            'Quien tenga personas teletrabajando más del 40 % de su jornada',
        icon: House,
        points: [
            'Lleva el listado de las personas que teletrabajan: lugar convenido y el equipo que se les entregó.',
            'Cada persona contesta desde su celular la lista de verificación de su lugar de trabajo, la inicial y las periódicas. RH la revisa y la valida.',
            'Avisa cuando toca una verificación periódica.',
            'Ayuda a escribir la política de teletrabajo y registra que cada persona la recibió.',
            'Deja la evidencia ordenada para una inspección.',
        ],
    },
    {
        key: 'ley_silla',
        name: 'Ley Silla',
        official:
            'Ley Federal del Trabajo, arts. 132 y 133 · Asientos y descansos',
        appliesTo: 'Todo patrón, en todo centro de trabajo',
        icon: Armchair,
        points: [
            'Registra, por centro de trabajo, qué puestos se hacen de pie y con qué asientos cuentan, para trabajar y para descansar.',
            'Pregunta al personal, de forma confidencial, si de verdad tiene asiento y descansos.',
            'Propone el texto del reglamento interior con los periodos de reposo y las reglas de uso de los asientos.',
            'Arma un plan de acción con lo que falta y deja la evidencia de cumplimiento.',
        ],
    },
];
