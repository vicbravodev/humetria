import type { CSSProperties, ReactNode } from 'react';
import { CARD, CARD_PAD, HEADER_H, c, floatShadow, radius, risk, type Risk } from './theme';

const icons = {
    file: (
        <>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
            <path d="M14 3v5h5" />
        </>
    ),
    upload: (
        <>
            <path d="M12 16V4" />
            <path d="m6 10 6-6 6 6" />
            <path d="M5 20h14" />
        </>
    ),
    mail: (
        <>
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <path d="m3 7 9 6 9-6" />
        </>
    ),
    bell: (
        <>
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
        </>
    ),
    lock: (
        <>
            <rect x="4" y="11" width="16" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
        </>
    ),
    check: <path d="M5 12.5l4.5 4.5L19 7.5" />,
    listCheck: (
        <>
            <path d="M11 6h10" />
            <path d="M11 12h10" />
            <path d="M11 18h10" />
            <path d="m3 6 1.5 1.5L7 5" />
            <path d="m3 12 1.5 1.5L7 11" />
            <path d="m3 18 1.5 1.5L7 17" />
        </>
    ),
    eyeOff: (
        <>
            <path d="M3 3l18 18" />
            <path d="M10.6 5.1A10 10 0 0 1 12 5c7 0 10 7 10 7a17 17 0 0 1-3.2 4.2" />
            <path d="M6.6 6.6A17 17 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
        </>
    ),
    shield: (
        <>
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
            <path d="m9 12 2 2 4-4" />
        </>
    ),
} as const;

export const Icon = ({
    name,
    size = 28,
    color = c.fg,
    stroke = 2,
    style,
}: {
    name: keyof typeof icons;
    size?: number;
    color?: string;
    stroke?: number;
    style?: CSSProperties;
}) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke={color}
        strokeWidth={stroke}
        strokeLinecap="round"
        strokeLinejoin="round"
        style={{ flexShrink: 0, ...style }}
    >
        {icons[name]}
    </svg>
);

export const MainCard = ({ style, children }: { style?: CSSProperties; children: ReactNode }) => (
    <div
        style={{
            position: 'absolute',
            left: CARD.x,
            top: CARD.y,
            width: CARD.w,
            height: CARD.h,
            background: c.card,
            border: `1px solid ${c.border}`,
            borderRadius: radius.card,
            overflow: 'hidden',
            boxSizing: 'border-box',
            ...style,
        }}
    >
        {children}
    </div>
);

export const CardHeader = ({
    title,
    subtitle,
    style,
}: {
    title: ReactNode;
    subtitle: ReactNode;
    style?: CSSProperties;
}) => (
    <div
        style={{
            position: 'absolute',
            left: CARD_PAD,
            top: 0,
            height: HEADER_H,
            display: 'flex',
            flexDirection: 'column',
            justifyContent: 'center',
            gap: 4,
            ...style,
        }}
    >
        <div style={{ fontSize: 36, fontWeight: 600, color: c.fg, letterSpacing: -0.4, lineHeight: 1.15 }}>
            {title}
        </div>
        <div style={{ fontSize: 28, color: c.mutedFg, lineHeight: 1.2 }}>{subtitle}</div>
    </div>
);

export const HeaderRule = () => (
    <div
        style={{
            position: 'absolute',
            left: 0,
            right: 0,
            top: HEADER_H,
            height: 1,
            background: c.border,
        }}
    />
);

/** Estado inicial y final del loop: zona para subir la plantilla. */
export const Dropzone = ({
    highlight,
    content = 1,
    style,
}: {
    highlight: number;
    content?: number;
    style?: CSSProperties;
}) => (
    <div
        style={{
            position: 'absolute',
            left: CARD_PAD,
            right: CARD_PAD,
            top: HEADER_H + 28,
            bottom: CARD_PAD,
            borderRadius: radius.card,
            ...style,
        }}
    >
        <div
            style={{ position: 'absolute', inset: 0, borderRadius: radius.card, border: `2px dashed ${c.border}` }}
        />
        <div
            style={{
                position: 'absolute',
                inset: 0,
                borderRadius: radius.card,
                border: `2px dashed ${c.primary}`,
                background: 'rgba(60,207,189,0.04)',
                opacity: highlight,
            }}
        />
        <div
            style={{
                position: 'absolute',
                inset: 0,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                gap: 14,
                opacity: content,
            }}
        >
            <div
                style={{
                    width: 76,
                    height: 76,
                    borderRadius: 999,
                    background: c.accent,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    marginBottom: 8,
                }}
            >
                <Icon name="upload" size={34} color={c.primary} />
            </div>
            <div style={{ fontSize: 34, fontWeight: 600, color: c.fg }}>Sube tu plantilla</div>
            <div style={{ fontSize: 28, color: c.mutedFg }}>Nombre, área y correo de cada persona</div>
        </div>
    </div>
);

