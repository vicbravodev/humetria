import PersonCheckRow from '@/components/campaigns/person-check-row';
import ConsolePagination from '@/components/console-pagination';
import SearchField from '@/components/humetria/search-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { useChosenPeople } from '@/hooks/use-chosen-people';
import type { Paginated, RosterPerson } from '@/types';

type Filters = { q: string; work_center: string };

const query = (filters: Filters) => {
    const q = filters.q.trim();

    return { work_center: filters.work_center, ...(q === '' ? {} : { q }) };
};

/**
 * The work center's active people, a page at a time and searched on the server, to tick who went through the event.
 * What was ticked stays ticked across pages and searches.
 */
export default function PeopleChecklist({
    url,
    workCenterId,
    people,
    search,
    error,
}: {
    url: string;
    workCenterId: string;
    people: Paginated<RosterPerson>;
    search: string;
    error: string | undefined;
}) {
    const { chosen, toggle, chooseFound, clear } =
        useChosenPeople(workCenterId);
    const filters = { q: search, work_center: workCenterId };
    const { text, type } = useQueryFilters(url, filters, query);
    return (
        <fieldset
            className="flex flex-col gap-3"
            aria-describedby="trauma-people-error"
        >
            <legend className="mb-1 text-sm font-medium">
                Personas que lo vivieron o presenciaron ({chosen.length}{' '}
                elegidas)
            </legend>
            <div className="flex flex-wrap items-center gap-3">
                <SearchField
                    label="Buscar por nombre, correo o número"
                    value={text}
                    onChange={type}
                    className="max-w-sm flex-1"
                />
                {people.total > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={chooseFound}
                    >
                        Elegir a{' '}
                        {people.total === 1
                            ? 'la persona encontrada'
                            : `las ${people.total} encontradas`}
                    </Button>
                )}
                {chosen.length > 0 && (
                    <Button type="button" variant="ghost" onClick={clear}>
                        Quitar a todas
                    </Button>
                )}
            </div>
            {chosen.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="employee_ids[]"
                    value={id}
                />
            ))}
            <ul className="flex flex-col divide-y rounded-lg border bg-card empty:hidden">
                {people.data.map((person) => (
                    <PersonCheckRow
                        key={person.id}
                        person={person}
                        checked={chosen.includes(person.id)}
                        onCheckedChange={(checked) =>
                            toggle(person.id, checked)
                        }
                    />
                ))}
            </ul>
            {people.data.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Nadie coincide con la búsqueda.
                </p>
            )}
            <ConsolePagination
                page={people}
                label="Páginas de personas"
                preserveState
                previous="Anteriores"
                next="Siguientes"
            />
            <InputError id="trauma-people-error" role="alert" message={error} />
        </fieldset>
    );
}
