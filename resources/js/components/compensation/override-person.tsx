import { Badge } from '@/components/ui/badge';
import type { OverrideRow } from '@/types';

/** A person's cell: their company ID and category, or a note that the roster no longer has them to cost. */
export default function OverridePerson({ person }: { person: OverrideRow }) {
    return (
        <div className="min-w-36">
            <p className="flex flex-wrap items-center gap-2 font-medium">
                {person.employee_number}
                {!person.active && (
                    <Badge variant="warning" className="h-5 px-2 text-xs">
                        Sin persona que costear
                    </Badge>
                )}
            </p>
            <p className="text-caption text-muted-foreground">
                {person.grade ?? 'No está activa en el padrón con categoría'}
            </p>
        </div>
    );
}
