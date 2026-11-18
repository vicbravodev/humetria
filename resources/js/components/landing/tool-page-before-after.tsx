import { SECTION, TITLE, WRAP } from '@/components/landing/landing-layout';
import { cn } from '@/lib/utils';
import type { ToolPageContent } from '@/types';

/** What changes, row by row: how it is done today and how it is done with Humetría. */
export default function ToolPageBeforeAfter({
    page,
}: {
    page: ToolPageContent;
}) {
    return (
        <section
            aria-labelledby="antes-title"
            className={cn('border-y bg-band', SECTION)}
        >
            <div className={cn(WRAP, 'grid gap-8')}>
                <h2 id="antes-title" className={TITLE}>
                    {page.beforeAfterTitle}
                </h2>
                <div
                    role="region"
                    aria-labelledby="antes-title"
                    tabIndex={0}
                    className="overflow-x-auto rounded-2xl border bg-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <table className="w-full min-w-[560px] border-collapse text-left">
                        <thead>
                            <tr className="bg-muted text-meta">
                                <th
                                    scope="col"
                                    className="px-6 py-3.5 font-semibold text-muted-foreground"
                                >
                                    Hoy
                                </th>
                                <th
                                    scope="col"
                                    className="px-6 py-3.5 font-semibold text-primary"
                                >
                                    Con Humetría
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {page.beforeAfter.map((row) => (
                                <tr key={row.after} className="border-t">
                                    <td className="w-1/2 px-6 py-4 leading-snug text-muted-foreground">
                                        {row.before}
                                    </td>
                                    <td className="px-6 py-4 leading-snug font-semibold">
                                        {row.after}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    );
}
