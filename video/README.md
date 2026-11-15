# Películas del landing (Remotion)

Humetría Motion Kit: las 11 películas del landing (nueve herramientas, el hero «Cómo funciona» y Humi) y la pieza de
Anonimato, todas narradas, en tema oscuro y claro, a 1920×1080 y 30 fps. Proyecto aparte de la app: su propio `package.json`,
`node_modules` y `tsconfig.json`. La raíz lo ignora (`vite.config.ts`, `.gitignore`).

El sistema (estándar visual, voz, receta de una pieza nueva, revisión de desbordes, trazabilidad e integración al landing)
está en `docs/MOTION-KIT.md`. Cada película vive en `src/presentations/<id>/` (`script.json`, `scenes.tsx`, `index.tsx`) y
se registra en `src/Root.tsx`; las piezas comunes están en `src/kit/`.

## Ver

```bash
cd video
npm install
npx remotion studio
```

## Volver a generar una película

```bash
cd video
npm run types:check
node scripts/qa-layout.mjs <Composición> <id> dark                  # obligatorio antes de renderizar
node scripts/render-film.mjs <Composición> <nombre> <fotograma>     # mp4, webm y póster, oscuro y claro, en ../public/video/
```

`<nombre>` es el archivo en `public/video` (`humetria-nom-035`); el tema claro sale como `humetria-nom-035-light.*` y el
landing elige según el tema. Las películas se sirven por `/media/films/{archivo}` (`ToolFilmController`) y no como archivos
estáticos: el navegador solo puede saltar a un capítulo si el servidor responde rangos de bytes.

## Voz

`node scripts/voice-master.mjs <id> angie` masteriza las tomas crudas de `public/audio/<id>/angie/raw/` y anota sus duraciones;
`node scripts/chapters.mjs <id>` calcula dónde empieza cada capítulo (`voice/chapters.<id>.json`, que se copia a `timing` en
`resources/js/components/landing/landing-tools.ts`).

## Decisiones
- **Remotion**: ya está en el repo, es React y es determinista.
- **Sin zod**: la validación es `kit/schema.ts#validate` (cero dependencias; la regla del proyecto exige aprobar las nuevas).
- **Un audio por línea del guion**: la duración real (ffprobe) manda la línea de tiempo, no los caracteres; regenerar una escena no toca las demás.
