import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import WorkplaceStatusBadge from '@/components/telework/workplace-status-badge';
import { maritalText } from '@/lib/telework/center';
import type { CenterPerson } from '@/types';

/** A person of the roster with where each of their workplaces stands; RH also reads their 5.1 personal data. */
export default function CenterPersonRow({ person }: { person: CenterPerson }) {
    const personal = [
        person.maritalStatus === undefined
            ? null
            : maritalText(person.maritalStatus),
        person.contactPhone,
        person.homeAddress,
    ].filter((line) => line !== null && line !== undefined);

    return (
        <li className="flex flex-wrap items-start justify-between gap-3 border-t px-[22px] py-4 first:border-t-0">
            <div className="flex min-w-0 flex-col gap-0.5">
                {person.href === null ? (
                    <p className="font-medium">{person.name}</p>
                ) : (
                    <Link
                        href={person.href}
                        className="w-fit rounded-sm font-medium hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {person.name}
                    </Link>
                )}
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    {person.jobTitle} · {person.percentage} % en teletrabajo
                </p>
                {personal.length > 0 && (
                    <p className="text-[13px] leading-[18px] text-muted-foreground">
                        {personal.join(' · ')}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {person.status === 'aspirant' && (
                    <Badge variant="outline">Aspirante</Badge>
                )}
                {person.workplaces.length === 0 ? (
                    <span className="text-[15px] text-muted-foreground">
                        Sin lugar de trabajo
                    </span>
                ) : (
                    person.workplaces.map((place) => (
                        <WorkplaceStatusBadge
                            key={place.id}
                            status={place.status}
                            label={place.statusLabel}
                        />
                    ))
                )}
            </div>
        </li>
    );
}
