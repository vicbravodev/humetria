import { Check, Copy, Link2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { describeDate } from '@/lib/calendar-date';

/** The address the people use to reach the channel, to copy, and since when it exists. */
export default function ChannelLinkCard({
    url,
    publishedOn,
}: {
    url: string;
    publishedOn: string | null;
}) {
    const [copied, copy] = useClipboard();
    const published =
        publishedOn === null ? undefined : describeDate(publishedOn);

    return (
        <section className="flex flex-col gap-3 card p-5">
            <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Link2 aria-hidden className="size-4" />
                Enlace del canal
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <code className="min-w-0 flex-[1_1_240px] rounded-lg bg-muted px-3 py-2.5 text-sm break-all">
                    {url}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy(url)}
                >
                    {copied === url ? (
                        <Check aria-hidden />
                    ) : (
                        <Copy aria-hidden />
                    )}
                    {copied === url ? 'Copiado' : 'Copiar enlace'}
                </Button>
            </div>
            {published !== undefined && (
                <p className="text-sm text-muted-foreground">
                    Publicado el {published}.
                </p>
            )}
        </section>
    );
}
