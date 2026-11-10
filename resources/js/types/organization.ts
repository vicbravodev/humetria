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
};

/** Each chosen filter of the org chart with its name. */
export type OrgChartFilters = {
    work_center: { value: string; label: string } | null;
    area: { value: string; label: string } | null;
};
