import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[] | undefined;
};

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/** The variants of the Badge primitive (components/ui/badge): what a state looks like in a list or a header. */
export type BadgeVariant =
    | 'default'
    | 'secondary'
    | 'success'
    | 'warning'
    | 'destructive'
    | 'outline';

/** One fact under a page title, with its icon: "Acta del 2 de marzo de 2026", "3 integrantes". */
export type PageFact = { icon: LucideIcon; text: string };
