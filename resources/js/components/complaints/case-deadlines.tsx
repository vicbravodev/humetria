import CaseDeadline from '@/components/complaints/case-deadline';
import type { CaseDeadlines } from '@/types';

/** «Plazos que te fijaste» (board V4-Quejas-Caso): the acknowledgement and the answer, which the company set and the norm does not. */
export default function CaseDeadlinesCard({
    deadlines,
    received,
    today,
}: {
    deadlines: CaseDeadlines;
    received: string;
    today: string;
}) {
    return (
        <section
            aria-labelledby="case-deadlines"
            className="flex flex-col gap-4 card p-6"
        >
            <h2 id="case-deadlines" className="text-lg font-semibold">
                Plazos que te fijaste
            </h2>
            <CaseDeadline
                label="Acuse de recibo"
                due={deadlines.acknowledgeBy}
                met={deadlines.acknowledgedOn !== null}
                received={received}
                today={today}
            />
            <CaseDeadline
                label="Respuesta"
                due={deadlines.respondBy}
                met={deadlines.closedOn !== null}
                received={received}
                today={today}
            />
        </section>
    );
}
