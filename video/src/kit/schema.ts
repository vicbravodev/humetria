import type { ComponentType } from 'react';

/** Lo que recibe cada escena: su frame local y los tiempos de su narración, todos en frames. */
export type SceneProps = { f: number; dur: number; audioAt: number; audioDur: number };

/** `text` se muestra; `speech`, si existe, es lo que se le dice a la voz (p. ej. «Umetría» para la marca). */
export type ScriptLine = { id: string; text: string; speech?: string };

/** Registro de una toma masterizada (lo escribe scripts/voice-master.mjs). */
export type ManifestLine = {
    id: string;
    file: string;
    durationSec: number;
    fingerprint: string;
    generatedWith: { voiceId: string; modelId: string };
};

export type SceneSpec = {
    id: string;
    /** Línea del guion que narra esta escena. */
    lineId: string;
    /** Evidencia en el repositorio de lo que la escena afirma (ruta o documento). */
    evidence: readonly string[];
    component: ComponentType<SceneProps>;
};

export type Presentation = {
    id: string;
    title: string;
    /** Qué necesidad atiende y qué beneficio comunica. */
    goal: string;
    width: number;
    height: number;
    fps: number;
    /** Marco de película de herramienta: nombre y un rótulo por escena (capítulo). */
    frame?: { tool: string; chapters: readonly string[] };
    /** Frames que se queda la última escena (por defecto, el cierre de una pieza suelta). */
    hold?: number;
    lines: readonly ScriptLine[];
    manifest: readonly ManifestLine[];
    scenes: readonly SceneSpec[];
};

/** Errores estructurales de una presentación. Vacío = válida. */
export const validate = (p: Presentation): string[] => {
    const errors: string[] = [];
    const lineIds = new Set(p.lines.map((l) => l.id));
    const seen = new Set<string>();
    if (p.frame && p.frame.chapters.length !== p.scenes.length) {
        errors.push('El marco declara un rótulo de capítulo por escena');
    }
    if (p.scenes.length === 0) {
        errors.push('Sin escenas');
    }
    for (const s of p.scenes) {
        if (seen.has(s.id)) {
            errors.push(`Escena repetida: ${s.id}`);
        }
        seen.add(s.id);
        if (!lineIds.has(s.lineId)) {
            errors.push(`${s.id}: la línea ${s.lineId} no existe en el guion`);
        }
        const take = p.manifest.find((m) => m.id === s.lineId);
        if (!take) {
            errors.push(`${s.id}: la línea ${s.lineId} no tiene audio masterizado`);
        } else if (!(take.durationSec > 0)) {
            errors.push(`${s.id}: duración de audio inválida`);
        }
        if (s.evidence.length === 0) {
            errors.push(`${s.id}: toda escena declara su evidencia en el repositorio`);
        }
    }
    return errors;
};
