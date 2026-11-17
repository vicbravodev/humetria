import { Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import PersonFrame from '@/components/person-frame';

const AREA = { icon: Clock, label: 'Registro de jornada' };

/** The frame of the pages a person opens for their own time record (boards P1 to P4). */
export default function AttendancePersonFrame({
    company,
    actions,
    centered = false,
    back,
    children,
}: {
    company: string;
    /** The buttons of the screen, stuck to the bottom: the main one first. */
    actions?: ReactNode;
    /** A screen of state: centered in the free space (see `PersonFrame`). */
    centered?: boolean;
    /** «Atrás» at the top left, where it goes: the portal from Marcar, Marcar from «Mi semana». */
    back?: string | undefined;
    children: ReactNode;
}) {
    return (
        <PersonFrame
            company={company}
            area={AREA}
            actions={actions}
            centered={centered}
            back={back}
        >
            {children}
        </PersonFrame>
    );
}
