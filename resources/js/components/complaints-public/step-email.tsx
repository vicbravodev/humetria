import { MailQuestion } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Draft } from '@/lib/complaints/form';

/**
 * Step 5, optional: an address to be told there is news, and nothing else. Said in the words of what it is: nobody reads it,
 * the system only uses it to write «hay novedades», and it is erased after the case closes.
 */
export default function EmailStep({
    draft,
    onChange,
}: {
    draft: Draft;
    onChange: (changes: Partial<Draft>) => void;
}) {
    return (
        <div className="flex flex-col gap-4">
            <h2 className="flex items-center gap-2 text-xl font-semibold">
                <MailQuestion aria-hidden className="size-5 text-primary" />
                ¿Quiere que le avisemos cuando haya novedades? (opcional)
            </h2>
            <p>
                Si deja un correo, <strong>nadie lo verá nunca</strong>, ni la
                persona que atiende su caso. Solo lo usará el sistema para
                escribirle «hay novedades en su caso», con un enlace a la página
                de seguimiento, sin la referencia ni nada del caso. Lo borramos
                30 días después de que se cierre.
            </p>
            <p className="text-base text-muted-foreground">
                Un correo del trabajo puede delatarle: si lo deja, prefiera uno
                personal.
            </p>
            <div className="grid gap-2">
                <Label htmlFor="notify-email">Correo (opcional)</Label>
                <Input
                    id="notify-email"
                    type="email"
                    autoComplete="off"
                    value={draft.notifyEmail}
                    maxLength={190}
                    onChange={(event) =>
                        onChange({ notifyEmail: event.target.value })
                    }
                />
            </div>
        </div>
    );
}
