import { router } from '@inertiajs/react';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import ConsolePagination from '@/components/console-pagination';
import Heading from '@/components/heading';
import PasskeyItem from '@/components/passkeys/passkey-item';
import PasskeysEmptyState from '@/components/passkeys/passkeys-empty-state';
import PasskeyRegistration from '@/components/passkeys/passkey-register';
import type { Paginated } from '@/types';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean | undefined;
    passkeys?: Paginated<Passkey> | null | undefined;
};

export default function ManagePasskeys(props: Props) {
    const page = props.passkeys ?? null;
    const passkeys = page?.data ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Llaves de acceso"
                description="Administra tus llaves de acceso para iniciar sesión sin contraseña"
            />

            <div className="overflow-hidden rounded-lg border border-border">
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

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </div>
    );
}
