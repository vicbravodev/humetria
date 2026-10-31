import { Users } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import SearchField from '@/components/humetria/search-field';
import AckChoiceBar from '@/components/telework/ack-choice-bar';
import AckPersonRow from '@/components/telework/ack-person-row';
import type { useChosenPeople } from '@/hooks/use-chosen-people';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { ackQuery } from '@/lib/telework/acknowledgements';
import { index } from '@/routes/tenant/telework/documents/acknowledgements';
import type { AckFilters, AckPerson, Paginated } from '@/types';

/**
 * The people asked to confirm, a page at a time and searched on the server. Where RH manages, the pending ones can be
 * ticked (what is ticked stays across pages and searches) to record their paper confirmations.
 */
export default function AckPeopleList({
    tenantSlug,
    documentId,
    people,
    filters,
    choice,
    canManage,
}: {
    tenantSlug: string;
    documentId: string;
    people: Paginated<AckPerson>;
    filters: AckFilters;
    choice: ReturnType<typeof useChosenPeople>;
    canManage: boolean;
}) {
    const url = index.url({ tenant: tenantSlug, document: documentId });
    const { text, type } = useQueryFilters(url, filters, ackQuery);
    const { chosen, toggle, chooseFound, clear } = choice;

    return (
        <section aria-label="Personas" className="flex flex-col gap-2">
            <SearchField
                label="Buscar por nombre o número"
                value={text}
                onChange={type}
            />
            {canManage && (
                <AckChoiceBar
                    found={filters.status === 'pending' ? people.total : 0}
                    chosen={chosen.length}
                    onChooseFound={chooseFound}
                    onClear={clear}
                />
            )}
            {people.data.length === 0 ? (
                <EmptyState icon={Users} title="Nadie coincide">
                    Prueba con otro nombre o cambia de vista.
                </EmptyState>
            ) : (
                <ul className="flex flex-col divide-y rounded-lg border bg-card">
                    {people.data.map((person) => (
                        <AckPersonRow
                            key={person.id}
                            person={person}
                            selectable={canManage}
                            checked={chosen.includes(person.id)}
                            onCheckedChange={(checked) =>
                                toggle(person, checked)
                            }
                        />
                    ))}
                </ul>
            )}
            <ConsolePagination
                page={people}
                label="Páginas de personas"
                preserveState
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
