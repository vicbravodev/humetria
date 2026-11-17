import { Form } from '@inertiajs/react';
import type { FormComponentRef } from '@inertiajs/core';
import { useRef, useState } from 'react';
import InputError from '@/components/humetria/input-error';
import PortalFrame from '@/components/seating/portal-frame';
import PortalOffline from '@/components/seating/portal-offline';
import PortalQuestion from '@/components/seating/portal-question';
import PortalQuestionsNav from '@/components/seating/portal-questions-nav';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useResendWhenOnline } from '@/hooks/use-resend-when-online';
import { show } from '@/routes/portal/seating';
import { store } from '@/routes/portal/seating/inputs';
import type { PortalCondition } from '@/types';

/**
 * The two questions of the person's Ley Silla page (board SillaPregunta), one at a time: the footwear, then the discomfort
 * with an optional note. It never moves on alone: «Siguiente» waits for an answer, stuck to the bottom with «Anterior». The
 * notice was accepted on the screen before. The form wraps the whole frame, so the stuck buttons are its own. Without a network, the answers stay in the page and go out when it is back.
 */
export default function PortalQuestions({
    page,
    onSent,
}: {
    page: {
        slug: string;
        company: string;
        footwear: PortalCondition;
        discomfort: PortalCondition;
    };
    onSent: () => void;
}) {
    const [step, setStep] = useState<1 | 2>(1);
    const form = useRef<FormComponentRef>(null);
    const offline = useResendWhenOnline(() => form.current?.submit());
    const [answered, setAnswered] = useState({
        footwear: false,
        discomfort: false,
    });

    return (
        <Form
            ref={form}
            {...store.form(page.slug)}
            options={{ preserveScroll: true }}
            onNetworkError={offline.failed}
            onSuccess={() => {
                offline.sent();
                onSent();
            }}
            noValidate
        >
            {({ processing, errors }) => (
                <PortalFrame
                    company={page.company}
                    privacy="identified"
                    back={show.url(page.slug)}
                    actions={
                        <PortalQuestionsNav
                            step={step}
                            ready={
                                step === 1
                                    ? answered.footwear
                                    : answered.discomfort
                            }
                            processing={processing}
                            onStep={setStep}
                        />
                    }
                >
                    <input type="hidden" name="accepted" value="1" />
                    {offline.waiting && <PortalOffline />}
                    <PortalQuestion
                        number={1}
                        question="¿Cómo es el calzado que usa para trabajar?"
                        name="footwear"
                        condition={page.footwear}
                        hidden={step !== 1}
                        error={errors['footwear']}
                        onChoose={() =>
                            setAnswered((now) => ({ ...now, footwear: true }))
                        }
                    />
                    <div hidden={step !== 2} className="flex flex-col gap-6">
                        <PortalQuestion
                            number={2}
                            question="Al trabajar de pie, ¿ha sentido alguna molestia?"
                            name="discomfort"
                            condition={page.discomfort}
                            hidden={false}
                            error={errors['discomfort']}
                            onChoose={() =>
                                setAnswered((now) => ({
                                    ...now,
                                    discomfort: true,
                                }))
                            }
                        />
                        <div className="grid gap-2">
                            <Label htmlFor="portal-note">
                                ¿Algo más que quiera contarnos? (opcional)
                            </Label>
                            <Textarea id="portal-note" name="note" rows={3} />
                            <InputError message={errors['note']} />
                        </div>
                        <InputError message={errors['accepted']} />
                    </div>
                </PortalFrame>
            )}
        </Form>
    );
}
