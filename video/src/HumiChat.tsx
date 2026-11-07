import './fonts';
import { AbsoluteFill, useCurrentFrame } from 'remotion';
import { Icon } from './components';
import { easeInOut, enter, exit, lerp, tween } from './ease';
import { Block, PrimaryButton, ScenarioCard, SourceChip, Streamed, ToolStep, UserBubble } from './humi/ChatParts';
import { HumiLive } from './humi/HumiLive';
import { A1, A2, HC, Q1, Q2 } from './humi/timeline';
import { c, radius } from './theme';

const PANEL = { x: 60, y: 60, s: 960 } as const;
const PAD = 32;
const HEADER_H = 140;
const INPUT = { top: 740, h: 92 } as const;
const VIEW = { top: HEADER_H + 1, bottom: INPUT.top - 16 } as const;

const typed = (f: number): string => {
    if (f >= HC.q1Type && f < HC.q1Send) {
        return Q1.slice(0, Math.round(Q1.length * tween(f, HC.q1Type, 56, (t) => t)));
    }
    if (f >= HC.q2Type && f < HC.q2Send) {
        return Q2.slice(0, Math.round(Q2.length * tween(f, HC.q2Type, 40, (t) => t)));
    }
    return '';
};

const Header = ({ f }: { f: number }) => (
    <div
        style={{
            position: 'absolute',
            left: 0,
            right: 0,
            top: 0,
            height: HEADER_H,
            borderBottom: `1px solid ${c.border}`,
            display: 'flex',
            alignItems: 'center',
            gap: 22,
            padding: `0 ${PAD}px`,
        }}
    >
        <div
            style={{
                width: 100,
                height: 100,
                borderRadius: 999,
                background: c.accent,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                overflow: 'hidden',
            }}
        >
            <div style={{ marginTop: 10 }}>
                <HumiLive f={f} width={76} />
            </div>
        </div>
        <div>
            <div style={{ fontSize: 44, fontWeight: 600, lineHeight: 1.1 }}>Humi</div>
            <div style={{ marginTop: 4, fontSize: 34, color: c.mutedFg }}>Asistente de Humetría</div>
        </div>
    </div>
);

const Greeting = ({ f }: { f: number }) => {
    let style;
    if (f < HC.q1Send + 20) {
        style = exit(f, HC.q1Send - 2, { dur: 16, y: -20 });
    } else if (f >= HC.calmIn) {
        style = enter(f, HC.calmIn, { dur: 28, y: 20 });
    } else {
        return null;
    }
    return (
        <div
            style={{
                position: 'absolute',
                inset: 0,
                display: 'flex',
                flexDirection: 'column',
                alignItems: 'center',
                justifyContent: 'center',
                ...style,
            }}
        >
            <HumiLive f={f} width={200} />
            <div style={{ marginTop: 26, fontSize: 48, fontWeight: 600, letterSpacing: -0.5 }}>Hola, ¿en qué te ayudo?</div>
            <div style={{ marginTop: 10, fontSize: 34, color: c.mutedFg }}>Pregúntame por tus centros, normas o escenarios.</div>
        </div>
    );
};

const Conversation = ({ f }: { f: number }) => {
    if (f < HC.q1Send || f > HC.out + 24) {
        return null;
    }
    return (
        <div
            style={{
                position: 'absolute',
                left: 0,
                right: 0,
                bottom: 0,
                paddingBottom: 8,
                display: 'flex',
                flexDirection: 'column',
                ...(f >= HC.out ? exit(f, HC.out, { dur: 22, y: -24, scale: 0.98 }) : {}),
            }}
        >
            <Block f={f} at={HC.q1Send} h={163}>
                <UserBubble f={f} at={HC.q1Send} text={Q1} />
            </Block>
            <Block f={f} at={HC.tool1} h={62}>
                <ToolStep f={f} at={HC.tool1} done={HC.tool1Done} label="Consultó el avance de Planta Querétaro" />
            </Block>
            <Block f={f} at={HC.tool2} h={80}>
                <ToolStep f={f} at={HC.tool2} done={HC.tool2Done} label="Revisó las listas de NOM-037" />
            </Block>
            <Block f={f} at={HC.ans1} h={128}>
                <Streamed f={f} at={HC.ans1} text={A1} />
            </Block>
            <Block f={f} at={HC.chips1} h={76}>
                <div style={{ display: 'flex', gap: 12 }}>
                    <SourceChip label="NOM-035-STPS-2018" style={enter(f, HC.chips1, { dur: 16, y: 8 })} />
                    <SourceChip label="NOM-037-STPS-2023" style={enter(f, HC.chips1 + 2, { dur: 16, y: 8 })} />
                </div>
            </Block>
            <Block f={f} at={HC.btn1} h={96}>
                <PrimaryButton f={f} at={HC.btn1} press={HC.press1} label="Ver pendientes" />
            </Block>
            <Block f={f} at={HC.q2Send} h={113}>
                <UserBubble f={f} at={HC.q2Send} text={Q2} />
            </Block>
            <Block f={f} at={HC.tool3} h={80}>
                <ToolStep f={f} at={HC.tool3} done={HC.tool3Done} label="Consultó Compensación" />
            </Block>
            <Block f={f} at={HC.ans2} h={128}>
                <Streamed f={f} at={HC.ans2} text={A2} />
            </Block>
            <Block f={f} at={HC.card2} h={300}>
                <ScenarioCard f={f} at={HC.card2} />
            </Block>
        </div>
    );
};

