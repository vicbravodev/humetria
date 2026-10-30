import { Link } from '@inertiajs/react';
import { FileText, HeartHandshake, Play } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { nextStepTexts, pendingText } from '@/lib/dashboard';
import { report, screenings, show } from '@/routes/tenant/campaigns';
import type { NextStep } from '@/types';

const ICONS = { launch: Play, report: FileText, screenings: HeartHandshake };

const ROUTES = { launch: show, report, screenings };

/** "Lo que sigue" (pantalla Panel): what is waiting on this person, each with the button that takes them there. */
export default function DashboardNextSteps({
    tenantSlug,
    steps,
    pending,
}: {
    tenantSlug: string;
    steps: NextStep[];
    pending: number;
}) {
    return (
        <article
            aria-labelledby="next-steps"
            className="flex flex-col gap-1 rounded-xl border bg-card px-6 pt-6 pb-2.5"
        >
            <div className="flex items-center justify-between gap-3">
                <h2
                    id="next-steps"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Lo que sigue
                </h2>
                {pending > 0 && (
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        {pendingText(pending)}
                    </span>
                )}
            </div>
            {steps.length === 0 ? (
                <p className="py-3.5 text-muted-foreground">
                    Nada pendiente por ahora.
                </p>
            ) : (
                <ul>
                    {steps.map((step) => {
                        const texts = nextStepTexts(step);
                        const Icon = ICONS[step.kind];

                        return (
                            <li
                                key={`${step.kind}-${step.campaignId}`}
                                className="flex items-center gap-3.5 border-t py-3.5 first:border-t-0"
                            >
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-accent text-primary">
                                    <Icon aria-hidden className="size-[18px]" />
                                </span>
                                <span className="flex min-w-0 grow flex-col">
                                    <span className="text-[15px] leading-[22px] font-medium">
                                        {texts.title}
                                    </span>
                                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                                        {texts.detail}
                                    </span>
                                </span>
                                <Button asChild variant="outline" size="sm">
                                    <Link
                                        href={ROUTES[step.kind]([
                                            tenantSlug,
                                            step.campaignId,
                                        ])}
                                        aria-label={`${texts.action}: ${texts.title}`}
                                    >
                                        {texts.action}
                                    </Link>
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </article>
    );
}
