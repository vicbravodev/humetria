import type { TenantRow } from '@/types';

/** What a person can decide about a company's access; it comes back to its dates only by reactivating it. */
export type Restriction = Extract<
    TenantRow['status'],
    'read_only' | 'suspended' | 'archived'
>;

export function isRestriction(
    status: TenantRow['status'],
): status is Restriction {
    return (
        status === 'read_only' ||
        status === 'suspended' ||
        status === 'archived'
    );
}

type RestrictionCopy = {
    action: string;
    title: string;
    submit: string;
    /** Whether it is the red kind of action: cutting the people off, not just limiting them. */
    destructive: boolean;
};

const ACTIONS: Record<Restriction, { action: string; title: string }> = {
    read_only: {
        action: 'Solo lectura',
        title: 'Poner a {name} en solo lectura',
    },
    suspended: { action: 'Suspender', title: 'Suspender a {name}' },
    archived: { action: 'Archivar', title: 'Archivar a {name}' },
};

export function restrictionCopy(
    restriction: Restriction,
    tradeName: string,
): RestrictionCopy {
    const { action, title } = ACTIONS[restriction];

    return {
        action,
        title: title.replace('{name}', () => tradeName),
        submit: action,
        destructive: restriction !== 'read_only',
    };
}

export type Effect = { label: string; text: string };

const ACCESS: Record<Restriction, string> = {
    read_only:
        'Su gente puede consultar y exportar según su rol. No puede cambiar nada: cualquier cambio se rechaza.',
    suspended:
        'Nadie de la empresa entra al panel: ven un aviso de acceso en pausa.',
    archived:
        'Nadie de la empresa entra al panel: ven un aviso de acceso en pausa.',
};

/** A company that records working time with Humetría cannot do it while restricted: the law still asks for the record. */
const ATTENDANCE_WARNING: Effect = {
    label: 'Asistencia',
    text: 'Esta empresa registra la jornada con Humetría. Mientras dure, nadie podrá marcar entrada ni salida: debe registrar la jornada por otro medio. Esto no la exime de esa obligación.',
};

/** What happens to the company the moment the person confirms, so nothing is a surprise. */
export function restrictionEffects(
    restriction: Restriction,
    usesAttendance: boolean,
): Effect[] {
    return [
        { label: 'Acceso', text: ACCESS[restriction] },
        ...(usesAttendance ? [ATTENDANCE_WARNING] : []),
        {
            label: 'Portales y envíos',
            text: 'Las encuestas, el buzón, los portales, el checador y los correos programados dejan de operar.',
        },
        {
            label: 'Cobro',
            text: 'El saldo no cambia: esto no cancela ni perdona lo que la empresa deba.',
        },
        {
            label: 'Datos',
            text: 'Nunca se borran. Reactivar la deja como estaba y no reinicia su prueba ni su vigencia.',
        },
        ...(restriction === 'archived'
            ? [
                  {
                      label: 'Lista',
                      text: 'Sale de la lista normal; la encuentras en «Archivadas».',
                  },
              ]
            : []),
    ];
}
