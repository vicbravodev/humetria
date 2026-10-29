import { Check, ChevronDown, Search } from 'lucide-react';
import { useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useCombobox } from '@/hooks/use-combobox';
import type { OptionSource } from '@/hooks/use-option-search';
import type { Option } from '@/lib/option-search';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    /** The form field: it submits the chosen option's value. */
    name: string;
    source: OptionSource;
    defaultValue?: Option | undefined;
    placeholder: string;
    invalid?: boolean;
    describedBy?: string | undefined;
};

const TRIGGER =
    'flex h-11 w-full items-center justify-between gap-2 rounded-md border border-input bg-card px-4 text-left text-base transition-[color,border-color] duration-150 ease-out hover:border-foreground/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring data-[state=open]:border-ring aria-invalid:border-destructive [&[data-state=open]>svg]:rotate-180';

/**
 * A field that looks like Select and opens a search over its options: for long lists, or ones that live on the
 * server (see OptionSource). Choosing one closes it and gives focus back to the field.
 */
export default function Combobox(props: Props) {
    const { id, name, source, placeholder, describedBy } = props;
    const [selected, setSelected] = useState(props.defaultValue);
    const [open, setOpen] = useState(false);
    const search = useCombobox(id, source, (option) => {
        setSelected(option);
        setOpen(false);
    });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <input type="hidden" name={name} value={selected?.value ?? ''} />
            <PopoverTrigger
                id={id}
                data-slot="combobox"
                aria-invalid={props.invalid === true || undefined}
                aria-describedby={describedBy}
                className={TRIGGER}
            >
                <span
                    className={cn(
                        'truncate',
                        selected === undefined && 'text-muted-foreground',
                    )}
                >
                    {selected?.label ?? placeholder}
                </span>
                <ChevronDown
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground transition-transform duration-200"
                />
            </PopoverTrigger>
            <PopoverContent
                aria-label={placeholder}
                className="w-(--radix-popover-trigger-width) min-w-56 p-0"
            >
                <div className="flex h-11 items-center gap-2 border-b px-3">
                    <Search
                        aria-hidden
                        className="size-4 shrink-0 text-muted-foreground"
                    />
                    <input
                        role="combobox"
                        aria-label="Buscar"
                        aria-expanded
                        aria-controls={search.listId}
                        aria-autocomplete="list"
                        aria-activedescendant={search.activeId}
                        autoComplete="off"
                        placeholder="Buscar"
                        value={search.query}
                        onChange={(event) => search.type(event.target.value)}
                        onKeyDown={search.onKeyDown}
                        className="h-full min-w-0 flex-1 bg-transparent text-base outline-none placeholder:text-muted-foreground"
                    />
                </div>
                <ul
                    id={search.listId}
                    role="listbox"
                    aria-label="Opciones"
                    className="max-h-64 overflow-y-auto p-1 empty:hidden"
                >
                    {search.options.map((option, index) => (
                        <li
                            key={option.value}
                            id={`${search.listId}-${index}`}
                            role="option"
                            aria-selected={index === search.active}
                            onPointerMove={() => search.setActive(index)}
                            onClick={() => search.pick(option)}
                            className={cn(
                                'flex h-10 cursor-default items-center gap-2 rounded-md px-3 text-base',
                                index === search.active &&
                                    'bg-accent text-accent-foreground',
                                option.value === selected?.value &&
                                    'font-medium',
                            )}
                        >
                            <span className="flex-1 truncate">
                                {option.label}
                            </span>
                            {option.value === selected?.value && (
                                <Check
                                    aria-hidden
                                    className="size-4 text-primary"
                                />
                            )}
                        </li>
                    ))}
                </ul>
                <p
                    aria-live="polite"
                    className="px-4 py-3 text-sm text-muted-foreground empty:hidden"
                >
                    {search.note}
                </p>
            </PopoverContent>
        </Popover>
    );
}
