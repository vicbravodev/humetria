import { Form, Link } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import ContactStep from '@/components/campaigns/contact-step';
import DeadlineStep from '@/components/campaigns/deadline-step';
import InvitationPreview from '@/components/campaigns/invitation-preview';
import ReviewStep from '@/components/campaigns/review-step';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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
        <Form
            {...launch.form([tenant.slug, campaign.id])}
            className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_minmax(320px,1fr)]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-col rounded-xl border bg-card px-6 pt-1 pb-6">
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
                                error={errors['closes_on']}
                            />
                        </ol>
                        {errors['campaign'] !== undefined && (
                            <Alert variant="destructive" className="mb-4">
                                <AlertDescription>
                                    {errors['campaign']}
                                </AlertDescription>
                            </Alert>
                        )}
                        <div className="border-t pt-5">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <Send aria-hidden="true" />
                                )}
                                Lanzar e invitar a{' '}
                                {peopleText(contact.reachable)}
                            </Button>
                        </div>
                    </div>
                    <InvitationPreview
                        company={tenant.name}
                        lastDay={lastDay}
                    />
                </>
            )}
        </Form>
    );
}
