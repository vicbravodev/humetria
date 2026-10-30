import { historyItems } from '@/lib/evaluation';
import { cn } from '@/lib/utils';
import type { CampaignDetail } from '@/types';

/** What happened to the evaluation, one dated step after another, and by when the next one is due (pantalla Eval-Cerrada). */
export default function CampaignHistory({
    campaign,
}: {
    campaign: CampaignDetail;
}) {
    return (
        <section
            aria-labelledby="history-title"
            className="rounded-xl border bg-card p-6"
        >
            <h2
                id="history-title"
                className="mb-[18px] text-lg leading-7 font-semibold"
            >
                Historial
            </h2>
            <ol>
                {historyItems(campaign).map((item) => (
                    <li
                        key={item.title}
                        className="relative flex gap-3.5 pb-[18px] before:absolute before:top-5 before:-bottom-1.5 before:left-[5px] before:w-0.5 before:bg-border last:before:hidden"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'mt-[5px] size-3 shrink-0 rounded-full border-2',
                                item.done
                                    ? 'border-primary bg-primary'
                                    : 'border-input bg-card',
                            )}
                        />
                        <span className="flex flex-col">
                            <span className="text-sm font-medium">
                                {item.title}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {item.date}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}