export const FileChip = ({ style }: { style?: CSSProperties }) => (
    <div
        style={{
            position: 'absolute',
            display: 'flex',
            alignItems: 'center',
            gap: 12,
            height: 60,
            padding: '0 22px 0 18px',
            borderRadius: radius.control,
            background: c.secondary,
            border: `1px solid ${c.border}`,
            boxShadow: floatShadow,
            fontSize: 28,
            fontWeight: 500,
            color: c.fg,
            whiteSpace: 'nowrap',
            ...style,
        }}
    >
        <Icon name="file" size={28} color={c.primary} />
        plantilla.csv
    </div>
);

export const Tag = ({ label, style }: { label: string; style?: CSSProperties }) => (
    <div
        style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 8,
            height: 42,
            padding: '0 14px',
            borderRadius: radius.control,
            background: c.accent,
            border: `1px solid ${c.border}`,
            fontSize: 26,
            fontWeight: 500,
            color: c.fg,
            whiteSpace: 'nowrap',
            ...style,
        }}
    >
        <span style={{ width: 8, height: 8, borderRadius: 99, background: c.primary }} />
        {label}
    </div>
);

export const RiskPill = ({ level, label, style }: { level: Risk; label: string; style?: CSSProperties }) => (
    <div
        style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: 10,
            height: 42,
            padding: '0 16px',
            borderRadius: 999,
            background: risk[level].bg,
            color: risk[level].fg,
            fontSize: 26,
            fontWeight: 500,
            whiteSpace: 'nowrap',
            ...style,
        }}
    >
        <span style={{ width: 10, height: 10, borderRadius: 99, background: risk[level].solid }} />
        {label}
    </div>
);

export const Avatar = ({ initials, style }: { initials: string; style?: CSSProperties }) => (
    <div
        style={{
            width: 52,
            height: 52,
            borderRadius: 999,
            background: c.accent,
            color: c.fg,
            fontSize: 22,
            fontWeight: 600,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexShrink: 0,
            ...style,
        }}
    >
        {initials}
    </div>
);

export const PHONE = { w: 360, h: 700 } as const;

export const Phone = ({ style, children }: { style?: CSSProperties; children?: ReactNode }) => (
    <div
        style={{
            position: 'absolute',
            width: PHONE.w,
            height: PHONE.h,
            borderRadius: 54,
            background: c.card,
            border: `1px solid ${c.border}`,
            boxSizing: 'border-box',
            padding: 10,
            ...style,
        }}
    >
        <div
            style={{
                position: 'relative',
                width: '100%',
                height: '100%',
                borderRadius: 44,
                background: c.bg,
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    top: 14,
                    left: '50%',
                    width: 96,
                    height: 28,
                    marginLeft: -48,
                    borderRadius: 99,
                    background: c.card,
                }}
            />
            {children}
        </div>
    </div>
);

export const CheckBadge = ({ size, style }: { size: number; style?: CSSProperties }) => (
    <div
        style={{
            width: size,
            height: size,
            borderRadius: 999,
            background: c.primary,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            flexShrink: 0,
            ...style,
        }}
    >
        <Icon name="check" size={size * 0.56} color={c.primaryFg} stroke={2.6} />
    </div>
);

const W = c.fg;

export const Humi = ({ width, style }: { width: number; style?: CSSProperties }) => (
    <svg width={width} height={(width * 340) / 320} viewBox="0 0 320 340" style={style}>
        <g stroke={c.primary} strokeWidth="8" strokeLinecap="round">
            <line x1="63" y1="114" x2="49" y2="106" />
            <line x1="104" y1="73" x2="96" y2="59" />
            <line x1="160" y1="58" x2="160" y2="42" />
            <line x1="216" y1="73" x2="224" y2="59" opacity=".25" />
            <line x1="257" y1="114" x2="271" y2="106" opacity=".25" />
        </g>
        <path
            d="M60 276 V170 A100 100 0 0 1 260 170 V276 A24 24 0 0 1 236 300 H84 A24 24 0 0 1 60 276 Z"
            fill={c.primary}
        />
        <rect x="92" y="294" width="52" height="22" rx="11" fill={c.primary} />
        <rect x="176" y="294" width="52" height="22" rx="11" fill={c.primary} />
        <circle cx="134" cy="170" r="40" fill={W} />
        <circle cx="186" cy="170" r="40" fill={W} />
        <circle cx="134" cy="174" r="11" fill={c.bg} />
        <circle cx="186" cy="174" r="11" fill={c.bg} />
        <circle cx="138" cy="170" r="3.5" fill={W} />
        <circle cx="190" cy="170" r="3.5" fill={W} />
        <path d="M140 234 Q160 252 180 234" fill="none" stroke={W} strokeWidth="7" strokeLinecap="round" />
    </svg>
);
