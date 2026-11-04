import { useForm } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import ContentEditor from '@/components/disclosure/content-editor';
import PlainText from '@/components/disclosure/plain-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { store } from '@/routes/tenant/disclosure/contents/draft';
import type { ContentBlock } from '@/types';

/**
 * One block of 5.7 that the company writes (pantalla Ajustes: a card with its fields): what it is, the text in force with where
 * it was published, and the draft or the button that starts one.
 */
export default function ContentCard({
    block,
    centersInUse,
}: {
    block: ContentBlock;
    centersInUse: number;
}) {
    const tenantSlug = useTenantSlug();
    const start = useForm({});
    const { latest, draft } = block;

    return (
        <section
            aria-labelledby={`block-${block.kind}`}
            className="grid gap-4 card p-5 md:p-6"
        >
            <header className="flex flex-wrap items-center gap-3">
                <h2
                    id={`block-${block.kind}`}
                    className="min-w-0 flex-1 text-lg font-semibold"
                >
                    <span className="mr-2 text-base font-medium text-muted-foreground">
                        {block.inciso})
                    </span>
                    {block.label}
                </h2>
                {latest === null ? (
                    <Badge variant="outline">Sin publicar</Badge>
                ) : (
                    <Badge variant="success">Versión {latest.version}</Badge>
                )}
            </header>
            {latest !== null && (
                <div className="grid gap-2">
                    <p className="text-meta text-muted-foreground">
                        Publicado el {latest.publishedOn} en{' '}
                        {countOf(latest.centers, 'centro', 'centros')}.
                    </p>
                    <PlainText text={latest.text} />
                </div>
            )}
            {draft === null ? (
                <div className="grid gap-2">
                    {latest === null && (
                        <p className="text-sm text-muted-foreground">
                            {block.note}
                        </p>
                    )}
                    <div>
                        <Button
                            variant="outline"
                            disabled={start.processing}
                            onClick={() =>
                                start.post(
                                    store.url({
                                        tenant: tenantSlug,
                                        kind: block.kind,
                                    }),
                                )
                            }
                        >
                            {start.processing ? (
                                <Spinner />
                            ) : (
                                <Pencil aria-hidden />
                            )}
                            {latest === null ? 'Escribir' : 'Nueva versión'}
                        </Button>
                    </div>
                </div>
            ) : (
                <ContentEditor
                    key={draft.id}
                    block={block}
                    draft={draft}
                    centersInUse={centersInUse}
                />
            )}
        </section>
    );
}
