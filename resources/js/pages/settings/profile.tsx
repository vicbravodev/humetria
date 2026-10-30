import { Head } from '@inertiajs/react';
import DeleteUser from '@/components/delete-user';
import ProfileForm from '@/components/settings/profile-form';

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    return (
        <>
            <Head title="Perfil" />
            <ProfileForm mustVerifyEmail={mustVerifyEmail} status={status} />
            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [{ title: 'Cuenta' }, { title: 'Configuración' }],
};
