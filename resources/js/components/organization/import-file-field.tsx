import { FileSpreadsheet, FileUp } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { importFileHint } from '@/lib/employees';
import { cn } from '@/lib/utils';

/**
 * The CSV to upload. The native input covers the whole box, so a file can be dropped on it or chosen with a click;
 * once chosen, the box shrinks to the file's name with the way to change it.
 */
export default function ImportFileField({
    error,
    rows,
}: {
    error: string | undefined;
    /** How many rows the checked file has, once checked. */
    rows: number | undefined;
}) {
    const [name, setName] = useState<string | null>(null);

    return (
        <div className="grid gap-2">
            <div
                className={cn(
                    'relative flex items-center gap-3.5 rounded-xl has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                    name === null
                        ? 'flex-col justify-center border-[1.5px] border-dashed border-input bg-muted px-6 py-10 text-center'
                        : 'border bg-card px-[18px] py-3.5',
                    error !== undefined && 'border-destructive',
                )}
            >
                <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-accent text-primary">
                    {name === null ? (
                        <FileUp aria-hidden className="size-5" />
                    ) : (
                        <FileSpreadsheet aria-hidden className="size-5" />
                    )}
                </span>
                <label
                    htmlFor="employees-file"
                    className="flex min-w-0 flex-1 flex-col"
                >
                    <span className="truncate font-medium">
                        {name ?? 'Arrastra aquí tu CSV o elígelo'}
                    </span>
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        {importFileHint(name, rows)}
                    </span>
                </label>
                {name !== null && (
                    <span aria-hidden className="text-sm font-medium">
                        Cambiar archivo
                    </span>
                )}
                <input
                    id="employees-file"
                    name="file"
                    type="file"
                    accept=".csv,text/csv"
                    onChange={(event) =>
                        setName(event.target.files?.[0]?.name ?? null)
                    }
                    className="absolute inset-0 cursor-pointer opacity-0"
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined ? undefined : 'employees-file-error'
                    }
                />
            </div>
            <InputError
                id="employees-file-error"
                role="alert"
                message={error}
            />
        </div>
    );
}
