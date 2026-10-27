import { Form } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { enable } from '@/routes/two-factor';

type Props = {
    hasSetupData: boolean;
    onShowSetup: () => void;
};

export default function EnableTwoFactor({ hasSetupData, onShowSetup }: Props) {
    return (
        <div className="flex flex-col items-start justify-start space-y-4">
            <p className="text-sm text-muted-foreground">
                Al activar la verificación en dos pasos, te pediremos un código
                de seguridad al iniciar sesión. Puedes obtenerlo en una
                aplicación de autenticación (TOTP) en tu celular.
            </p>

            <div>
                {hasSetupData ? (
                    <Button onClick={onShowSetup}>
                        <ShieldCheck />
                        Continuar configuración
                    </Button>
                ) : (
                    <Form {...enable.form()} onSuccess={onShowSetup}>
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                Activar verificación en dos pasos
                            </Button>
                        )}
                    </Form>
                )}
            </div>
        </div>
    );
}
