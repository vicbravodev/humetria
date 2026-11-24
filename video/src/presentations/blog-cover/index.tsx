import { AbsoluteFill, Composition } from 'remotion';
import '../../fonts';
import { TOOL_PATHS } from './icons';

// Color de cada familia de herramientas (--family-* de resources/css/app.css, tema claro) y el nombre de cada herramienta.
const FAMILY = {
    compliance: { solid: '#1F5FC4', deep: '#17489A', soft: '#E8EFFC' },
    listening: { solid: '#A02F6E', deep: '#7A2354', soft: '#FBEBF3' },
    operations: { solid: '#5B3FC4', deep: '#432E99', soft: '#EFEBFD' },
} as const;

export const BLOG_TOOLS = {
    nom_035: { name: 'NOM-035', family: 'compliance' },
    nom_037: { name: 'NOM-037', family: 'compliance' },
    ley_silla: { name: 'Ley Silla', family: 'compliance' },
    attendance: { name: 'Registro de jornada', family: 'compliance' },
    complaints: { name: 'Buzón de quejas', family: 'listening' },
    engagement: { name: 'Clima y pulso', family: 'listening' },
    leave: { name: 'Vacaciones y permisos', family: 'operations' },
    org_chart: { name: 'Organigrama', family: 'operations' },
    compensation: { name: 'Compensación', family: 'operations' },
} as const;

export type CoverProps = { tool: keyof typeof BLOG_TOOLS; title: string; withTitle: boolean; variant: number };

const FONT = 'Figtree, system-ui, sans-serif';

/** Anillos concéntricos desde la esquina: dan profundidad sin ruido; cada herramienta los desplaza distinto. */
const Rings = ({ cx, cy, color }: { cx: number; cy: number; color: string }) => (
    <svg width="1200" height="630" style={{ position: 'absolute', inset: 0 }}>
        {[120, 200, 290, 390, 500, 620].map((r, i) => (
            <circle key={r} cx={cx} cy={cy} r={r} fill="none" stroke={color} strokeOpacity={0.22 - i * 0.03} strokeWidth={2} />
        ))}
    </svg>
);

/** Portada de un artículo del blog: el color de la familia de la herramienta y su dibujo. Con título solo para Open Graph. */
export const BlogCover = ({ tool, title, withTitle, variant }: CoverProps) => {
    const meta = BLOG_TOOLS[tool];
    const color = FAMILY[meta.family];
    const seed = variant;
    const cx = withTitle ? 1020 : 820 + (seed % 3) * 70;
    const cy = withTitle ? 120 : 140 + (seed % 2) * 160;

    return (
        <AbsoluteFill style={{ background: `linear-gradient(135deg, ${color.solid} 0%, ${color.deep} 100%)`, fontFamily: FONT, color: '#FFFFFF' }}>
            <Rings cx={cx} cy={cy} color="#FFFFFF" />
            <div style={{ position: 'absolute', left: withTitle ? 880 : 760, top: withTitle ? 40 : 110, width: withTitle ? 240 : 330, height: withTitle ? 240 : 330, borderRadius: 56, background: color.soft, display: 'flex', alignItems: 'center', justifyContent: 'center', boxShadow: '0 24px 60px rgba(0,0,0,.22)' }}>
                <svg viewBox="0 0 24 24" width={withTitle ? 150 : 210} height={withTitle ? 150 : 210} fill="none" stroke={color.solid} strokeWidth={1.5} strokeLinecap="round" strokeLinejoin="round">
                    {(TOOL_PATHS[tool] ?? []).map((d) => <path key={d} d={d} />)}
                </svg>
            </div>
            {withTitle ? (
                <div style={{ position: 'absolute', left: 72, top: 190, width: 770, fontSize: 62, lineHeight: 1.06, fontWeight: 600, letterSpacing: '-0.035em' }}>{title}</div>
            ) : null}
            <div style={{ position: 'absolute', left: 72, bottom: 64, display: 'flex', alignItems: 'center', gap: 16, fontSize: 30, fontWeight: 600, letterSpacing: '-0.01em' }}>
                <span style={{ padding: '10px 22px', borderRadius: 999, background: 'rgba(255,255,255,.16)', border: '2px solid rgba(255,255,255,.45)' }}>{meta.name}</span>
                <span style={{ opacity: 0.85, fontWeight: 500 }}>Blog de Humetría</span>
            </div>
        </AbsoluteFill>
    );
};

export const BlogCoverStills = () => (
    <Composition id="BlogCover" component={BlogCover} durationInFrames={1} fps={30} width={1200} height={630} defaultProps={{ tool: 'nom_035', title: 'Título', withTitle: false, variant: 0 } satisfies CoverProps} />
);
