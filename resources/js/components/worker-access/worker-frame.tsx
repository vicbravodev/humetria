import { UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import PersonFrame from '@/components/person-frame';

const AREA = { icon: UserRound, label: 'Mi portal' };

/** The frame of the person's own pages (enter, start, a link, a key): their company, «Mi portal», the actions at the foot. */
export default function WorkerFrame({
    company,
    actions,
    children,
}: {
    company: string;
    actions?: ReactNode;
    children: ReactNode;
}) {
    return (
        <PersonFrame company={company} area={AREA} actions={actions}>
            <div className="flex w-full max-w-md flex-col gap-5">
                {children}
            </div>
        </PersonFrame>
    );
}
