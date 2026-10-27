import { KeyRound } from 'lucide-react';

export default function PasskeysEmptyState() {
    return (
        <div className="p-8 text-center">
            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted">
                <KeyRound className="h-7 w-7 text-muted-foreground" />
            </div>
            <p className="font-medium">Aún no tienes llaves de acceso</p>
            <p className="mt-1 text-sm text-muted-foreground">
                Agrega una llave de acceso para iniciar sesión sin contraseña
            </p>
        </div>
    );
}
