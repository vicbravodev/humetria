import DocumentPreview from '@/components/humetria/document-preview';
import PreviewSection from '@/components/humetria/preview-section';
import TopicChips from '@/components/humetria/topic-chips';
import { reached, topicStates } from '@/lib/question-flow';
import type { FlowQuestion, FlowSection, FlowTopic } from '@/lib/question-flow';

/**
 * The NOM-037 document beside its questions (boards V2-Politica and V3-Doc-Riesgos): the incisos it covers as chips, when
 * the norm names them (done once their question is passed), and the text as far as the questions went. While the
 * questions go on, the base texts fold into one line.
 */
export default function WritePreview({
    owner,
    title,
    numeral,
    items,
    questions,
    index,
    sections,
}: {
    owner: string;
    title: string;
    numeral: string;
    items: FlowTopic[];
    questions: FlowQuestion[];
    /** The question on screen; the number of questions is the review. */
    index: number;
    sections: FlowSection[];
}) {
    const reviewing = index >= questions.length;
    const current = questions[index]?.key ?? null;
    const based = sections.filter(({ base }) => base);
    const shown = reached(
        reviewing ? sections : sections.filter(({ base }) => !base),
        questions,
        index,
    );

    return (
        <div className="flex min-w-0 flex-col gap-4">
            {items.length > 0 && (
                <TopicChips
                    topics={topicStates(
                        items,
                        reached(sections, questions, index - 1),
                        reviewing,
                    )}
                    label={`Temas que pide el numeral ${numeral}`}
                    note={`Los ${items.length} temas del numeral ${numeral}. Los punteados ya tienen texto base que puedes editar.`}
                />
            )}
            <DocumentPreview owner={owner} title={title}>
                {shown.map((section) => (
                    <PreviewSection
                        key={section.key}
                        section={section}
                        current={section.key === current}
                    />
                ))}
                {!reviewing && based.length > 0 && (
                    <div className="flex flex-col gap-0.5 py-2.5">
                        <p className="font-semibold text-muted-foreground">
                            {based.map(({ heading }) => heading).join(', ')}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Texto base de Humetría, listo para editar.
                        </p>
                    </div>
                )}
            </DocumentPreview>
        </div>
    );
}
