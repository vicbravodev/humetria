import type { Ref } from 'react';

type Props = {
    recoveryCodesList: string[];
    ref: Ref<HTMLDivElement>;
};

export default function RecoveryCodesList({ recoveryCodesList, ref }: Props) {
    return (
        <>
            <div
                ref={ref}
                className="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                role="list"
                aria-label="Códigos de recuperación"
            >
                {recoveryCodesList.length ? (
                    recoveryCodesList.map((code, index) => (
                        <div
                            key={index}
                            role="listitem"
                            className="select-text"
                        >
                            {code}
                        </div>
                    ))
                ) : (
                    <div
                        className="space-y-2"
                        aria-label="Cargando códigos de recuperación"
                    >
                        {Array.from({ length: 8 }, (_, index) => (
                            <div
                                key={index}
                                className="h-4 animate-pulse rounded bg-muted-foreground/20"
                                aria-hidden="true"
                            />
                        ))}
                    </div>
                )}
            </div>

            <div className="text-xs text-muted-foreground select-none">
                <p id="regenerate-warning">
                    Cada código de recuperación se puede usar una sola vez para
                    entrar a tu cuenta y se elimina después de usarlo. Si
                    necesitas más, elige{' '}
                    <span className="font-bold">Generar códigos nuevos</span>{' '}
                    arriba.
                </p>
            </div>
        </>
    );
}
