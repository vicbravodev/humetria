import { MessageSquareWarning } from 'lucide-react';
import { index as complaintCases } from '@/routes/tenant/complaints';
import { show as channel } from '@/routes/tenant/complaints/channel';
import { index as complaintHandlers } from '@/routes/tenant/complaints/handlers';
import type { NavItem, NavTenant } from '@/types';

/**
 * The channel for complaints and reports of NOM-035 (8.1 b): the cases of whoever handles them, and the channel and who
 * handles it for whoever manages it. A handler's start is its cases; a manager who handles none starts at the channel.
 */
export const complaints = (tenant: NavTenant): NavItem => ({
    title: 'Quejas',
    href: tenant.can.handle_complaints
        ? complaintCases(tenant.slug)
        : channel(tenant.slug),
    icon: MessageSquareWarning,
    children: [
        ...(tenant.can.handle_complaints
            ? [{ title: 'Casos', href: complaintCases(tenant.slug) }]
            : []),
        ...(tenant.can.manage_complaint_channel
            ? [
                  { title: 'Canal', href: channel(tenant.slug) },
                  {
                      title: 'Quién atiende',
                      href: complaintHandlers(tenant.slug),
                  },
              ]
            : []),
    ],
});

export const handlesOrManagesComplaints = (tenant: NavTenant): boolean =>
    tenant.can.handle_complaints || tenant.can.manage_complaint_channel;

/** The outside handler's whole menu (board 40): its cases, or nothing when it may no longer handle them. */
export const complaintCasesItems = (tenant: NavTenant): NavItem[] =>
    tenant.can.handle_complaints
        ? [
              {
                  title: 'Casos',
                  href: complaintCases(tenant.slug),
                  icon: MessageSquareWarning,
              },
          ]
        : [];
