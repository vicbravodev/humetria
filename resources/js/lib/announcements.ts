import type { AnnouncementKind, AnnouncementState } from '@/types';

type Tone = 'default' | 'secondary' | 'success' | 'warning' | 'destructive';

/** The color of an announcement's kind: a maintenance asks for a look, an incident is an alarm, the rest are news. */
export function kindTone(kind: AnnouncementKind): Tone {
    switch (kind) {
        case 'maintenance':
            return 'warning';
        case 'incident':
            return 'destructive';
        case 'billing':
            return 'secondary';
        default:
            return 'default';
    }
}

/** The color of where it stands: running is the one live state; to come, in the accent too; drafts and the ended ones, quiet. */
export function stateTone(state: AnnouncementState): Tone {
    switch (state) {
        case 'active':
            return 'success';
        case 'scheduled':
            return 'default';
        default:
            return 'secondary';
    }
}
