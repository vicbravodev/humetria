import { Link } from '@inertiajs/react';
import { ImageUp } from 'lucide-react';
import type { RouteDefinition } from '@/wayfinder';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * One logo of a company in its brand form: the one it has (shown on the page it is made for: the dark version on a dark
 * one), the way to take it off, and the file to change it.
 */
export default function TenantLogoSlot({
    id,
    name,
    label,
    hint,
    current,
    remove,
    onDark = false,
    error,
    onRemoved,
}: {
    id: string;
    name: string;
    label: string;
    hint: string;
    current: string | null;
    remove: RouteDefinition<'delete'>;
    onDark?: boolean;
    error: string | undefined;
    onRemoved: () => void;
}) {
    return (
        <div className="grid gap-2">
            <p className="text-base font-medium">{label}</p>
            {current !== null && (
                <div
                    className={cn(
                        'flex items-center justify-between gap-4 rounded-lg border p-4',
                        // The dark version is shown on a dark surface whatever the theme: that is where it will be read.
                        onDark && 'dark bg-background text-foreground',
                    )}
                >
                    <img
                        src={current}
                        alt={label}
                        className="h-10 w-auto max-w-48"
                    />
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={remove} as="button" onSuccess={onRemoved}>
                            Quitar
                        </Link>
                    </Button>
                </div>
            )}
            <FileDropZone
                id={id}
                name={name}
                accept="image/png,image/jpeg,image/webp"
                icon={ImageUp}
                title={
                    current === null
                        ? 'Arrastra aquí el archivo o elígelo'
                        : 'Cambiar: arrástralo o elígelo'
                }
                hint={hint}
                invalid={error !== undefined}
                describedBy={`${id}-error`}
            />
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
