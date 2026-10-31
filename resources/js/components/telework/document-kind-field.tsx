import { FileText } from 'lucide-react';
import SettingRow from '@/components/humetria/setting-row';
import InputError from '@/components/input-error';
import type { DocumentKindOption } from '@/types';

const CARD =
    'flex cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

/** Step 1: which document of the norm this PDF is. */
export default function DocumentKindField({
    kinds,
    chosen,
    onChoose,
    error,
}: {
    kinds: DocumentKindOption[];
    chosen: string;
    onChoose: (kind: string) => void;
    error: string | undefined;
}) {
    return (
        <SettingRow
            icon={FileText}
            title="Paso 1 · Tipo de documento"
            description="Elige cuál documento de la norma vas a subir."
        >
            <fieldset className="grid gap-2 sm:grid-cols-2">
                <legend className="sr-only">Tipo de documento</legend>
                {kinds.map((kind) => (
                    <label key={kind.value} className={CARD}>
                        <input
                            type="radio"
                            name="kind"
                            value={kind.value}
                            checked={chosen === kind.value}
                            onChange={() => onChoose(kind.value)}
                            className="size-5 shrink-0 accent-primary"
                        />
                        <span>
                            {kind.label}
                            {kind.numeral !== null && (
                                <span className="block text-[13px] text-muted-foreground">
                                    Numeral {kind.numeral}
                                </span>
                            )}
                        </span>
                    </label>
                ))}
            </fieldset>
            <InputError role="alert" message={error} />
        </SettingRow>
    );
}
