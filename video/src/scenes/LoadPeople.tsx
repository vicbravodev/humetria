import type { CSSProperties } from 'react';
import { useCurrentFrame } from 'remotion';
import { CardHeader, Dropzone, FileChip, HeaderRule, MainCard, Tag, Avatar } from '../components';
import { easeInOut, enter, exit, lerp, tween } from '../ease';
import { CARD_PAD, COLHEAD_H, HEADER_H, ROW_H, T, c } from '../theme';

export const PEOPLE = [
    { initials: 'ML', name: 'Mariana López', area: 'Producción', tools: ['Guía III', 'Ley Silla'] },
    { initials: 'JR', name: 'Jorge Ramírez', area: 'Administración', tools: ['Guía III', 'NOM-037'] },
    { initials: 'LH', name: 'Lucía Hernández', area: 'Almacén', tools: ['Guía III', 'Ley Silla'] },
    { initials: 'AT', name: 'Andrés Torres', area: 'Producción', tools: ['Guía III', 'Ley Silla'] },
    { initials: 'SM', name: 'Sofía Mendoza', area: 'Administración', tools: ['Guía III', 'NOM-037'] },
] as const;

const COL_AREA = 392;
const COL_TOOLS = 652;
/** Primer renglón de la tabla, relativo a la tarjeta. */
export const ROWS_TOP = HEADER_H + COLHEAD_H;
/** Cuándo cada renglón se vuelve sobre (escena 2). */
export const rowExitAt = (i: number): number => T.s2 + 2 + i * 3;

/** Encabezado y zona de carga del estado de reposo: idéntico al último frame. */
export const CalmContent = ({
    subtitle,
    dropzone,
    headerStyle,
}: {
    subtitle: string;
    headerStyle?: CSSProperties;
    dropzone?: { highlight: number; content?: number; style?: CSSProperties };
}) => (
    <>
        <CardHeader title="Planta Querétaro" subtitle={subtitle} {...(headerStyle ? { style: headerStyle } : {})} />
        <HeaderRule />
        {dropzone ? <Dropzone {...dropzone} /> : null}
    </>
);

export const LoadPeople = () => {
    const f = useCurrentFrame();
    if (f > T.s2 + 50) {
        return null;
    }

    const count = Math.round(120 * tween(f, 62, 40));

    // Archivo: cae a la zona (12→34), luego se acomoda en el encabezado (50→72).
    const drop = tween(f, 12, 22);
    const dock = tween(f, 50, 22, easeInOut);
    const landX = 544;
    const landY = 414;
    const dockX = 1088 - CARD_PAD - 128;
    const dockY = HEADER_H / 2;
    const chipX = lerp(landX, dockX, dock);
    const chipY = lerp(landY - 90, landY, drop) + (dockY - landY) * dock;
    const chipScale = lerp(0.94, 1, drop) * lerp(1, 0.9, dock);

    const zoneOut = tween(f, 48, 14, easeInOut);
    const zoneText = 1 - tween(f, 24, 10, easeInOut);

    return (
        <MainCard style={exit(f, T.s2 + 6, { dur: 24, x: -80, y: 0 })}>
            <CalmContent
                subtitle={`${count} personas`}
                dropzone={{
                    highlight: tween(f, 26, 8) * (1 - zoneOut),
                    content: zoneText,
                    style: { opacity: 1 - zoneOut, transform: `scale(${lerp(1, 0.98, zoneOut)})` },
                }}
            />
            {/* Encabezados de columna */}
            <div
                style={{
                    position: 'absolute',
                    left: 0,
                    right: 0,
                    top: HEADER_H,
                    height: COLHEAD_H,
                    background: c.muted,
                    borderBottom: `1px solid ${c.border}`,
                    fontSize: 26,
                    fontWeight: 500,
                    color: c.mutedFg,
                    ...enter(f, 56, { dur: 16, y: 0, scale: 1 }),
                }}
            >
                {(
                    [
                        ['Persona', CARD_PAD],
                        ['Área', COL_AREA],
                        ['Herramientas', COL_TOOLS],
                    ] as const
                ).map(([label, x]) => (
                    <div key={label} style={{ position: 'absolute', left: x, top: 0, lineHeight: `${COLHEAD_H}px` }}>
                        {label}
                    </div>
                ))}
            </div>

            {PEOPLE.map((p, i) => {
                const rowIn = enter(f, 62 + i * 3, { dur: 18, y: 16, scale: 1 });
                const contentOut = 1 - tween(f, rowExitAt(i), 10, easeInOut);
                return (
                    <div
                        key={p.name}
                        style={{
                            position: 'absolute',
                            left: 0,
                            right: 0,
                            top: ROWS_TOP + i * ROW_H,
                            height: ROW_H,
                            borderBottom: `1px solid ${c.border}`,
                            ...rowIn,
                        }}
                    >
                        <div style={{ position: 'absolute', inset: 0, opacity: contentOut }}>
                            <Avatar
                                initials={p.initials}
                                style={{ position: 'absolute', left: CARD_PAD, top: (ROW_H - 52) / 2 }}
                            />
                            <div
                                style={{
                                    position: 'absolute',
                                    left: CARD_PAD + 70,
                                    top: 0,
                                    lineHeight: `${ROW_H}px`,
                                    fontSize: 30,
                                    fontWeight: 500,
                                    color: c.fg,
                                }}
                            >
                                {p.name}
                            </div>
                            <div
                                style={{
                                    position: 'absolute',
                                    left: COL_AREA,
                                    top: 0,
                                    lineHeight: `${ROW_H}px`,
                                    fontSize: 28,
                                    color: c.mutedFg,
                                }}
                            >
                                {p.area}
                            </div>
                            <div
                                style={{
                                    position: 'absolute',
                                    left: COL_TOOLS,
                                    top: (ROW_H - 42) / 2,
                                    display: 'flex',
                                    gap: 10,
                                }}
                            >
                                {p.tools.map((t, k) => (
                                    <Tag key={t} label={t} style={enter(f, 104 + i * 4 + k * 2, { dur: 14, y: 6 })} />
                                ))}
                            </div>
                        </div>
                    </div>
                );
            })}

            <div
                style={{
                    position: 'absolute',
                    left: CARD_PAD,
                    top: ROWS_TOP + 5 * ROW_H,
                    height: 720 - ROWS_TOP - 5 * ROW_H,
                    display: 'flex',
                    alignItems: 'center',
                    fontSize: 26,
                    color: c.mutedFg,
                    ...enter(f, 82, { dur: 16, y: 8, scale: 1 }),
                    opacity: tween(f, 82, 16) * (1 - tween(f, T.s2 + 2, 10, easeInOut)),
                }}
            >
                y 115 personas más
            </div>

            <FileChip
                style={{
                    left: chipX,
                    top: chipY,
                    transform: `translate(-50%, -50%) scale(${chipScale})`,
                    opacity: drop,
                    filter: `blur(${lerp(4, 0, drop)}px)`,
                    boxShadow: `0 8px 24px rgba(0,0,0,${0.32 * (1 - dock)})`,
                }}
            />
        </MainCard>
    );
};
