import { usePage } from '@inertiajs/react';
import * as climate from '@/routes/tenant/climate';
import * as pulse from '@/routes/tenant/pulse';

/** The survey kind of the page: the pages of Clima and of Pulso are the same ones, each with its own routes. */
export function useEngagementKind(): 'climate' | 'pulse' {
    return usePage().props['kind'] === 'pulse' ? 'pulse' : 'climate';
}

/** The routes of the kind of survey the page is showing (`tenant.climate.*` or `tenant.pulse.*`). */
export function useEngagementRoutes(): typeof climate | typeof pulse {
    return useEngagementKind() === 'pulse' ? pulse : climate;
}

/** What the tool is called in the interface. */
export function useEngagementName(): 'Clima' | 'Pulso' {
    return useEngagementKind() === 'pulse' ? 'Pulso' : 'Clima';
}
