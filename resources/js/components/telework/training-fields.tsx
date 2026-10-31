import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/input-error';
import TrainingAttendeesField from '@/components/telework/training-attendees-field';
import TrainingTopicsField from '@/components/telework/training-topics-field';
import { Label } from '@/components/ui/label';
import type { Option } from '@/lib/option-search';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/telework/work-centers';
import type { TrainingChoice } from '@/types';

const CARD =
    'flex cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 transition-colors duration-100 ease-out hover:bg-muted has-checked:border-2 has-checked:border-primary has-checked:bg-accent has-checked:px-[15px] has-checked:py-[11px] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';

type Errors = Partial<Record<string, string>>;

/** The data of a training: center, day, modality, 8.1 topics, attendees and the optional signed list. */
export default function TrainingFields({
    tenantSlug,
    center,
    onCenter,
    today,
    topics,
    modalities,
    errors,
}: {
    tenantSlug: string;
    center: Option | null;
    onCenter: (center: Option) => void;
    today: string;
    topics: TrainingChoice[];
    modalities: TrainingChoice[];
    errors: Errors;
}) {
    return (
        <>
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="training-center">Centro de trabajo</Label>
                    <Combobox
                        id="training-center"
                        name="work_center_id"
                        source={serverSource(options.url(tenantSlug))}
                        defaultValue={center ?? undefined}
                        placeholder="Elige un centro"
                        invalid={errors['work_center_id'] !== undefined}
                        describedBy="training-center-error"
                        onChange={onCenter}
                    />
                    <InputError
                        id="training-center-error"
                        role="alert"
                        message={errors['work_center_id']}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="training-date">Fecha</Label>
                    <DatePicker
                        id="training-date"
                        name="held_on"
                        defaultValue={today}
                        invalid={errors['held_on'] !== undefined}
                        describedBy="training-date-error"
                    />
                    <InputError
                        id="training-date-error"
                        role="alert"
                        message={errors['held_on']}
                    />
                </div>
            </div>
            <fieldset className="grid gap-2">
                <legend className="mb-1 font-medium">Modalidad</legend>
                <div className="grid gap-2 sm:grid-cols-3">
                    {modalities.map((modality) => (
                        <label key={modality.value} className={CARD}>
                            <input
                                type="radio"
                                name="modality"
                                value={modality.value}
                                className="size-5 shrink-0 accent-primary"
                            />
                            {modality.label}
                        </label>
                    ))}
                </div>
                <InputError role="alert" message={errors['modality']} />
            </fieldset>
            <TrainingTopicsField topics={topics} error={errors['topics']} />
            <TrainingAttendeesField
                key={center?.value ?? ''}
                tenantSlug={tenantSlug}
                centerId={center?.value ?? ''}
                error={errors['employee_ids']}
            />
            <div className="grid gap-2">
                <Label htmlFor="training-evidence">
                    Lista de asistencia firmada (PDF, opcional)
                </Label>
                <input
                    id="training-evidence"
                    name="evidence"
                    type="file"
                    accept="application/pdf"
                    aria-invalid={
                        errors['evidence'] === undefined ? undefined : true
                    }
                    className="w-full rounded-md border border-input bg-card px-4 py-2.5 text-base file:mr-3 file:font-medium"
                />
                <InputError role="alert" message={errors['evidence']} />
            </div>
        </>
    );
}
