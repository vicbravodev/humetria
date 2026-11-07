/** The tools Humetría offers, keyed as the demo form sends them (App\Modules\Demos\Enums\Tool). */
export type ToolKey =
    | 'nom_035'
    | 'nom_037'
    | 'ley_silla'
    | 'complaints'
    | 'compensation';

export type LandingTool = {
    key: ToolKey;
    name: string;
    /** What the tool is about, in two or three words. */
    hint: string;
    appliesTo: string;
    points: readonly string[];
};

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
        appliesTo: 'Quien teletrabaja más del 40 % de su jornada',
        points: [
            'Lleva el listado de personas en teletrabajo',
            'Cada persona contesta la lista de verificación de su lugar de trabajo desde el celular',
            'Arma la política y sus documentos contestando preguntas',
            'Avisa cuando toca una verificación periódica',
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
    {
        key: 'complaints',
        name: 'Buzón de quejas',
        hint: 'Quejas y denuncias',
        appliesTo: 'Toda tu gente, sin crear una cuenta',
        points: [
            'Cada queja llega a quien le toca, nunca a la persona señalada',
            'Se presenta desde el celular y puede ser anónima',
            'Enlace y cartel con código QR para cada centro',
            'Los avisos por correo nunca llevan el contenido de la queja',
        ],
    },
    {
        key: 'compensation',
        name: 'Compensación',
        hint: 'Revisión salarial y contractual',
        appliesTo: 'La empresa o el sindicato, cada uno con sus datos aparte',
        points: [
            'Costea propuestas de aumento con cargas e IMSS',
            'Compara escenarios y la distancia entre propuestas',
            'Proyecta el primer año y los siguientes',
            'Arma un reporte para compartir en la mesa',
        ],
    },
];
