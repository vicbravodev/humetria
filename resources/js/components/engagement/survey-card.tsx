import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import SurveyStatusBadge from '@/components/engagement/survey-status-badge';
import ProgressBar from '@/components/humetria/progress-bar';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { answeredText, deadlineText } from '@/lib/engagement/panel';
import type { ClimateSurveyCard } from '@/types';

/** The survey in progress (pantalla NOM035, CampaignCard): its state, how far it is and the one thing to do next. */
export default function SurveyCard({ survey }: { survey: ClimateSurveyCard }) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const deadline = deadlineText(survey);

    return (
        <article className="flex max-w-[640px] flex-col gap-5 card p-6 shadow-xs">
            <header className="flex items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="text-lg leading-7 font-semibold">
                        {survey.name}
                    </h2>
                    {deadline !== undefined && (
                        <p className="text-sm text-muted-foreground">
                            {deadline}
                        </p>
                    )}
                </div>
                <SurveyStatusBadge
                    status={survey.status}
                    label={survey.statusLabel}
                />
            </header>
            {survey.invited === null || survey.answered === null ? (
                <p className="text-sm text-muted-foreground">
                    {answeredText(survey)} Revisa el borrador y lánzalo para
                    invitar a las personas.
                </p>
            ) : (
                <div className="flex flex-col gap-2">
                    <p className="text-2xl leading-8 font-semibold tabular-nums">
                        {answeredText(survey)}
                    </p>
                    <ProgressBar
                        value={survey.answered}
                        max={survey.invited}
                        label={`Avance de ${survey.name}`}
                    />
                </div>
            )}
            <footer className="flex justify-end border-t pt-4">
                <Button variant="outline" asChild>
                    <Link href={routes.show([tenantSlug, survey.id])}>
                        {survey.status === 'draft'
                            ? 'Continuar borrador'
                            : 'Ver avance'}
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </footer>
        </article>
    );
}
