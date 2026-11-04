import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ComplaintCategoryOption } from '@/types';

/** The kind of practice or violence, if the person knows: not choosing is valid. */
export default function StoryCategoryField({
    categories,
    value,
    onChange,
}: {
    categories: ComplaintCategoryOption[];
    value: string;
    onChange: (category: string) => void;
}) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="complaint-category">
                ¿De qué tipo es? (opcional)
            </Label>
            <Select
                value={value === '' ? 'none' : value}
                onValueChange={(next) => onChange(next === 'none' ? '' : next)}
            >
                <SelectTrigger id="complaint-category" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">
                        No estoy seguro o segura
                    </SelectItem>
                    {categories.map((category) => (
                        <SelectItem key={category.value} value={category.value}>
                            {category.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <p className="text-caption text-muted-foreground">
                Es una clasificación de Humetría para ordenar los casos; no
                tiene que acertar.
            </p>
        </div>
    );
}
