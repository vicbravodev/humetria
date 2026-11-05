import PillChoice from '@/components/humetria/pill-choice';
import { SECTORS } from '@/lib/applicability';
import type { SectorValue } from '@/lib/applicability';
import type {
    CenterAnswers,
    OnboardingCenter as Center,
} from '@/lib/onboarding';

const YES_NO: { value: 'yes' | 'no'; label: string }[] = [
    { value: 'yes', label: 'Sí' },
    { value: 'no', label: 'No' },
];

const choice = (answer: boolean | null) =>
    answer === null ? null : answer ? 'yes' : 'no';

/** One center of the setup (board V3-Onboarding): who it is, and its two questions, with the sector when someone stands. */
export default function OnboardingCenter({
    center,
    answers,
    onAnswer,
}: {
    center: Center;
    answers: CenterAnswers;
    onAnswer: (answers: Partial<CenterAnswers>) => void;
}) {
    return (
        <section
            aria-labelledby={`center-${center.id}`}
            className="flex flex-col gap-5 card p-5 md:p-6"
        >
            <div>
                <h2
                    id={`center-${center.id}`}
                    className="text-xl font-semibold"
                >
                    {center.name}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {center.activity} · {center.people} personas registradas
                </p>
            </div>
            <PillChoice
                name={`telework-${center.id}`}
                legend="¿Alguien trabaja desde su casa más del 40 % de su tiempo?"
                options={YES_NO}
                value={choice(answers.hasTeleworkers)}
                onChange={(value) =>
                    onAnswer({ hasTeleworkers: value === 'yes' })
                }
            />
            <PillChoice
                name={`standing-${center.id}`}
                legend="¿Alguien trabaja de pie la mayor parte de su jornada?"
                options={YES_NO}
                value={choice(answers.standing)}
                onChange={(value) =>
                    onAnswer(
                        value === 'yes'
                            ? { standing: true }
                            : { standing: false, sector: null },
                    )
                }
            />
            {answers.standing === true && (
                <PillChoice
                    name={`sector-${center.id}`}
                    legend="¿A qué se dedica el centro?"
                    options={SECTORS}
                    value={answers.sector}
                    onChange={(sector: SectorValue) => onAnswer({ sector })}
                />
            )}
        </section>
    );
}
