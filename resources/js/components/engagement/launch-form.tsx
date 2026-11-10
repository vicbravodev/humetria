import { Form } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import AudienceReview from '@/components/engagement/audience-review';
import DeadlineStep from '@/components/engagement/deadline-step';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { launch } from '@/routes/tenant/climate';
import type { ClimateDraft, ClimateUnreachable, Paginated } from '@/types';

/** The draft reviewed in three steps and launched (pantalla Eval-Borrador): who, how the links reach them, and the last day. */
export default function LaunchForm({
    surveyId,
    draft,
    unreachable,
}: {
    surveyId: string;
    draft: ClimateDraft;
    unreachable: Paginated<ClimateUnreachable>;
}) {
    const tenantSlug = useTenantSlug();
    const [lastDay, setLastDay] = useState(draft.limits.suggested);
    const reached = draft.people - draft.withoutEmail;

    return (
        <Form
            {...launch.form([tenantSlug, surveyId])}
            className="flex flex-col card px-6 pt-1 pb-6"
        >
            {({ processing, errors }) => (
                <>
                    <ol>
                        <AudienceReview
                            draft={draft}
                            unreachable={unreachable}
                        />
                        <DeadlineStep
                            value={lastDay}
                            onChange={setLastDay}
                            limits={draft.limits}
                            error={errors['closes_on']}
                        />
                    </ol>
                    {errors['survey'] !== undefined && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>
                                {errors['survey']}
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
                            {countOf(reached, 'persona', 'personas')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
