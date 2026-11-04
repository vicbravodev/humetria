import { CircleCheck, CircleDashed } from 'lucide-react';
import type { ChecklistItem } from '@/types';

/** What the channel needs to serve, and what is still missing: the four things a company sets up before opening it. */
export default function ChannelChecklist({
    items,
}: {
    items: ChecklistItem[];
}) {
    return (
        <ul aria-label="Lo que necesita el canal" className="divide-y card">
            {items.map((item) => (
                <li
                    key={item.key}
                    className="flex items-center gap-3 px-5 py-3.5"
                >
                    {item.done ? (
                        <CircleCheck
                            aria-hidden
                            className="size-5 shrink-0 text-primary"
                        />
                    ) : (
                        <CircleDashed
                            aria-hidden
                            className="size-5 shrink-0 text-muted-foreground"
                        />
                    )}
                    <span className={item.done ? '' : 'font-medium'}>
                        {item.label}
                    </span>
                    <span className="ml-auto text-sm text-muted-foreground">
                        {item.done ? 'Listo' : 'Falta'}
                    </span>
                </li>
            ))}
        </ul>
    );
}
