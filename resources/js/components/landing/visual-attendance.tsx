import { m } from 'motion/react';
import { MapPin } from 'lucide-react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

const marks = [
    { initials: 'AL', who: 'Ana López', mark: 'Entrada 08:58' },
    { initials: 'JR', who: 'Jorge Ruiz', mark: 'Salida a comer 14:02' },
    { initials: 'MS', who: 'María Sánchez', mark: 'Salida 18:07' },
] as const;

/** Each person's mark, stamped by the server and checked against the work center's zone. */
export default function VisualAttendance() {
    return (
        <ul className="flex flex-col divide-y card">
            {marks.map((item, index) => (
                <li
                    key={item.who}
                    className="flex items-center gap-3 px-4 py-3"
                >
                    <span
                        aria-hidden
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground"
                    >
                        {item.initials}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">
                            {item.who}
                        </span>
                        <span className="block text-sm text-muted-foreground tabular-nums">
                            {item.mark}
                        </span>
                    </span>
                    <m.span
                        initial={{ opacity: 0, transform: 'scale(0.9)' }}
                        whileInView={{ opacity: 1, transform: 'scale(1)' }}
                        viewport={ONCE}
                        transition={{ ...ENTER, delay: 0.3 + index * 0.25 }}
                        className="inline-flex items-center gap-1 rounded-md bg-accent px-2 py-0.5 text-sm font-medium text-accent-foreground"
                    >
                        <MapPin aria-hidden className="size-3.5 text-primary" />
                        En zona
                    </m.span>
                </li>
            ))}
        </ul>
    );
}
