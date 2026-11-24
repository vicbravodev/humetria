import type { BlogFamily } from '@/types/blog';

/** The class that dresses a family's things in its color (`.family-*` in app.css: `text-family`, `bg-family-soft`). */
export const FAMILY_CLASS: Record<BlogFamily, string> = {
    compliance: 'family-compliance',
    listening: 'family-listening',
    operations: 'family-operations',
};
