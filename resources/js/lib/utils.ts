import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

// The type scale's own sizes (app.css @theme), so `text-caption` and a text color don't cancel each other.
const twMerge = extendTailwindMerge({
    extend: { classGroups: { 'font-size': [{ text: ['caption', 'meta'] }] } },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

/** The page a link opens, without its query: a sidebar link may open its page on a view (`?view=board`). */
export function toPath(url: NonNullable<InertiaLinkProps['href']>): string {
    return toUrl(url).split(/[?#]/, 1)[0] ?? '';
}
