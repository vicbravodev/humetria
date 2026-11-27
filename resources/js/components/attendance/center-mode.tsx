import { router } from '@inertiajs/react';
import { Route } from 'lucide-react';
import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import IconTile from '@/components/humetria/icon-tile';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { mode as saveMode } from '@/routes/tenant/attendance/centers';
import type { CenterMarkMode } from '@/types';

const OPTIONS: { value: CenterMarkMode; label: string }[] = [
    { value: 'company', label: 'Igual que la empresa' },
    { value: 'field', label: 'En campo' },
];

/**
 * «Cómo marcan aquí»: by the company's policy (zones and QR) or in the field, from the phone wherever they are, with the
 * position kept and no zone asked. Saved as soon as RH picks one (it goes to the audit trail); read-only for a viewer.
 */
export default function CenterMode({
    centerId,
    mode,
    canManage,
}: {
    centerId: string;
    mode: CenterMarkMode;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [error, setError] = useState<string | undefined>(undefined);
    const change = (next: CenterMarkMode) =>
        router.put(
            saveMode.url({ tenant: tenantSlug, workCenter: centerId }),
            { mode: next },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors['mode']),
                onSuccess: () => setError(undefined),
            },
        );

    return (
        <section
            aria-labelledby="como-marcan"
            data-tour="attendance-center-mode"
            className="flex flex-col gap-4 card p-5 md:p-6"
        >
            <div className="flex items-start gap-4">
                <IconTile icon={Route} />
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h2
                        id="como-marcan"
                        className="text-lg leading-[26px] font-semibold"
                    >
                        Cómo marcan aquí
                    </h2>
                    <p className="text-sm text-pretty text-muted-foreground">
                        {mode === 'field'
                            ? 'En campo: cada persona marca desde su celular donde esté. Se guarda su ubicación, sin zona que la limite; el QR es opcional.'
                            : 'Igual que la empresa: zonas y QR como dice la política de asistencia.'}
                    </p>
                </div>
            </div>
            <fieldset disabled={!canManage} className="min-w-0">
                <ChoiceField
                    name="mode"
                    legend="Modo de este centro"
                    options={OPTIONS}
                    value={mode}
                    onChange={change}
                    columns={2}
                    error={error}
                />
            </fieldset>
        </section>
    );
}
