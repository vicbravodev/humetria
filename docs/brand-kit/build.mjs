// Genera las imágenes de redes en docs/brand-kit/png. Desde la raíz del repo, con `npm run build` hecho (fuentes en public/build):
//   node docs/brand-kit/build.mjs
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '../..');
const out = resolve(import.meta.dirname, 'png');
mkdirSync(out, { recursive: true });
const tmp = resolve(import.meta.dirname, 'html');
mkdirSync(tmp, { recursive: true });
const f = (p) => `file://${root}/${p}`;
const font = (w, file) => `@font-face{font-family:Figtree;font-weight:${w};src:url(${f('public/build/assets/' + file)}) format('woff2')}`;

// Tokens de docs/design-system/tokens.json
const themes = {
    light: { bg: '#FBFDFC', fg: '#14211F', sub: '#56635F', primary: '#0B7A6F', deco: '#E5F1EE', card: '#FFFFFF', line: '#DCE5E2', logo: 'humetria-horizontal-color', sym: 'humetria-simbolo-color' },
    jade: { bg: '#0B7A6F', fg: '#FFFFFF', sub: '#E5F1EE', primary: '#FFFFFF', deco: 'rgba(255,255,255,.09)', card: 'rgba(255,255,255,.12)', line: 'rgba(255,255,255,.35)', logo: 'humetria-horizontal-blanco', sym: 'humetria-simbolo-blanco' },
    dark: { bg: '#0F1716', fg: '#F1FAF8', sub: '#9DAEAA', primary: '#3CCFBD', deco: '#1A2523', card: '#141E1C', line: '#26332F', logo: 'humetria-horizontal-color-oscuro', sym: 'humetria-simbolo-color-oscuro' },
};

const arc = (c) => `<svg class="deco" viewBox="0 0 64 64"><path d="M12 6V58M12 36C12 17 52 17 52 36V58" fill="none" stroke="${c}" stroke-width="10" stroke-linecap="round"/></svg>`;

const base = (t, w, h, body, extra = '') => `<!doctype html><meta charset="utf-8"><style>
${font(400, 'figtree-400-normal-g7Dtegnw.woff2')}${font(500, 'figtree-500-normal-B_yy1phn.woff2')}${font(600, 'figtree-600-normal-Cv_xCTDl.woff2')}
*{box-sizing:border-box;margin:0}html,body{width:${w}px;height:${h}px;overflow:hidden}
body{background:${t.bg};color:${t.fg};font-family:Figtree,sans-serif;position:relative}
.deco{position:absolute;right:-170px;bottom:-150px;width:820px;height:820px}
${extra}</style>${body}`;

// Publicación vertical 1080x1350 (Instagram, LinkedIn y Facebook)
function post({ theme = 'light', eyebrow, big, bigSize = 260, title, text, footer = true, n }) {
    const t = themes[theme];
    return base(t, 1080, 1350, `${arc(t.deco)}
<main>
  <p class="eb">${eyebrow}</p>
  ${big ? `<p class="big">${big}</p>` : ''}
  <h1>${title}</h1>
  ${text ? `<p class="tx">${text}</p>` : ''}
</main>
${n ? `<span class="n">${n}</span>` : ''}
${footer ? `<footer><img src="${f('public/brand/' + t.logo + '.svg')}" height="46"><span>humetria.com.mx</span></footer>` : ''}`, `
main{position:absolute;left:96px;right:96px;top:150px;bottom:230px;display:flex;flex-direction:column;justify-content:center;z-index:1}
.eb{font-size:30px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:${t.primary};margin-bottom:36px}
.big{white-space:nowrap;font-size:${bigSize}px;line-height:.95;font-weight:600;letter-spacing:-.03em;color:${t.primary};margin-bottom:28px}
h1{text-wrap:balance;font-size:${big ? 80 : 98}px;line-height:1.05;font-weight:600;letter-spacing:-.02em}
.tx{font-size:40px;line-height:1.35;color:${t.sub};margin-top:40px;max-width:820px}
.n{position:absolute;right:96px;top:88px;font-size:28px;font-weight:500;color:${t.sub};z-index:1}
footer{position:absolute;left:96px;right:96px;bottom:84px;display:flex;justify-content:space-between;align-items:center;border-top:2px solid ${t.line};padding-top:36px;font-size:32px;color:${t.sub};z-index:1}
footer img{width:auto}`);
}

