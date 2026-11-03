import PlainText from '@/components/disclosure/plain-text';
import PublicBlock from '@/components/disclosure/public-block';
import type { PublicBlockText } from '@/types';

/** A block of 5.7 the company writes (b, c, d, f) on the public page: its text and day, or that nothing is published yet. */
export default function PublicTextBlock({
    inciso,
    title,
    block,
}: {
    inciso: string;
    title: string;
    block: PublicBlockText | null | undefined;
}) {
    return (
        <PublicBlock id={`block-${inciso}`} inciso={inciso} title={title}>
            {block === null || block === undefined ? (
                <p className="text-muted-foreground">
                    Aún no hay información publicada.
                </p>
            ) : (
                <>
                    <PlainText text={block.text} />
                    <p className="text-sm text-muted-foreground">
                        Publicado el {block.publishedOn}.
                    </p>
                </>
            )}
        </PublicBlock>
    );
}
