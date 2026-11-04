import { Building2, X } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { addCenter, removeCenter } from '@/lib/disclosure';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/work-centers';

/**
 * The work centers a text is published to: every one in use, or the ones searched on the server and added one by one
 * (CLAUDE.md: a field that picks a record that grows searches it on the server). It sends `all_centers` or
 * `work_center_ids[]`.
 */
export default function CenterPicker({
    inUse,
    error,
}: {
    /** How many centers are in use, to say what «todos» means. */
    inUse: number;
    error: string | undefined;
}) {
    const tenantSlug = useTenantSlug();
    const [all, setAll] = useState(true);
    const [chosen, setChosen] = useState<Option[]>([]);

    return (
        <fieldset className="grid gap-3">
            <legend className="mb-1 flex items-center gap-2 text-sm font-medium">
                <Building2 aria-hidden className="size-4" />
                Centros de trabajo
            </legend>
            <div className="flex items-center gap-3">
                <Checkbox
                    id="publish-all-centers"
                    name="all_centers"
                    value="1"
                    checked={all}
                    onCheckedChange={(checked) => setAll(checked === true)}
                />
                <Label htmlFor="publish-all-centers">
                    Todos los centros en uso ({inUse})
                </Label>
            </div>
            {!all && (
                <>
                    <Combobox
                        id="publish-center"
                        name="center_search"
                        source={serverSource(options.url(tenantSlug))}
                        placeholder="Busca y agrega un centro"
                        invalid={error !== undefined}
                        onChange={(center) =>
                            setChosen((current) => addCenter(current, center))
                        }
                    />
                    <ul
                        aria-label="Centros elegidos"
                        className="flex flex-wrap gap-2"
                    >
                        {chosen.map((center) => (
                            <li
                                key={center.value}
                                className="inline-flex items-center gap-1 rounded-full bg-accent py-1 pr-1 pl-3 text-sm text-primary"
                            >
                                <input
                                    type="hidden"
                                    name="work_center_ids[]"
                                    value={center.value}
                                />
                                {center.label}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-6"
                                    aria-label={`Quitar ${center.label}`}
                                    onClick={() =>
                                        setChosen((current) =>
                                            removeCenter(current, center.value),
                                        )
                                    }
                                >
                                    <X aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </>
            )}
            <InputError message={error} />
        </fieldset>
    );
}
