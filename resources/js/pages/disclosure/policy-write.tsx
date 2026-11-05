import { Head, Link } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import PolicyWriteReview from '@/components/disclosure/policy-write-review';
import SaveError from '@/components/humetria/save-error';
import SetupFrame from '@/components/setup-frame';
import WritePreview from '@/components/write-preview';
import WriteQuestion from '@/components/write-question';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useWriting } from '@/hooks/use-writing';
import { unanswered } from '@/lib/question-flow';
import { update } from '@/routes/tenant/disclosure/write';
import type { DisclosureWritingProps } from '@/types';

/** The policy of prevention written with questions (board V2-N035-Politica; decisión A3), from Guía de referencia IV. */
export default function DisclosurePolicyWrite(props: DisclosureWritingProps) {
    const tenantSlug = useTenantSlug();
    const url = update.url([tenantSlug, props.document.kind]);
    const writing = useWriting(url, props, props.questions.length);
    const reviewing = writing.index >= props.questions.length;

    return (
        <SetupFrame
            lead={
                <Link
                    href={props.back.href}
                    className="-ml-3 inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-medium hover:bg-muted"
                >
                    <X aria-hidden className="size-[18px]" />
                    {props.back.label}
                </Link>
            }
            caption={props.document.caption}
            aside={
                <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Check aria-hidden className="size-4 text-primary" />
                    Se guarda en cada respuesta
                </span>
            }
        >
            <Head title={props.document.title} />
            <div className="mx-auto grid w-full max-w-[1264px] items-start gap-10 min-[1100px]:grid-cols-[minmax(0,560px)_minmax(0,1fr)] min-[1100px]:gap-14">
                <div className="flex flex-col gap-6">
                    {reviewing ? (
                        <PolicyWriteReview
                            missing={unanswered(
                                props.questions,
                                writing.answers,
                            )}
                            questions={props.questions}
                            publication={props.publication}
                            center={props.back.center}
                            onGo={writing.go}
                        />
                    ) : (
                        <WriteQuestion
                            questions={props.questions}
                            index={writing.index}
                            answers={writing.answers}
                            minutes={props.minutes}
                            onAnswer={writing.answer}
                            onBack={writing.back}
                            onNext={writing.next}
                        />
                    )}
                    {writing.saving.failed && (
                        <SaveError
                            onRetry={writing.saving.retry}
                            processing={writing.saving.processing}
                        />
                    )}
                </div>
                <WritePreview
                    owner={props.document.owner}
                    title={props.document.title}
                    numeral="5.1"
                    items={[]}
                    questions={props.questions}
                    index={writing.index}
                    sections={props.sections}
                    editUrl={url}
                    baseNote="Texto de la Guía de referencia IV, listo para editar."
                />
            </div>
        </SetupFrame>
    );
}
