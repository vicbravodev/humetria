import { Check } from 'lucide-react';

const results = [
    'Resultados por grupo',
    'Plan de acción',
    'Evidencia para la STPS',
];

/** Step 3: what you receive, ready for the file. */
export default function ResultsVisual() {
    return (
        <div className="grid justify-items-start gap-3.5">
            <ul className="grid w-full gap-2.5">
                {results.map((result) => (
                    <li
                        key={result}
                        className="flex items-center gap-3 rounded-xl border bg-background px-3.5 py-3 text-meta"
                    >
                        <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                            <Check aria-hidden className="size-3.5" />
                        </span>
                        {result}
                    </li>
                ))}
            </ul>
            <span className="inline-flex items-center gap-2 rounded-full bg-risk-bajo-bg px-3.5 py-2 text-meta font-semibold text-risk-bajo-fg">
                <Check aria-hidden className="size-4" />
                Expediente listo
            </span>
        </div>
    );
}
