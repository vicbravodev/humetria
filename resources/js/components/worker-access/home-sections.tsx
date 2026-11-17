import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { sectionIcon } from '@/lib/worker-access/sections';
import type { WorkerSection } from '@/types';

/** The tools the person has, one big card each (at least 64 px tall, for a thumb), one line each, with what waits in it. */
export default function HomeSections({
    sections,
    label,
}: {
    sections: WorkerSection[];
    /** What the list is, said as its heading. */
    label: string;
}) {
    if (sections.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-caption font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </h2>
            <ul className="flex flex-col gap-3">
                {sections.map((section) => (
                    <li key={section.key}>
                        <Link
                            href={section.href}
                            className="flex min-h-16 items-center gap-3.5 card p-4 transition-[background-color,scale] duration-150 ease-out hover:bg-accent/50 active:scale-[0.99]"
                        >
                            <IconTile icon={sectionIcon(section.key)} />
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="text-base font-semibold">
                                    {section.label}
                                </span>
                                <span className="truncate text-base text-muted-foreground">
                                    {section.description}
                                </span>
                            </span>
                            {section.pending !== null &&
                                section.pending > 0 && (
                                    <span className="rounded-full bg-primary px-2.5 py-0.5 text-sm font-semibold text-primary-foreground">
                                        {section.pending}
                                        <span className="sr-only">
                                            {section.pending === 1
                                                ? ' pendiente'
                                                : ' pendientes'}
                                        </span>
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
        </section>
    );
}
