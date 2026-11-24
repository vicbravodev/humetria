import type { TenantRole } from './tenancy';

/** A template of Humetría a company hands out, locked, as «Roles y permisos» lists it. */
export type RoleTemplateRow = {
    key: Exclude<TenantRole, 'owner'>;
    label: string;
    /** People of this company who hold it. */
    people: number;
    permissions: number;
};

/** A role of the company's own, as «Roles y permisos» lists it. */
export type CompanyRoleRow = {
    id: number;
    label: string;
    /** The label of the template it was copied from, if any. */
    based_on: string | null;
    people: number;
    /** Open invitations that name it, expired ones included. */
    invitations: number;
    permissions: number;
};

/** One switch of the roles screen: a permission of the catalog, on where it is held, off with its reason where the plan lacks its tool. */
export type PermissionToggle = {
    name: string;
    label: string;
    description: string;
    sensitive: boolean;
    /** Held: by the role, or by the person besides their role (`source`). */
    enabled: boolean;
    unavailableReason: string | null;
    source: 'role' | 'direct' | null;
};

/** The switches of one tool. */
export type PermissionGroup = {
    module: string;
    label: string;
    permissions: PermissionToggle[];
};

/** What the editor of a role gets: a new one (`role` null, maybe copied `from` a template) or one of the company's own. */
export type RoleEditorProps = {
    role: {
        id: number;
        label: string;
        based_on: string | null;
        people: number;
        invitations: number;
    } | null;
    from: string | null;
    templates: RoleTemplateOption[];
    groups: PermissionGroup[];
    can_manage: boolean;
};

/** A template a new role can start from, with what it switches on. */
export type RoleTemplateOption = {
    key: string;
    label: string;
    permissions: string[];
};
