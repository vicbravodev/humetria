import { shortMoment } from '@/lib/attendance/corrections';
import type { AccessKey, AccessPerson } from '@/types';

/**
 * What kind of key it is, said plainly: a synced key lives in the person's passkey account, so it may be used from another
 * device of that account; one that cannot be backed up stays on this device.
 */
export function keyKind(synced: boolean): { label: string; hint: string } {
    return synced
        ? {
              label: 'Llave sincronizada',
              hint: 'Vive en la cuenta de llaves de acceso de la persona (iCloud, Google): puede usarse desde otro equipo de esa cuenta, no solo desde este teléfono.',
          }
        : {
              label: 'Solo este dispositivo',
              hint: 'La llave no sale de este equipo: para entrar desde otro, la persona necesita un enlace nuevo.',
          };
}

/** When a key was registered, and when it was last used or revoked. */
export function keyUse(
    key: Pick<AccessKey, 'registeredAt' | 'lastUsedAt' | 'revokedAt'>,
): string {
    const registered = `Registrada el ${shortMoment(key.registeredAt)}`;

    if (key.revokedAt !== null) {
        return `${registered} · revocada el ${shortMoment(key.revokedAt)}`;
    }

    return key.lastUsedAt === null
        ? `${registered} · aún sin usar`
        : `${registered} · usada el ${shortMoment(key.lastUsedAt)}`;
}

const STATUSES: Record<
    AccessPerson['status'],
    { label: string; variant: 'success' | 'warning' | 'secondary' }
> = {
    with_key: { label: 'Con llave', variant: 'success' },
    invited: { label: 'Invitada', variant: 'secondary' },
    not_invited: { label: 'Sin invitar', variant: 'warning' },
};

/** Where a person stands with their access, in a chip. */
export function accessStatus(status: AccessPerson['status']) {
    return STATUSES[status];
}

/** Where a person is on the road to their key, in a line: since when they have one, or what happened to their invitation. */
export function inviteCaption(
    person: Pick<AccessPerson, 'status' | 'link' | 'keys'>,
): string {
    const { link } = person;

    if (person.status === 'with_key') {
        const newest = person.keys.find((key) => key.revokedAt === null);

        return `Con llave desde el ${shortMoment(newest?.registeredAt ?? '')}`;
    }

    if (link?.state === 'sent') {
        return `Invitada el ${shortMoment(link.sentAt)} · vence en ${link.hoursLeft} h`;
    }

    if (link !== null) {
        return `La invitación del ${shortMoment(link.sentAt)} ya no abre`;
    }

    return person.keys.length > 0
        ? 'Sus llaves se revocaron: necesita una invitación nueva'
        : 'Aún no se le invita';
}

/** What to do about a person who has no key yet, or null when they have one. */
export function nextForPerson(
    person: Pick<AccessPerson, 'status' | 'link' | 'hasEmail'>,
): string | null {
    if (person.status === 'with_key') {
        return null;
    }

    if (person.link?.state === 'sent') {
        return 'Esperando que abra su invitación.';
    }

    if (person.link !== null) {
        return 'La invitación ya no abre: envía otra.';
    }

    return person.hasEmail
        ? 'Tiene correo registrado: se le puede invitar.'
        : 'Sin correo no podemos enviarle la invitación: crea su enlace y entrégaselo.';
}

/** What revoking one key does: it stops at once, the marks stay, and a lost phone is better reissued. */
export function revokeKeyText(owner: string, label: string): string {
    return `Con «${label}» ${owner} ya no podrá entrar ni marcar. Las marcas que ya hizo se conservan. Si perdió el teléfono, mejor reemite su acceso: también cierra las sesiones que tenga abiertas.`;
}

/** What reissuing a person's access does (CUJ 7): every key and session stops, and a new link goes to them or to RH. */
export function reissueText(
    person: Pick<AccessPerson, 'name' | 'hasEmail'>,
    linkHours: number,
): string {
    const delivery = person.hasEmail
        ? 'Le llega a su correo un enlace nuevo para crear su llave otra vez'
        : 'No tiene correo: verás el enlace nuevo una sola vez para entregárselo';

    return `Todas las llaves de ${person.name} dejan de servir y se cierran sus sesiones abiertas. ${delivery}; funciona una vez y vence en ${linkHours} h.`;
}
