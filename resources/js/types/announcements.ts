export type AnnouncementKind = 'news' | 'maintenance' | 'incident' | 'billing';

export type AnnouncementState = 'draft' | 'scheduled' | 'active' | 'ended';

/** One announcement as the console lists it; its days and hours are Mexico City's, to edit it as written. */
export type AnnouncementRow = {
    id: string;
    kind: AnnouncementKind;
    kind_label: string;
    audience: 'all' | 'plan';
    plan: string | null;
    audience_label: string;
    title: string;
    message: string;
    state: AnnouncementState;
    state_label: string;
    published: boolean;
    /** «27 nov 2026, 22:00 – 29 nov 2026, 01:00». */
    when: string;
    starts_on: string;
    starts_time: string;
    ends_on: string;
    ends_time: string;
};

export type AnnouncementOption = { value: string; label: string };

/** The banner a company's panel shows from the platform; its id changes when the announcement does. */
export type PlatformNotice = {
    id: string;
    kind: AnnouncementKind;
    message: string;
    dismissible: boolean;
};

