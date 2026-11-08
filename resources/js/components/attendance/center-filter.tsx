import Combobox from '@/components/humetria/combobox';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/work-centers';

const ALL_CENTERS = { value: '', label: 'Todos los centros' };

/** Narrows a list of the registro to one center in use, searched on the server; «Todos los centros» undoes it. */
export default function CenterFilter({
    id,
    center,
    onChange,
}: {
    id: string;
    center: { id: string; name: string } | null;
    onChange: (center: string) => void;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div className="w-full sm:w-64">
            <label htmlFor={id} className="sr-only">
                Centro de trabajo
            </label>
            <Combobox
                id={id}
                name="center"
                source={serverSource(options.url(tenantSlug), [ALL_CENTERS])}
                defaultValue={
                    center === null
                        ? ALL_CENTERS
                        : { value: center.id, label: center.name }
                }
                placeholder="Centro de trabajo"
                onChange={(option) => onChange(option.value)}
            />
        </div>
    );
}
