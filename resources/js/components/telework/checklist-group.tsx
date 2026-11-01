import ChecklistQuestion from '@/components/telework/checklist-question';
import type { useChecklistForm } from '@/hooks/use-checklist-form';
import type { PortalQuestion } from '@/types';

/** The questions under one heading of the checklist. */
export default function ChecklistGroup({
    heading,
    questions,
    form,
}: {
    heading: string;
    questions: PortalQuestion[];
    form: ReturnType<typeof useChecklistForm>;
}) {
    return (
        <section className="grid gap-1 rounded-xl border bg-card px-4 pt-4">
            <h2 className="text-xl font-semibold">{heading}</h2>
            {questions.map((question) => (
                <ChecklistQuestion
                    key={question.id}
                    question={question}
                    answer={form.state.answers[question.id]}
                    note={form.state.notes[question.id] ?? ''}
                    photos={form.state.photos[question.id] ?? []}
                    canPhotograph={form.state.consent === true}
                    error={
                        form.pending.includes(question.id)
                            ? 'Conteste esta pregunta y, si hay riesgo, descríbalo.'
                            : (form.errors[`answers.${question.id}`] ??
                              form.errors[`risk_notes.${question.id}`])
                    }
                    onAnswer={(value) => form.answer(question.id, value)}
                    onNote={(value) => form.note(question.id, value)}
                    onPhotos={(files) => form.photos(question.id, files)}
                />
            ))}
        </section>
    );
}
