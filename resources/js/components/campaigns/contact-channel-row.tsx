import { Check } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { peopleText } from '@/lib/evaluation';

/** One channel the draft reaches people through, with how many it reaches. */
export default function ContactChannelRow({
    icon: Icon,
    label,
    people,
}: {
    icon: LucideIcon;
    label: string;
    people: number;
}) {
    return (
        <div className="flex min-h-13 items-center gap-3 rounded-[10px] border-2 border-primary bg-accent px-3.5 py-2.5">
            <span className="inline-flex size-5 items-center justify-center rounded-[5px] bg-primary text-primary-foreground">
                <Check
                    aria-hidden="true"
                    className="size-3.5"
                    strokeWidth={3}
                />
            </span>
            <Icon
                aria-hidden="true"
                className="size-[18px] text-muted-foreground"
            />
            <span className="flex flex-col">
                <span className="text-sm font-medium">{label}</span>
                <span className="text-xs text-muted-foreground">
                    {peopleText(people)}
                </span>
            </span>
        </div>
    );
}
