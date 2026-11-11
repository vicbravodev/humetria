// Masteriza las tomas crudas de una voz a -16 LUFS, mide su duración real y la anota en el manifiesto.
// Uso: node scripts/voice-master.mjs anonimato angie   (espera public/audio/<id>/<voz>/raw/l1.mp3 … lN.mp3)
// No llama a ElevenLabs: la generación va por el MCP (ver docs/MOTION-KIT.md) y aquí solo se registra lo aprobado.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';

const [id, variant = 'angie'] = process.argv.slice(2);
if (!id) throw new Error('Falta el id de la presentación');
const profile = JSON.parse(readFileSync('voice/profile.json', 'utf8'));
const voice = profile.voices[variant];
if (!voice) throw new Error(`Voz desconocida: ${variant}`);
const script = JSON.parse(readFileSync(`src/presentations/${id}/script.json`, 'utf8'));
const { integratedLufs: i, truePeakDb: tp, loudnessRange: lra } = profile.mastering;
const rem = (args) => execFileSync('npx', ['remotion', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });

const lines = script.lines.map((line, n) => {
    const raw = `public/audio/${id}/${variant}/raw/l${n + 1}.mp3`;
    const out = `public/audio/${id}/${variant}/l${n + 1}.mp3`;
    if (!existsSync(raw)) throw new Error(`Falta la toma cruda ${raw}`);
    rem(['ffmpeg', '-y', '-i', raw, '-af', `loudnorm=I=${i}:TP=${tp}:LRA=${lra}`, '-ac', '1', '-ar', '44100', '-b:a', '128k', out]);
    const durationSec = Number(rem(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', out]).trim());
    // La huella usa lo que se le dice a la voz (speech), no lo que se muestra (text).
    const spoken = line.speech ?? line.text;
    const fingerprint = createHash('sha256').update([spoken, voice.voiceId, profile.modelId].join('|')).digest('hex').slice(0, 12);
    return { id: line.id, file: `audio/${id}/${variant}/l${n + 1}.mp3`, durationSec, fingerprint, generatedWith: { voiceId: voice.voiceId, modelId: profile.modelId } };
});
writeFileSync(`voice/manifest.${id}.${variant}.json`, JSON.stringify({ presentation: id, voice: variant, masteredAt: new Date().toISOString(), lines }, null, 2) + '\n');
console.log(`${variant}\n` + lines.map((l) => `${l.id}: ${l.durationSec.toFixed(2)} s (${l.fingerprint})`).join('\n'));
