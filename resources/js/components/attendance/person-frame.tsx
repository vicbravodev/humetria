import { Clock } from 'lucide-react';
import type { ReactNode } from 'react';
import PersonFrame from '@/components/person-frame';

const AREA = { icon: Clock, label: 'Registro de jornada' };

/** The frame of the pages a person opens for their own time record (boards P1 to P4). */
export default function AttendancePersonFrame({
    company,
    actions,
    children,
}: {
    company: string;
    /** The buttons of the screen, stuck to the bottom: the main one first. */
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <PersonFrame company={company} area={AREA} actions={actions}>
            {children}
        </PersonFrame>
    );
}
