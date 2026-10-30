export type LandingTool = {
    key: 'nom_035' | 'nom_037' | 'ley_silla';
    name: string;
    /** What the tool is about, in two or three words. */
    hint: string;
    appliesTo: string;
    points: readonly string[];
};

/** What Humetría does for each tool, as the positioning spec words it (docs/superpowers/specs/2026-10-28-hr-tools-positioning-design.md). */
export type ToolKey = LandingTool['key'];

export const TOOLS: readonly LandingTool[] = [
    {
        key: 'nom_035',
        name: 'NOM-035',
        hint: 'Factores de riesgo psicosocial',
        appliesTo: 'Todo centro de trabajo',
        points: [
            'Elige las guías según el tamaño de cada centro',
            'Calcula y sortea la muestra por género',
            'Invita a cada persona y recuerda a quien falta',
            'Canaliza la Guía I solo con personas autorizadas',
            'Genera el informe del numeral 7.7',
        ],
    },
    {
        key: 'nom_037',
        name: 'NOM-037',
        hint: 'Teletrabajo',
        appliesTo: 'Quien teletrabaja más del 40 % de su jornada',
        points: [
            'Lleva el listado de personas en teletrabajo',
            'Cada persona contesta la lista de verificación de su lugar de trabajo desde el celular',
            'Avisa cuando toca una verificación periódica',
            'Registra quién recibió la política',
        ],
    },
    {
        key: 'ley_silla',
        name: 'Ley Silla',
        hint: 'Asientos y descansos',
        appliesTo: 'Todo patrón, en todo centro de trabajo',
        points: [
            'Registra qué puestos se hacen de pie',
            'Pregunta al personal, de forma confidencial',
            'Propone el texto del reglamento interior',
            'Arma el plan de acción con la evidencia',
        ],
    },
];
