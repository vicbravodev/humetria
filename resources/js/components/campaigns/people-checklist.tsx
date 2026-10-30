import ChosenPeopleActions from '@/components/campaigns/chosen-people-actions';
import ChosenPeopleField from '@/components/campaigns/chosen-people-field';
import PersonCheckRow from '@/components/campaigns/person-check-row';
import ConsolePagination from '@/components/console-pagination';
import InputError from '@/components/input-error';
import type { useChosenPeople } from '@/hooks/use-chosen-people';
import { useQueryFilters } from '@/hooks/use-query-filters';
import type { Paginated, RosterPerson } from '@/types';

type Filters = { q: string; work_center: string };

const query = (filters: Filters) => {
    const q = filters.q.trim();

    return { work_center: filters.work_center, ...(q === '' ? {} : { q }) };
};

/**
 * Who went through the event (pantalla Guia-I-Acontecimiento): the chosen as chips in the field, and the work center's
 * active people under it, a page at a time and searched on the server. What was ticked stays ticked across pages and
 * searches.
 */
export default function PeopleChecklist({
    url,
    workCenter,
    people,
    search,
    maxPeople,
    choice,
    error,
}: {
    url: string;
    workCenter: { value: string; label: string };
    people: Paginated<RosterPerson>;
    search: string;
    /** Most people that can be chosen: a search that finds more is narrowed first. */
    maxPeople: number;
    /** Who was ticked, kept by the form that sends them. */
    choice: ReturnType<typeof useChosenPeople>;
    error: string | undefined;
}) {
    const { chosen, chips, toggle, chooseFound, clear } = choice;
    const filters = { q: search, work_center: workCenter.value };
    const { text, type } = useQueryFilters(url, filters, query);

    return (
        <fieldset
            className="flex flex-col gap-1.5"
            aria-describedby="trauma-people-help trauma-people-error"
        >
            <legend className="mb-1.5 text-sm leading-5 font-medium">
                Personas que lo vivieron o presenciaron{' '}
                <span className="font-normal text-muted-foreground">
                    ({chosen.length} elegidas)
                </span>
            </legend>
            <ChosenPeopleField
                chips={chips}
                search={text}
                onSearch={type}
                onRemove={(person) => toggle(person, false)}
            />
            <p
                id="trauma-people-help"
                className="text-[13px] leading-[18px] text-muted-foreground"
            >
                Solo personas de {workCenter.label}.
            </p>
            <ChosenPeopleActions
                found={people.total <= maxPeople ? people.total : 0}
                chosen={chosen.length}
                onChooseFound={chooseFound}
                onClear={clear}
            />
            {chosen.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="employee_ids[]"
                    value={id}
                />
            ))}
            <ul className="mt-1.5 flex flex-col divide-y rounded-lg border bg-card empty:hidden">
                {people.data.map((person) => (
                    <PersonCheckRow
                        key={person.id}
                        person={person}
                        checked={chosen.includes(person.id)}
                        onCheckedChange={(checked) => toggle(person, checked)}
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
                pinned={false}
            />
            <InputError id="trauma-people-error" role="alert" message={error} />
        </fieldset>
    );
}
