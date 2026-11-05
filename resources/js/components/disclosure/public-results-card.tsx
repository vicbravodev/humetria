import PublicResultsBlock from '@/components/disclosure/public-results';
import type { PublicResults } from '@/types';

/**
 * «Resultados de la evaluación» (board V4-Pagina-Personas, num. 7.8): in plain words, who answered and what most needs
 * attention, for the whole center and above the minimum (criterio C-54); the figures that RH sees fold under them. Below the
 * minimum, only that very few answered, without the count.
 */
export default function PublicResultsCard({
    results,
}: {
    results: PublicResults;
}) {
    const { published } = results;

    if (published === null) {
        return null;
    }

    return (
        <section
            aria-labelledby="results"
            className="flex flex-col gap-3 card p-5"
        >
            <h2 id="results" className="text-lg leading-7 font-semibold">
                Resultados de la evaluación
            </h2>
            {published.state === 'below_minimum' ? (
                <p className="text-sm text-muted-foreground">
                    Evaluación cerrada el {published.closedOn}. Contestaron muy
                    pocas personas: para proteger su confidencialidad, no se
                    muestran cifras.
                </p>
            ) : (
                <>
                    <p className="text-sm text-muted-foreground">
                        Evaluación cerrada el {published.closedOn} ·{' '}
                        {published.answered === 1
                            ? 'contestó 1 persona'
                            : `contestaron ${published.answered} personas`}
                    </p>
                    {published.themes.length > 0 && (
                        <div className="flex flex-col gap-1 text-sm">
                            <p className="font-medium">
                                Lo que más atención necesita
                            </p>
                            <ol className="ml-5 list-decimal">
                                {published.themes.map((theme) => (
                                    <li key={theme}>{theme}</li>
                                ))}
                            </ol>
                        </div>
                    )}
                    <details>
                        <summary className="touch-hit w-fit cursor-pointer text-sm font-medium text-primary">
                            Ver todas las cifras
                        </summary>
                        <div className="pt-3">
                            <PublicResultsBlock results={results} bare />
                        </div>
                    </details>
                </>
            )}
        </section>
    );
}
