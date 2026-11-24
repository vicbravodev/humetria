// Portadas del blog: una por artículo de content/blog, del color de la familia de su herramienta (`tool:` del front matter).
// Escribe public/images/blog/{slug}.webp (la de la página y las tarjetas) y public/images/blog/{slug}-og.jpg (con título, para Open Graph).
// Uso, desde video/: npm run covers:blog [slug ...]    Requiere `cwebp` (brew install webp).
import { execFileSync } from 'node:child_process';
import { mkdirSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const articles = join(import.meta.dirname, '../../content/blog');
const out = join(import.meta.dirname, '../../public/images/blog');
const only = process.argv.slice(2);
mkdirSync(out, { recursive: true });

const field = (front, key) => front.match(new RegExp(`^${key}:\\s*(.+)$`, 'm'))?.[1].trim().replace(/^['"]|['"]$/g, '');

for (const [variant, file] of readdirSync(articles).filter((name) => name.endsWith('.md')).entries()) {
    const slug = file.replace(/\.md$/, '');
    if (only.length > 0 && !only.includes(slug)) continue;
    const front = readFileSync(join(articles, file), 'utf8').split(/^---$/m)[1] ?? '';
    const tool = field(front, 'tool');
    const title = field(front, 'title');
    if (!tool || !title) { console.warn(`${slug}: sin tool o title, se omite`); continue; }

    const png = join(tmpdir(), `${slug}.png`);
    const render = (withTitle, target, format) => execFileSync('npx', ['remotion', 'still', 'BlogCover', target, `--props=${JSON.stringify({ tool, title, withTitle, variant })}`, `--image-format=${format}`, '--jpeg-quality=88', '--log=error'], { stdio: 'inherit' });
    render(false, png, 'png');
    execFileSync('cwebp', ['-quiet', '-q', '82', png, '-o', join(out, `${slug}.webp`)]);
    rmSync(png);
    render(true, join(out, `${slug}-og.jpg`), 'jpeg');
    console.log(`${slug}: ok`);
}
