import { m } from 'motion/react';
import { EyeOff, Inbox } from 'lucide-react';
import { EASE_OUT, ENTER, ONCE } from '@/components/landing/landing-motion';

/** A complaint against the plant manager goes to HR's director: the person it names never sees it. */
export default function VisualComplaints() {
    return (
        <div className="flex flex-col items-stretch card p-4">
            <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                <span className="font-medium">Queja QJ-4F82</span>{' '}
                <span className="text-muted-foreground">
                    contra la Gerencia de planta
                </span>
            </p>
            <m.span
                aria-hidden
                initial={{ transform: 'scaleY(0)' }}
                whileInView={{ transform: 'scaleY(1)' }}
                viewport={ONCE}
                transition={{ duration: 0.6, ease: EASE_OUT, delay: 0.2 }}
                className="mx-auto block h-6 w-px origin-top bg-primary"
            />
            <div className="grid grid-cols-2 gap-3">
                <m.p
                    initial={{ opacity: 0, transform: 'translateY(8px)' }}
                    whileInView={{ opacity: 1, transform: 'translateY(0px)' }}
                    viewport={ONCE}
                    transition={{ ...ENTER, delay: 0.6 }}
                    className="flex flex-col gap-1 rounded-lg border border-primary px-3 py-2.5"
                >
                    <span className="flex items-center gap-1.5 text-sm font-medium text-primary">
                        <Inbox aria-hidden className="size-4" />
                        La atiende
                    </span>
                    <span className="text-sm">Dirección de RH</span>
                </m.p>
                <p className="flex flex-col gap-1 rounded-lg border border-dashed px-3 py-2.5 text-muted-foreground">
                    <span className="flex items-center gap-1.5 text-sm font-medium">
                        <EyeOff aria-hidden className="size-4" />
                        No la ve
                    </span>
                    <span className="text-sm">Gerencia de planta</span>
                </p>
            </div>
        </div>
    );
}
