import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { filterChipText } from '@/lib/demo-requests';
import type { FilterOption } from '@/lib/demo-requests';
import { cn } from '@/lib/utils';

/** A radio item cannot carry an empty value: this one stands for "all", which narrows nothing. */
const ALL = 'all';

/**
 * A filter of the console as a chip (tablero Demos: «Origen: todos ▾»): its menu offers every value and «Todos»;
 * the server narrows the requests.
 */
export default function DemoFilterChip({
    name,
    idle,
    options,
    filter,
}: {
    name: string;
    /** What the chip says while it narrows nothing, when not just its name: «Origen: todos». */
    idle?: string;
    options: FilterOption[];
    filter: { value: string; choose: (value: string) => void };
}) {
    const text = filterChipText(name, options, filter.value);
    const active = text !== name;

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger
                data-test={`filter-${name.toLowerCase()}`}
                className={cn(
                    'relative inline-flex h-9 items-center gap-1.5 rounded-lg border border-input bg-card px-3 text-sm font-medium whitespace-nowrap transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-1 hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                    active &&
                        'border-primary bg-accent text-primary hover:bg-accent',
                )}
            >
                {active ? text : (idle ?? name)}
                <ChevronDown aria-hidden className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-44">
                <DropdownMenuRadioGroup
                    value={active ? filter.value : ALL}
                    onValueChange={(value) =>
                        filter.choose(value === ALL ? '' : value)
                    }
                >
                    <DropdownMenuRadioItem value={ALL}>
                        Todos
                    </DropdownMenuRadioItem>
                    {options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                        >
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
