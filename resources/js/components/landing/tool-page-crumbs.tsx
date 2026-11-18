import { ChevronRight } from 'lucide-react';
import { home } from '@/routes';
import type { ToolPageContent } from '@/types';

/** Where the page sits: the landing, the family and the tool. */
export default function ToolPageCrumbs({ page }: { page: ToolPageContent }) {
    return (
        <nav aria-label="Migas de pan">
            <ol className="flex flex-wrap items-center gap-1 text-meta text-muted-foreground">
                <li>
                    <a
                        href={home().url}
                        className="inline-flex min-h-11 items-center rounded-sm hover:text-foreground"
                    >
                        Inicio
                    </a>
                </li>
                <li aria-hidden>
                    <ChevronRight className="size-3.5" />
                </li>
                <li>{page.family}</li>
                <li aria-hidden>
                    <ChevronRight className="size-3.5" />
                </li>
                <li aria-current="page" className="text-foreground">
                    {page.name}
                </li>
            </ol>
        </nav>
    );
}
