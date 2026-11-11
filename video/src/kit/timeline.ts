import { pacing } from './tokens';
import { validate, type Presentation } from './schema';

export type PlacedScene = { id: string; index: number; from: number; dur: number; audioAt: number; audioDur: number; file: string };

/** Coloca las escenas una tras otra según la duración real de cada audio (nunca la estimada por caracteres). */
export const layout = (p: Presentation): { scenes: PlacedScene[]; total: number } => {
    const errors = validate(p);
    if (errors.length > 0) {
        throw new Error(`Presentación «${p.id}» inválida:\n- ${errors.join('\n- ')}`);
    }
    let cursor = 0;
    const scenes = p.scenes.map((s, index): PlacedScene => {
        const take = p.manifest.find((m) => m.id === s.lineId);
        const audioDur = Math.ceil((take?.durationSec ?? 0) * p.fps);
        const last = index === p.scenes.length - 1;
        const dur = pacing.lead + audioDur + (last ? pacing.outro : pacing.tail);
        const placed = { id: s.id, index, from: cursor, dur, audioAt: pacing.lead, audioDur, file: take?.file ?? '' };
        cursor += dur;
        return placed;
    });
    return { scenes, total: cursor };
};
