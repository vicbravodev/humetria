import { Link } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

export default function NoCompanies() {
    return (
        <EmptyState icon={Building2} title="Acceso pendiente">
            Aún no tienes acceso a ninguna empresa. Contacta al administrador de
            Humetría.
            <Button
                asChild
                variant="outline"
                className="mx-auto mt-6 block w-fit"
            >
                <Link href={logout()} as="button" data-test="logout-button">
                    Cerrar sesión
                </Link>
            </Button>
        </EmptyState>
    );
}
