import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';

/** The photo or PDF of a means of the disclosure (C4: a drop zone, never the native field), with what was wrong with it. */
export default function EvidenceField({
    id,
    label,
    title,
    hint,
    error,
}: {
    id: string;
    label: string;
    title: string;
    hint: string;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <p className="text-sm font-medium">{label}</p>
            <FileDropZone
                id={id}
                name="evidence"
                accept="application/pdf,image/jpeg,image/png"
                title={title}
                hint={hint}
                invalid={error !== undefined}
                describedBy={`${id}-error`}
            />
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
