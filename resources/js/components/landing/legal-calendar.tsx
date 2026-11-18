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

/** What the law already asks and what comes next, in order, with the registro de jornada of 2027 marked. */
export default function LegalCalendar() {
    return (
        <section
            aria-labelledby="calendario-title"
            data-test="legal-calendar"
            className="border-b bg-band"
        >
            <div className={cn(WRAP, 'grid gap-4 py-8')}>
                <h2 id="calendario-title" className="text-lg font-semibold">
                    Lo que la ley ya pide, y lo que viene
                </h2>
                <ol className="grid grid-cols-2 gap-y-2 md:grid-cols-3 xl:grid-cols-6">
                    {MILESTONES.map((milestone, index) => (
                        <li
                            key={milestone.name}
                            data-reveal="idle"
                            aria-current={
                                milestone.next === true ? 'step' : undefined
                            }
                            className={cn(
                                'grid content-start gap-1 border-l border-family px-4 py-3 reveal-slide-8',
                                FAMILY_CLASS[milestone.family ?? 'compliance'],
                                `enter-delay-${index * 60}`,
                                milestone.next === true &&
                                    'rounded-xl border-transparent bg-family text-background',
                            )}
                        >
                            <span
                                className={cn(
                                    'text-caption',
                                    milestone.next !== true &&
                                        'text-muted-foreground',
                                )}
                            >
                                {milestone.date}
                            </span>
                            <span
                                className={cn(
                                    'font-semibold',
                                    milestone.next !== true && 'text-family',
                                )}
                            >
                                {milestone.name}
                            </span>
                            <span
                                className={cn(
                                    'text-caption',
                                    milestone.next !== true &&
                                        'text-muted-foreground',
                                )}
                            >
                                {milestone.source}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>
        </section>
    );
}
