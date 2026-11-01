import InputError from '@/components/input-error';
import PhotoPicker from '@/components/telework/photo-picker';
import { Label } from '@/components/ui/label';
import { answerLabel, isUnfavorable } from '@/lib/telework/answers';
import { cn } from '@/lib/utils';
import type { PortalAnswer, PortalQuestion } from '@/types';

const ANSWERS: PortalAnswer[] = ['yes', 'no', 'not_applicable'];

/**
 * One question of the checklist (pantalla: Encuesta-Movil): three large answers that never move on by themselves. A
 * flagged answer opens the risk note, which is required, and, with consent, "Agregar foto".
 */
export default function ChecklistQuestion({
    question,
    answer,
    note,
    photos,
    canPhotograph,
    error,
    onAnswer,
    onNote,
    onPhotos,
}: {
    question: PortalQuestion;
    answer: PortalAnswer | undefined;
    note: string;
    photos: File[];
    canPhotograph: boolean;
    error: string | undefined;
    onAnswer: (value: PortalAnswer) => void;
    onNote: (value: string) => void;
    onPhotos: (files: File[]) => void;
}) {
    const flagged = isUnfavorable(question.favorable, answer);
    const noteId = `note-${question.id}`;

    return (
        <fieldset className="flex flex-col gap-3 border-b py-5 last:border-b-0">
            <legend className="mb-1 text-lg leading-7 font-medium">
                {question.text}
            </legend>
            <div className="flex flex-col gap-2.5 sm:flex-row">
                {ANSWERS.map((value) => (
                    <label
                        key={value}
                        className={cn(
                            'flex min-h-14 flex-1 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 text-lg hover:bg-muted',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            answer === value &&
                                'border-2 border-primary bg-accent px-[15px] py-[11px] font-medium hover:bg-accent',
                        )}
                    >
                        <input
                            type="radio"
                            name={`answer-${question.id}`}
                            value={value}
                            checked={answer === value}
                            onChange={() => onAnswer(value)}
                            className="size-5 shrink-0 accent-primary focus-visible:outline-none"
                        />
                        {answerLabel(value)}
                    </label>
                ))}
            </div>
            {flagged && (
                <div className="grid gap-2 rounded-lg bg-warning p-4 text-warning-foreground">
                    <Label htmlFor={noteId} className="text-base">
                        Describa el riesgo que encontró
                    </Label>
                    <textarea
                        id={noteId}
                        rows={3}
                        value={note}
                        maxLength={2000}
                        onChange={(event) => onNote(event.target.value)}
                        aria-invalid={error === undefined ? undefined : true}
                        className="w-full rounded-lg border border-input bg-card px-3 py-2 text-lg text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    />
                    {canPhotograph && (
                        <PhotoPicker
                            id={question.id}
                            photos={photos}
                            onPhotos={onPhotos}
                        />
                    )}
                </div>
            )}
            <InputError role="alert" message={error} />
        </fieldset>
    );
}
