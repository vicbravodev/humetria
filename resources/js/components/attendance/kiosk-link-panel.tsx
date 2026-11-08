import { Check, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useClipboard } from '@/hooks/use-clipboard';

/** The link of a screen just issued, shown this once: it opens once, on the tablet that will show the code. */
export default function KioskLinkPanel({ link }: { link: string }) {
    const [copied, copy] = useClipboard();

    return (
        <div className="grid gap-2 rounded-lg border bg-accent/40 p-4">
            <Label htmlFor="kiosk-link">Enlace de la pantalla</Label>
            <div className="flex gap-2">
                <Input
                    id="kiosk-link"
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
                Ábrelo en la tableta que mostrará el código: funciona una sola
                vez y no se vuelve a mostrar. Si un chat o un correo lo abre
                antes (al mostrar su vista previa), vuelve a emitirlo.
            </p>
        </div>
    );
}
