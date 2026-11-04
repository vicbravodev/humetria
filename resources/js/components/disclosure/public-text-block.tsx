import type { ReactNode } from 'react';
import PlainText from '@/components/disclosure/plain-text';
import PublicBlock from '@/components/disclosure/public-block';
import PublicMeasures from '@/components/disclosure/public-measures';
import type { PublicBlockText, PublicMeasure } from '@/types';

/**
 * A block of 5.7 on the public page (b, c, d, f): what other tools give (the measures of the Programa, the channel), then the
 * text the company wrote, joined and never replaced one by the other; or that nothing is published yet.
 */
export default function PublicTextBlock({
    inciso,
    title,
    block,
    measures = [],
    extra,
}: {
    inciso: string;
    title: string;
    block: PublicBlockText | null | undefined;
    measures?: PublicMeasure[];
    /** What another tool gives for this block, drawn above the company's text (the channel). */
    extra?: ReactNode;
}) {
    const text = block ?? null;
    const empty = text === null && measures.length === 0 && extra === undefined;

    return (
        <PublicBlock id={`block-${inciso}`} inciso={inciso} title={title}>
            {empty && (
                <p className="text-muted-foreground">
                    Aún no hay información publicada.
                </p>
            )}
            {extra}
            {measures.length > 0 && <PublicMeasures measures={measures} />}
            {text !== null && (
                <>
                    {(measures.length > 0 || extra !== undefined) && (
                        <h3 className="text-base font-semibold">
                            Texto de la empresa
                        </h3>
                    )}
                    <PlainText text={text.text} />
                    <p className="text-sm text-muted-foreground">
                        Publicado el {text.publishedOn}.
                    </p>
                </>
            )}
        </PublicBlock>
    );
}
