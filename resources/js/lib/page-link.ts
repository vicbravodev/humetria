import type { BadgeVariant, PageLinkData } from '@/types';

/** How the state of the link reads as a badge. */
export const linkVariant = (
    state: PageLinkData['state']['value'],
): BadgeVariant => {
    switch (state) {
        case 'active':
            return 'success';
        case 'disabled':
            return 'secondary';
        case 'expired':
            return 'destructive';
    }
};

/** What the link says about its end, in one sentence. */
export const expiryText = (link: PageLinkData): string =>
    link.expiresOn === null
        ? 'No vence: es una página permanente.'
        : `Abre hasta el ${link.expiresOn}.`;
