import { shortDay } from '@/lib/complaints/deadline';
import type { Option } from '@/lib/option-search';
import type {
    ChannelStatus,
    CoverageByKind,
    HandlerCandidate,
} from '@/types/complaints';
import type { BadgeVariant } from '@/types/ui';

const STATUS: Record<ChannelStatus, { label: string; variant: BadgeVariant }> =
    {
        inactive: { label: 'Sin activar', variant: 'secondary' },
        active: { label: 'Activo', variant: 'success' },
        paused: { label: 'En pausa', variant: 'warning' },
    };

/** How the state of the channel reads in the badge of the page. */
export function channelBadge(status: ChannelStatus): {
    label: string;
    variant: BadgeVariant;
} {
    return STATUS[status];
}

/** The sentence under the title: where the channel stands and what the next step is. */
export function channelSummary(status: ChannelStatus): string {
    if (status === 'active') {
        return 'Tus compañeras y compañeros pueden presentar quejas y denuncias, de forma anónima o confidencial, desde el enlace o el cartel.';
    }

    if (status === 'paused') {
        return 'El canal no recibe quejas nuevas por ahora. Lo que ya llegó sigue en atención.';
    }

    return 'Revisa el procedimiento y los plazos, y activa el canal para que las personas puedan usarlo.';
}

const AGAINST: Record<string, string> = {
    direct_supervisor: 'contra una jefa o jefe inmediato',
    coworker: 'contra una compañera o compañero',
    hr: 'contra recursos humanos',
    management: 'contra la dirección o el dueño',
    third_party: 'contra una persona ajena a la empresa',
};

/** Against whom a kind of complaint is, in a sentence: «contra recursos humanos». */
export function againstWhom(kind: string): string {
    return AGAINST[kind] ?? 'sin decir contra quién';
}

const NOT: Record<string, string> = {
    hr: 'que no sea de recursos humanos',
    owner: 'que no tenga el rol de dueño',
};

export type ChannelGap = { kind: string | null; title: string; text: string };

/**
 * What is missing for the channel to be safe (board V4-Quejas-Canal, criterio C-45): somebody at all, then each kind of
 * complaint nobody independent of the whole company would receive. The form does not accept such a kind.
 */
export function channelGaps(
    coverage: CoverageByKind[],
    handlersCount: number,
): ChannelGap[] {
    if (handlersCount === 0) {
        return [
            {
                kind: null,
                title: 'Nadie atiende las quejas todavía',
                text: 'Si hoy alguien presenta una queja, no tendría quién la atienda. Designa a alguien de tu equipo o de la Comisión de seguridad e higiene.',
            },
        ];
    }

    return coverage
        .filter((row) => row.handlers.length === 0)
        .map((row) => {
            const who =
                row.excludedRole === null
                    ? 'para toda la empresa: hoy solo atienden centros'
                    : `${NOT[row.excludedRole] ?? ''}: alguien de tu equipo o de la Comisión`;

            return {
                kind: row.kind,
                title: `Nadie atiende las quejas ${againstWhom(row.kind)}`,
                text: `Si hoy alguien se queja ${againstWhom(row.kind)}, la queja no tendría quién la atienda sin conflicto y el formulario no la aceptaría. Designa a alguien ${who}.`,
            };
        });
}

/** «Falta 1 cosa para que el canal sea seguro». */
export function gapsTitle(count: number): string {
    return count === 1
        ? 'Falta 1 cosa para que el canal sea seguro'
        : `Faltan ${count} cosas para que el canal sea seguro`;
}

/** The state of the channel beside its title: since when it receives complaints, once it does. */
export function channelSince(
    status: ChannelStatus,
    publishedOn: string | null,
): string {
    return status === 'active' && publishedOn !== null
        ? `Recibe quejas desde el ${shortDay(publishedOn).split(' ').slice(1).join(' ')}`
        : STATUS[status].label;
}

/**
 * Who may be designated for a kind of complaint (board V4-Quejas-Designar): the team and the committee apart, leaving out
 * the role the complaint is against, and who that leaves out by name.
 */
export function eligibleFor(
    candidates: HandlerCandidate[],
    excludedRole: string | null,
): { team: Option[]; committee: Option[]; excluded: string[] } {
    const allowed = candidates.filter((person) => person.role !== excludedRole);

    return {
        team: allowed
            .filter((person) => person.role !== 'committee')
            .map((person) => ({
                value: person.value,
                label: `${person.label} · ${person.roleLabel}`,
            })),
        committee: allowed
            .filter((person) => person.role === 'committee')
            .map((person) => ({ value: person.value, label: person.label })),
        excluded: candidates
            .filter((person) => person.role === excludedRole)
            .map((person) => person.label),
    };
}

const LEADS: Record<string, string> = {
    owner: 'Debe ser alguien que no tenga el rol de dueño. Solo verá los casos que le lleguen.',
    hr: 'Debe ser alguien que no sea de recursos humanos. Solo verá los casos que le lleguen.',
};

/** What the dialog that designates says (board V4-Quejas-Designar), for a kind of complaint or for any. */
export function designateWords(
    row: CoverageByKind | null,
    people: { committee: Option[]; excluded: string[] },
): { title: string; lead: string; teamHint: string; committeeHint: string } {
    return {
        title:
            row === null
                ? '¿Quién atiende las quejas?'
                : `¿Quién atiende las quejas ${againstWhom(row.kind)}?`,
        lead:
            LEADS[row?.excludedRole ?? ''] ??
            'Recibe las quejas que le lleguen, nunca una contra su propio rol.',
        teamHint:
            people.excluded.length === 0
                ? 'RH, quien administra la empresa o quien atiende quejas'
                : `${people.excluded.join(', ')} no puede: es contra su rol`,
        committeeHint:
            people.committee.length === 0
                ? 'Nadie de la Comisión tiene cuenta todavía'
                : 'Sus integrantes con cuenta',
    };
}
