import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { groupCode } from '@/lib/complaints/receipt';

/** A code in groups of four, big, with the button that copies it whole. */
export default function CodeRow({
    label,
    code,
}: {
    label: string;
    code: string;
}) {
    const [copied, copy] = useClipboard();

    return (
        <div className="grid gap-1.5">
            <p className="text-base text-muted-foreground">{label}</p>
            <div className="flex flex-wrap items-center gap-3">
                <code className="rounded-lg bg-muted px-3 py-2.5 text-xl font-semibold tracking-wider">
                    {groupCode(code)}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy(code)}
                >
                    {copied === code ? (
                        <Check aria-hidden />
                    ) : (
                        <Copy aria-hidden />
                    )}
                    {copied === code ? 'Copiado' : 'Copiar'}
                </Button>
            </div>
        </div>
    );
}
