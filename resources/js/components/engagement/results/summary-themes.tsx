import { Info } from 'lucide-react';
import type { ReactNode } from 'react';
import ThemeList from '@/components/engagement/results/theme-list';
import { mostMentions } from '@/lib/engagement/results';
import type { CommentSummary } from '@/types';

const WITHOUT_THEMES: Record<CommentSummary['status'], string> = {
    pending: 'El resumen de comentarios se está preparando.',
    withheld:
        'No hay suficientes comentarios para resumirlos sin señalar a nadie.',
    ai_off: 'La IA está apagada para esta empresa, así que no hay resumen.',
    failed: 'No se pudo hacer el resumen de los comentarios.',
    ready: 'No se pudo hacer el resumen de los comentarios.',
};

/**
 * The summary of a group of comments made with AI when the survey closed: the themes and how many comments mention each,
 * never a comment. When there is none, only the reason; `action` is what can be done about it (retry).
 */
export default function SummaryThemes({
    summary,
    action,
}: {
    summary: CommentSummary;
    action?: ReactNode;
}) {
    return (
        <section
            aria-labelledby="summary-themes"
            className="flex flex-col gap-4 card p-5 sm:p-6"
        >
            <h2 id="summary-themes" className="text-xl font-semibold">
                Resumen de los comentarios
            </h2>
            {summary.themes === null ? (
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-muted-foreground">
                        {WITHOUT_THEMES[summary.status]}
                    </p>
                    {action}
                </div>
            ) : (
                <>
                    <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                        <Info aria-hidden className="size-4 shrink-0" />
                        Resumen hecho con IA al cerrar la encuesta. Son temas,
                        no comentarios de nadie.
                    </p>
                    <div className="grid gap-6 md:grid-cols-2 md:gap-x-10">
                        <ThemeList
                            heading="Lo que valoran"
                            themes={summary.themes.valued}
                            max={mostMentions(summary.themes)}
                        />
                        <ThemeList
                            heading="Lo que cambiarían"
                            themes={summary.themes.change}
                            max={mostMentions(summary.themes)}
                        />
                    </div>
                </>
            )}
        </section>
    );
}
