export type RoadmapItem = {
    title: string;
    text: string;
};

export type RoadmapLane = {
    id: 'now' | 'soon' | 'later' | 'exploring';
    title: string;
    summary: string;
    items: readonly RoadmapItem[];
};

/** What Humetría plans to add, in four lanes. No dates: a lane says how close a tool is, not when it ships. */
export const ROADMAP_LANES: readonly RoadmapLane[] = [
    {
        id: 'now',
        title: 'Ahora',
        summary: 'Lo que estamos construyendo en este momento.',
        items: [
            {
                title: 'Capacitación y constancias DC-3',
                text: 'Detecta necesidades, arma el plan anual, registra los cursos y emite las constancias DC-3 y el reporte anual DC-4.',
            },
            {
                title: 'Jornada de 40 horas',
                text: 'Registro electrónico de la jornada y alertas de horas extra sobre tu asistencia, listos para la reducción escalonada.',
            },
            {
                title: 'Reparto de utilidades (PTU)',
                text: 'Calcula el reparto, lleva el acta de su comisión mixta y da seguimiento a los pagos.',
            },
            {
                title: 'Protocolo contra violencia y acoso laboral',
                text: 'Publícalo con acuse de lectura y dale seguimiento desde tu canal de denuncias.',
            },
        ],
    },
    {
        id: 'soon',
        title: 'Pronto',
        summary: 'Lo siguiente en la fila, ya con alcance definido.',
        items: [
            {
                title: 'Comisiones mixtas',
                text: 'Integrantes, actas y seguimiento de acuerdos de las comisiones de seguridad e higiene, capacitación y PTU.',
            },
            {
                title: 'Expediente y onboarding',
                text: 'Contratos, reglamento interior y avisos con acuse, y un alta guiada para quien entra.',
            },
            {
                title: 'Integraciones',
                text: 'Inicio de sesión único (SSO), API y directorio de personas con Microsoft 365 y Google Workspace.',
            },
            {
                title: 'Transparencia salarial y brecha de género',
                text: 'Compara salarios por nivel y por género a partir de tu compensación.',
            },
            {
                title: 'Incidencias',
                text: 'Faltas, retardos, incapacidades y actas administrativas en un solo registro, con un reporte listo para tu nómina.',
            },
            {
                title: 'Evaluación de desempeño y 360',
                text: 'Evalúa a tu gente con la mirada de su jefe, sus pares y ella misma, y compara los resultados por ciclo.',
            },
            {
                title: 'OKRs y KPIs',
                text: 'Define objetivos y resultados clave por área y por persona, y mide su avance.',
            },
        ],
    },
    {
        id: 'later',
        title: 'Después',
        summary: 'Sabemos que hacen falta; aún falta definirlas.',
        items: [
            {
                title: 'Seguridad y salud para oficinas',
                text: 'Diagnóstico y programa anual en versión ligera para oficinas (NOM-030 y NOM-019).',
            },
            {
                title: 'Igualdad laboral',
                text: 'Política, indicadores y evidencia para la norma mexicana de igualdad laboral y no discriminación.',
            },
            {
                title: 'Cursos y rutas de aprendizaje',
                text: 'Una plataforma de cursos con evaluaciones, certificados y contenido propio, sobre tu plan de capacitación.',
            },
            {
                title: 'Matriz 9-Box',
                text: 'Ubica a cada persona por desempeño y potencial para decidir relevos y ascensos.',
            },
            {
                title: 'Planes de desarrollo con IA',
                text: 'Propuestas de desarrollo para cada persona a partir de su evaluación, con Humi.',
            },
            {
                title: 'Reclutamiento',
                text: 'Vacantes, candidatos y su avance en un solo tablero, con portal de empleo.',
            },
            {
                title: 'Felicidad laboral y comunicación interna',
                text: 'Medición del bienestar de tu gente y avisos de la empresa en un solo lugar.',
            },
        ],
    },
    {
        id: 'exploring',
        title: 'Explorando',
        summary: 'Ideas que estamos escuchando; sin compromiso.',
        items: [
            {
                title: 'Subcontratación (REPSE)',
                text: 'Contratos y reportes para quien presta o recibe servicios especializados.',
            },
            {
                title: 'Evaluación de personalidad',
                text: 'Perfiles para apoyar la selección y el desarrollo de equipos.',
            },
            {
                title: 'Seguridad industrial',
                text: 'Una versión para plantas y centros de trabajo operativos.',
            },
        ],
    },
];
