import ActionKindFields from '@/components/interventions/action-kind-fields';
import ActionSubjectsFields from '@/components/interventions/action-subjects-fields';
import ResponsibleFields from '@/components/interventions/responsible-fields';
import TextField from '@/components/interventions/text-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useActionForm } from '@/hooks/use-action-form';
import { actionDefaults } from '@/lib/action-form';
import { inheritedResponsible } from '@/lib/interventions';
import type {
    ActionCatalog,
    AreaOption,
    ProgramAction,
    ProgramMember,
} from '@/types';

type Errors = Partial<Record<string, string>>;

/** Every field of an action (num. 8.4 and 8.5): what, at which level, for whom, when and who answers. */
export default function ActionFormFields({
    itemId,
    action,
    tools,
    errors,
}: {
    itemId: string;
    action: ProgramAction | null;
    tools: {
        catalog: ActionCatalog;
        areas: AreaOption[];
        members: ProgramMember[];
        /** The Programa's responsible: what an empty one falls to. */
        responsible: string | null;
    };
    errors: Errors;
}) {
    const state = useActionForm(tools.catalog, action);
    const start = actionDefaults(tools.catalog, action);

    return (
        <div className="grid gap-6">
            <ActionKindFields
                catalog={tools.catalog}
                state={state}
                errors={errors}
            />
            <TextField
                name="title"
                label="Título"
                defaultValue={start.title}
                error={errors['title']}
            />
            <TextField
                name="description"
                label="Qué se hará"
                defaultValue={start.description}
                error={errors['description']}
                multiline
            />
            <ActionSubjectsFields
                areas={tools.areas}
                itemId={itemId}
                selected={start.areaIds}
                subjects={state.subjects}
                onSubjects={state.setSubjects}
                errors={errors}
            />
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="action-starts">Inicio (opcional)</Label>
                    <DatePicker
                        id="action-starts"
                        name="starts_on"
                        defaultValue={start.startsOn}
                    />
                    <InputError message={errors['starts_on']} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="action-due">Fecha límite</Label>
                    <DatePicker
                        id="action-due"
                        name="due_on"
                        defaultValue={start.dueOn}
                        invalid={errors['due_on'] !== undefined}
                    />
                    <InputError message={errors['due_on']} />
                </div>
            </div>
            <ResponsibleFields
                members={tools.members}
                userId={start.userId}
                name={start.name}
                errors={errors}
                inherited={inheritedResponsible(tools.responsible)}
            />
        </div>
    );
}
