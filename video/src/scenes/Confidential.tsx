import { useCurrentFrame } from 'remotion';
import { Icon, PHONE, Phone } from '../components';
import { easeInOut, enter, lerp, tween } from '../ease';
import { T, c, radius } from '../theme';
import { MINI, MORPH, PHONE_AT, SuccessScreen } from './Invite';

const PHONES_Y = [245, PHONE_AT.y, 715] as const;
const LINE_X = 470;

const BUCKET = { x: 690, w: 494, h: 170, tops: [190, 400, 610] } as const;
const AREAS = [
    { name: 'Producción', total: 64 },
    { name: 'Administración', total: 21 },
    { name: 'Almacén', total: 4 },
] as const;

const N = 84;
const TRAVEL = 34;
const FIRST = MORPH.at + 46;
const bucketOf = (i: number): number => ([8, 38, 68].includes(i) ? 2 : i % 4 === 1 ? 1 : 0);
const startOf = (i: number): number => FIRST + Math.round(i * 1.4);
const NAMES: Record<number, string> = { 0: 'Mariana L.', 7: 'Jorge R.', 14: 'Lucía H.', 21: 'Andrés T.', 28: 'Sofía M.' };

const LOCK_AT = startOf(N - 1) + TRAVEL + 6;

/** Llegadas por área: primer y último frame, para contar suave. */
const span = (b: number): [number, number] => {
    const arrivals = Array.from({ length: N }, (_, i) => i)
        .filter((i) => bucketOf(i) === b)
        .map((i) => startOf(i) + TRAVEL);
    return [Math.min(...arrivals) - 4, Math.max(...arrivals)];
};

const Dot = ({ f, i }: { f: number; i: number }) => {
    const s = startOf(i);
    if (f < s || f > s + TRAVEL) {
        return null;
    }
    const src = i % 3;
    const b = bucketOf(i);
    const x0 = MINI.x + 70;
    const y0 = (PHONES_Y[src] ?? 0) + (((i * 37) % 60) - 30);
    const x1 = BUCKET.x + 40;
    const y1 = (BUCKET.tops[b] ?? 0) + BUCKET.h / 2 + (((i * 53) % 40) - 20);
    const p = tween(f, s, TRAVEL, easeInOut);
    const x = lerp(x0, x1, p);
    const yp = tween(p, 0.2, 0.7, easeInOut);
    const y = lerp(y0, y1, yp);
    const appear = tween(f, s, 6);
    const land = tween(p, 0.86, 0.14);

    const name = NAMES[i];
    let label = null;
    if (name) {
        const pD = (LINE_X - 30 - x0) / (x1 - x0);
        const pL = Math.min(p, pD);
        const lx = lerp(x0, x1, pL);
        const ly = lerp(y0, y1, tween(pL, 0.2, 0.7, easeInOut));
        const gone = tween(p, pD, 0.22);
        label = (
            <div
                style={{
                    position: 'absolute',
                    left: lx + 14,
                    top: ly - 52,
                    height: 40,
                    padding: '0 14px',
                    borderRadius: radius.control,
                    background: c.secondary,
                    border: `1px solid ${c.border}`,
                    fontSize: 26,
                    lineHeight: '40px',
                    color: c.fg,
                    whiteSpace: 'nowrap',
                    opacity: appear * (1 - gone),
                    filter: `blur(${gone * 8}px)`,
                    transform: `translate(${-gone * 12}px, ${-gone * 10}px) scale(${lerp(1, 0.94, gone)})`,
                }}
            >
                {name}
            </div>
        );
    }
    return (
        <>
            {label}
            <div
                style={{
                    position: 'absolute',
                    left: x - 7,
                    top: y - 7,
                    width: 14,
                    height: 14,
                    borderRadius: 99,
                    background: c.primary,
                    opacity: appear * (1 - land),
                    transform: `scale(${lerp(0.94, 1, appear) * lerp(1, 0.5, land)})`,
                }}
            />
        </>
    );
};

