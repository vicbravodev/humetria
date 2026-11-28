import { ChevronDown, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * A filter of the trail as a chip (tablero Bitácora): «Empresa ▾» opens its control; in use it reads «Empresa: Acme»
 * in the accent, with a ✕ beside it that takes it off.
 */
export default function AuditFilterChip({
    label,
    value,
    onClear,
    children,
}: {
    label: string;
    /** What the filter is set to, or null when it is not in use. */
    value: string | null;
    onClear: () => void;
    children: ReactNode;
}) {
    const on = value !== null;

    return (
        <div className="inline-flex">
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        size="sm"
                        className={cn(
                            'font-medium',
                            on &&
                                'rounded-r-none border-primary bg-accent text-primary hover:bg-accent',
                        )}
                    >
                        {on ? `${label}: ${value}` : label}
                        <ChevronDown aria-hidden />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    data-slot="filter-chip-content"
                    className="grid w-80 gap-3"
                >
                    {children}
                </PopoverContent>
            </Popover>
            {on && (
                <Button
                    variant="outline"
                    size="sm"
                    aria-label={`Quitar el filtro: ${label}`}
                    onClick={onClear}
                    className="-ml-px rounded-l-none border-primary bg-accent text-primary hover:bg-accent"
                >
                    <X aria-hidden />
                </Button>
            )}
        </div>
    );
}
