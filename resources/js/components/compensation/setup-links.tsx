import { Link } from '@inertiajs/react';
import { Gift, Layers, UserRoundPen } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as benefits } from '@/routes/tenant/compensation/benefits';
import { index as overrides } from '@/routes/tenant/compensation/overrides';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';

/** The three things a contract is costed from, each a step: what it grants, what each category earns, and the exceptions. */
export default function SetupLinks() {
    const tenantSlug = useTenantSlug();
    const links = [
        {
            icon: Gift,
            title: 'Prestaciones',
            text: 'Lo que da el contrato y sus términos de hoy.',
            href: benefits(tenantSlug),
        },
        {
            icon: Layers,
            title: 'Tabulador',
            text: 'El salario diario de cada categoría y nivel.',
            href: tabulator(tenantSlug),
        },
        {
            icon: UserRoundPen,
            title: 'Sueldos individuales',
            text: 'Quien gana distinto a su categoría.',
            href: overrides(tenantSlug),
        },
    ];

    return (
        <nav
            aria-label="Datos del contrato"
            className="grid gap-3 sm:grid-cols-3"
        >
            {links.map(({ icon, title, text, href }) => (
                <Link
                    key={title}
                    href={href}
                    className="flex items-start gap-3.5 card px-5 py-[18px] transition-colors hover:border-foreground/40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <IconTile icon={icon} />
                    <span className="min-w-0">
                        <span className="block font-semibold">{title}</span>
                        <span className="block text-sm text-muted-foreground">
                            {text}
                        </span>
                    </span>
                </Link>
            ))}
        </nav>
    );
}
