import FormSheet from '@/components/humetria/form-sheet';
import ActionFormFields from '@/components/interventions/action-form-fields';
import type {
    ActionCatalog,
    AreaOption,
    ProgramAction,
    ProgramMember,
} from '@/types';

/**
 * The form of an action in a side panel (pantalla Empleados-Alta), to add one to a row or to correct it, with the Programa
 * still in view behind it.
 */
export default function ActionSheet({
    open,
    onOpenChange,
    form,
    itemId,
    itemLabel,
    action,
    tools,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    form: { action: string; method: 'post' | 'put' };
    itemId: string;
    itemLabel: string;
    action: ProgramAction | null;
    tools: {
        catalog: ActionCatalog;
        areas: AreaOption[];
        members: ProgramMember[];
        responsible: string | null;
    };
}) {
    return (
        <FormSheet
            open={open}
            onOpenChange={onOpenChange}
            title={action === null ? 'Agregar acción' : 'Editar acción'}
            description={itemLabel}
            form={form}
            submit="Guardar acción"
        >
            {(errors) => (
                <ActionFormFields
                    itemId={itemId}
                    action={action}
                    tools={tools}
                    errors={errors}
                />
            )}
        </FormSheet>
    );
}
