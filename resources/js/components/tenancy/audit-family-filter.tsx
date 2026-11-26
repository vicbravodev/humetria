import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AuditFamily } from '@/types';

const ALL = 'all';

/** Narrows the trail to one family of event; «Todos los eventos» undoes it. */
export default function AuditFamilyFilter({
    family,
    families,
    onChange,
}: {
    family: string;
    families: AuditFamily[];
    onChange: (family: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor="audit-family">Tipo de evento</Label>
            <Select
                value={family === '' ? ALL : family}
                onValueChange={(next) => onChange(next === ALL ? '' : next)}
            >
                <SelectTrigger id="audit-family" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>Todos los eventos</SelectItem>
                    {families.map(({ value, label }) => (
                        <SelectItem key={value} value={value}>
                            {label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
