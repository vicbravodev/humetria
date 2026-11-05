import { inputsLine } from '@/lib/seating/route';
import type { PeopleInputs } from '@/lib/seating/route';

/** «Las personas también participan» (board V2-Ruta-Silla): how many answered their two questions in the portal. */
export default function RouteInputsCard({ inputs }: { inputs: PeopleInputs }) {
    return (
        <section
            aria-labelledby="inputs"
            className="flex flex-col gap-3 rounded-2xl bg-accent p-[22px]"
        >
            <h2 id="inputs" className="text-sm font-semibold">
                Las personas también participan
            </h2>
            <p className="text-sm">{inputsLine(inputs)}</p>
        </section>
    );
}
