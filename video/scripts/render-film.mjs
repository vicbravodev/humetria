// Renderiza una película en tema oscuro y claro: mp4 (Safari), webm (Chromium) y póster, a ../public/video.
// Uso: node scripts/render-film.mjs <composición> <nombre-público> <frame-del-póster>
//   p. ej.: node scripts/render-film.mjs NOM035 humetria-nom-035-v2 150
import { execFileSync } from 'node:child_process';

const [comp, name, poster = '120'] = process.argv.slice(2);
if (!comp || !name) throw new Error('Uso: render-film.mjs <composición> <nombre> [frame-póster]');
const run = (args) => execFileSync('npx', ['remotion', ...args], { stdio: 'inherit' });
for (const theme of ['dark', 'light']) {
    const out = `../public/video/${theme === 'light' ? `${name}-light` : name}`;
    const props = `--props={"theme":"${theme}"}`;
    run(['render', comp, `${out}.mp4`, '--codec=h264', '--crf=20', '--pixel-format=yuv420p', props]);
    run(['render', comp, `${out}.webm`, '--codec=vp9', '--crf=32', props]);
    run(['still', comp, `${out}.jpg`, `--frame=${poster}`, '--image-format=jpeg', '--jpeg-quality=85', props]);
}
