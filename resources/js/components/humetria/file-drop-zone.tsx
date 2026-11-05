import { Upload } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent, DragEvent } from 'react';
import { cn } from '@/lib/utils';

/**
 * A zone to drop a file on or to click (C4, rediseño v2), in place of the native «Choose File». The real input lives
 * inside, visually hidden: it takes the focus, Enter or Space opens the picker, and a form sends it under its `name` as
 * before. A dropped file is put in that input. With `onFiles` the zone hands the files over and forgets them (the caller
 * lists them, as the portal's photos do); without it, it names what was chosen.
 */
export default function FileDropZone({
    title,
    hint,
    accept,
    name,
    id,
    multiple = false,
    invalid = false,
    describedBy,
    testId,
    icon: Icon = Upload,
    onFiles,
}: {
    title: string;
    hint?: string;
    accept: string;
    name?: string;
    id?: string;
    multiple?: boolean;
    invalid?: boolean;
    describedBy?: string;
    testId?: string;
    icon?: LucideIcon;
    onFiles?: (files: File[]) => void;
}) {
    const [chosen, setChosen] = useState<string[]>([]);
    const [over, setOver] = useState(false);

    const take = (files: FileList | null, input: HTMLInputElement | null) => {
        const list = Array.from(files ?? []);

        if (onFiles === undefined) {
            setChosen(list.map((file) => file.name));
        } else {
            onFiles(list);

            if (input !== null) {
                input.value = '';
            }
        }
    };

    const drop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setOver(false);
        const input = event.currentTarget.querySelector('input');

        if (input !== null && onFiles === undefined) {
            input.files = event.dataTransfer.files;
        }

        take(event.dataTransfer.files, input);
    };

    return (
        <label
            onDragOver={(event) => {
                event.preventDefault();
                setOver(true);
            }}
            onDragLeave={() => setOver(false)}
            onDrop={drop}
            className={cn(
                'flex min-h-[88px] cursor-pointer items-center gap-3.5 rounded-xl border-[1.5px] border-dashed border-input bg-card px-4 py-3.5 transition-colors duration-100 ease-out hover:bg-muted',
                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                over && 'border-primary bg-accent',
                invalid && 'border-destructive',
            )}
        >
            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <Icon aria-hidden className="size-5" />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5">
                <span className="font-medium">{title}</span>
                {chosen.length > 0 ? (
                    <span className="truncate text-sm text-primary">
                        {chosen.join(', ')}
                    </span>
                ) : (
                    hint !== undefined && (
                        <span className="text-sm text-muted-foreground">
                            {hint}
                        </span>
                    )
                )}
            </span>
            <input
                id={id}
                name={name}
                type="file"
                accept={accept}
                multiple={multiple}
                aria-invalid={invalid ? true : undefined}
                aria-describedby={describedBy}
                data-testid={testId}
                className="sr-only"
                onChange={(event: ChangeEvent<HTMLInputElement>) =>
                    take(event.currentTarget.files, event.currentTarget)
                }
            />
        </label>
    );
}
