import type { NavLink } from '@/types/public-nav';

/**
 * What the header of the public pages holds: three things in the bar (Herramientas, Cómo funciona and the group
 * Recursos) and the rest inside the group. One list for every page that wears the header.
 */
export const PUBLIC_LINKS: readonly NavLink[] = [
    {
        id: 'herramientas',
        label: 'Herramientas',
        kind: 'section',
        section: 'herramientas',
        test: 'header-tools',
    },
    {
        id: 'como-funciona',
        label: 'Cómo funciona',
        kind: 'section',
        section: 'seccion-como-funciona',
        test: 'header-how',
    },
    {
        id: 'recursos',
        label: 'Recursos',
        kind: 'group',
        test: 'header-resources',
        items: [
            {
                id: 'blog',
                label: 'Blog',
                hint: 'Guías de cumplimiento laboral',
                kind: 'page',
                path: '/blog',
                test: 'header-blog',
                paths: [
                    'M12 6.5C10.5 5.3 8.2 4.8 4.5 5v12.5c3.7-.2 6 .3 7.5 1.5 1.5-1.2 3.8-1.7 7.5-1.5V5c-3.7-.2-6 .3-7.5 1.5z',
                    'M12 6.5V19',
                ],
            },
            {
                id: 'roadmap',
                label: 'Roadmap',
                hint: 'Lo que viene en Humetría',
                kind: 'page',
                path: '/roadmap',
                test: 'header-roadmap',
                paths: ['M5.5 20.5v-17', 'M5.5 5h11l-2.2 3.6 2.2 3.6h-11'],
            },
            {
                id: 'guia-jornada',
                label: 'Guía del registro de jornada',
                hint: 'Qué cambia en 2027',
                kind: 'page',
                path: '/guias/registro-de-jornada-2027',
                test: 'header-guide',
                paths: [
                    'M6.5 3.5H14l3.5 3.5v13.5h-11z',
                    'M14 3.5V7h3.5',
                    'M9.5 12h5',
                    'M9.5 15.5h5',
                ],
            },
            {
                id: 'confidencialidad',
                label: 'Confidencialidad',
                hint: 'Cómo protegemos las respuestas',
                kind: 'section',
                section: 'seccion-confidencialidad',
                test: 'header-privacy',
                paths: [
                    'M12 3.5 5 6v5.5c0 4.2 2.8 7.2 7 9 4.2-1.8 7-4.8 7-9V6z',
                    'M9.5 12.2l1.8 1.8 3.4-3.8',
                ],
            },
            {
                id: 'humi',
                label: 'Humi',
                hint: 'El asistente de IA de Humetría',
                kind: 'section',
                section: 'seccion-humi',
                test: 'header-humi',
                image: 'humi-avatar',
                paths: [],
            },
        ],
    },
];
