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
    gender: 'female' | 'male' | 'other';
    gender_label: string;
    work_center_id: string;
    work_center: string;
    area: string;
    job_title: string;
    employee_number: string | null;
    /** The day they were deactivated, written for display; null while active. */
    deactivated_on: string | null;
};

/** A choice of a list (a work center, an area, a gender): what the form sends and what the person reads. */
export type ChoiceOption = { value: string; label: string };

/** What the employee form chooses from: the routes that search work centers and areas, and the genders. */
export type EmployeeChoices = {
    workCentersUrl: string;
    areasUrl: string;
    genders: ChoiceOption[];
};
