import type { BreadcrumbItem } from '@/types';

/** The trail of a Vacaciones page: «Ordena tu operación › Vacaciones › …»; a level is a title or a title with its link. */
export function leaveTrail(
    ...levels: (string | BreadcrumbItem)[]
): BreadcrumbItem[] {
    return [
        { title: 'Ordena tu operación' },
        { title: 'Vacaciones' },
        ...levels.map((level) =>
            typeof level === 'string' ? { title: level } : level,
        ),
    ];
}
