import {
    FAMILIES,
    FAMILY_CLASS,
    familyTools,
} from '@/components/landing/landing-families';
import { WRAP } from '@/components/landing/landing-layout';
import { toolPageUrl } from '@/components/landing/tool-page-links';
import { cn } from '@/lib/utils';

const linkClass =
    'inline-flex min-h-11 items-center rounded-md text-meta text-muted-foreground transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** The nine tools at the foot of every public page, by family and in its color. */
export default function FooterTools() {
    return (
        <nav
            aria-label="Herramientas"
            className={cn(WRAP, 'grid gap-6 border-b pb-8 sm:grid-cols-3')}
        >
            {FAMILIES.map((family) => (
                <div
                    key={family.key}
                    className={cn(
                        'grid content-start gap-1',
                        FAMILY_CLASS[family.key],
                    )}
                >
                    <p className="pb-1 text-meta font-semibold text-family">
                        {family.name}
                    </p>
                    {familyTools(family).map((tool) => (
                        <a
                            key={tool.key}
                            href={toolPageUrl(tool.key)}
                            className={linkClass}
                        >
                            {tool.name}
                        </a>
                    ))}
                </div>
            ))}
        </nav>
    );
}
