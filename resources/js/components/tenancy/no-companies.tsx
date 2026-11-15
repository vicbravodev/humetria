import { Link, router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import SupportLink from '@/components/humetria/support-link';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function NoCompanies() {
    return (
        <EmptyState
            icon={Building2}
            title="Sin acceso a empresas"
            action={
                <Button asChild variant="outline">
                    <Link
                        href={logout()}
                        as="button"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                    >
                        Cerrar sesión
                    </Link>
                </Button>
            }
        >
            Aún no tienes acceso a ninguna empresa.{' '}
            <SupportLink subject="Quiero acceso a mi empresa">
                Contacta al administrador de Humetría
            </SupportLink>
            .
        </EmptyState>
    );
}