// Historia / portada de Reel 1080x1920 (lo importante queda entre y=250 y y=1550)
function reel({ theme = 'jade', eyebrow, title, text }) {
    const t = themes[theme];
    return base(t, 1080, 1920, `${arc(t.deco)}
<main><p class="eb">${eyebrow}</p><h1>${title}</h1><p class="tx">${text}</p></main>
<footer><img src="${f('public/brand/' + t.logo + '.svg')}" height="52"></footer>`, `
.deco{width:1000px;height:1000px;right:-260px;bottom:-200px}
main{position:absolute;left:96px;right:96px;top:300px;bottom:380px;display:flex;flex-direction:column;justify-content:center;z-index:1}
.eb{font-size:34px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:${t.primary};margin-bottom:44px}
h1{text-wrap:balance;font-size:120px;line-height:1.03;font-weight:600;letter-spacing:-.025em}
.tx{font-size:46px;line-height:1.3;color:${t.sub};margin-top:52px}
footer{position:absolute;left:96px;bottom:300px;z-index:1}footer img{width:auto}`);
}

function profile(theme) {
    const t = themes[theme];
    return base(t, 1080, 1080, `<img src="${f('public/brand/' + t.sym + '.svg')}">`, `
body{display:flex;align-items:center;justify-content:center}img{width:560px;height:560px}`);
}

function cover(w, h, theme, scale) {
    const t = themes[theme];
    return base(t, w, h, `${arc(t.deco)}
<div class="c"><img src="${f('public/brand/' + t.logo + '.svg')}"><p class="tag">Herramientas de RH para cumplir con la STPS</p><p class="tl">NOM-035 · NOM-037 · Ley Silla · y más</p></div>`, `
.deco{width:${h * 1.9}px;height:${h * 1.9}px;right:${-h * 0.3}px;bottom:${-h * 0.55}px}
.c{position:absolute;inset:0;display:flex;flex-direction:column;justify-content:center;align-items:${w > 1600 ? 'center' : 'flex-start'};padding-left:${w > 1600 ? 0 : 520}px;z-index:1;text-align:${w > 1600 ? 'center' : 'left'}}
img{height:${70 * scale}px;width:auto;margin-bottom:${26 * scale}px}
.tag{font-size:${46 * scale}px;font-weight:600;letter-spacing:-.015em}
.tl{font-size:${28 * scale}px;color:${t.sub};margin-top:${14 * scale}px}`);
}

