import CommitmentGroupField from '@/components/engagement/commitments/commitment-group-field';
import CommitmentTextFields from '@/components/engagement/commitments/commitment-text-fields';
import Combobox from '@/components/humetria/combobox';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type {
    CommitmentGroupOption,
    CommitmentOption,
    CommitmentRow,
} from '@/types';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';

/**
 * Writes a commitment, or edits one (its group stays): what people said, what the company will do, who answers and
 * by when. The theme is optional.
 */
export default function CommitmentDialog({
    open,
    onOpenChange,
    surveyId,
    editing,
    groups,
    drivers,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    surveyId: string;
    editing: CommitmentRow | null;
    groups: CommitmentGroupOption[];
    drivers: CommitmentOption[];
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const driver = drivers.find((option) => option.value === editing?.driver);

    return (
        <FormDialog
            key={editing?.id ?? 'new'}
            open={open}
            onOpenChange={onOpenChange}
            title={editing === null ? 'Nuevo compromiso' : 'Editar compromiso'}
            description="Lo que escribas aquí lo leerá todo el personal en su portal cuando lo publiques. No cites a nadie ni pegues comentarios."
            form={
                editing === null
                    ? routes.commitments.store.form([tenantSlug, surveyId])
                    : routes.commitments.update.form.put([
                          tenantSlug,
                          surveyId,
                          editing.id,
                      ])
            }
            submit="Guardar"
        >
            {(errors) => (
                <>
                    {editing === null ? (
                        <CommitmentGroupField
                            groups={groups}
                            error={errors['group_id'] ?? errors['group_type']}
                        />
                    ) : (
                        <>
                            <input
                                type="hidden"
                                name="group_type"
                                value={editing.group_type}
                            />
                            <input
                                type="hidden"
                                name="group_id"
                                value={editing.group_id ?? ''}
                            />
                            <p className="text-sm text-muted-foreground">
                                Para: {editing.group_name}
                            </p>
                        </>
                    )}
                    <div className="grid gap-2">
                        <Label htmlFor="commitment-driver">
                            Tema (opcional)
                        </Label>
                        <Combobox
                            id="commitment-driver"
                            name="driver"
                            source={{ options: drivers }}
                            {...(driver === undefined
                                ? {}
                                : { defaultValue: driver })}
                            placeholder="Sin tema"
                            invalid={errors['driver'] !== undefined}
                        />
                        <InputError message={errors['driver']} />
                    </div>
                    <CommitmentTextFields editing={editing} errors={errors} />
                    <InputError message={errors['survey']} />
                </>
            )}
        </FormDialog>
    );
}
