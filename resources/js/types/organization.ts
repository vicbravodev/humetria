import type { Paginated } from '@/types/demos';

export type {
    EmployeeCounts,
    EmployeeFilters,
    EmployeeImportSummary,
} from '@/lib/employees';

/** One employee as the «Empleados» list shows it. */
export type EmployeeRow = {
    id: string;
    name: string;
    email: string | null;
    /** 10 digits. */
    mobile: string | null;
    /** Empty for someone a union loaded without it. */
    gender: 'female' | 'male' | 'other' | '';
    gender_label: string;
    work_center_id: string;
    work_center: string;
    area: string;
    job_title: string;
    employee_number: string | null;
    /** Empty while the person has no category and level in the pay scale. */
    job_category: string;
    job_level: string;
    /** `AAAA-MM-DD`. */
    hired_on: string | null;
    /** `AAAA-MM-DD`: the day their seniority counts from, when it is not the day they joined. */
    seniority_on: string | null;
    /** Their direct manager, as «Nombre · Puesto». */
    manager: ChoiceOption | null;
    /** How many active people report directly to them. */
    direct_reports: number;
    staff_group: 'unionized' | 'trusted' | '';
    /** The state's code; empty when not given. */
    state: string;
    /** The day they were deactivated, written for display; null while active. */
    deactivated_on: string | null;
};

/** A choice of a list (a work center, an area, a gender): what the form sends and what the person reads. */
export type ChoiceOption = { value: string; label: string };

/**
 * What the employee form chooses from: the routes that search work centers, areas, managers and the pay scale's
 * categories and levels, and the fixed catalogs (genders, staff groups, states).
 */
export type EmployeeChoices = {
    workCentersUrl: string;
    areasUrl: string;
    managersUrl: string;
    jobCategoriesUrl: string;
    jobLevelsUrl: string;
    genders: ChoiceOption[];
    staffGroups: ChoiceOption[];
    states: ChoiceOption[];
};

/** A person in the org chart: who they are, where, and the size of their team. Never contact data. */
export type OrgNode = {
    id: string;
    name: string;
    job_title: string;
    area: string;
    work_center: string;
    active: boolean;
    /** Active people who report to them. */
    direct_reports: number;
    /** Active people below them, at any level. */
    total_reports: number;
    flag: { value: 'few' | 'many'; label: string } | null;
    /** Whether the person is within the filters, or only on the way to someone who is. */
    matches: boolean;
    /** Their manager left and nobody took their place. */
    manager_inactive: boolean;
    /** Away today with approved vacations or a leave: only that, never why nor until when. */
    absent_today: boolean;
};

/** The first page of the reports of each root of the page, keyed by the root; a root who leads nobody has no entry. */
export type OrgFirstLevel = Record<string, OrgNode[]>;

/** Someone with no manager who leads nobody, in the tray of the editing mode. Never contact data. */
export type UnassignedPerson = {
    id: string;
    name: string;
    job_title: string;
    area: string;
    work_center: string;
};

/** The shape of the company: its levels, and how many people a manager leads. */
export type OrgIndicators = {
    levels: number;
    /** Among whoever leads at least one person; null where nobody does. */
    median_direct_reports: number | null;
    managers: number;
    /** Managers with 1–2 or more than 15 direct reports. */
    to_review: number;
};

/** Each chosen filter of the org chart with its name. */
export type OrgChartFilters = {
    work_center: { value: string; label: string } | null;
    area: { value: string; label: string } | null;
};

/** A view of the list of the reporting line, with how many people it holds. */
export type ReportingIssue = {
    value: 'all' | 'no_manager' | 'many_reports' | 'inactive_manager';
    label: string;
    count: number;
    /** Whether that many people are something to fix: one person at the top is not; everybody never is. */
    pending: boolean;
};

/** A person in the list. Never contact data. */
export type QualityPerson = {
    id: string;
    name: string;
    job_title: string;
    area: string;
    work_center: string;
    /** Their manager as it is saved, who may have left. */
    manager: ChoiceOption | null;
    /** Their manager left and nobody took their place. */
    manager_inactive: boolean;
    /** Active people who report to them. */
    direct_reports: number;
};

/** The person's manager as their portal names them. */
export type PortalTeamManager = { name: string; job_title: string };

/** Someone of the person's team in their portal: no contact data and no id. */
export type PortalTeamPerson = {
    name: string;
    job_title: string;
    area: string;
};

/** What the org chart page receives. */
export type OrgChartPageProps = {
    roots: Paginated<OrgNode>;
    first_level: OrgFirstLevel;
    /** From the top down to the focused person, who comes last; empty without a focus. */
    chain: OrgNode[];
    focus: { id: string; children: Paginated<OrgNode> } | null;
    filters: OrgChartFilters;
    total_people: number;
    with_manager: number;
    /** People with no manager who lead nobody: the chart does not draw them. */
    without_manager: number;
    indicators: OrgIndicators;
    can_manage: boolean;
};
