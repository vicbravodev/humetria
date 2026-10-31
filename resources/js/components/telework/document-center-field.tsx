import { Building2, X } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { addCenter, removeCenter } from '@/lib/telework/documents';
import { options } from '@/routes/tenant/telework/work-centers';

/** Step 3: the work centers that get this version: all those in use, or the ones searched and added one by one. */
export default function DocumentCenterField({
    tenantSlug,
    error,
}: {
    tenantSlug: string;
    error: string | undefined;
}) {
    const [chosen, setChosen] = useState<Option[]>([]);
    const [all, setAll] = useState(false);

    return (
        <SettingRow
            icon={Building2}
            title="Paso 3 · Centros de trabajo"
            description="El mismo archivo se guarda como una versión en cada centro que elijas."
        >
            <div className="flex items-center gap-3">
                <Checkbox
                    id="document-all-centers"
                    name="all_centers"
                    value="1"
                    checked={all}
                    onCheckedChange={(checked) => setAll(checked === true)}
                />
                <Label htmlFor="document-all-centers">
                    Todos los centros de trabajo
                </Label>
            </div>
            {!all && (
                <>
                    <Combobox
                        id="document-center"
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
            <InputError role="alert" message={error} />
        </SettingRow>
    );
}
