import { ChevronRight } from 'lucide-react';
import { LEAD, WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { attendance } from '@/routes/tools';
import type { GuideContent } from '@/types';

const TRAIL = [
    { label: 'Inicio', href: home().url },
    { label: 'Registro de jornada', href: attendance().url },
];

/** Where the guide sits, its title, what it covers and the day it was last checked against the law. */
export default function GuideHero({ guide }: { guide: GuideContent }) {
    const updated = new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'long',
        timeZone: 'UTC',
    }).format(new Date(`${guide.updated}T12:00:00Z`));

    return (
        <section
            id="inicio"
            aria-labelledby="guide-title"
            className={cn(WRAP, 'grid max-w-[820px] gap-6 pt-8 pb-12 sm:pt-10')}
        >
            <nav aria-label="Migas de pan">
                <ol className="flex flex-wrap items-center gap-1.5 text-meta text-muted-foreground">
                    {TRAIL.map((step) => (
                        <li
                            key={step.href}
                            className="flex items-center gap-1.5"
                        >
                            <a
                                href={step.href}
                                className="inline-flex min-h-11 items-center rounded-sm hover:text-foreground"
                            >
                                {step.label}
                            </a>
                            <ChevronRight aria-hidden className="size-3.5" />
                        </li>
                    ))}
                    <li aria-current="page" className="text-foreground">
                        Guía 2027
                    </li>
                </ol>
            </nav>
            <h1
                id="guide-title"
                className="text-[34px] leading-[1.08] font-semibold tracking-[-0.03em] text-balance sm:text-5xl"
            >
                {guide.h1}
            </h1>
            <p className={LEAD}>{guide.lead}</p>
            <p className="text-caption text-muted-foreground">
                Revisada contra el texto de la ley el {updated}. No sustituye la
                asesoría de un especialista.
            </p>
        </section>
    );
}
