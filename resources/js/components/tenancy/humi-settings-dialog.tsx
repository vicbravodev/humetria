import { Form } from '@inertiajs/react';
import HumiSettingsFields from '@/components/tenancy/humi-settings-fields';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { useTenantAiSettings } from '@/hooks/use-tenant-ai-settings';
import { update } from '@/routes/companies/assistant';

/**
 * What the superadmin decides about Humi for a company: whether it is on, and its budget of tokens for the month (empty:
 * the platform's default). It shows what the company used this month, read when the dialog opens.
 */
export default function HumiSettingsDialog({
    tenantId,
    tradeName,
    open,
    onOpenChange,
}: {
    tenantId: string;
    tradeName: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const settings = useTenantAiSettings(tenantId, open);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Humi en {tradeName}</DialogTitle>
                <DialogDescription>
                    Enciende o apaga al asistente de IA de esta empresa y fija
                    cuánto puede usar al mes.
                </DialogDescription>
                {settings === null ? (
                    <div
                        className="grid gap-3"
                        role="status"
                        aria-label="Cargando"
                    >
                        <Skeleton className="h-6 w-2/3" />
                        <Skeleton className="h-11 w-full" />
                    </div>
                ) : (
                    <Form
                        {...update.form(tenantId)}
                        noValidate
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <HumiSettingsFields
                                    settings={settings}
                                    budgetError={errors['monthly_token_budget']}
                                />
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="outline">
                                            Cancelar
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" disabled={processing}>
                                        {processing && <Spinner />}
                                        Guardar
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
