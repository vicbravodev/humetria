import { Link } from '@inertiajs/react';
import { Check, Eye, Link2, Printer } from 'lucide-react';
import ChannelActions from '@/components/complaints/channel-actions';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { poster } from '@/routes/tenant/complaints';
import type { ChannelState } from '@/types';

/**
 * «Cómo llegan las personas» (board V4-Quejas-Canal): the QR and the address of the channel, to copy, to print on its poster
 * and to see as a worker does, and pausing it or changing its address.
 */
export default function ChannelReach({
    channel,
}: {
    channel: ChannelState & { url: string; qr: string };
}) {
    const tenantSlug = useTenantSlug();
    const [copied, copy] = useClipboard();

    return (
        <section
            aria-labelledby="reach"
            className="flex flex-col gap-4 card p-6"
        >
            <h2 id="reach" className="text-lg font-semibold">
                Cómo llegan las personas
            </h2>
            <div className="flex items-start gap-4">
                <img
                    src={channel.qr}
                    alt="Código QR del canal de quejas"
                    className="size-24 shrink-0 rounded-lg border"
                />
                <div className="flex min-w-0 flex-col gap-1.5">
                    <code className="font-mono text-sm break-all">
                        {channel.url.replace(/^https?:\/\//u, '')}
                    </code>
                    <p className="text-sm text-muted-foreground">
                        El mismo enlace va en el cartel y en la página de las
                        personas de tus centros.
                    </p>
                </div>
            </div>
            <div className="flex flex-col gap-2">
                <Button
                    variant="outline"
                    className="justify-start"
                    onClick={() => void copy(channel.url)}
                >
                    {copied === channel.url ? (
                        <Check aria-hidden />
                    ) : (
                        <Link2 aria-hidden />
                    )}
                    {copied === channel.url
                        ? 'Enlace copiado'
                        : 'Copiar enlace'}
                </Button>
                <Button variant="outline" className="justify-start" asChild>
                    <Link href={poster(tenantSlug)}>
                        <Printer aria-hidden />
                        Cartel para imprimir
                    </Link>
                </Button>
                <Button variant="outline" className="justify-start" asChild>
                    <a href={channel.url} target="_blank" rel="noreferrer">
                        <Eye aria-hidden />
                        Ver como persona trabajadora
                    </a>
                </Button>
            </div>
            <div className="flex flex-wrap gap-1 border-t pt-3">
                <ChannelActions status={channel.status} />
            </div>
        </section>
    );
}
