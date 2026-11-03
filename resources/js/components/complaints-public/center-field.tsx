import Combobox from '@/components/humetria/combobox';
import { Label } from '@/components/ui/label';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { centers } from '@/routes/complaints/public';

/** The work center, searched on the server: optional, because in a small center saying it narrows down who writes. */
export default function CenterField({
    code,
    value,
    onChange,
}: {
    code: string;
    value: Option | null;
    onChange: (center: Option) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="complaint-center">
                Centro de trabajo (opcional)
            </Label>
            <Combobox
                id="complaint-center"
                name="work_center_id"
                source={serverSource(centers.url({ code }))}
                placeholder="Busque su centro de trabajo"
                {...(value === null ? {} : { defaultValue: value })}
                onChange={onChange}
            />
            <p className="text-caption text-muted-foreground">
                Si su centro es pequeño, decirlo puede acotar quién es usted.
                Déjelo vacío si prefiere no indicarlo.
            </p>
        </div>
    );
}
