/** One step of an area's ladder: what it is called, what it lets do, the permissions it gives (each includes the one before). */
export type AreaLevel = {
    key: string;
    label: string;
    phrase: string;
    permissions: string[];
    /** How the summary says it when it is not plain «ve» or «administra»: «atiende quejas asignadas». */
    summary: string | null;
};

/** A sensitive or special permission of an area, ticked apart under its row. */
export type AreaExtra = { permission: string; label: string; summary: string };

/** One area of the roles screen: a tool or part of the panel, with one access level and maybe some extras. */
export type PermissionArea = {
    key: string;
    section: string;
    label: string;
    /** How the summary names it: «Vacaciones», «NOM-037». */
    short: string;
    levels: AreaLevel[];
    extras: AreaExtra[];
};

const none: AreaLevel = {
    key: 'none',
    label: 'Sin acceso',
    phrase: 'Sin acceso.',
    permissions: [],
    summary: null,
};

const view = (permissions: string[], phrase: string): AreaLevel => ({
    key: 'view',
    label: 'Ver',
    phrase,
    permissions,
    summary: null,
});

const manage = (permissions: string[], phrase: string): AreaLevel => ({
    key: 'manage',
    label: 'Administrar',
    phrase,
    permissions,
    summary: null,
});

/** The usual ladder: «Sin acceso», «Ver» (view-x), «Administrar» (view-x and manage-x). */
const ladder = (
    tool: string,
    phrases: { view: string; manage: string },
): AreaLevel[] => [
    none,
    view([`view-${tool}`], phrases.view),
    manage([`view-${tool}`, `manage-${tool}`], phrases.manage),
];

const NOM035_VIEW = [
    'view-nom035-route',
    'view-interventions',
    'view-disclosure',
];

/** The groups of the sidebar, in its order. */
export const SECTIONS = [
    { key: 'general', label: 'General' },
    { key: 'compliance', label: 'Cumple la norma' },
    { key: 'listening', label: 'Escucha a tu gente' },
    { key: 'operations', label: 'Ordena tu operación' },
];

/**
 * The areas, in the sidebar's order. Their permissions are written down in tests/Fixtures/permissions/areas.json too:
 * permission-areas.test.ts holds this list to that file and PermissionAreasTest (PHP) holds the file to the catalog.
 */
