import { useState } from 'react';
import ContentPublishDialog from '@/components/disclosure/content-publish-dialog';
import FlowProgress from '@/components/humetria/flow-progress';
import { Button } from '@/components/ui/button';
import WriteMissing from '@/components/write-missing';
import WriteSectionEdit from '@/components/write-section-edit';
import type { FlowQuestion, FlowSection } from '@/lib/question-flow';
import type { DisclosureWritingProps } from '@/types';

/**
 * The end of a block's questions (board V4-Difusion-Contenidos): what is left to answer, or the text to read and rewrite part
 * by part, and the way to publish it to the centers. With no question (the health effects), it is where the draft is checked.
 */
export default function ContentWriteReview({
    props,
    missing,
    editUrl,
    onGo,
}: {
    props: DisclosureWritingProps;
    missing: FlowQuestion[];
    editUrl: string;
    onGo: (step: number) => void;
}) {
    const [publishing, setPublishing] = useState(false);
    const { publication, questions } = props;
    const draftId = publication.draftId;

    if (missing.length > 0) {
        return (
            <WriteMissing missing={missing} questions={questions} onGo={onGo} />
        );
    }

    return (
        <div className="flex flex-col gap-5">
            {questions.length > 0 && (
                <FlowProgress
                    label="Listo para revisar"
                    left={null}
                    value={questions.length}
                    max={questions.length}
                />
            )}
            <h2 className="text-xl leading-7 font-semibold">
                {questions.length > 0
                    ? 'Revisa el texto antes de publicarlo'
                    : 'Revisa el borrador antes de publicarlo'}
            </h2>
            <div className="flex flex-col">
                {props.sections.map((section: FlowSection) => (
                    <WriteSectionEdit
                        key={`${section.key}:${section.text ?? ''}`}
                        section={section}
                        url={editUrl}
                    />
                ))}
            </div>
            {publication.version !== null && (
                <p className="text-sm text-muted-foreground">
                    Vigente: versión {publication.version.number} del{' '}
                    {publication.version.publishedOn}. Las versiones anteriores
                    se guardan.
                </p>
            )}
            <div className="flex flex-wrap gap-3">
                {draftId === null ? (
                    <Button size="lg" onClick={() => onGo(questions.length)}>
                        Usar este borrador
                    </Button>
                ) : (
                    <Button size="lg" onClick={() => setPublishing(true)}>
                        Publicar en tus centros
                    </Button>
                )}
                {questions.length > 0 && (
                    <Button variant="ghost" size="lg" onClick={() => onGo(0)}>
                        Revisar las respuestas
                    </Button>
                )}
            </div>
            {draftId !== null && (
                <ContentPublishDialog
                    open={publishing}
                    onOpenChange={setPublishing}
                    kind={props.document.kind}
                    documentId={draftId}
                    label={props.document.title}
                    centersInUse={publication.centersInUse}
                    back="board"
                />
            )}
        </div>
    );
}
