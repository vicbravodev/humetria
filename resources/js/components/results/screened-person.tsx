import Initials from '@/components/humetria/initials';
import type { ScreenedPerson as Person } from '@/types';

/** One person who requires a clinical assessment: who they are and when they answered, for the record of num. 5.8. */
export default function ScreenedPerson({ person }: { person: Person }) {
    return (
        <li className="flex items-center gap-3 rounded-xl border bg-card px-[22px] py-5">
            <Initials name={person.name} className="size-10 text-[13px]" />
            <div className="flex min-w-0 flex-col">
                <p className="font-medium">{person.name}</p>
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    {person.area} · {person.jobTitle} · contestó el{' '}
                    {person.answeredOn}
                </p>
            </div>
        </li>
    );
}
