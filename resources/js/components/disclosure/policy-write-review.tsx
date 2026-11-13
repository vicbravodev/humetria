import { Check } from 'lucide-react';
import { useState } from 'react';
import PublishDialog from '@/components/disclosure/publish-dialog';
import FlowProgress from '@/components/humetria/flow-progress';
import { Button } from '@/components/ui/button';
import WriteMissing from '@/components/write-missing';
import type { Option } from '@/lib/option-search';
import type { FlowQuestion } from '@/lib/question-flow';
import type { DisclosureWritingProps } from '@/types';

/**
 * The end of the policy's questions (board V2-N035-Politica): what is left to answer, or the policy ready to read on the
 * right and to publish with the publication that exists (the centers, the incisos of 5.1 and the review, criterio C-51).
 */
export default function PolicyWriteReview({
    missing,
    questions,
    publication,
    center,
    onGo,
}: {
    missing: FlowQuestion[];
    questions: FlowQuestion[];
    publication: DisclosureWritingProps['publication'];
    /** The center whose route it came from, to go back to it once published. */
    center: Option | null;
    onGo: (step: number) => void;
}) {
    const [publishing, setPublishing] = useState(false);

    if (missing.length > 0) {
        return (
            <WriteMissing missing={missing} questions={questions} onGo={onGo} />
        );
    }

    return (
        <div className="flex flex-col gap-[18px]">
            <FlowProgress
                label="Lista para revisar"
                left={null}
                value={questions.length}
                max={questions.length}
            />
            <span
                aria-hidden
                className="inline-flex size-[52px] items-center justify-center rounded-full bg-primary text-primary-foreground"
            >
                <Check className="size-[26px]" strokeWidth={2.5} />
            </span>
            <h1 className="text-[28px] leading-9 font-semibold tracking-[-0.01em]">
                Tu política cubre lo que pide la norma
            </h1>
            <p className="text-[17px] leading-[26px] text-muted-foreground">
                Léela a la derecha y edita lo que quieras. Al publicarla, le
                llega a las personas de tus centros para que confirmen que la
                leyeron, y queda en tu expediente.
            </p>
            {publication.version !== null && (
                <p className="text-sm text-muted-foreground">
                    Vigente: versión {publication.version.number} del{' '}
                    {publication.version.publishedOn}
                </p>
            )}
            <div className="flex flex-wrap gap-3">
                <Button
                    size="lg"
                    disabled={publication.draftId === null}
                    onClick={() => setPublishing(true)}
                >
                    Publicar
                </Button>
                <Button variant="ghost" size="lg" onClick={() => onGo(0)}>
                    Revisar las respuestas
                </Button>
            </div>
            {publication.draftId !== null && (
                <PublishDialog
                    open={publishing}
                    onOpenChange={setPublishing}
                    documentId={publication.draftId}
                    centersInUse={publication.centersInUse}
                    center={center}
                />
            )}
        </div>
    );
}
