import { Users } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import CenterPersonRow from '@/components/telework/roster-person-row';
import type { Paginated, CenterPerson } from '@/types';

/** The padrón of a center (numeral 5.1): who teleworks and where each place stands. Paged by the server. */
export default function CenterRoster({
    people,
}: {
    people: Paginated<CenterPerson>;
}) {
    return (
        <section
            id="padron"
            className="grid gap-3"
            aria-labelledby="roster-title"
        >
            <h2 id="roster-title" className="text-xl font-semibold">
                Padrón de personas teletrabajadoras
            </h2>
            {people.total === 0 ? (
                <EmptyState icon={Users} title="Aún no hay personas">
                    Registra a la primera persona aspirante: su lugar de trabajo
                    se valida antes de pasar al padrón.
                </EmptyState>
            ) : (
                <ul
                    aria-label="Personas del padrón"
                    className="overflow-hidden rounded-xl border bg-card"
                >
                    {people.data.map((person) => (
                        <CenterPersonRow key={person.id} person={person} />
                    ))}
                </ul>
            )}
            <ConsolePagination
                page={people}
                label="Páginas del padrón"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
