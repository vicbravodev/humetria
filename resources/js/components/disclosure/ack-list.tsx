import { Users } from 'lucide-react';
import { useState } from 'react';
import AckRow from '@/components/disclosure/ack-row';
import AckToolbar from '@/components/disclosure/ack-toolbar';
import PaperDialog from '@/components/disclosure/paper-dialog';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { ackQuery } from '@/lib/disclosure';
import { dissemination } from '@/routes/tenant/disclosure';
import type { PolicyAckFilters, PolicyAckPerson, Paginated } from '@/types';

/**
 * The people asked to confirm, a page at a time and searched on the server (CLAUDE.md, «Listas»). RH ticks the pending ones,
 * or takes at once every pending person the search found, to record their paper confirmations.
 */
export default function AckList({
    documentId,
    version,
    people,
    filters,
    days,
}: {
    documentId: string;
    version: string | null;
    people: Paginated<PolicyAckPerson>;
    filters: PolicyAckFilters;
    days: { first: string; today: string };
}) {
    const tenantSlug = useTenantSlug();
    const { text, type } = useQueryFilters(
        dissemination.url(tenantSlug),
        { ...filters, version },
        ackQuery,
    );
    const [chosen, setChosen] = useState<string[]>([]);
    const [all, setAll] = useState<string | null>(null);
    const [recording, setRecording] = useState(false);
    const count = all === null ? chosen.length : people.total;

    return (
        <section aria-label="Personas" className="flex flex-col gap-3">
            <SearchField
                label="Buscar por nombre o número"
                value={text}
                onChange={type}
            />
            <AckToolbar
                count={count}
                offerAll={
                    filters.status === 'pending' &&
                    people.total > 0 &&
                    all === null
                        ? people.total
                        : null
                }
                onChooseAll={() => setAll(filters.q)}
                onClear={() => {
                    setChosen([]);
                    setAll(null);
                }}
                onRecord={() => setRecording(true)}
            />
            {people.data.length === 0 ? (
                <EmptyState icon={Users} title="Nadie coincide">
                    Prueba con otro nombre o cambia de vista.
                </EmptyState>
            ) : (
                <ul className="flex flex-col divide-y rounded-lg border bg-card">
                    {people.data.map((person) => (
                        <AckRow
                            key={person.id}
                            person={person}
                            checked={all !== null || chosen.includes(person.id)}
                            disabled={all !== null}
                            onCheckedChange={(checked) =>
                                setChosen((current) =>
                                    checked
                                        ? [...current, person.id]
                                        : current.filter(
                                              (id) => id !== person.id,
                                          ),
                                )
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
            {recording && (
                <PaperDialog
                    open={recording}
                    onOpenChange={setRecording}
                    documentId={documentId}
                    choice={{ ids: chosen, allMatching: all }}
                    days={days}
                />
            )}
        </section>
    );
}
