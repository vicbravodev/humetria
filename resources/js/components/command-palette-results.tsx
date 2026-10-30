import { CornerDownLeft } from 'lucide-react';
import { groupCommands } from '@/lib/commands';
import type { PaletteCommand } from '@/lib/palette-commands';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    results: PaletteCommand[];
    active: number;
    onPoint: (index: number) => void;
    onRun: (index: number) => void;
};

/**
 * The listbox under the palette's search field, under a heading per group (pantalla Paleta); the active option
 * follows the arrows and the pointer, and shows the key that opens it.
 */
export function CommandPaletteResults({
    id,
    results,
    active,
    onPoint,
    onRun,
}: Props) {
    if (results.length === 0) {
        return (
            <p className="px-4 py-10 text-center text-muted-foreground">
                Sin resultados
            </p>
        );
    }

    return (
        <div
            id={id}
            role="listbox"
            aria-label="Resultados"
            className="flex max-h-[min(420px,60vh)] flex-col gap-1 overflow-y-auto px-2 pt-1.5 pb-2.5"
        >
            {groupCommands(results).map(({ group, items }) => (
                <div
                    key={group}
                    role="group"
                    aria-labelledby={`${id}-${group}`}
                    className="flex flex-col gap-0.5"
                >
                    <span
                        id={`${id}-${group}`}
                        className="px-2.5 pt-2 pb-1 text-[13px] leading-[18px] font-medium text-muted-foreground"
                    >
                        {group}
                    </span>
                    {items.map(
                        ({
                            command: { id: key, label, icon: Icon },
                            index,
                        }) => (
                            <div
                                key={key}
                                id={`${id}-${index}`}
                                role="option"
                                aria-selected={index === active}
                                onPointerMove={() => onPoint(index)}
                                onClick={() => onRun(index)}
                                className={cn(
                                    'flex min-h-12 cursor-default items-center gap-3 rounded-md px-2.5 py-1.5',
                                    index === active && 'bg-accent',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground',
                                        index === active &&
                                            'bg-card text-primary',
                                    )}
                                >
                                    <Icon aria-hidden className="size-5" />
                                </span>
                                <span
                                    data-slot="command-label"
                                    className="min-w-0 flex-1 truncate text-sm font-medium"
                                >
                                    {label}
                                </span>
                                {index === active && (
                                    <kbd
                                        aria-hidden
                                        className="flex h-[18px] items-center rounded-[5px] border bg-muted px-1.5 text-muted-foreground"
                                    >
                                        <CornerDownLeft className="size-3" />
                                    </kbd>
                                )}
                            </div>
                        ),
                    )}
                </div>
            ))}
        </div>
    );
}
