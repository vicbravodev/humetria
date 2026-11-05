import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import AckPeopleEmpty from '@/components/telework/ack-people-empty';
import AckChoiceBar from '@/components/telework/ack-choice-bar';
import AckPersonRow from '@/components/telework/ack-person-row';
import type { useChosenPeople } from '@/hooks/use-chosen-people';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ackQuery } from '@/lib/telework/acknowledgements';
import { index } from '@/routes/tenant/telework/documents/acknowledgements';
import type { AckAllMatching, AckFilters, AckPerson, Paginated } from '@/types';

/**
 * The people asked to confirm, a page at a time and searched on the server. Where RH manages, the pending ones can be
 * ticked (what is ticked stays across pages and searches) to record their paper confirmations.
 */
export default function AckPeopleList({
    documentId,
    people,
    filters,
    choice,
    all,
    onAll,
    onClear,
    canManage,
}: {
    documentId: string;
    people: Paginated<AckPerson>;
    filters: AckFilters;
    choice: ReturnType<typeof useChosenPeople>;
    /** Everyone pending the search found, when RH took them at once. */
    all: AckAllMatching | null;
    onAll: (all: AckAllMatching | null) => void;
    onClear: () => void;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const url = index.url({ tenant: tenantSlug, document: documentId });
    const { text, type } = useQueryFilters(url, filters, ackQuery);
    const { chosen, toggle } = choice;

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
                    chosen={all?.count ?? chosen.length}
                    onChooseFound={() =>
                        onAll({ q: filters.q, count: people.total })
                    }
                    onClear={onClear}
                />
            )}
            {people.data.length === 0 ? (
                <AckPeopleEmpty filters={filters} />
            ) : (
                <ul className="flex flex-col divide-y rounded-lg border bg-card">
                    {people.data.map((person) => (
                        <AckPersonRow
                            key={person.id}
                            person={person}
                            selectable={canManage}
                            checked={all !== null || chosen.includes(person.id)}
                            disabled={all !== null}
                            onCheckedChange={(checked) =>
                                toggle(person, checked)
                            }
                        />
                    ))}
                </ul>
            )}
            <Pagination
                page={people}
                label="Páginas de personas"
                preserveState
                pinned={false}
            />
        </section>
    );
}
