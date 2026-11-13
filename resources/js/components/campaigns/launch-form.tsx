import { Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import ContactStep from '@/components/campaigns/contact-step';
import DeadlineStep from '@/components/campaigns/deadline-step';
import InvitationPreview from '@/components/campaigns/invitation-preview';
import ReviewStep from '@/components/campaigns/review-step';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { launchSummary } from '@/lib/campaign-launch';
import { instrumentsText, quotasText } from '@/lib/campaigns';
import { peopleText, staffBand } from '@/lib/evaluation';
import { launch } from '@/routes/tenant/campaigns';
import { index as employees } from '@/routes/tenant/employees';
import type { CampaignDetail, DraftContact } from '@/types';

/**
 * The draft reviewed in three steps and launched (pantalla Eval-Borrador): who answers which guides, how the links reach
 * them, and the last day; next to it, the email each person gets.
 */
export default function LaunchForm({
    tenant,
    campaign,
    limits,
    contact,
}: {
    tenant: { slug: string; name: string };
    campaign: CampaignDetail;
    limits: { min: string; suggested: string };
    contact: DraftContact;
}) {
    const [lastDay, setLastDay] = useState(limits.suggested);
    const employeesUrl = employees.url(tenant.slug, {
        query: { work_center: campaign.workCenterId },
    });

    return (
        <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_minmax(320px,1fr)]">
            <div className="flex flex-col card px-6 pt-1 pb-6">
                <ol>
                    <ReviewStep title="Personas y guías">
                        <p className="text-sm text-muted-foreground">
                            {peopleText(campaign.workers ?? 0)} de{' '}
                            {campaign.workCenter} contestarán las{' '}
                            <strong className="font-semibold text-foreground">
                                {instrumentsText(campaign.instruments)}
                            </strong>
                            , porque el centro tiene{' '}
                            {staffBand(campaign.workers ?? 0)}.
                            {campaign.sampleSize !== null &&
                                ` La Guía III la contesta una muestra sorteada. Muestra de ${campaign.sampleSize}: ${quotasText(campaign.quotas)}.`}
                        </p>
                        <Link
                            href={employeesUrl}
                            className="touch-hit w-fit text-sm font-medium text-primary hover:underline"
                        >
                            Ver las {peopleText(campaign.workers ?? 0)}
                        </Link>
                    </ReviewStep>
                    <ContactStep
                        contact={contact}
                        employeesUrl={employeesUrl}
                    />
                    <DeadlineStep
                        value={lastDay}
                        onChange={setLastDay}
                        limits={limits}
                        error={undefined}
                    />
                </ol>
                <div className="border-t pt-5">
                    <ConfirmDialog
                        trigger={
                            <Button type="button" className="w-full">
                                <Send aria-hidden="true" />
                                Lanzar e invitar a{' '}
                                {peopleText(contact.reachable)}
                            </Button>
                        }
                        title="¿Lanzar la evaluación?"
                        description={launchSummary(contact.reachable, lastDay)}
                        confirmLabel="Lanzar e invitar"
                        destructive={false}
                        form={launch.form([tenant.slug, campaign.id])}
                        transform={() => ({ closes_on: lastDay })}
                    >
                        <p className="text-sm text-muted-foreground">
                            Solo recibe la evaluación quien está en Empleados:
                            revisa que tu plantilla esté completa.
                        </p>
                    </ConfirmDialog>
                </div>
            </div>
            <InvitationPreview company={tenant.name} lastDay={lastDay} />
        </div>
    );
}
