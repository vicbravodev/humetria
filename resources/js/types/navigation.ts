import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

/** One step of the top bar's trail; without href it is a label (a section, or the page itself). */
export type BreadcrumbItem = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Something waiting there, as a count next to the name (NOM-035 · 1 en curso). */
    badge?: number;
};
