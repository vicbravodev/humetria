# Video del hero (Remotion)

Loop de 39 s (1180 frames, 30 fps, 1280×960) para el hero de la landing. Proyecto aparte de la app:
su propio `package.json`, `node_modules` y `tsconfig.json`. La raíz lo ignora (`vite.config.ts`, `.gitignore`).

- `src/HeroLoop.tsx`: rótulo, riel de avance y orden de escenas.
- `src/scenes/`: una escena por archivo. `src/theme.ts`: tokens y línea de tiempo. `src/ease.ts`: curvas.
- El último frame debe quedar igual que el frame 0 (estado de reposo): si cambias el inicio, cambia también el cierre de `Dossier.tsx`.

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
```

Un frame suelto para revisar: `npx remotion still HeroLoop out/frame.png --frame=600`.
