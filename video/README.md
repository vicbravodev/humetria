# Videos de la landing (Remotion)

Dos loops: `HeroLoop` (39 s, 1180 frames, 30 fps, 1280×960) para el hero y `HumiChat` (25 s, 750 frames,
30 fps, 1080×1080) para la sección de Humi. Proyecto aparte de la app:
su propio `package.json`, `node_modules` y `tsconfig.json`. La raíz lo ignora (`vite.config.ts`, `.gitignore`).

- `src/HeroLoop.tsx`: rótulo, riel de avance y orden de escenas.
- `src/scenes/`: una escena por archivo. `src/theme.ts`: tokens y línea de tiempo. `src/ease.ts`: curvas.
- `src/HumiChat.tsx` y `src/humi/`: panel de Humi, piezas del chat, Humi animado (`HumiLive.tsx`) y su línea de tiempo (`timeline.ts`).
- En los dos, el último frame debe quedar igual que el frame 0 (estado de reposo): si cambias el inicio, cambia también el cierre (`Dossier.tsx` en el hero, `HC.calmIn` en HumiChat).

## Ver

```bash
cd video
npm install
npx remotion studio
```

## Volver a generar

```bash
cd video
npm run types:check
npm run render   # public/video/humetria-hero.mp4 (H.264, Safari) y .webm (VP9, Chromium)
npm run poster   # public/video/humetria-hero.jpg (frame 880)
npm run render:humi   # public/video/humi-chat.mp4 y .webm
npm run poster:humi   # public/video/humi-chat.jpg (frame 280, primera respuesta completa)
```

Un frame suelto para revisar: `npx remotion still HeroLoop out/frame.png --frame=600`.

## Herramientas (HyperFrames)

`hyperframes/<herramienta>/` guarda el video de cada herramienta de la landing (24 s, 1600×900, cuatro capítulos de 6 s:
la landing salta a 0, 6, 12 y 18 s), hecho con HTML y GSAP. Texto mínimo de 40 px: se muestran a ~900 px de ancho.
Necesitan Docker para renderizar (no hay ffmpeg local). Revisar y renderizar uno:

```bash
cd video/hyperframes/attendance && npx hyperframes check
video/hyperframes/render.sh attendance humetria-attendance 4   # carpeta, nombre en public/video, segundo del póster
```

Salen `public/video/humetria-*.{mp4,webm,jpg}`. El navegador solo puede saltar a un capítulo si el servidor da
rangos de bytes (nginx sí; el servidor de PHP de desarrollo no: ahí el salto no funciona).
