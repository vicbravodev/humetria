import { Head } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SettingRow from '@/components/humetria/setting-row';
import ConnectionForm from '@/components/sso/connection-form';
import DomainList from '@/components/sso/domain-list';
import type { SsoConnection, SsoDomain } from '@/types';

/** «Inicio de sesión único» (the owner's): the company's identity provider, its domains and who must use it. */
export default function Settings({
    connection,
    domains,
}: {
    connection: SsoConnection | null;
    domains: SsoDomain[];
}) {
    return (
        <>
            <Head title="Inicio de sesión único" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Inicio de sesión único"
                    description="Tu gente de RH entra con la cuenta de tu empresa (Microsoft Entra, Google u Okta) en lugar de una contraseña de Humetría."
                />
                <SettingRow
                    icon={KeyRound}
                    title="Proveedor de identidad"
                    description="Crea una aplicación OpenID Connect en tu proveedor, y copia aquí sus datos."
                >
                    <ConnectionForm connection={connection} domains={domains} />
                </SettingRow>
                <SettingRow
                    icon={KeyRound}
                    title="Dominios"
                    description="Publica el registro TXT de cada dominio en su DNS: así sabemos que es de tu empresa."
                >
                    <DomainList domains={domains} />
                </SettingRow>
            </div>
        </>
    );
}
