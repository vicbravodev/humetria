import type { TenantRole } from './tenancy';

type PendingInvitation = {
    trade_name: string;
    /** What the person joins as, written for display ("Te invitaron como RH de Acme"). */
    invited_as: string;
    /** Who sent it; null when no sender was recorded. */
    inviter: string | null;
    role: TenantRole;
    role_label: string;
    email: string;
    name: string;
};

/** What the invitation link page receives: the state says what the person reaching it can do. */
export type InvitationPage = { token: string; passwordRules: string } & (
    | {
          state: 'invalid';
          trade_name: null;
          invited_as: null;
          inviter: null;
          role: null;
          role_label: null;
          email: null;
          name: null;
      }
    | ({ state: 'new_account' } & PendingInvitation)
    | ({ state: 'sign_in' | 'confirm' | 'other_account' } & PendingInvitation)
);
