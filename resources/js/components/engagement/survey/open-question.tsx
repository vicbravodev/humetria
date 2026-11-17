import { Sparkles } from 'lucide-react';
import QuestionHeading from '@/components/humetria/question-heading';
import { Textarea } from '@/components/ui/textarea';
import { focusOnMount } from '@/lib/focus';

export const COMMENT_LIMIT = 1000;

/**
 * An open question: free text, optional, with the notice about names and a counter of 1000. Kept only in memory. Under
 * the box, where the person writes, that an AI provider summarizes the comments: they really go to it
 * (`CommentSummarizer`), so it is said here instead of in the welcome.
 */
export default function OpenQuestion({
    name,
    topic,
    text,
    notice,
    value,
    onChange,
}: {
    name: string;
    topic: string;
    text: string;
    notice: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <div
                ref={focusOnMount}
                tabIndex={-1}
                className="focus:outline-none"
            >
                <QuestionHeading topic={topic} title={text} hint={notice} />
            </div>
            <div className="flex flex-col gap-2">
                <Textarea
                    name={name}
                    aria-label={text}
                    aria-describedby={`${name}-ai`}
                    maxLength={COMMENT_LIMIT}
                    rows={6}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    className="min-h-40 text-lg"
                />
                <p className="text-right text-sm text-muted-foreground">
                    {value.length} de {COMMENT_LIMIT} caracteres
                </p>
            </div>
            <div
                id={`${name}-ai`}
                className="flex items-start gap-2.5 rounded-xl bg-accent px-3.5 py-3 text-base leading-6 text-accent-foreground"
            >
                <Sparkles
                    aria-hidden="true"
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                <p>
                    <strong className="block font-semibold">
                        Un proveedor de inteligencia artificial resume los
                        comentarios.
                    </strong>
                    Por eso, no escriba su nombre ni datos que lo identifiquen.
                </p>
            </div>
        </div>
    );
}
