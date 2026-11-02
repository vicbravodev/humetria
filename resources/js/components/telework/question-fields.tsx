import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ChecklistQuestion, RequirementOption } from '@/types';

const selectClass =
    'h-11 w-full rounded-lg border border-input bg-card px-3 text-meta focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** The fields a question is written with; `prefix` keeps ids unique when several forms are open. */
export default function QuestionFields({
    prefix,
    question,
    requirements,
    errors,
}: {
    prefix: string;
    question?: ChecklistQuestion;
    requirements: RequirementOption[];
    errors: Record<string, string>;
}) {
    return (
        <>
            <div className="grid gap-2 sm:col-span-2">
                <Label htmlFor={`${prefix}-text`}>Pregunta</Label>
                <Textarea
                    id={`${prefix}-text`}
                    name="text"
                    rows={2}
                    defaultValue={question?.text}
                    required
                />
                <InputError message={errors['text']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-favorable`}>
                    Respuesta favorable
                </Label>
                <select
                    id={`${prefix}-favorable`}
                    name="favorable"
                    defaultValue={question?.favorable ?? 'yes'}
                    className={selectClass}
                >
                    <option value="yes">Sí</option>
                    <option value="no">No</option>
                    <option value="informative">Solo informativa</option>
                </select>
                <InputError message={errors['favorable']} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-requirement`}>
                    Requisito que cubre
                </Label>
                <select
                    id={`${prefix}-requirement`}
                    name="requirement"
                    defaultValue={question?.requirement ?? ''}
                    className={selectClass}
                >
                    <option value="">Ninguno</option>
                    {requirements.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.value} · {option.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors['requirement']} />
            </div>
            <label className="flex items-center gap-2 text-meta sm:col-span-2">
                <Input
                    type="checkbox"
                    name="sensitive"
                    value="1"
                    defaultChecked={question?.sensitive}
                    className="size-4"
                />
                Pregunta sensible (dato de salud): se contesta solo con
                consentimiento
            </label>
        </>
    );
}
