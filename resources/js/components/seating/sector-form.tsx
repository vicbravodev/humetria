import { Form } from '@inertiajs/react';
import { Armchair, Factory } from 'lucide-react';
import InputError from '@/components/humetria/input-error';
import SettingRow from '@/components/humetria/setting-row';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { update } from '@/routes/tenant/seating/profile';
import type { SectorOption, SeatingProfile } from '@/types';

const CARD =
    'flex cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

/** The declaration of a center (pantallas: Ajustes): its sector, whether nobody works standing, and a note. */
export default function SectorForm({
    workCenterId,
    sectors,
    profile,
}: {
    workCenterId: string;
    sectors: SectorOption[];
    profile: SeatingProfile | null;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...update.form([tenantSlug, workCenterId])}
            options={{ preserveScroll: true }}
            noValidate
            className="flex flex-col gap-7"
        >
            {({ processing, errors }) => (
                <>
                    <SettingRow
                        icon={Factory}
                        title="Sector del centro"
                        description="La ley alcanza a servicios, comercio y análogos; a los industriales, solo donde la naturaleza del trabajo permite el asiento."
                    >
                        <fieldset className="grid gap-2 sm:grid-cols-2">
                            <legend className="sr-only">Sector</legend>
                            {sectors.map((sector) => (
                                <label key={sector.value} className={CARD}>
                                    <input
                                        type="radio"
                                        name="sector"
                                        value={sector.value}
                                        defaultChecked={
                                            profile?.sector === sector.value
                                        }
                                        className="size-5 shrink-0 accent-primary"
                                    />
                                    {sector.label}
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={errors['sector']} />
                    </SettingRow>
                    <SettingRow
                        icon={Armchair}
                        title="Personas de pie"
                        description="Si en este centro nadie trabaja de pie, decláralo: queda con fecha en el expediente."
                    >
                        <input
                            type="hidden"
                            name="no_standing_positions"
                            value="0"
                        />
                        <label className={CARD}>
                            <input
                                type="checkbox"
                                name="no_standing_positions"
                                value="1"
                                defaultChecked={
                                    profile?.noStandingPositions ?? false
                                }
                                className="size-5 shrink-0 accent-primary"
                            />
                            En este centro nadie trabaja de pie
                        </label>
                        <Textarea
                            name="note"
                            aria-label="Nota de la declaración"
                            placeholder="Nota (opcional)"
                            defaultValue={profile?.note ?? ''}
                            rows={3}
                        />
                        <InputError message={errors['note']} />
                    </SettingRow>
                    <div className="flex justify-end border-t pt-[18px]">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            Guardar declaración
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
