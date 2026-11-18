import {
    FAMILY_CLASS,
    type FamilyKey,
} from '@/components/landing/landing-families';
import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

type Milestone = {
    date: string;
    name: string;
    source: string;
    /** The family of tools it belongs to, for its color; `compliance` unless said. */
    family?: FamilyKey;
    next?: boolean;
};

const MILESTONES: readonly Milestone[] = [
    { date: 'DOF, 23 oct 2018', name: 'NOM-035', source: 'Riesgo psicosocial' },
    {
        date: '1 ene 2023',
        name: 'Vacaciones dignas',
        source: 'LFT, art. 76',
        family: 'operations',
    },
    { date: '5 dic 2023', name: 'NOM-037', source: 'Teletrabajo' },
    { date: '17 jun 2025', name: 'Ley Silla', source: 'LFT, art. 132, fr. V' },
    {
        date: '1 de enero de 2027',
        name: 'Registro de jornada',
        source: 'LFT, art. 132, fr. XXXIV',
        next: true,
    },
    {
        date: 'De 2027 a 2030',
        name: 'Jornada de 46 a 40 h',
        source: 'LFT, art. 59',
    },
];

/** What the law already asks and what comes next, on a timeline: a dot per rule in its family's color, the next one pulsing. */
export default function LegalCalendar() {
    return (
        <section
            aria-labelledby="calendario-title"
            data-test="legal-calendar"
            className="border-b bg-band"
        >
            <div className={cn(WRAP, 'grid gap-6 py-10')}>
                <h2 id="calendario-title" className="text-lg font-semibold">
                    Lo que la ley ya pide, y lo que viene
                </h2>
                <ol className="relative grid grid-cols-2 gap-x-6 gap-y-8 md:grid-cols-3 xl:grid-cols-6 xl:before:absolute xl:before:inset-x-0 xl:before:top-[7px] xl:before:h-px xl:before:bg-border">
                    {MILESTONES.map((milestone, index) => (
                        <li
                            key={milestone.name}
                            data-reveal="idle"
                            aria-current={
                                milestone.next === true ? 'step' : undefined
                            }
                            className={cn(
                                'relative grid content-start gap-1 reveal-rise',
                                FAMILY_CLASS[milestone.family ?? 'compliance'],
                                `enter-delay-${index * 70}`,
                            )}
                        >
                            <span
                                aria-hidden
                                className="relative mb-2 flex size-[15px]"
                            >
                                {milestone.next === true && (
                                    <span className="absolute inset-0 rounded-full bg-family opacity-60 motion-safe:animate-ping" />
                                )}
                                <span className="relative size-[15px] rounded-full border-[3px] border-band bg-family" />
                            </span>
                            <span className="flex flex-wrap items-center gap-2 text-caption text-muted-foreground">
                                {milestone.date}
                                {milestone.next === true && (
                                    <span className="rounded-full bg-family px-2 py-0.5 font-semibold text-background">
                                        Próximo
                                    </span>
                                )}
                            </span>
                            <span className="font-semibold text-family">
                                {milestone.name}
                            </span>
                            <span className="text-caption text-muted-foreground">
                                {milestone.source}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
