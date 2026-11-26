import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';

const ANY = 'any';

/**
 * One filter of a list as a chip («Antigüedad ▾», «Tipo: Consultor ▾»): what it says while nothing is chosen, its name and
 * the choice once something is. Choosing «any» clears it.
 */
export default function FilterChip({
    name,
    idle = name,
    any,
    value,
    options,
    onChange,
}: {
    name: string;
    /** What it says while nothing is chosen: «Tipo: todas»; its name by default. */
    idle?: string;
    /** What the empty choice says: «Todas», «Cualquiera». */
    any: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    const chosen = options.find((option) => option.value === value);

    return (
        <Select
            value={value === '' ? ANY : value}
            onValueChange={(next) => onChange(next === ANY ? '' : next)}
        >
            <SelectTrigger
                aria-label={name}
                className="h-9 w-auto gap-1.5 font-medium data-[state=open]:border-primary"
            >
                {chosen === undefined ? idle : `${name}: ${chosen.label}`}
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ANY}>{any}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