const Input = ({ f }: { f: number }) => {
    const text = typed(f);
    const active = Math.max(
        tween(f, HC.q1Type, 6) * (1 - tween(f, HC.q1Send, 6)),
        tween(f, HC.q2Type, 6) * (1 - tween(f, HC.q2Send, 6)),
    );
    const focus = Math.max(
        tween(f, HC.q1Type - 14, 8) * (1 - tween(f, HC.q1Send + 4, 10)),
        tween(f, HC.q2Type - 14, 8) * (1 - tween(f, HC.q2Send + 4, 10)),
    );
    const typing = text.length > 0 && text.length < Math.max(Q1.length, Q2.length) && f < (f < HC.q2Type ? HC.q1Type + 56 : HC.q2Type + 40);
    const caret = focus > 0.5 && (typing || Math.floor(f / 15) % 2 === 0);
    const press = Math.max(
        tween(f, HC.q1Send - 4, 4, easeInOut) * (1 - tween(f, HC.q1Send, 8, easeInOut)),
        tween(f, HC.q2Send - 4, 4, easeInOut) * (1 - tween(f, HC.q2Send, 8, easeInOut)),
    );
    return (
        <div
            style={{
                position: 'absolute',
                left: PAD,
                right: PAD,
                top: INPUT.top,
                height: INPUT.h,
                borderRadius: radius.card,
                background: c.bg,
                border: `1px solid ${c.border}`,
                display: 'flex',
                alignItems: 'center',
                gap: 16,
                padding: '0 14px 0 26px',
                boxSizing: 'border-box',
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    inset: -1,
                    borderRadius: radius.card,
                    border: `2px solid ${c.primary}`,
                    opacity: focus,
                }}
            />
            {/* Si el texto ya no cabe, se ve el final, como en un campo real. */}
            <div style={{ position: 'relative', flex: 1, display: 'flex', justifyContent: 'flex-end', overflow: 'hidden' }}>
                <div style={{ flexShrink: 0, minWidth: '100%', display: 'flex', alignItems: 'center', whiteSpace: 'pre', fontSize: 36 }}>
                    {text}
                    <span
                        style={{
                            width: 3,
                            height: 42,
                            marginLeft: 2,
                            borderRadius: 2,
                            background: c.primary,
                            opacity: caret ? 1 : 0,
                        }}
                    />
                </div>
                <div
                    style={{
                        position: 'absolute',
                        left: 6,
                        top: 0,
                        bottom: 0,
                        display: 'flex',
                        alignItems: 'center',
                        fontSize: 36,
                        color: c.mutedFg,
                        opacity: text.length > 0 ? 0 : 1,
                    }}
                >
                    Pregúntale a Humi
                </div>
            </div>
            <div
                style={{
                    position: 'relative',
                    width: 64,
                    height: 64,
                    borderRadius: radius.control,
                    background: c.accent,
                    flexShrink: 0,
                    transform: `scale(${lerp(1, 0.94, press)})`,
                }}
            >
                <div style={{ position: 'absolute', inset: 0, borderRadius: radius.control, background: c.primary, opacity: active }} />
                <Icon
                    name="arrowUp"
                    size={32}
                    color={active > 0.5 ? c.primaryFg : c.mutedFg}
                    stroke={2.4}
                    style={{ position: 'absolute', left: 16, top: 16 }}
                />
            </div>
        </div>
    );
};

export const HumiChat = () => {
    const f = useCurrentFrame();
    return (
        <AbsoluteFill style={{ background: c.bg, fontFamily: 'Figtree, sans-serif', fontVariantNumeric: 'tabular-nums', color: c.fg }}>
            <div
                style={{
                    position: 'absolute',
                    left: PANEL.x,
                    top: PANEL.y,
                    width: PANEL.s,
                    height: PANEL.s,
                    borderRadius: radius.card,
                    background: c.card,
                    border: `1px solid ${c.border}`,
                    boxSizing: 'border-box',
                    overflow: 'hidden',
                }}
            >
                <Header f={f} />
                <div
                    style={{
                        position: 'absolute',
                        left: PAD,
                        right: PAD,
                        top: VIEW.top,
                        height: VIEW.bottom - VIEW.top,
                        overflow: 'hidden',
                        maskImage: 'linear-gradient(to bottom, transparent 0, black 56px)',
                    }}
                >
                    <Greeting f={f} />
                    <Conversation f={f} />
                </div>
                <Input f={f} />
                <div
                    style={{
                        position: 'absolute',
                        left: PAD,
                        right: PAD,
                        top: INPUT.top + INPUT.h + 16,
                        textAlign: 'center',
                        fontSize: 34,
                        lineHeight: 1.3,
                        color: c.mutedFg,
                    }}
                >
                    Humi puede equivocarse. Verifica las cifras antes de reportarlas.
                </div>
            </div>
        </AbsoluteFill>
    );
};
