/** The badge of a service: the variant carries the tone, the label always names the state. */
export type ServiceState = {
    variant: 'success' | 'warning' | 'destructive' | 'secondary';
    label: string;
};

/** Horizon as the queues dashboard sees it; `unreachable` when Redis did not answer. */
export type HorizonStatus = 'running' | 'paused' | 'inactive' | 'unreachable';

export type QueueLoad = { name: string; length: number; wait: number };

/** One kind of job that failed for good in the last 7 days: its name, never its payload. */
export type FailedJobKind = {
    job: string;
    module: string | null;
    queue: string;
    count: number;
    lastFailedAt: string;
};

/** The messages of one channel in a range: how many went out, arrived (read included), were read or rejected. */
export type ChannelTraffic = {
    channel: 'mail' | 'whatsapp';
    total: number;
    delivered: number;
    read: number;
    rejected: number;
};

export type WhatsAppState = {
    enabled: boolean;
    paused: boolean;
    pauseReason: string | null;
};

/** A WhatsApp template in code and what it sent in 30 days. `pendingInCode`: a button waits for a route (not Meta's approval). */
export type TemplateTraffic = {
    key: string;
    category: 'UTILITY' | 'AUTHENTICATION';
    pendingInCode: boolean;
    total: number;
    delivered: number;
    rejected: number;
};

/** A company with five or more rejected messages in 7 days. */
export type CompanyRejections = { id: string; name: string; rejected: number };

export type MessagingConnection = WhatsAppState & {
    pausedSince: string | null;
    lastEventAt: string | null;
};

export type OptOutTally = {
    everyCompany: number;
    oneCompany: number;
    lastThirtyDays: number;
};

export type MessagingConsoleData = {
    month: ChannelTraffic[];
    today: ChannelTraffic[];
    templates: TemplateTraffic[];
    companies: CompanyRejections[];
    connection: MessagingConnection;
    optOuts: OptOutTally;
};

export type SystemHealthData = {
    queue: { status: HorizonStatus; waiting: number; queues: QueueLoad[] };
    failedJobs: { today: number; week: FailedJobKind[] };
    heartbeat: { lastAt: string | null; late: boolean };
    webhooks: { meta: string | null; resend: string | null };
    whatsApp: WhatsAppState;
    today: ChannelTraffic[];
};
