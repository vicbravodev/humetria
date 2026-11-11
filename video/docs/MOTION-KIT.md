# Humetria Motion Kit

Extiende `video/` (Remotion 4.0.538, ya existente) con piezas narradas. No agrega dependencias ni toca la app.

## Decisiones
- **Remotion**, no HyperFrames: ya está en el repo, es React (reusa `components.tsx` y `theme.ts`) y es determinista. HyperFrames queda para prototipar.
- **Sin zod**: la validación es `kit/schema.ts#validate` (cero dependencias; la regla del proyecto exige aprobar las nuevas).
- **Un audio por línea del guion**: la duración real (ffprobe) manda la línea de tiempo, no los caracteres; regenerar una escena no toca las demás.
- **Video prerenderizado** para la landing (estable y barato); React interactivo solo si el visitante debe actuar.

## Mapa
| Capa | Archivo |
|---|---|
| Color y forma | `src/theme.ts` (espejo del design system, tema oscuro) |
| Movimiento | `src/kit/tokens.ts` (duraciones, desfase, ritmo) + `src/ease.ts` (curvas) |
| Primitivas | `src/kit/primitives.tsx` (`Stage`, `SceneFrame`, `Reveal`, `Narration`, `Subtitle`, `Logo`) |
| Contrato | `src/kit/schema.ts` (`Presentation`, `SceneSpec`, `validate`) |
| Línea de tiempo | `src/kit/timeline.ts` (`layout`) |
| Pieza | `src/presentations/<id>/` (`script.json`, `scenes.tsx`, `index.tsx`) |
| Voz | `voice/profile.json`, `voice/glossary.md`, `voice/manifest.<id>.<voz>.json` |
| Scripts | `scripts/voice-master.mjs`, `scripts/check.mjs` |

## Voz (perfil maestro)
Angie (`eeTcHFmLuUzCOHvnwvdr`, es-mexicano, biblioteca, «segura, clara y cercana») con `eleven_v4`.
Voz elegida por el propietario tras probar también a Tamara (2026-11-10); no se cambia sin justificación.
`eleven_v4` no expone estabilidad ni velocidad; el ritmo sale de la puntuación.

**Pronunciación de la marca: «Humetría» se dice con h muda, «u-me-TRÍ-a», nunca «jumetría» con j.** Con «Humetría» escrito la voz decía «jumetría»; por eso `script.json` separa `text` (lo que se lee en pantalla, «Humetría») de `speech` (lo que se le dice a la voz, «Umetría»). Toda línea nueva que nombre la marca lleva `speech` con «Umetría».
Masterizado a −16 LUFS, pico −1.5 dB, mono 128 kbps (`voice-master.mjs`).

**Créditos.** Antes de generar: `estimate_only` (≈ 90 créditos ≈ US$0.02 por línea de ~100 caracteres). Una toma por línea. `check.mjs` detecta si el texto, la voz o el modelo cambiaron respecto a la toma aprobada: solo entonces se regenera. La generación va por el MCP de ElevenLabs conectado a Claude; no hay API key en el repo.

## Nueva pieza (receta)
1. Brief: necesidad, beneficio y evidencia por escena (ruta en el repo; sin eso `validate` falla).
2. `src/presentations/<id>/script.json`: líneas ≤ ~28 palabras, para el oído, sin promesas legales (ver `voice/glossary.md`).
3. Generar cada línea (MCP, `estimate_only` primero), guardar en `public/audio/<id>/angie/raw/lN.mp3`.
4. `node scripts/voice-master.mjs <id> angie` (la duración real queda en el manifiesto).
5. Escenas en `scenes.tsx` con los tokens y primitivas; `index.tsx` con la `Presentation`; registrar en `Root.tsx`.
6. `npm run check:film`, `npm run types:check`, `npx remotion still …` en cada escena, `npm run render:<id>`.

Los scripts npm de `anonimato` son el modelo; se parametrizan cuando haya una segunda pieza.

## Trazabilidad (Anonimato)
| Escena | Afirmación | Fuente | Estado |
|---|---|---|---|
| need | Una respuesta de la Guía II (ítem 1) | `docs/normas/nom-035/texto-de-referencia.md` | real |
| link | Enlace propio, de un solo uso, desde el celular; «Gracias. Su respuesta es anónima.» ya existe en la app | CLAUDE.md#Privacidad, `landing-tools.ts`, `scenes/Invite.tsx` | real |
| split | Respuestas sin llave a empleado/invitación; la invitación solo registra «ya contestó» | CLAUDE.md#Privacidad | invariante del producto |
| groups | Mínimo de respondentes por grupo (default 5, configurable); debajo no se muestra | CLAUDE.md#Privacidad | real; cifras 64/21/4 y «Mariana L.» son **demostrativas** |
| close | Resumen | — | copy, sin afirmación nueva |

Las pantallas son recreaciones con los tokens del producto (como el hero), no capturas de datos reales. No hay datos de empleados.

## QA hecho / pendiente
Hecho: tipos, `check:film`, stills de las escenas revisados a ojo, render MP4/WebM con pista de audio (AAC, 41.3 s), subtítulos incrustados.
Pendiente: confirmar a oído que ya dice «umetría» (no pude escucharlo), medir LUFS del mix final, revisión en móvil, axe/rendimiento en la página que lo inserte, prueba en Safari/Firefox, ver la escena final (cierre) en still.

## Integración web (propuesta, sin implementar)
Un componente `NarratedVideo` hermano de `loop-video.tsx`: `<video controls preload="none" poster>` con MP4/WebM, sonido activado solo por el visitante, subtítulos como `<track>` WebVTT (hoy van incrustados; generar el .vtt desde `script.json` + manifiesto antes de integrar), sin autoplay (siempre, y más con `prefers-reduced-motion`). Se insertaría en la sección de confidencialidad de la landing. No se tocó la landing.