const Bucket = ({ f, k }: { f: number; k: number }) => {
    const area = AREAS[k];
    if (!area) {
        return null;
    }
    const [a, z] = span(k);
    const n = Math.round(area.total * tween(f, a, z - a, (t) => t));
    const locked = k === 2 ? tween(f, LOCK_AT, 16, easeInOut) : 0;
    return (
        <div
            style={{
                position: 'absolute',
                left: BUCKET.x,
                top: BUCKET.tops[k],
                width: BUCKET.w,
                height: BUCKET.h,
                boxSizing: 'border-box',
                padding: 28,
                borderRadius: radius.card,
                background: c.card,
                border: `1px solid ${c.border}`,
                ...enter(f, MORPH.at + 10 + k * 3, { dur: 22, x: 40, y: 0 }),
            }}
        >
            <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
                <div style={{ fontSize: 32, fontWeight: 600, color: c.fg }}>{area.name}</div>
                <div
                    style={{
                        fontSize: 56,
                        fontWeight: 600,
                        letterSpacing: -1,
                        lineHeight: 1,
                        color: c.fg,
                        opacity: 1 - locked,
                        filter: `blur(${locked * 8}px)`,
                    }}
                >
                    {n}
                </div>
            </div>
            <div style={{ marginTop: 6, fontSize: 26, color: c.mutedFg, opacity: 1 - locked }}>respuestas</div>
            <div
                style={{
                    position: 'absolute',
                    left: 28,
                    right: 28,
                    bottom: 28,
                    height: 10,
                    borderRadius: 99,
                    background: c.track,
                    opacity: 1 - locked,
                }}
            >
                <div
                    style={{
                        width: '100%',
                        height: '100%',
                        borderRadius: 99,
                        background: c.primary,
                        transformOrigin: 'left',
                        transform: `scaleX(${n / 64})`,
                    }}
                />
            </div>
            {k === 2 ? (
                <div
                    style={{
                        position: 'absolute',
                        left: 28,
                        bottom: 24,
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        fontSize: 26,
                        color: c.mutedFg,
                        ...enter(f, LOCK_AT + 6, { dur: 18, y: 8 }),
                    }}
                >
                    Oculto: menos de 5 respuestas
                </div>
            ) : null}
            {k === 2 ? (
                <div
                    style={{
                        position: 'absolute',
                        right: 28,
                        top: 26,
                        width: 52,
                        height: 52,
                        borderRadius: radius.control,
                        background: c.accent,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        ...enter(f, LOCK_AT + 4, { dur: 18, y: 0 }),
                    }}
                >
                    <Icon name="lock" size={28} color={c.primary} />
                </div>
            ) : null}
        </div>
    );
};

export const Confidential = () => {
    const f = useCurrentFrame();
    if (f < MORPH.at || f > T.s4 + 24) {
        return null;
    }
    const out = tween(f, T.s4, 16, easeInOut);
    return (
        <div
            style={{
                position: 'absolute',
                inset: 0,
                opacity: 1 - out,
                transform: `translateX(${-70 * out}px)`,
                filter: out > 0 ? `blur(${4 * out}px)` : 'none',
            }}
        >
            {PHONES_Y.map((y, j) =>
                // El del centro es el teléfono de la escena 2, que llega aquí al terminar su morph.
                j === 1 && f <= MORPH.at + MORPH.dur ? null : (
                <Phone
                    key={y}
                    style={{
                        left: PHONE_AT.x - PHONE.w / 2,
                        top: PHONE_AT.y - PHONE.h / 2,
                        transform: `translate(${MINI.x - PHONE_AT.x}px, ${y - PHONE_AT.y}px) scale(${MINI.scale})`,
                        ...(j === 1 ? {} : { opacity: tween(f, MORPH.at + 24 + j * 2, 18) }),
                    }}
                >
                    <SuccessScreen textOpacity={0} checkScale={2.2} />
                </Phone>
                ),
            )}

            <div
                style={{
                    position: 'absolute',
                    left: LINE_X - 1,
                    top: 150,
                    bottom: 150,
                    borderLeft: `2px dashed ${c.border}`,
                    ...enter(f, MORPH.at + 20, { dur: 20, y: 0, scale: 1 }),
                }}
            />
            <div
                style={{
                    position: 'absolute',
                    left: LINE_X,
                    top: 820,
                    transform: 'translateX(-50%)',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 10,
                        height: 46,
                        padding: '0 16px',
                        borderRadius: 999,
                        background: c.accent,
                        border: `1px solid ${c.border}`,
                        fontSize: 26,
                        fontWeight: 500,
                        color: c.fg,
                        whiteSpace: 'nowrap',
                        ...enter(f, MORPH.at + 26, { dur: 20, y: 8 }),
                    }}
                >
                    <Icon name="eyeOff" size={26} color={c.primary} />
                    Sin nombres
                </div>
            </div>

            {AREAS.map((a, k) => (
                <Bucket key={a.name} f={f} k={k} />
            ))}
            {Array.from({ length: N }, (_, i) => (
                <Dot key={i} f={f} i={i} />
            ))}
        </div>
    );
};
