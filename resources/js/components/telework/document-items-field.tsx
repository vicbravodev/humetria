import { ListChecks } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { DocumentKindOption } from '@/types';

/** Step 4: the items of the norm the document covers. What is left unmarked is warned about, but publishing is allowed. */
export default function DocumentItemsField({
    kind,
    error,
}: {
    kind: DocumentKindOption | undefined;
    error: string | undefined;
}) {
    const items = kind?.items ?? [];

    return (
        <SettingRow
            icon={ListChecks}
            title="Paso 4 · Incisos que cubre"
            description={
                items.length === 0
                    ? 'Este tipo de documento no tiene incisos que marcar.'
                    : `Marca los incisos del numeral ${kind?.numeral ?? ''} que cubre el documento. Los que dejes sin marcar se avisarán como faltantes.`
            }
        >
            <ul className="grid gap-2">
                {items.map((item) => (
                    <li key={item.value} className="flex items-center gap-3">
                        <Checkbox
                            id={`document-item-${item.value}`}
                            name="covered_items[]"
                            value={item.value}
                        />
                        <Label htmlFor={`document-item-${item.value}`}>
                            {item.value}) {item.label}
                        </Label>
                    </li>
                ))}
            </ul>
            <InputError role="alert" message={error} />
        </SettingRow>
    );
}
