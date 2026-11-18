import {
    BookOpen,
    Brain,
    CalendarX,
    Clock3,
    Coins,
    Equal,
    FolderOpen,
    GraduationCap,
    HardHat,
    LayoutGrid,
    Network,
    PlugZap,
    Scale,
    ShieldAlert,
    ShieldCheck,
    Smile,
    Sparkles,
    Target,
    UserSearch,
    UsersRound,
} from 'lucide-react';
import type { RoadmapArea, RoadmapLane } from '@/types/roadmap';

/** The name of each area, as the filters and the tags of the cards say it. */
export const AREA_LABELS: Record<RoadmapArea, string> = {
    evaluation: 'Evaluación',
    compliance: 'Cumplimiento',
    talent: 'Talento',
    operation: 'Operación',
};

/**
 * What Humetría plans to add, in four stages. No dates: a stage says how close a tool is, not when it ships. The
 * evaluation of people comes first because it is what customers ask for most.
 */
export const ROADMAP_LANES: readonly RoadmapLane[] = [
    {
        id: 'now',
        title: 'Ahora',
        summary: 'Lo que estamos construyendo en este momento.',
        status: 'En construcción',
        items: [
            {
                title: 'Evaluación de desempeño y 360',
                text: 'Evalúa a tu gente con la mirada de su jefe, sus pares y ella misma, y compara los resultados ciclo tras ciclo.',
                area: 'evaluation',
                icon: UsersRound,
                sketch: 'feedback',
            },
            {
                title: 'OKRs y KPIs',
                text: 'Define objetivos y resultados clave por área y por persona, y mide su avance sin hojas de cálculo.',
                area: 'evaluation',
                icon: Target,
                sketch: 'goals',
            },
            {
                title: 'Capacitación y constancias DC-3',
                text: 'Detecta necesidades, arma el plan anual, registra los cursos y emite las constancias DC-3 y el reporte anual DC-4.',
                area: 'compliance',
                icon: GraduationCap,
            },
            {
                title: 'Jornada de 40 horas',
                text: 'Registro electrónico de la jornada y alertas de horas extra sobre tu asistencia, listos para la reducción escalonada.',
                area: 'compliance',
                icon: Clock3,
            },
            {
                title: 'Reparto de utilidades (PTU)',
                text: 'Calcula el reparto, lleva el acta de su comisión mixta y da seguimiento a los pagos.',
                area: 'compliance',
                icon: Coins,
            },
            {
                title: 'Protocolo contra violencia y acoso laboral',
                text: 'Publícalo con acuse de lectura y dale seguimiento desde tu canal de denuncias.',
                area: 'compliance',
                icon: ShieldAlert,
            },
        ],
    },
    {
        id: 'soon',
        title: 'Pronto',
        summary: 'Lo siguiente en la fila, ya con alcance definido.',
        status: 'Con alcance definido',
        items: [
            {
                title: 'Matriz 9-Box',
                text: 'Ubica a cada persona por desempeño y potencial para decidir relevos y ascensos.',
                area: 'evaluation',
                icon: LayoutGrid,
            },
            {
                title: 'Planes de desarrollo con IA',
                text: 'Propuestas de desarrollo para cada persona a partir de su evaluación, con Humi.',
                area: 'evaluation',
                icon: Sparkles,
            },
            {
                title: 'Comisiones mixtas',
                text: 'Integrantes, actas y seguimiento de acuerdos de las comisiones de seguridad e higiene, capacitación y PTU.',
                area: 'compliance',
                icon: UsersRound,
            },
            {
                title: 'Transparencia salarial y brecha de género',
                text: 'Compara salarios por nivel y por género a partir de tu compensación.',
                area: 'compliance',
                icon: Scale,
            },
            {
                title: 'Expediente y onboarding',
                text: 'Contratos, reglamento interior y avisos con acuse, y un alta guiada para quien entra.',
                area: 'operation',
                icon: FolderOpen,
            },
            {
                title: 'Incidencias',
                text: 'Faltas, retardos, incapacidades y actas administrativas en un solo registro, con un reporte listo para tu nómina.',
                area: 'operation',
                icon: CalendarX,
            },
            {
                title: 'Integraciones',
                text: 'Inicio de sesión único (SSO), API y directorio de personas con Microsoft 365 y Google Workspace.',
                area: 'operation',
                icon: PlugZap,
            },
        ],
    },
    {
        id: 'later',
        title: 'Después',
        summary: 'Sabemos que hacen falta; aún falta definirlas.',
        status: 'Por definir',
        items: [
            {
                title: 'Cursos y rutas de aprendizaje',
                text: 'Una plataforma de cursos con evaluaciones, certificados y contenido propio, sobre tu plan de capacitación.',
                area: 'talent',
                icon: BookOpen,
            },
            {
                title: 'Reclutamiento',
                text: 'Vacantes, candidatos y su avance en un solo tablero, con portal de empleo.',
                area: 'talent',
                icon: UserSearch,
            },
            {
                title: 'Felicidad laboral y comunicación interna',
                text: 'Medición del bienestar de tu gente y avisos de la empresa en un solo lugar.',
                area: 'talent',
                icon: Smile,
            },
            {
                title: 'Seguridad y salud para oficinas',
                text: 'Diagnóstico y programa anual en versión ligera para oficinas (NOM-030 y NOM-019).',
                area: 'compliance',
                icon: ShieldCheck,
            },
            {
                title: 'Igualdad laboral',
                text: 'Política, indicadores y evidencia para la norma mexicana de igualdad laboral y no discriminación.',
                area: 'compliance',
                icon: Equal,
            },
        ],
    },
    {
        id: 'exploring',
        title: 'Explorando',
        summary: 'Ideas que estamos escuchando; sin compromiso.',
        status: 'Escuchando',
        items: [
            {
                title: 'Evaluación de personalidad',
                text: 'Perfiles para apoyar la selección y el desarrollo de equipos.',
                area: 'talent',
                icon: Brain,
            },
            {
                title: 'Subcontratación (REPSE)',
                text: 'Contratos y reportes para quien presta o recibe servicios especializados.',
                area: 'compliance',
                icon: Network,
            },
            {
                title: 'Seguridad industrial',
                text: 'Una versión para plantas y centros de trabajo operativos.',
                area: 'compliance',
                icon: HardHat,
            },
        ],
    },
];
