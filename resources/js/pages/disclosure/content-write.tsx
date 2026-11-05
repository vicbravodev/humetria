import { Head, setLayoutProps } from '@inertiajs/react';
import ContentPhone from '@/components/disclosure/content-phone';
import ContentWriteReview from '@/components/disclosure/content-write-review';
import DisclosureHeader from '@/components/disclosure/disclosure-header';
import SaveError from '@/components/humetria/save-error';
import WriteQuestion from '@/components/write-question';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { useWriting } from '@/hooks/use-writing';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import { reached, unanswered } from '@/lib/question-flow';
import { update } from '@/routes/tenant/disclosure/write';
import type { DisclosureWritingProps } from '@/types';

/**
 * A block of 5.7 written with questions (board V4-Difusion-Contenidos; decisión A3): one question at a time in its card and,
 * beside it, how the people will read it on their phone. The health effects have no question: their draft is reviewed.
 */
export default function DisclosureContentWrite(props: DisclosureWritingProps) {
    const tenantSlug = useTenantSlug();
    const url = update.url([tenantSlug, props.document.kind]);
    const writing = useWriting(url, props, props.questions.length);
    const reviewing = writing.index >= props.questions.length;
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, {
            title: props.document.title,
        }),
    });

    return (
        <>
            <Head title={props.document.title} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <DisclosureHeader
                    eyebrow={`NOM-035 · numeral 5.7 ${props.document.inciso}`}
                    title={props.document.title}
                    lead={
                        props.questions.length > 0
                            ? 'Elige lo que aplica en tu empresa. Lo redactamos en lenguaje sencillo y lo ves como lo verán tus personas.'
                            : 'Es un borrador de Humetría. Revísalo con una persona con criterio médico antes de publicarlo; puedes reescribir cada parte.'
                    }
                />
                <div className="grid items-start gap-8 min-[1100px]:grid-cols-[minmax(0,1fr)_400px]">
                    <section
                        aria-label="Preguntas"
                        className="flex flex-col gap-6 card p-5 md:p-7"
                    >
                        {reviewing ? (
                            <ContentWriteReview
                                props={props}
                                missing={unanswered(
                                    props.questions,
                                    writing.answers,
                                )}
                                editUrl={url}
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
                    </section>
                    <ContentPhone
                        company={props.document.company}
                        title={props.document.title}
                        sections={reached(
                            props.sections,
                            props.questions,
                            writing.index,
                        )}
                    />
                </div>
            </div>
        </>
    );
}
