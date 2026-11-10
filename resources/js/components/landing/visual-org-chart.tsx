import { m } from 'motion/react';
import { EASE_OUT, ENTER, ONCE } from '@/components/landing/landing-motion';

const reports = [
    { name: 'Nómina', who: 'Laura Díaz', people: 4 },
    { name: 'Reclutamiento', who: 'Iván Torres', people: 3 },
] as const;

/** A director and the two managers who report to her, drawn from who reports to whom. */
export default function VisualOrgChart() {
    return (
        <div className="flex flex-col items-stretch card p-4">
            <p className="rounded-lg bg-muted px-3 py-2 text-center text-sm">
                <span className="block font-medium">Dirección de RH</span>
                <span className="text-muted-foreground">Sofía Herrera</span>
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
                {reports.map((report, index) => (
                    <m.p
                        key={report.name}
                        initial={{ opacity: 0, transform: 'translateY(8px)' }}
                        whileInView={{
                            opacity: 1,
                            transform: 'translateY(0px)',
                        }}
                        viewport={ONCE}
                        transition={{ ...ENTER, delay: 0.6 + index * 0.15 }}
                        className="flex flex-col gap-0.5 rounded-lg border border-primary px-3 py-2.5 text-sm"
                    >
                        <span className="font-medium text-primary">
                            {report.name}
                        </span>
                        <span>{report.who}</span>
                        <span className="text-muted-foreground">
                            {report.people} personas
                        </span>
                    </m.p>
                ))}
            </div>
        </div>
    );
}
