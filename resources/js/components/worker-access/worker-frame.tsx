import { UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import PersonFrame from '@/components/person-frame';
import { cn } from '@/lib/utils';

const AREA = { icon: UserRound, label: 'Mi portal' };

/** The frame of the person's own pages (enter, start, a link, a key): their company, «Mi portal», the actions at the foot. */
export default function WorkerFrame({
    company,
    actions,
    centered = false,
    back,
    bar,
    children,
}: {
    company: string;
    actions?: ReactNode;
    /** A screen of state: centered in the free space (see `PersonFrame`). */
    centered?: boolean;
    back?: string;
    bar?: ReactNode;
    children: ReactNode;
}) {
    return (
        <PersonFrame
            company={company}
            area={AREA}
            actions={actions}
            centered={centered}
            back={back}
            bar={bar}
        >
            <div
                className={cn(
                    'flex w-full max-w-md min-w-0 flex-col gap-5',
                    centered && 'items-center text-center',
                )}
            >
                {children}
            </div>
        </PersonFrame>
    );
}
