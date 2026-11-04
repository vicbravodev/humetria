import type { BadgeVariant } from '@/types/ui';
import type { ChannelStatus } from '@/types/complaints';

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
