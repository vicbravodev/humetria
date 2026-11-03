import HumiMarkdownInlines from '@/components/assistant/humi-markdown-inlines';
import { leadOf } from '@/lib/assistant/markdown';
import type { ListRow } from '@/lib/assistant/tool-cards';

/** A criterion of a norm in a card: which one it is and where it comes from, its title and the lead of what it says. */
export default function HumiCriterionRow({
    row,
}: {
    row: ListRow & { ref: string };
}) {
    const lead = leadOf(row.detail ?? '');

    return (
        <>
            <span className="flex flex-wrap items-center gap-x-2 gap-y-1 text-caption text-muted-foreground">
                <span className="rounded bg-muted px-1.5 py-0.5 font-medium text-foreground">
                    {row.ref}
                </span>
                {row.source !== null && <span>{row.source}</span>}
            </span>
            <span className="font-medium">{row.title}</span>
            {lead.length > 0 && (
                <p className="line-clamp-3 text-sm text-muted-foreground">
                    <HumiMarkdownInlines nodes={lead} />
                </p>
            )}
        </>
    );
}
