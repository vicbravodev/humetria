import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import PlainText from '@/components/disclosure/plain-text';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { write } from '@/routes/tenant/disclosure';
import type { ContentBlock } from '@/types';

/** What the button of a block says: written with questions (board V4-Difusion-Contenidos), go on with its draft or write the next. */
function action(published: boolean, drafting: boolean): string {
    if (drafting) {
        return 'Continuar';
    }

    return published ? 'Nueva versión' : 'Escribir con preguntas';
}

/**
 * One block of 5.7 that the company writes (pantalla Ajustes: a card with its fields): what it is, the text in force with where
 * it was published, and the way to write it with questions.
 */
export default function ContentCard({ block }: { block: ContentBlock }) {
    const tenantSlug = useTenantSlug();
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
            {latest === null && draft === null && (
                <p className="text-sm text-muted-foreground">{block.note}</p>
            )}
            <div>
                <Button variant="outline" asChild>
                    <Link href={write.url([tenantSlug, block.kind])}>
                        <Pencil aria-hidden />
                        {action(latest !== null, draft !== null)}
                    </Link>
                </Button>
            </div>
        </section>
    );
}
