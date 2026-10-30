import TenantField from '@/components/tenancy/tenant-field';
import WorkCenterStateField from '@/components/tenancy/work-center-state-field';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

/** The parts of a work center its form writes as text. */
type TextKey = keyof Pick<
    WorkCenterRow,
    | 'name'
    | 'street'
    | 'exterior_number'
    | 'interior_number'
    | 'neighborhood'
    | 'postal_code'
    | 'municipality'
    | 'main_activity'
>;

/** The fields of a work center: its name, its address in parts, as the NOM-035 report names it, and its activity. */
export default function WorkCenterFormFields({
    errors,
    states,
    workCenter,
}: {
    errors: Partial<Record<string, string>>;
    states: MexicanStateOption[];
    workCenter?: WorkCenterRow;
}) {
    const field = (name: TextKey) => ({
        name,
        defaultValue: workCenter?.[name] ?? undefined,
        error: errors[name],
    });

    return (
        <div className="grid gap-4">
            <TenantField
                {...field('name')}
                label="Nombre"
                required
                maxLength={120}
            />
            <div className="grid items-start gap-4 sm:grid-cols-[1fr_7rem_7rem]">
                <TenantField
                    {...field('street')}
                    label="Calle"
                    required
                    maxLength={150}
                />
                <TenantField
                    {...field('exterior_number')}
                    label="Núm. exterior"
                    required
                    maxLength={20}
                />
                <TenantField
                    {...field('interior_number')}
                    label="Núm. interior"
                    maxLength={20}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-[1fr_9rem]">
                <TenantField
                    {...field('neighborhood')}
                    label="Colonia"
                    required
                    maxLength={120}
                />
                <TenantField
                    {...field('postal_code')}
                    label="Código postal"
                    required
                    inputMode="numeric"
                    maxLength={5}
                />
            </div>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <TenantField
                    {...field('municipality')}
                    label="Municipio o alcaldía"
                    required
                    maxLength={120}
                />
                <WorkCenterStateField
                    states={states}
                    defaultValue={workCenter?.state}
                    error={errors['state']}
                />
            </div>
            <TenantField
                {...field('main_activity')}
                label="Actividad principal"
                required
                maxLength={200}
            />
        </div>
    );
}
