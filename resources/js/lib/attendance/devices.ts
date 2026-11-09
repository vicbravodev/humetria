import { shortMoment } from '@/lib/attendance/corrections';
import type { DevicePerson, LinkedDevice } from '@/types';

export type PanelListFilters = {
    q: string;
    status: string;
    /** The center the list narrows to; empty for every center. */
    center: string;
};

/** The address of a list of the registro (devices, schedules) as the server reads it: only what differs from the default view. */
export function panelListQuery(
    filters: PanelListFilters,
): Partial<Record<keyof PanelListFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'all' ? {} : { status: filters.status }),
        ...(filters.center === '' ? {} : { center: filters.center }),
    };
}

/**
 * What kind of key a device holds, said plainly: a synced passkey lives in the person's passkey account, so it may mark
 * from another device of that account; one that cannot be backed up stays on this device.
 */
export function deviceKind(synced: boolean): { label: string; hint: string } {
    return synced
        ? {
              label: 'Llave sincronizada',
              hint: 'Vive en la cuenta de llaves de acceso de la persona (iCloud, Google): puede usarse desde otro equipo de esa cuenta, no solo desde este teléfono.',
          }
        : {
              label: 'Solo este dispositivo',
              hint: 'La llave no sale de este equipo: para marcar desde otro, la persona necesita un enlace nuevo.',
          };
}

/** When a device was linked, and when it last marked or was revoked. */
export function deviceUse(
    device: Pick<LinkedDevice, 'enrolledAt' | 'lastUsedAt' | 'revokedAt'>,
): string {
    const linked = `Vinculado el ${shortMoment(device.enrolledAt)}`;

    if (device.revokedAt !== null) {
        return `${linked} · revocado el ${shortMoment(device.revokedAt)}`;
    }

    return device.lastUsedAt === null
        ? `${linked} · aún sin marcar`
        : `${linked} · usado el ${shortMoment(device.lastUsedAt)}`;
}

const STATUSES: Record<
    DevicePerson['status'],
    { label: string; variant: 'success' | 'warning' | 'secondary' }
> = {
    linked: { label: 'Con dispositivo', variant: 'success' },
    unlinked: { label: 'Sin dispositivo', variant: 'warning' },
    revoked: { label: 'Revocado', variant: 'secondary' },
};

/** Where a person stands with their devices, in a chip. */
export function deviceStatus(status: DevicePerson['status']) {
    return STATUSES[status];
}

/** One step of the road to the phone: done, the one waiting, or not reached. */
export type StepState = 'done' | 'current' | 'todo';

/**
 * Where a person is on the road «Enlace → Acuerdo → Llave»: the agreement and the key are given in the same sitting, on the
 * phone, so a link sent puts them at the agreement and the phone linked finishes all three. The caption says it in a line.
 */
export function linkProgress(
    person: Pick<DevicePerson, 'status' | 'link' | 'devices'>,
): { steps: [StepState, StepState, StepState]; caption: string } {
    const { link } = person;

    if (person.status === 'linked') {
        const newest = person.devices.find(
            (device) => device.revokedAt === null,
        );

        return {
            steps: ['done', 'done', 'done'],
            caption: `Vinculada el ${shortMoment(newest?.enrolledAt ?? '')}`,
        };
    }

    if (link?.state === 'sent') {
        return {
            steps: ['done', 'current', 'todo'],
            caption: `Enviado el ${shortMoment(link.sentAt)} · vence en ${link.hoursLeft} h`,
        };
    }

    if (link !== null) {
        return {
            steps: ['current', 'todo', 'todo'],
            caption: `El enlace del ${shortMoment(link.sentAt)} ya no abre`,
        };
    }

    return {
        steps: ['todo', 'todo', 'todo'],
        caption:
            person.status === 'revoked'
                ? 'Su teléfono se revocó: necesita un enlace nuevo'
                : 'Aún no se le envía el enlace',
    };
}

/** What to do about a person who has no phone yet, or null when they have one. */
export function nextForPerson(
    person: Pick<DevicePerson, 'status' | 'link' | 'hasEmail'>,
): string | null {
    if (person.status === 'linked') {
        return null;
    }

    if (person.link?.state === 'sent') {
        return 'Esperando que abra el enlace.';
    }

    if (person.link !== null) {
        return 'El enlace ya no abre: envía uno nuevo.';
    }

    return person.hasEmail
        ? 'Tiene correo registrado: se le puede enviar.'
        : 'Sin correo no podemos enviarle el enlace: crea el suyo y entrégaselo.';
}

/** «Bob, Carla y Elena»: the first names of a stage, and how many more there are beyond those named. */
export function stageNames(stage: { count: number; names: string[] }): string {
    if (stage.count === 0) {
        return 'Nadie';
    }

    const first = stage.names.map((name) => name.split(' ')[0] ?? name);
    const more = stage.count - first.length;
    const last = more > 0 ? `${more} más` : first.pop();

    return first.length === 0
        ? (last ?? '')
        : `${first.join(', ')} y ${last ?? ''}`;
}

/** What revoking a phone does (board A8): it loses its key at once, the marks stay and the person links one again. */
export function revokeDeviceText(owner: string, label: string): string {
    return `Con «${label}» ${owner} dejará de poder marcar de inmediato. Las marcas que ya hizo se conservan. Para volver a marcar tendrá que vincular un teléfono otra vez: envíale un enlace de alta nuevo.`;
}
