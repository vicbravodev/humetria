import type { LucideIcon } from 'lucide-react';
import { Lock, Users } from 'lucide-react';

/**
 * Who sees what a Ley Silla screen of the person collects (finding 14): the health questions and the result are identified
 * and only RH and the Comisión see them; the seat report is anonymous. Never «Confidencial», which is the anonymous surveys'.
 */
export const PORTAL_PRIVACY = {
    identified: { icon: Users, label: 'Solo RH y la Comisión' },
    anonymous: { icon: Lock, label: 'Anónimo' },
} satisfies Record<string, { icon: LucideIcon; label: string }>;

export type PortalPrivacy = keyof typeof PORTAL_PRIVACY;
