import { router } from '@inertiajs/react';
import { KeyRound, Plus } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import ConsolePagination from '@/components/console-pagination';
import SettingRow from '@/components/humetria/setting-row';
import PasskeyItem from '@/components/passkeys/passkey-item';
import PasskeysEmptyState from '@/components/passkeys/passkeys-empty-state';
import PasskeyRegistration from '@/components/passkeys/passkey-register';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean | undefined;
    passkeys?: Paginated<Passkey> | null | undefined;
};

export default function ManagePasskeys(props: Props) {
    const [adding, setAdding] = useState(false);
    const page = props.passkeys ?? null;
    const passkeys = page?.data ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <SettingRow
            icon={KeyRound}
            title="Llaves de acceso"
            description="Entra con tu huella, tu rostro o el PIN de tu equipo, sin contraseña."
            action={
                <Button
                    variant="outline"
                    onClick={() => setAdding(true)}
                    disabled={adding}
                >
                    <Plus aria-hidden />
                    Agregar llave
                </Button>
            }
        >
            {adding && (
                <PasskeyRegistration
                    onSuccess={() => {
                        setAdding(false);
                        router.reload();
                    }}
                    onCancel={() => setAdding(false)}
                />
            )}
            <div className="overflow-hidden rounded-xl border">
                {passkeys.length > 0 ? (
                    passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))
                ) : (
                    <PasskeysEmptyState />
                )}
            </div>
            {page !== null && (
                <ConsolePagination
                    page={page}
                    label="Páginas de llaves de acceso"
                    previous="Más recientes"
                    next="Más antiguas"
                    pinned={false}
                />
            )}
        </SettingRow>
    );
}
