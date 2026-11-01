import { Users } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import TrainingPersonRow from '@/components/telework/training-person-row';
import type { Paginated, TrainingPerson } from '@/types';

/** The people of the roster, the soonest due first: when each one's yearly training falls due. */
export default function TrainingPeople({
    people,
}: {
    people: Paginated<TrainingPerson>;
}) {
    return (
        <section className="grid gap-3" aria-labelledby="people-title">
            <h2 id="people-title" className="text-xl font-semibold">
                Quién toca capacitar
            </h2>
            {people.total === 0 ? (
                <EmptyState
                    icon={Users}
                    title="Aún no hay personas en el padrón"
                >
                    Cuando alguien pase al padrón de teletrabajo, aquí verás
                    cuándo le toca su capacitación.
                </EmptyState>
            ) : (
                <ol
                    aria-label="Personas del padrón"
                    className="flex flex-col gap-3"
                >
                    {people.data.map((person) => (
                        <TrainingPersonRow key={person.id} person={person} />
                    ))}
                </ol>
            )}
            <ConsolePagination
                page={people}
                label="Páginas de personas"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
