import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { create } from '@/routes/tenant/compensation/benefits';

/** «Nueva prestación»: its page, with what it costs today as it is answered (board V4-Comp-Prestacion). */
export default function NewBenefitButton() {
    const tenantSlug = useTenantSlug();

    return (
        <Button asChild>
            <Link href={create(tenantSlug)}>
                <Plus />
                Nueva prestación
            </Link>
        </Button>
    );
}
