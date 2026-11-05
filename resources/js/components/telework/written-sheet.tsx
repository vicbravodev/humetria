import { paragraphs } from '@/lib/question-flow';
import type { WrittenDocument } from '@/types';

/** A version of a NOM-037 document written with questions, as people read and print it: whose, which version and its text. */
export default function WrittenSheet({
    document,
}: {
    document: WrittenDocument;
}) {
    return (
        <article className="flex flex-col gap-5 rounded-md bg-card px-6 py-8 shadow-float md:px-11 md:py-10 print:p-0 print:shadow-none">
            <header className="flex flex-col gap-1 border-b pb-4">
                <p className="text-sm font-medium text-muted-foreground">
                    {document.owner}
                </p>
                <h1 className="text-2xl leading-8 font-semibold">
                    {document.title}
                </h1>
                <p className="text-sm text-muted-foreground">
                    Versión {document.version} · publicada el{' '}
                    {document.publishedOn}
                </p>
            </header>
            {document.sections.map((section) => (
                <section key={section.heading} className="flex flex-col gap-1">
                    <h2 className="font-semibold">{section.heading}</h2>
                    {paragraphs(section.text).map((paragraph) => (
                        <p key={paragraph}>{paragraph}</p>
                    ))}
                </section>
            ))}
        </article>
    );
}
