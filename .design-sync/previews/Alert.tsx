import { Alert, AlertDescription, AlertTitle } from 'humetria';
import { AlertCircle, Info, LockKeyhole } from 'lucide-react';

export const Default = () => (
    <div className="max-w-xl">
        <Alert>
            <Info />
            <AlertTitle>Faltan 3 personas por invitar</AlertTitle>
            <AlertDescription>
                Sin correo ni celular no les llega la evaluación. Agrégalos desde Empleados.
            </AlertDescription>
        </Alert>
    </div>
);

export const Destructive = () => (
    <div className="max-w-xl">
        <Alert variant="destructive">
            <AlertCircle />
            <AlertTitle>No se pudo lanzar la evaluación</AlertTitle>
            <AlertDescription>
                Planta Apodaca no tiene personas activas. Carga tu plantilla y vuelve a intentarlo.
            </AlertDescription>
        </Alert>
    </div>
);

export const Confidentiality = () => (
    <div className="max-w-xl">
        <Alert>
            <LockKeyhole />
            <AlertTitle>Sus respuestas son confidenciales</AlertTitle>
            <AlertDescription>
                Recursos Humanos solo ve resultados de grupos de 5 personas o más.
            </AlertDescription>
        </Alert>
    </div>
);

export const TitleOnly = () => (
    <div className="max-w-xl">
        <Alert>
            <Info />
            <AlertTitle>La evaluación cierra el 14 de diciembre</AlertTitle>
        </Alert>
    </div>
);
