// Valida una presentación sin renderizar: guion ↔ manifiesto (huella), audios presentes, duraciones y largo de línea.
// Uso: node scripts/check.mjs anonimato angie
import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';

const [id, variant = 'angie'] = process.argv.slice(2);
const profile = JSON.parse(readFileSync('voice/profile.json', 'utf8'));
const voice = profile.voices[variant];
const script = JSON.parse(readFileSync(`src/presentations/${id}/script.json`, 'utf8'));
const manifest = JSON.parse(readFileSync(`voice/manifest.${id}.${variant}.json`, 'utf8'));
const errors = [];
for (const line of script.lines) {
    const take = manifest.lines.find((m) => m.id === line.id);
    if (!take) { errors.push(`${line.id}: sin audio masterizado`); continue; }
    const spoken = line.speech ?? line.text;
    const fp = createHash('sha256').update([spoken, voice.voiceId, profile.modelId].join('|')).digest('hex').slice(0, 12);
    if (fp !== take.fingerprint) errors.push(`${line.id}: el texto, la voz o el modelo cambiaron desde la toma aprobada (regenerar y masterizar)`);
    if (!existsSync(`public/${take.file}`)) errors.push(`${line.id}: falta public/${take.file}`);
    if (!(take.durationSec > 0.5 && take.durationSec < 15)) errors.push(`${line.id}: duración fuera de rango (${take.durationSec} s)`);
    if (line.text.split(/\s+/).length > 28) errors.push(`${line.id}: más de ~28 palabras`);
}
if (errors.length) { console.error(errors.join('\n')); process.exit(1); }
console.log(`${id}/${variant}: ${script.lines.length} líneas, audios y huellas en orden`);
