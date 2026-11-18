import type { LandingTool } from '@/components/landing/landing-tools';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import { enterDelay } from '@/lib/enter-delay';
import { cn } from '@/lib/utils';

/** The tool's narrated film, whole: nothing sits over it. It enters after the headline, like the landing's. */
export default function ToolPageStage({
    tool,
}: {
    tool: LandingTool | undefined;
}) {
    return (
        <figure className={cn('m-0 grid enter-after gap-3', enterDelay(0.25))}>
            {tool === undefined ? null : <ToolFilmPlayer film={tool.film} />}
            <figcaption className="text-right text-caption text-muted-foreground">
                Datos de ejemplo de una empresa ficticia
            </figcaption>
        </figure>
    );
}
