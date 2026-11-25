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

export type SystemHealthData = {
    queue: { status: HorizonStatus; waiting: number; queues: QueueLoad[] };
    failedJobs: { today: number; week: FailedJobKind[] };
    heartbeat: { lastAt: string | null; late: boolean };
    webhooks: { meta: string | null; resend: string | null };
    whatsApp: WhatsAppState;
    today: ChannelTraffic[];
};
