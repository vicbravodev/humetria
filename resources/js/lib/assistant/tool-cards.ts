export type LevelCount = { label: string; count: number };

export type RiskRow = {
    kind: string;
    label: string;
    answered: number | null;
    levels: readonly LevelCount[];
};

export type GuideProgress = {
    label: string;
    invited: number;
    answered: number;
};

export type ProgressRow = {
    label: string;
    status: string;
    closesOn: string | null;
    daysLeft: number | null;
    guides: readonly GuideProgress[];
    link: string | null;
};

export type StatusState =
    | 'up_to_date'
    | 'in_progress'
    | 'pending'
    | 'not_applicable';

export type StatusRow = {
    key: string;
    label: string;
    state: StatusState;
    stateLabel: string;
    summary: string;
    link: string | null;
};

export type ListRow = {
    /** Which criterion of which norm it is («NOM-037 · C-01»), for what comes from the norms. */
    ref: string | null;
    source: string | null;
    title: string;
    detail: string | null;
    due: string | null;
    link: string | null;
};

type Base = {
    title: string;
    link: string | null;
    note: string | null;
    truncated: boolean;
};

/** What a tool of Humi answered, ready to draw: the card says which one to draw, and carries its rows. */
export type ToolCard =
    | (Base & { render: 'progress'; rows: readonly ProgressRow[] })
    | (Base & {
          render: 'risk_levels';
          rows: readonly RiskRow[];
          minGroup: number | null;
      })
    | (Base & { render: 'status_list'; rows: readonly StatusRow[] })
    | (Base & { render: 'list'; rows: readonly ListRow[] });

function asObject(value: unknown): object | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
        ? value
        : null;
}

function field(source: object, key: string): unknown {
    return key in source ? Reflect.get(source, key) : undefined;
}

function text(source: object, key: string): string | null {
    const value = field(source, key);

    return typeof value === 'string' ? value : null;
}

function count(source: object, key: string): number | null {
    const value = field(source, key);

    return typeof value === 'number' ? value : null;
}

function items(source: object, key: string): readonly object[] {
    const value = field(source, key);

    return Array.isArray(value)
        ? value.flatMap((item) => asObject(item) ?? [])
        : [];
}

function levelsOf(source: object): readonly LevelCount[] {
    const levels = asObject(field(source, 'levels'));

    return levels === null
        ? []
        : Object.entries(levels).flatMap(([label, value]) =>
              typeof value === 'number' ? [{ label, count: value }] : [],
          );
}

function riskRow(row: object): RiskRow | null {
    const label = text(row, 'label');

    return label === null
        ? null
        : {
              kind: text(row, 'kind') ?? 'domain',
              label,
              answered: count(row, 'answered'),
              levels: levelsOf(row),
          };
}

function progressRow(row: object): ProgressRow | null {
    const label = text(row, 'label');

    if (label === null) {
        return null;
    }

    const guides = items(row, 'guides').flatMap((guide) => {
        const guideLabel = text(guide, 'label');

        return guideLabel === null
            ? []
            : [
                  {
                      label: guideLabel,
                      invited: count(guide, 'invited') ?? 0,
                      answered: count(guide, 'answered') ?? 0,
                  },
              ];
    });

    return {
        label,
        status: text(row, 'status') ?? 'active',
        closesOn: text(row, 'closes_on'),
        daysLeft: count(row, 'days_left'),
        guides,
        link: text(row, 'link'),
    };
}

function statusState(value: string | null): StatusState | null {
    return value === 'up_to_date' ||
        value === 'in_progress' ||
        value === 'pending' ||
        value === 'not_applicable'
        ? value
        : null;
}

function statusRow(row: object): StatusRow | null {
    const state = statusState(text(row, 'state'));
    const label = text(row, 'label');

    if (state === null || label === null) {
        return null;
    }

    return {
        key: text(row, 'key') ?? label,
        label,
        state,
        stateLabel: text(row, 'stateLabel') ?? label,
        summary: text(row, 'summary') ?? '',
        link: text(row, 'link'),
    };
}

function listRow(row: object): ListRow | null {
    const title = text(row, 'title');

    if (title === null) {
        return null;
    }

    const norm = text(row, 'norm');
    const id = text(row, 'id');

    return {
        ref: norm !== null && id !== null ? `${norm} · ${id}` : null,
        source: text(row, 'source'),
        title,
        detail: text(row, 'detail') ?? text(row, 'text'),
        due: text(row, 'due'),
        link: text(row, 'link'),
    };
}

function payloadOf(output: unknown): object | null {
    if (typeof output !== 'string') {
        return asObject(output);
    }

    try {
        return asObject(JSON.parse(output));
    } catch {
        return null;
    }
}

/** The card a tool answered, from the output of its call (the JSON text the server sends), or null if it is not one. */
export function parseToolCard(output: unknown): ToolCard | null {
    const payload = payloadOf(output);
    const title = payload === null ? null : text(payload, 'title');

    if (payload === null || title === null) {
        return null;
    }

    const base = {
        title,
        link: text(payload, 'link'),
        note: text(payload, 'note'),
        truncated: field(payload, 'truncated') === true,
    };
    const rows = items(payload, 'rows');

    switch (text(payload, 'render')) {
        case 'progress':
            return {
                ...base,
                render: 'progress',
                rows: rows.flatMap((row) => progressRow(row) ?? []),
            };
        case 'risk_levels':
            return {
                ...base,
                render: 'risk_levels',
                rows: rows.flatMap((row) => riskRow(row) ?? []),
                minGroup: count(payload, 'minGroup'),
            };
        case 'status_list':
            return {
                ...base,
                render: 'status_list',
                rows: rows.flatMap((row) => statusRow(row) ?? []),
            };
        case 'list':
            return {
                ...base,
                render: 'list',
                rows: rows.flatMap((row) => listRow(row) ?? []),
            };
        default:
            return null;
    }
}
