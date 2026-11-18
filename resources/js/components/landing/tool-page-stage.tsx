import type { LandingTool } from '@/components/landing/landing-tools';
import ToolFilmPlayer from '@/components/landing/tool-film-player';
import { cn } from '@/lib/utils';
import type { ToolPageContent } from '@/types';

type Props = { page: ToolPageContent; tool: LandingTool | undefined };

/** The tool's narrated film and, over its corner, the phone screen of the person who uses it. */
export default function ToolPageStage({ page, tool }: Props) {
    return (
        <figure
            className={cn(
                'relative m-0 grid gap-3',
                page.heroPhone !== null && 'sm:pt-10 sm:pl-14',
            )}
        >
            {tool === undefined ? null : <ToolFilmPlayer film={tool.film} />}
            {page.heroPhone === null ? null : (
                <img
                    src={`/images/tools/${page.heroPhone}.webp`}
                    alt={page.phoneAlt ?? ''}
                    width={585}
                    height={1266}
                    loading="lazy"
                    className="absolute top-0 left-0 hidden w-36 rounded-[22px] border-[5px] border-foreground bg-foreground shadow-raised sm:block"
                />
            )}
            <figcaption className="text-right text-caption text-muted-foreground">
                Datos de ejemplo de una empresa ficticia
            </figcaption>
        </figure>
    );
}
