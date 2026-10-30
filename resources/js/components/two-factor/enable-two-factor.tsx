import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { enable } from '@/routes/two-factor';

type Props = {
    hasSetupData: boolean;
    onShowSetup: () => void;
};

/** Starts the two-step setup, or picks it up where it was left. */
export default function EnableTwoFactor({ hasSetupData, onShowSetup }: Props) {
    if (hasSetupData) {
        return (
            <Button variant="outline" onClick={onShowSetup}>
                Continuar configuración
            </Button>
        );
    }

    return (
        <Form {...enable.form()} onSuccess={onShowSetup}>
            {({ processing }) => (
                <Button
                    type="submit"
                    variant="outline"
                    disabled={processing}
                    aria-label="Activar verificación en dos pasos"
                >
                    Activar
                </Button>
            )}
        </Form>
    );
}
