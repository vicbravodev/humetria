import { CalendarCheck, ListChecks, Users } from 'lucide-react';
import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { Committee, PageFact } from '@/types';

/** Under the Comisión's title: the day of its act, who sits on it, and that the checklist draft is ready. */
export function committeeFacts(committee: Committee | null): PageFact[] {
    if (committee === null) {
        return [];
    }

    const written = describeDate(committee.constitutedOn);

    return [
        ...(written === undefined
            ? []
            : [{ icon: CalendarCheck, text: `Acta del ${written}` }]),
        {
            icon: Users,
            text: countOf(
                committee.members.length,
                'integrante',
                'integrantes',
            ),
        },
        { icon: ListChecks, text: 'Borrador de la lista listo' },
    ];
}
