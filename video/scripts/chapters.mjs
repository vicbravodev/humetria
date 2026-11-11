// Escribe los tiempos de capítulo de una película (segundos), derivados de la duración real de cada audio.
// La landing los lee para saltar de capítulo. Uso: node scripts/chapters.mjs nom-035 angie
import { readFileSync, writeFileSync } from 'node:fs';

const [id, variant = 'angie'] = process.argv.slice(2);
const pacing = JSON.parse(readFileSync('src/kit/pacing.json', 'utf8'));
const script = JSON.parse(readFileSync(`src/presentations/${id}/script.json`, 'utf8'));
const manifest = JSON.parse(readFileSync(`voice/manifest.${id}.${variant}.json`, 'utf8'));
let frame = 0;
const chapters = script.lines.map((line, i) => {
    const take = manifest.lines.find((m) => m.id === line.id);
    const at = frame / pacing.fps;
    frame += pacing.lead + Math.ceil(take.durationSec * pacing.fps) + pacing.tail + (i === script.lines.length - 1 ? script.outro ?? 0 : 0);
    return { label: script.frame.chapters[i], at: Math.round(at * 100) / 100 };
});
const out = { id, durationSec: Math.round((frame / pacing.fps) * 100) / 100, chapters };
writeFileSync(`voice/chapters.${id}.json`, JSON.stringify(out, null, 2) + '\n');
console.log(JSON.stringify(out));
