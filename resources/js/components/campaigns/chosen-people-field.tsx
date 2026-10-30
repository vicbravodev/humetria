import ChosenPersonChip from '@/components/campaigns/chosen-person-chip';

type Person = { id: string; name: string };

/**
 * The people field of Guía I by event: the chosen as chips and, after them, the search over the work center's people.
 * Those chosen at once without being seen on screen are counted in one chip.
 */
export default function ChosenPeopleField({
    chips,
    search,
    onSearch,
    onRemove,
}: {
    chips: { named: Person[]; unnamed: number };
    search: string;
    onSearch: (text: string) => void;
    onRemove: (person: Person) => void;
}) {
    return (
        <div className="flex min-h-11 flex-wrap items-center gap-1.5 rounded-md border border-input bg-card px-2 py-1.5 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring">
            <ul className="contents">
                {chips.named.map((person) => (
                    <ChosenPersonChip
                        key={person.id}
                        name={person.name}
                        onRemove={() => onRemove(person)}
                    />
                ))}
                {chips.unnamed > 0 && (
                    <li className="inline-flex h-8 items-center rounded-full bg-accent px-2.5 text-[13px] font-medium">
                        +{chips.unnamed} más
                    </li>
                )}
            </ul>
            <input
                type="search"
                aria-label="Buscar por nombre, correo o número"
                placeholder="Busca por nombre o número"
                value={search}
                onChange={(event) => onSearch(event.target.value)}
                className="h-8 min-w-0 flex-[1_1_160px] bg-transparent px-1 text-base outline-none placeholder:text-muted-foreground"
            />
        </div>
    );
}
