import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { CurrentTenant } from '@/types/tenancy';

/** The company a page works in, as far as the navigation cares: where it lives and what the role allows. */
export type NavTenant = Pick<CurrentTenant, 'slug' | 'role' | 'can'> &
    Partial<Pick<CurrentTenant, 'type'>>;

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
    /** Pages of the same tool, listed under it while it is open (NOM-037 · Documentos). */
    children?: NavItem[];
    /** Pages that belong to this one though their address does not hang from it (the poster of the channel lights Canal). */
    also?: NonNullable<InertiaLinkProps['href']>[];
};

/** The company's campaigns in course: how many, and of the soonest to close, its days left and percent gone by. */
export type InCourse = {
    count: number;
    daysLeft: number | null;
    elapsed: number;
};
