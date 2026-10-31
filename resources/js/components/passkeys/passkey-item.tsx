import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { Passkey } from '@/types/auth';

type Props = {
    passkey: Passkey;
    onDelete: (id: number, onError: () => void) => void;
};

export default function PasskeyItem({ passkey, onDelete }: Props) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = () => {
        setIsDeleting(true);
        onDelete(passkey.id, () => setIsDeleting(false));
    };

    return (
        <div className="flex items-center gap-3 border-b px-4 py-3.5 last:border-b-0">
            <KeyRound
                aria-hidden
                className="size-[18px] shrink-0 text-muted-foreground"
            />
            <div className="flex min-w-0 grow flex-col">
                <p className="text-sm font-medium">
                    {passkey.name}
                    {passkey.authenticator && (
                        <span className="font-normal text-muted-foreground">
                            {' '}
                            · {passkey.authenticator}
                        </span>
                    )}
                </p>
                <p className="text-[13px] leading-[18px] text-muted-foreground">
                    Agregada {passkey.created_at_diff}
                    {passkey.last_used_at_diff &&
                        ` · usada ${passkey.last_used_at_diff}`}
                </p>
            </div>

            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        aria-label={`Quitar la llave ${passkey.name}`}
                    >
                        Quitar
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>¿Eliminar la llave de acceso?</DialogTitle>
                    <DialogDescription>
                        Ya no podrás usar la llave de acceso "{passkey.name}"
                        para iniciar sesión.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="outline">Cancelar</Button>
                        </DialogClose>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting
                                ? 'Eliminando...'
                                : 'Eliminar llave de acceso'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
