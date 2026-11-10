import type { ReactNode } from 'react';
import Initials from '@/components/humetria/initials';
import Pagination from '@/components/humetria/pagination';
import { teamPersonText } from '@/lib/org-chart/labels';
import type { Paginated, PortalTeamPerson } from '@/types';

/** A list of people of the person's team (their peers, their reports), a few at a time; `empty` when it has nobody. */
export default function PortalTeamList({
    id,
    title,
    people,
    empty,
}: {
    id: string;
    title: string;
    people: Paginated<PortalTeamPerson>;
    empty: ReactNode;
}) {
    return (
        <section aria-labelledby={id} className="grid gap-3">
            <h2 id={id} className="text-xl font-semibold">
                {title}
            </h2>
            {people.data.length === 0 ? (
                <p className="text-base text-muted-foreground">{empty}</p>
            ) : (
                <ul className="divide-y card">
                    {people.data.map((person, position) => (
                        <li
                            // Nobody's id reaches the portal: the place in the page tells them apart.
                            key={`${position}-${person.name}`}
                            className="flex items-center gap-3.5 p-4"
                        >
                            <Initials
                                name={person.name}
                                className="size-10 text-sm"
                            />
                            <p className="flex min-w-0 flex-col">
                                <span className="text-base font-semibold">
                                    {person.name}
                                </span>
                                <span className="text-base text-muted-foreground">
                                    {teamPersonText(person)}
                                </span>
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            <Pagination page={people} label={title} pinned={false} />
        </section>
    );
}
