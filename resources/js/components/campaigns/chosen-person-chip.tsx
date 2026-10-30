import { X } from 'lucide-react';
import Initials from '@/components/humetria/initials';

/** One chosen person in the people field (pantalla Guia-I-Acontecimiento): initials, name and the way to take them out. */
export default function ChosenPersonChip({
    name,
    onRemove,
}: {
    name: string;
    onRemove: () => void;
}) {
    return (
        <li className="inline-flex h-8 items-center gap-1.5 rounded-full bg-accent pr-1.5 pl-1 text-[13px] leading-[18px] font-medium">
            <Initials name={name} className="size-6 bg-card text-[11px]" />
            {name}
            <button
                type="button"
                aria-label={`Quitar a ${name}`}
                onClick={onRemove}
                className="relative inline-flex size-[22px] items-center justify-center rounded-full text-muted-foreground transition-colors duration-150 ease-out before:absolute before:-inset-1 before:content-[''] hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <X aria-hidden className="size-3.5" />
            </button>
        </li>
    );
}
