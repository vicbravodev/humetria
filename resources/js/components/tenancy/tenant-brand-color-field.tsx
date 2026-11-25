import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

/**
 * The accent of a company, as part of the brand form: off, its pages keep Humetría's; on, the color picked is sent
 * (a disabled field is not sent, so off sends nothing and the accent is cleared).
 */
export default function TenantBrandColorField({
    current,
    error,
}: {
    current: string | null;
    error: string | undefined;
}) {
    const [own, setOwn] = useState(current !== null);

    return (
        <div className="grid gap-2">
            <Label
                htmlFor="tenant-use-brand-color"
                className="min-h-11 gap-3 text-base"
            >
                <Checkbox
                    id="tenant-use-brand-color"
                    name="use_brand_color"
                    value="1"
                    checked={own}
                    onCheckedChange={(checked) => setOwn(checked === true)}
                />
                Usar un color de acento propio
            </Label>
            <div className="flex items-center gap-3">
                <input
                    id="tenant-brand-color"
                    type="color"
                    name="brand_color"
                    aria-label="Color de acento"
                    defaultValue={current ?? '#0b7a6f'}
                    disabled={!own}
                    className="h-11 w-14 cursor-pointer rounded-md border bg-card p-1 disabled:cursor-not-allowed disabled:opacity-40"
                />
                <p className="text-sm text-muted-foreground">
                    {own
                        ? 'Se ajusta solo, en claro y en oscuro, para que el texto se lea bien.'
                        : 'Sin color propio, sus páginas usan el verde de Humetría.'}
                </p>
            </div>
            <InputError message={error} />
        </div>
    );
}
