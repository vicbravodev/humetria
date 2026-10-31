import { router } from '@inertiajs/react';
import PortalController from '@/actions/App/Modules/Telework/Http/Controllers/PortalController';
import ChecklistConsent from '@/components/telework/checklist-consent';
import ChecklistGroup from '@/components/telework/checklist-group';
import ChecklistIntro from '@/components/telework/checklist-intro';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { useChecklistForm } from '@/hooks/use-checklist-form';
import { groupByHeading } from '@/lib/telework/answers';
import type { PortalApplication, PortalQuestion } from '@/types';

/** The checklist as the person fills it in, one send at the end (pantalla: Encuesta-Movil). */
export default function ChecklistForm({
    token,
    application,
    instructions,
    notice,
    consent,
    questions,
}: {
    token: string;
    application: PortalApplication;
    instructions: string;
    notice: string;
    consent: string;
    questions: PortalQuestion[];
}) {
    const form = useChecklistForm(token, application.id, questions);
    const consented = form.state.consent === true;

    return (
        <form
            noValidate
            className="flex w-full max-w-2xl flex-col gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.send();
            }}
        >
            <ChecklistIntro
                workplace={application.workplace}
                instructions={instructions}
                notice={notice}
            />
            <ChecklistConsent
                text={consent}
                consent={form.state.consent}
                onDecide={form.decide}
            />
            {groupByHeading(form.shown).map((group) => (
                <ChecklistGroup
                    key={group.heading}
                    heading={group.heading}
                    questions={group.questions}
                    form={form}
                />
            ))}
            {consented && (
                <div className="grid gap-2">
                    <Label htmlFor="special-needs" className="text-base">
                        ¿Tiene alguna necesidad especial? (opcional)
                    </Label>
                    <textarea
                        id="special-needs"
                        rows={3}
                        maxLength={2000}
                        value={form.state.specialNeeds}
                        onChange={(event) =>
                            form.specialNeeds(event.target.value)
                        }
                        className="w-full rounded-lg border border-input bg-card px-3 py-2 text-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    />
                </div>
            )}
            <InputError
                role="alert"
                message={
                    form.errors['application'] ??
                    form.errors['photos'] ??
                    form.errors['answers']
                }
            />
            <Button
                type="submit"
                size="lg"
                className="h-14 text-lg"
                disabled={form.processing}
            >
                Enviar mi lista
            </Button>
            <Button
                type="button"
                variant="ghost"
                onClick={() => router.visit(PortalController.show.url(token))}
            >
                Volver sin enviar
            </Button>
        </form>
    );
}
