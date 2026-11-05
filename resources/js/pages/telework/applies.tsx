import { Head, Link } from '@inertiajs/react';
import SaveError from '@/components/humetria/save-error';
import SetupFrame from '@/components/setup-frame';
import AppliesIntro from '@/components/telework/applies-intro';
import AppliesNo from '@/components/telework/applies-no';
import AppliesQuestion from '@/components/telework/applies-question';
import AppliesYes from '@/components/telework/applies-yes';
import { useTeleworkApplies } from '@/hooks/use-telework-applies';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { QUESTIONS } from '@/lib/telework/applies';
import { dashboard } from '@/routes/tenant';
import { center } from '@/routes/tenant/telework';
import { update } from '@/routes/tenant/telework/applies';

type Props = {
    workCenter: { id: string; name: string };
    /** What the center answered last, if it did; "1 mar 2026". */
    declaration: { hasTeleworkers: boolean; declaredOn: string } | null;
};

/** «¿Te aplica la NOM-037?» of one center (board V2-Aplica): two questions, then its route or its «no aplica». */
export default function TeleworkApplies({ workCenter, declaration }: Props) {
    const tenantSlug = useTenantSlug();
    const wizard = useTeleworkApplies(
        declaration !== null,
        update.url([tenantSlug, workCenter.id]),
    );
    const number = QUESTIONS.findIndex(({ key }) => key === wizard.step);
    const question = QUESTIONS[number];

    return (
        <SetupFrame
            caption={`NOM-037 · ${workCenter.name} · configuración inicial`}
            aside={
                <Link
                    href={dashboard(tenantSlug)}
                    className="inline-flex min-h-11 items-center text-sm font-medium text-primary"
                >
                    Hacerlo después
                </Link>
            }
        >
            <Head title={`¿Te aplica la NOM-037? · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[620px] flex-col gap-6 md:pt-2">
                {wizard.step === 'intro' && (
                    <AppliesIntro
                        center={workCenter.name}
                        onStart={wizard.start}
                    />
                )}
                {question !== undefined && (
                    <AppliesQuestion
                        question={question}
                        number={number + 1}
                        answer={wizard.answers[question.key]}
                        onAnswer={(answer) =>
                            wizard.answer(question.key, answer)
                        }
                        onBack={wizard.back}
                        onNext={wizard.next}
                        processing={wizard.saving.processing}
                    />
                )}
                {wizard.saving.failed && (
                    <SaveError
                        onRetry={wizard.saving.retry}
                        processing={wizard.saving.processing}
                    />
                )}
                {wizard.step === 'result' &&
                    declaration?.hasTeleworkers === true && (
                        <AppliesYes
                            routeHref={center([tenantSlug, workCenter.id])}
                            onRestart={wizard.restart}
                        />
                    )}
                {wizard.step === 'result' &&
                    declaration?.hasTeleworkers === false && (
                        <AppliesNo
                            center={workCenter.name}
                            declaredOn={declaration.declaredOn}
                            panelHref={dashboard(tenantSlug)}
                            onRestart={wizard.restart}
                        />
                    )}
            </div>
        </SetupFrame>
    );
}
