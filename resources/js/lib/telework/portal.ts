import {
    Building2,
    CalendarDays,
    FileText,
    HeartHandshake,
    MessageSquareText,
    Network,
    ScrollText,
    Users,
    Armchair,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { PortalPending } from '@/types';

const SECTION_ICONS: Record<string, LucideIcon> = {
    telework: FileText,
    vacations: CalendarDays,
    team: Network,
    seating: Armchair,
    policy: ScrollText,
    climate: MessageSquareText,
    feedback: Users,
    support: HeartHandshake,
    center: Building2,
};

/** The icon of a tool of the portal; a tool this list does not know yet still gets one. */
export function portalSectionIcon(key: string): LucideIcon {
    return SECTION_ICONS[key] ?? FileText;
}

/** Whether the portal has nothing to show: no checklist, photo, visit or document, pending or sent. */
export function nothingPending(pending: PortalPending): boolean {
    return Object.values(pending).every((items) => items.length === 0);
}
