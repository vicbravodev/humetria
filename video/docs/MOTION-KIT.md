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

## Estándar visual de las películas de herramienta (obligatorio)
Fijado con NOM-035 (segunda iteración, 2026-11-10). Todas las películas de herramienta, el hero y Humi lo siguen; `src/presentations/nom-035/` es la referencia.

**Estructura**
- 1920×1080, 30 fps, una escena por capítulo (4). Cada capítulo dura lo que dura su línea de voz (`lead 12 + audio + tail 20` frames), no 6 s fijos; `scripts/chapters.mjs` escribe sus tiempos en `voice/chapters.<id>.json` para la landing.
- Marco fijo (`kit/film.tsx`): número y nombre del capítulo arriba a la izquierda, logo arriba a la derecha, subtítulo abajo. Nunca se mueven.
- **Cierre (outro):** al terminar el último capítulo, 75 frames (2.5 s) con el logo horizontal de la marca centrado, quieto, sin voz, sin subtítulo y sin lema (`outro: 75` en `script.json`). Entra con fundido (`Reveal calm`), nada de escala ni desenfoque. El lema («Confianza para responder…») es de la pieza *Anonimato*, no de las películas de herramienta.
- Los dos temas (`dark`, `light`) salen de la misma composición con `--props={"theme":…}`; `scripts/render-film.mjs` renderiza ambos (mp4, webm y póster).

**Composición**
- Nada se recorta ni se acerca de más: el contenido cabe completo con ≥ 80 px de margen, sin zoom sobre la interfaz.
- Texto ≥ 26 px (en la landing el video se ve a ~900 px). Tarjetas grandes con lo esencial de la pantalla real, no la interfaz densa a escala.
- Espacio: titular a y=134; contenido de y=250 a y=790; subtítulo desde y≈890. Todo elemento entra y sale dentro de su contenedor; nada asoma por encima ni se desborda (si una fila no cabe, se achica la fila, no se sale del panel). Sin huecos sin función: una tarjeta se dimensiona a su contenido.

**Movimiento (todo desde `kit/fx.tsx` y `kit/tokens.ts`; sin valores sueltos)**
- **Titular cinético** por capítulo (`frame.headlines`): palabras que suben desde una máscara, la última en el color de acento.
- **Puntos que viajan** (`FlyDot`): las personas salen de su origen en arco y llenan el avance de su destino (barra o arco) mientras el número cuenta. Así se cuenta el flujo de datos; es la firma de la marca desde el hero.
- **Cursor** (`Cursor`) que se mueve, hace clic con onda y dispara el efecto (recordatorios que salen del botón, filas que cambian).
- **Estados que se ganan**: chips e insignias entran con rebote ≤ 4 % (`pop`), bordes y resplandores del acento al completarse.
- **Profundidad**: fondo con dos resplandores del acento que derivan (`Backdrop`), empuje lento de cámara en cada capítulo (`PushIn`, ≈ 2 %). Se apaga (`camera: false`) en escenas con logo.
- Transiciones entre capítulos: solo fundido. Entradas con `easeOut`, salidas con `easeInOut`; sin deslizar contenedores al salir.
- Los logos y el texto final no vibran: solo fundido, sin escala, desplazamiento ni desenfoque.

**Teléfono (`kit/ui.tsx` → `Phone`)**
- Todo celular en pantalla usa este componente: cuerpo negro con bisel, isla dinámica con cámara, barra de estado (hora, señal, wifi, batería), botones laterales, indicador de inicio y un reflejo de cristal leve. Sin logos, marcas ni nombres de dispositivo. La pantalla toma el tema (claro u oscuro); el cuerpo es negro en ambos.
- Tamaño 360×700 por defecto; se puede reducir con `style` (`width`, `height`) si el capítulo lo pide. Contenido útil desde y≈66 (bajo la isla); las posiciones del contenido son absolutas dentro de la pantalla.
- Los chips, campos y botones dentro del teléfono respetan el mismo mínimo de 26 px.

**Voz y texto**
- Una línea de ~15 palabras por capítulo, tuteo, sin promesas legales; Angie con `eleven_v4`. La marca se dice «Umetría» (`speech`) y se escribe «Humetría» (`text`).
- Datos de ejemplo siempre marcados como tales en la trazabilidad; cada escena declara su evidencia.

**Peso:** cada película ronda 5–6 MB por tema (mp4/webm). Si una pasa de 8 MB, subir el `--crf` antes de recortar movimiento.
