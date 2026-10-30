import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';
import type { ReportLink as ReportLinkData } from '@/types';

/** A link just opened, to copy now: it is shown only this once. */
export default function ReportLink({ link }: { link: ReportLinkData }) {
    const [copied, copy] = useClipboard();

    return (
        <div className="grid gap-2 rounded-lg border bg-accent/40 p-4">
            <Label htmlFor="report-link">Enlace al informe</Label>
            <div className="flex gap-2">
                <Input
                    id="report-link"
                    readOnly
                    value={link.url}
                    onFocus={(event) => event.currentTarget.select()}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void copy(link.url)}
                >
                    {copied === link.url ? (
                        <Check aria-hidden="true" />
                    ) : (
                        <Copy aria-hidden="true" />
                    )}
                    {copied === link.url ? 'Copiado' : 'Copiar'}
                </Button>
            </div>
            <p className="text-sm text-muted-foreground">
                Abre el informe sin cuenta hasta el {link.expiresAt}. Cópialo
                ahora: no se vuelve a mostrar.
            </p>
        </div>
    );
}
