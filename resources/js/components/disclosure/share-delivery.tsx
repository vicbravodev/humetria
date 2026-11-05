import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { page as workersPage } from '@/routes/tenant/disclosure';
import { poster } from '@/routes/tenant/disclosure/page';

/**
 * «Cómo llega» (board V2-N035-Compartir): the page of the people, opened with their link or the poster, and the poster to
 * print; then the button that publishes it, or when it was published.
 */
export default function ShareDelivery({
    published,
    canPublish,
    center,
    processing,
}: {
    published: string | null;
    canPublish: boolean;
    center: string;
    processing: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const link = 'touch-hit text-sm font-medium text-primary hover:underline';

    return (
        <>
            <section
                aria-labelledby="share-how"
                className="flex flex-col gap-3"
            >
                <h2 id="share-how" className="text-lg font-semibold">
                    Cómo llega
                </h2>
                <ul className="overflow-hidden card">
                    <li className="px-[18px] py-3.5">
                        <p className="font-medium">
                            En la página de tus personas
                        </p>
                        <p className="text-sm text-muted-foreground">
                            La abren con su enlace o con el código del cartel,
                            sin iniciar sesión.
                        </p>
                    </li>
                    <li className="flex flex-wrap items-center justify-between gap-3 border-t px-[18px] py-3.5">
                        <span>
                            <span className="block font-medium">
                                Versión para imprimir
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                El cartel con el código, para el mural.
                            </span>
                        </span>
                        <Link href={poster(tenantSlug)} className={link}>
                            Ver el cartel
                        </Link>
                    </li>
                </ul>
            </section>
            {published === null ? (
                <div className="flex flex-wrap items-center gap-4">
                    <Button
                        type="submit"
                        size="lg"
                        disabled={processing || !canPublish}
                    >
                        {processing && <Spinner />}
                        Publicar para {center}
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        Después lo ves en la página de tus personas.
                    </p>
                </div>
            ) : (
                <div className="flex flex-wrap items-center gap-4">
                    <p className="font-medium">Publicado el {published}.</p>
                    <Link href={workersPage(tenantSlug)} className={link}>
                        Ver la página de tus personas
                    </Link>
                </div>
            )}
        </>
    );
}