export const AREAS: PermissionArea[] = [
    {
        key: 'dashboard',
        section: 'general',
        label: 'Panel',
        short: 'Panel',
        levels: [
            none,
            view(['view-dashboard'], 'Ve el resumen de la empresa.'),
        ],
        extras: [],
    },
    {
        key: 'organization',
        section: 'general',
        label: 'Centros de trabajo y empleados',
        short: 'Centros de trabajo y empleados',
        levels: [
            none,
            view(
                ['view-work-centers'],
                'Consulta los centros de trabajo y su situación.',
            ),
            manage(
                ['view-work-centers', 'manage-organization'],
                'Crea y edita centros, áreas, empleados y niveles.',
            ),
        ],
        extras: [],
    },
    {
        key: 'worker-access',
        section: 'general',
        label: 'Acceso de trabajadores',
        short: 'Acceso de trabajadores',
        levels: ladder('worker-access', {
            view: 'Ve quién tiene acceso a su portal.',
            manage: 'Invita y restablece el acceso al portal.',
        }),
        extras: [],
    },
    {
        key: 'assistant',
        section: 'general',
        label: 'Humi, el asistente',
        short: 'Humi',
        levels: [
            none,
            {
                key: 'use',
                label: 'Usar',
                phrase: 'Conversa con Humi.',
                permissions: ['use-assistant'],
                summary: 'conversa con Humi',
            },
        ],
        extras: [],
    },
    {
        key: 'nom035',
        section: 'compliance',
        label: 'NOM-035',
        short: 'NOM-035',
        levels: [
            none,
            view(
                NOM035_VIEW,
                'Consulta la ruta, las evaluaciones, el programa y la difusión.',
            ),
            manage(
                [
                    ...NOM035_VIEW,
                    'manage-campaigns',
                    'manage-interventions',
                    'manage-disclosure',
                ],
                'Lanza evaluaciones, registra acciones y publica documentos.',
            ),
        ],
        extras: [
            {
                permission: 'view-trauma-screenings',
                label: 'Ver tamizajes de la Guía I (lista de personas a canalizar)',
                summary: 've los tamizajes de la Guía I',
            },
        ],
    },
    {
        key: 'telework',
        section: 'compliance',
        label: 'NOM-037, teletrabajo',
        short: 'NOM-037',
        levels: ladder('telework', {
            view: 'Consulta teletrabajadores y su lista de verificación.',
            manage: 'Gestiona teletrabajadores, documentos y comité.',
        }),
        extras: [
            {
                permission: 'review-workplaces',
                label: 'Revisar los espacios de trabajo de las personas',
                summary: 'revisa espacios de teletrabajo',
            },
        ],
    },
    {
        key: 'seating',
        section: 'compliance',
        label: 'Ley Silla',
        short: 'Ley Silla',
        levels: ladder('seating', {
            view: 'Consulta asientos, evaluaciones y medidas.',
            manage: 'Gestiona asientos, medidas y encuestas.',
        }),
        extras: [
            {
                permission: 'assess-seating',
                label: 'Evaluar el riesgo de los puestos',
                summary: 'evalúa el riesgo de Ley Silla',
            },
        ],
    },
    {
        key: 'complaints',
        section: 'compliance',
        label: 'Canal de quejas',
        short: 'el canal de quejas',
        levels: [
            none,
            {
                key: 'handle',
                label: 'Atender',
                phrase: 'Da seguimiento a las quejas que le asignen.',
                permissions: ['handle-complaints'],
                summary: 'atiende quejas asignadas',
            },
            manage(
                ['handle-complaints', 'manage-complaint-channel'],
                'Configura el canal, designa a quien atiende y da seguimiento a sus quejas.',
            ),
        ],
        extras: [],
    },
    {
        key: 'climate',
        section: 'listening',
        label: 'Clima laboral',
        short: 'Clima laboral',
        levels: ladder('climate', {
            view: 'Consulta encuestas y resultados.',
            manage: 'Crea y lanza encuestas de clima.',
        }),
        extras: [],
    },
    {
        key: 'pulse',
        section: 'listening',
        label: 'Pulso',
        short: 'Pulso',
        levels: ladder('pulse', {
            view: 'Consulta encuestas y resultados.',
            manage: 'Crea y lanza encuestas de pulso.',
        }),
        extras: [],
    },
    {
        key: 'attendance',
        section: 'operations',
        label: 'Asistencia',
        short: 'Asistencia',
        levels: ladder('attendance', {
            view: 'Consulta marcas, horarios y correcciones.',
            manage: 'Gestiona checadores, zonas, horarios y correcciones.',
        }),
        extras: [],
    },
    {
        key: 'vacations',
        section: 'operations',
        label: 'Vacaciones',
        short: 'Vacaciones',
        levels: ladder('vacations', {
            view: 'Consulta saldos y solicitudes.',
            manage: 'Aprueba solicitudes y ajusta políticas y saldos.',
        }),
        extras: [],
    },
    {
        key: 'org-chart',
        section: 'operations',
        label: 'Organigrama',
        short: 'Organigrama',
        levels: ladder('org-chart', {
            view: 'Consulta el organigrama.',
            manage: 'Edita las líneas de reporte.',
        }),
        extras: [],
    },
    {
        key: 'compensation',
        section: 'operations',
        label: 'Compensación',
        short: 'Compensación',
        levels: ladder('compensation', {
            view: 'Consulta prestaciones, salarios y escenarios.',
            manage: 'Edita prestaciones, salarios y escenarios.',
        }),
        extras: [],
    },
];

/** What each template of Humetría is for, in a few words, for the cards a new role starts from. */
export const TEMPLATE_HINTS: Partial<Record<string, string>> = {
    hr: 'Administra todo menos los usuarios',
    viewer: 'Ve todo, no cambia nada',
    committee: 'Teletrabajo, Ley Silla y quejas',
    complaints: 'Solo atiende quejas',
};
