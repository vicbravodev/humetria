import { Check, Copy } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

/** A link just issued, shown this once to copy (a QR screen's, or a person's to link their phone), with what to know. */
export default function OneTimeLink({
    id,
    label,
    link,
    children,
}: {
    id: string;
    label: string;
    link: string;
    children: ReactNode;
}) {
    const [copied, copy] = useClipboard();

    return (
        <div className="grid gap-2 rounded-lg border bg-accent/40 p-4">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex gap-2">
                <Input
                    id={id}
                    readOnly
                    value={link}
                    onFocus={(event) => event.currentTarget.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy(link)}
                >
                    {copied === link ? (
                        <Check aria-hidden />
                    ) : (
                        <Copy aria-hidden />
                    )}
                    {copied === link ? 'Copiado' : 'Copiar'}
                </Button>
            </div>
            <p className="text-sm text-pretty text-muted-foreground">
                {children}
            </p>
        </div>
    );
}
