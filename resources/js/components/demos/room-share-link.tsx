import { Check, Link2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import type { DemoShareLink } from '@/types';

/** A link to share live: its QR to scan from the screen, the address and a button to copy it. */
export default function RoomShareLink({
    link,
    alt,
    children,
}: {
    link: DemoShareLink;
    /** What the QR opens, for whoever cannot see it. */
    alt: string;
    children?: ReactNode;
}) {
    const [copied, copy] = useClipboard();

    return (
        <div className="flex flex-col items-start gap-4 sm:flex-row">
            <img
                src={link.qr}
                alt={alt}
                className="size-36 shrink-0 rounded-lg border"
            />
            <div className="flex min-w-0 flex-col gap-2">
                <code className="font-mono text-sm break-all">{link.url}</code>
                {children !== undefined && (
                    <p className="text-sm text-pretty text-muted-foreground">
                        {children}
                    </p>
                )}
                <Button
                    variant="outline"
                    className="w-fit"
                    onClick={() => void copy(link.url)}
                >
                    {copied === link.url ? (
                        <Check aria-hidden />
                    ) : (
                        <Link2 aria-hidden />
                    )}
                    <span aria-live="polite">
                        {copied === link.url
                            ? 'Enlace copiado'
                            : 'Copiar enlace'}
                    </span>
                </Button>
            </div>
        </div>
    );
}
