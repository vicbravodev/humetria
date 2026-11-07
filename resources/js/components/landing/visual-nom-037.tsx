import { m } from 'motion/react';
import { Check } from 'lucide-react';
import { ENTER, ONCE } from '@/components/landing/landing-motion';

const places = [
    { initials: 'AL', who: 'Ana López', where: 'Coyoacán, CDMX' },
    { initials: 'JR', who: 'Jorge Ruiz', where: 'Zapopan, Jal.' },
    { initials: 'MS', who: 'María Sánchez', where: 'Querétaro, Qro.' },
] as const;

/** Each person's place of telework, its checklist validated one after the other. */
export default function VisualNom037() {
    return (
        <ul className="flex flex-col divide-y card">
            {places.map((place, index) => (
                <li
                    key={place.who}
                    className="flex items-center gap-3 px-4 py-3"
                >
                    <span
                        aria-hidden
                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent text-sm font-semibold text-accent-foreground"
                    >
                        {place.initials}
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium">
                            {place.who}
                        </span>
                        <span className="block text-sm text-muted-foreground">
                            {place.where}
                        </span>
                    </span>
                    <m.span
                        initial={{ opacity: 0, transform: 'scale(0.9)' }}
                        whileInView={{ opacity: 1, transform: 'scale(1)' }}
                        viewport={ONCE}
                        transition={{ ...ENTER, delay: 0.3 + index * 0.25 }}
                        className="inline-flex items-center gap-1 rounded-md bg-accent px-2 py-0.5 text-sm font-medium text-accent-foreground"
                    >
                        <Check aria-hidden className="size-3.5 text-primary" />
                        Validada
                    </m.span>
                </li>
            ))}
        </ul>
    );
}
