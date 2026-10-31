import { router } from '@inertiajs/react';
import { Check, Copy, Link2, Mail } from 'lucide-react';
import ConfirmAction from '@/components/telework/confirm-action';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import { link } from '@/routes/tenant/telework/teleworkers';

/**
 * The person's portal link. With an email it went there; without one (or to start over) RH makes a new link to copy and
 * hand over in person. A new link replaces the old one, and it is shown only once: only its hash is kept.
 */
export default function PortalLinkPanel({
    tenantSlug,
    teleworkerId,
    hasEmail,
    newLink,
}: {
    tenantSlug: string;
    teleworkerId: string;
    hasEmail: boolean;
    newLink: string | undefined;
}) {
    const [copied, copy] = useClipboard();

    return (
        <section className="grid gap-4 rounded-xl border bg-card p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-lg bg-accent text-primary">
                        {hasEmail ? (
                            <Mail aria-hidden className="size-5" />
                        ) : (
                            <Link2 aria-hidden className="size-5" />
                        )}
                    </span>
                    <div>
                        <h2 className="text-base font-semibold">
                            Enlace a su portal
                        </h2>
                        <p className="text-[15px] text-muted-foreground">
                            {hasEmail
                                ? 'Se envió a su correo al registrarla.'
                                : 'Sin correo'}
                        </p>
                    </div>
                </div>
                <ConfirmAction
                    title="¿Generar un enlace nuevo?"
                    description="El enlace anterior de esta persona dejará de funcionar. El nuevo se muestra una sola vez para que lo copies."
                    confirmLabel="Generar enlace"
                    onConfirm={() =>
                        router.post(
                            link.url({
                                tenant: tenantSlug,
                                teleworker: teleworkerId,
                            }),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <Button variant="outline">
                        Generar enlace para copiar
                    </Button>
                </ConfirmAction>
            </div>
            {newLink !== undefined && (
                <div className="grid gap-2 rounded-lg border bg-accent/40 p-4">
                    <Label htmlFor="portal-link">Enlace nuevo</Label>
                    <div className="flex gap-2">
                        <Input
                            id="portal-link"
                            readOnly
                            value={newLink}
                            onFocus={(event) => event.currentTarget.select()}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void copy(newLink)}
                        >
                            {copied === newLink ? (
                                <Check aria-hidden />
                            ) : (
                                <Copy aria-hidden />
                            )}
                            {copied === newLink ? 'Copiado' : 'Copiar'}
                        </Button>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Dáselo a la persona en privado. No se vuelve a mostrar y
                        el anterior ya no sirve.
                    </p>
                </div>
            )}
        </section>
    );
}
