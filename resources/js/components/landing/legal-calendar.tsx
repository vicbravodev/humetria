import { WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';

const MILESTONES = [
    { date: 'DOF, 23 oct 2018', name: 'NOM-035', source: 'Riesgo psicosocial' },
    { date: '1 ene 2023', name: 'Vacaciones dignas', source: 'LFT, art. 76' },
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
                    {MILESTONES.map((milestone) => (
                        <li
                            key={milestone.name}
                            aria-current={
                                milestone.next === true ? 'step' : undefined
                            }
                            className={cn(
                                'grid content-start gap-1 border-l px-4 py-3',
                                milestone.next === true &&
                                    'rounded-xl border-transparent bg-primary text-primary-foreground',
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
                            <span className="font-semibold">
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
