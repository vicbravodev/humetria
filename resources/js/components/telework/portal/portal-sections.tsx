import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { portalSectionIcon } from '@/lib/telework/portal';
import type { PortalSection } from '@/types';

/** The tools the person has, one big card each (at least 56 px tall, for a thumb), with what waits for them in it. */
export default function PortalSections({
    sections,
}: {
    sections: PortalSection[];
}) {
    if (sections.length === 0) {
        return null;
    }

    return (
        <ul className="grid gap-3" aria-label="Sus herramientas">
            {sections.map((section) => (
                <li key={section.key}>
                    <Link
                        href={section.href}
                        className="flex min-h-14 items-center gap-3.5 card p-4 hover:bg-accent/50"
                    >
                        <IconTile icon={portalSectionIcon(section.key)} />
                        <span className="flex min-w-0 flex-1 flex-col gap-1">
                            <span className="text-base font-semibold">
                                {section.label}
                            </span>
                            <span className="text-base text-muted-foreground">
                                {section.description}
                            </span>
                        </span>
                        {section.pending !== null && (
                            <span className="rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-primary-foreground">
                                {section.pending}
                                <span className="sr-only"> pendientes</span>
                            </span>
                        )}
                        <ChevronRight
                            aria-hidden="true"
                            className="size-5 shrink-0 text-muted-foreground"
                        />
                    </Link>
                </li>
            ))}
        </ul>
    );
}