const NOM = 'NOM-035';
const items = {
    'perfil-jade': [1080, 1080, profile('jade')],
    'perfil-claro': [1080, 1080, profile('light')],
    'portada-linkedin': [1584, 396, cover(1584, 396, 'light', 1)],
    'portada-facebook': [1640, 624, cover(1640, 624, 'light', 1.35)],
    'c1-obligatorio-2027': [1080, 1350, post({ theme: 'jade', eyebrow: 'Enero de 2027', title: 'Registrar la jornada de cada persona será obligatorio.', text: 'Reforma a la Ley Federal del Trabajo, art. 132, fracción XXXIV.' })],
    'c2-carrusel-1': [1080, 1350, post({ theme: 'light', eyebrow: 'Registro de jornada', title: 'Checador electrónico obligatorio: lo que dice la ley.', text: 'Reforma a la LFT publicada en el DOF el 1 de mayo de 2026.', n: '1 / 5' })],
    'c2-carrusel-2': [1080, 1350, post({ theme: 'light', eyebrow: 'Qué pide la ley', title: 'Inicio y fin de la jornada de cada persona.', text: 'Registrados de forma electrónica y disponibles para la autoridad cuando los requiera.', n: '2 / 5' })],
    'c2-carrusel-3': [1080, 1350, post({ theme: 'light', eyebrow: 'Valor probatorio', title: 'El registro hace prueba plena, si se acredita que fue acordado.', text: 'Por eso conviene que cada persona acepte y confirme su propio registro.', n: '3 / 5' })],
    'c2-carrusel-4': [1080, 1350, post({ theme: 'light', eyebrow: 'Si no se cumple', big: '250 a 5,000', bigSize: 150, title: 'UMA de multa para quien incumpla.', text: 'La sanción es para la persona empleadora (art. 994, fracción IV Bis).', n: '4 / 5' })],
    'c2-carrusel-5': [1080, 1350, post({ theme: 'jade', eyebrow: 'Humetría', title: 'Ya incluimos el registro de jornada.', text: 'Agenda una demo en humetria.com.mx', n: '5 / 5' })],
    'c3-jornada-baja': [1080, 1350, post({ theme: 'light', eyebrow: 'Jornada semanal máxima', big: '48 → 46 h', bigSize: 180, title: 'En 2027 baja a 46 horas y seguirá bajando hasta 40 en 2030.', text: 'Registrar la jornada es lo que permite saber cuántas horas se trabajan de verdad.' })],
    'c4-estas-listo': [1080, 1350, post({ theme: 'dark', eyebrow: 'Antes de enero', title: '¿Puedes entregar hoy el registro de jornada de cada persona?', text: 'Inicio y fin de cada jornada, de forma electrónica, cuando la autoridad lo pida.' })],
    'reel-3-portada': [1080, 1920, reel({ eyebrow: 'Registro de jornada', title: 'Checador obligatorio en enero de 2027.', text: '3 cosas que debes tener listas.' })],
    '01-presentacion': [1080, 1350, post({ theme: 'jade', eyebrow: 'Hola, somos Humetría', title: 'Herramientas de RH para cumplir con la STPS, sin complicarte.', text: 'NOM-035, NOM-037, Ley Silla y más, en un solo lugar.' })],
    '02-carrusel-1': [1080, 1350, post({ theme: 'light', eyebrow: NOM, title: '¿Qué guías de la NOM-035 te tocan?', text: 'Depende de cada centro de trabajo, no de la empresa.', n: '1 / 5' })],
    '02-carrusel-2': [1080, 1350, post({ theme: 'light', eyebrow: 'Hasta 15 trabajadores', big: 'Guía I', bigSize: 130, title: 'Identifica a quien vivió un acontecimiento traumático severo.', text: 'Es la única guía que te toca.', n: '2 / 5' })],
    '02-carrusel-3': [1080, 1350, post({ theme: 'light', eyebrow: 'De 16 a 50 trabajadores', big: 'Guías I y II', bigSize: 130, title: 'Además, la Guía II mide los factores de riesgo psicosocial.', text: 'Se aplica a todas las personas del centro.', n: '3 / 5' })],
    '02-carrusel-4': [1080, 1350, post({ theme: 'light', eyebrow: 'Más de 50 trabajadores', big: 'Guías I y III', bigSize: 130, title: 'La Guía III se aplica a una muestra representativa.', text: 'No a todas las personas del centro.', n: '4 / 5' })],
    '02-carrusel-5': [1080, 1350, post({ theme: 'jade', eyebrow: 'Humetría', title: 'Marcamos qué guías le tocan a cada centro.', text: 'Agenda una demo en humetria.com.mx', n: '5 / 5' })],
    '03-anonimato': [1080, 1350, post({ theme: 'dark', eyebrow: 'Confidencialidad', title: 'Nadie en RH ve qué contestó cada persona.', text: 'Los resultados solo se muestran en grupos de 5 personas o más.' })],
    '04-muestra': [1080, 1350, post({ theme: 'light', eyebrow: 'Más de 50 trabajadores', big: '100 → 80', bigSize: 180, title: 'Con 100 trabajadores, la Guía III se aplica a 80.', text: 'La muestra es aleatoria y proporcional por género.' })],
    '05-cada-2-anios': [1080, 1350, post({ theme: 'jade', eyebrow: NOM, title: 'La NOM-035 no se hace una sola vez.', text: 'Evalúa al menos cada 2 años y conserva la evidencia al menos 1 año.' })],
    '06-ley-silla': [1080, 1350, post({ theme: 'light', eyebrow: 'Ley Silla', title: 'Asientos con respaldo, a disposición de todas las personas.', text: 'Y nadie puede ser obligado a estar de pie toda la jornada.' })],
    '07-nom-037': [1080, 1350, post({ theme: 'dark', eyebrow: 'NOM-037 · Teletrabajo', title: 'El teletrabajo también se documenta.', text: 'Listado de personas, política y una lista de verificación por cada lugar donde trabajan.' })],
    'reel-1-portada': [1080, 1920, reel({ eyebrow: NOM, title: '3 tamaños, 3 combinaciones de guías.', text: 'Mira cuáles te tocan.' })],
    'reel-2-portada': [1080, 1920, reel({ theme: 'dark', eyebrow: 'Confidencialidad', title: 'Contestan sin que RH vea quién dijo qué.', text: 'Así lo hace Humetría.' })],
};

const browser = await chromium.launch();
for (const [name, [w, h, html]] of Object.entries(items)) {
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    writeFileSync(`${tmp}/${name}.html`, html);
    await page.goto(`file://${tmp}/${name}.html`, { waitUntil: 'load' });
    await page.evaluate(() => document.fonts.ready);
    await page.screenshot({ path: `${out}/${name}.png` });
    await page.close();
    console.log(name);
}
await browser.close();
