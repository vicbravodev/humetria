import { router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AreaResult } from '@/types';

const WHOLE_CENTER = 'all';

/**
 * Narrows the center's results to one area shown; nothing when none is. The minimum of five applies after the filter,
 * on the server.
 */
export default function AreaFilter({
    url,
    areas,
    selected,
}: {
    url: string;
    areas: AreaResult[];
    /** The area the results are narrowed to; null for the whole center. */
    selected: { value: string } | null;
}) {
    if (areas.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2 sm:w-72">
            <Label htmlFor="results-area">Área</Label>
            <Select
                value={selected?.value ?? WHOLE_CENTER}
                onValueChange={(value) =>
                    router.get(
                        url,
                        value === WHOLE_CENTER ? {} : { area: value },
                        { preserveScroll: true, replace: true },
                    )
                }
            >
                <SelectTrigger id="results-area">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={WHOLE_CENTER}>
                        Todo el centro de trabajo
                    </SelectItem>
                    {areas.map((area) => (
                        <SelectItem key={area.id} value={area.id}>
                            {area.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
