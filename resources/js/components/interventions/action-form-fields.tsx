import ActionDateFields from '@/components/interventions/action-date-fields';
import ActionKindFields from '@/components/interventions/action-kind-fields';
import ActionSubjectsFields from '@/components/interventions/action-subjects-fields';
import ResponsibleFields from '@/components/interventions/responsible-fields';
import TextField from '@/components/interventions/text-field';
import { useState } from 'react';
import { useActionForm } from '@/hooks/use-action-form';
import { actionDefaults } from '@/lib/action-form';
import { inheritedResponsible, unfixedErrors } from '@/lib/interventions';
import type {
    ActionCatalog,
    AreaOption,
    ProgramAction,
    ProgramMember,
} from '@/types';

type Errors = Partial<Record<string, string>>;

/**
 * Every field of an action (num. 8.4 and 8.5): what, at which level, for whom, when and who answers. The error of a field
 * goes as soon as it is edited.
 */
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
    const [edited, setEdited] = useState<{ for: Errors; fields: string[] }>({
        for: errors,
        fields: [],
    });
    // A new answer of the server brings its errors back, whatever was edited before it.
    const fields = edited.for === errors ? edited.fields : [];
    const markEdited = (name: string) =>
        setEdited({ for: errors, fields: [...fields, name] });
    const shown = unfixedErrors(errors, fields);

    return (
        <div
            className="grid gap-6"
            onInput={(event) => {
                if (
                    event.target instanceof HTMLInputElement ||
                    event.target instanceof HTMLTextAreaElement
                ) {
                    markEdited(event.target.name);
                }
            }}
        >
            <ActionKindFields
                catalog={tools.catalog}
                state={state}
                errors={shown}
            />
            <TextField
                name="title"
                label="Título"
                defaultValue={start.title}
                error={shown['title']}
            />
            <TextField
                name="description"
                label="Qué se hará"
                defaultValue={start.description}
                error={shown['description']}
                multiline
            />
            <ActionSubjectsFields
                areas={tools.areas}
                itemId={itemId}
                selected={start.areaIds}
                subjects={state.subjects}
                onSubjects={state.setSubjects}
                errors={shown}
            />
            <ActionDateFields
                startsOn={start.startsOn}
                dueOn={start.dueOn}
                errors={shown}
                onEdited={markEdited}
            />
            <ResponsibleFields
                members={tools.members}
                userId={start.userId}
                name={start.name}
                errors={shown}
                inherited={inheritedResponsible(tools.responsible)}
            />
        </div>
    );
}
