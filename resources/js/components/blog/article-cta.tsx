import { Button } from '@/components/ui/button';

/** The close of an article: the demo, or the page of the tool it is about. Outside the article, so it is not part of its text. */
export default function ArticleCta({
    toolName,
    toolUrl,
}: {
    toolName: string;
    toolUrl: string;
}) {
    return (
        <aside
            data-test="article-cta"
            className="mx-auto mt-14 w-full max-w-[1000px] px-5 sm:px-8"
        >
            <div className="grid gap-5 rounded-2xl border bg-accent p-7 text-accent-foreground sm:p-10">
                <h2 className="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">
                    ¿Quieres verlo funcionando?
                </h2>
                <p className="max-w-[56ch] leading-7 text-pretty">
                    Agenda una demo y te mostramos cómo Humetría lo resuelve con
                    tu gente.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button asChild size="lg" className="rounded-full">
                        <a href="/#agenda-una-demo">Agenda una demo</a>
                    </Button>
                    <Button
                        asChild
                        size="lg"
                        variant="outline"
                        className="rounded-full"
                    >
                        <a href={toolUrl}>Ver {toolName}</a>
                    </Button>
                </div>
            </div>
        </aside>
    );
}
