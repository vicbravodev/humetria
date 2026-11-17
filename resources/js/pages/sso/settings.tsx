import { Head, usePage } from '@inertiajs/react';
import { Globe, KeyRound, ShieldCheck } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SettingRow from '@/components/humetria/setting-row';
import ConnectionForm from '@/components/sso/connection-form';
import DomainList from '@/components/sso/domain-list';
import StateForm from '@/components/sso/state-form';
import type { SsoConnection, SsoDomain } from '@/types';

/** «Inicio de sesión único» (the owner's): the company's identity provider, its domains and who must use it. */
export default function Settings({
    connection,
    domains,
    redirect_uri: redirectUri,
}: {
    connection: SsoConnection | null;
    domains: SsoDomain[];
    redirect_uri: string;
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
                    description="Crea una aplicación OpenID Connect en tu proveedor con esta dirección de redirección, y copia aquí sus datos."
                >
                    <p className="text-sm">
                        <span className="text-muted-foreground">
                            Dirección de redirección:{' '}
                        </span>
                        <code className="font-mono break-all">
                            {redirectUri}
                        </code>
                    </p>
                    <ConnectionForm connection={connection} domains={domains} />
                </SettingRow>
                <SettingRow
                    icon={Globe}
                    title="Dominios"
                    description="Publica el registro TXT de cada dominio en su DNS: así sabemos que es de tu empresa."
                >
                    <DomainList
                        domains={domains}
                        error={usePage().props.errors['domain']}
                    />
                </SettingRow>
                {connection !== null && (
                    <SettingRow
                        icon={ShieldCheck}
                        title="Estado"
                        description="Actívalo cuando un dominio esté verificado. Exigirlo cierra la contraseña, la verificación en dos pasos y las llaves de acceso para todos menos el owner."
                    >
                        <StateForm
                            enabled={connection.enabled}
                            enforced={connection.enforced}
                        />
                    </SettingRow>
                )}
            </div>
        </>
    );
}
