import { Lightbulb } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import type { CommitmentSuggestion } from '@/types';

/** What could be done for the themes to act on first (a fixed catalog). It only suggests: nothing here is saved. */
export default function SuggestedActions({
    suggestions,
}: {
    suggestions: CommitmentSuggestion[];
}) {
    if (suggestions.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-5 card p-5 sm:p-6">
            <div className="flex items-start gap-3">
                <IconTile icon={Lightbulb} />
                <div>
                    <h2 className="text-lg font-semibold">
                        Ideas para empezar
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Para los temas donde actuar primero. Elige lo que sí
                        puedas cumplir y escríbelo como compromiso con
                        responsable y fecha.
                    </p>
                </div>
            </div>
            <div className="grid gap-5 lg:grid-cols-2">
                {suggestions.map((suggestion) => (
                    <div key={suggestion.driver}>
                        <h3 className="font-medium">
                            {suggestion.label}
                            <span className="font-normal text-muted-foreground">
                                {' · '}
                                Prioridad {suggestion.levelLabel.toLowerCase()}
                            </span>
                        </h3>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-muted-foreground">
                            {suggestion.actions.map((action) => (
                                <li key={action}>{action}</li>
                            ))}
                        </ul>
                    </div>
                ))}
            </div>
        </section>
    );
}
