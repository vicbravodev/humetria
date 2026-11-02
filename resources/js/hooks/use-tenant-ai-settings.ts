import { useEffect, useState } from 'react';
import {
    readTenantSettings,
    type TenantAiSettings,
} from '@/lib/assistant/tenant-settings';

/** What the server says of Humi for a company, read each time its dialog opens; null while it loads or if it cannot say. */
export function useTenantAiSettings(
    tenantId: string,
    open: boolean,
): TenantAiSettings | null {
    const [settings, setSettings] = useState<TenantAiSettings | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        let current = true;

        void readTenantSettings(tenantId).then((read) => {
            if (current) {
                setSettings(read);
            }
        });

        return () => {
            current = false;
            setSettings(null);
        };
    }, [open, tenantId]);

    return settings;
}
