/** The pages every template opens (dashboard, work centers, NOM-035's route, NOM-037), which a role of a company's own may not. */
export const coverage = {
    view_dashboard: true,
    view_work_centers: true,
    view_nom035_route: true,
    view_telework: true,
};

/** What a role may do in a company, as the server shares it with every page (`tenant.can`). */
export const can = (manage_organization: boolean, manage_members: boolean) => ({
    manage_organization,
    manage_members,
    manage_campaigns: manage_organization,
    view_trauma_screenings: manage_organization,
    manage_telework: manage_organization,
    review_workplaces: manage_organization,
    view_seating: true,
    manage_seating: manage_organization,
    assess_seating: manage_organization,
    view_compensation: manage_organization,
    manage_compensation: manage_organization,
    view_interventions: true,
    manage_interventions: manage_organization,
    view_disclosure: true,
    manage_disclosure: manage_organization,
    use_assistant: true,
    manage_complaint_channel: manage_organization,
    handle_complaints: manage_organization,
    view_attendance: manage_organization,
    manage_attendance: manage_organization,
    view_org_chart: manage_organization,
    manage_org_chart: manage_organization,
    view_vacations: manage_organization,
    manage_vacations: manage_organization,
    view_climate: manage_organization,
    manage_climate: manage_organization,
    view_pulse: manage_organization,
    manage_pulse: manage_organization,
    view_worker_access: true,
    manage_worker_access: manage_organization,
    ...coverage,
});

/** What each template allows, as the server seeds it (RoleStorageTest): the real `tenant.can` of a person with that role. */
const TEMPLATES: Record<string, string[]> = {
    owner: Object.keys(can(true, true)),
    hr: Object.keys(can(true, false)).filter((key) => key !== 'manage_members'),
    viewer: [
        'use_assistant',
        'view_attendance',
        'view_climate',
        'view_dashboard',
        'view_disclosure',
        'view_interventions',
        'view_nom035_route',
        'view_org_chart',
        'view_pulse',
        'view_seating',
        'view_telework',
        'view_vacations',
        'view_work_centers',
        'view_worker_access',
    ],
    committee: [
        'assess_seating',
        'handle_complaints',
        'review_workplaces',
        'view_dashboard',
        'view_seating',
        'view_telework',
    ],
    complaints: ['handle_complaints', 'view_dashboard'],
};

type Can = ReturnType<typeof can>;

/** The `tenant.can` of a template, plus `extra` permissions given to the person. */
export const templateCan = (template: string, extra: string[] = []): Can => {
    const all = can(true, true);
    const allowed = new Set([...(TEMPLATES[template] ?? []), ...extra]);
    const result: Can = { ...all };

    for (const key of Object.keys(all).filter(
        (name): name is keyof Can => name in all,
    )) {
        result[key] = allowed.has(key);
    }

    return result;
};
