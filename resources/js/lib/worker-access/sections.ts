import {
    Armchair,
    Building2,
    CalendarDays,
    Clock,
    FileText,
    HeartHandshake,
    MessageSquareText,
    Network,
    ScrollText,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

const SECTION_ICONS: Record<string, LucideIcon> = {
    telework: FileText,
    attendance: Clock,
    vacations: CalendarDays,
    team: Network,
    seating: Armchair,
    policy: ScrollText,
    climate: MessageSquareText,
    feedback: Users,
    support: HeartHandshake,
    center: Building2,
};

/** The icon of a tool in the person's start; a tool this list does not know yet still gets one. */
export function sectionIcon(key: string): LucideIcon {
    return SECTION_ICONS[key] ?? FileText;
}
