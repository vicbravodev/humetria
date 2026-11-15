# Brief común para los agentes de dibujo (Humetría · rediseño de Organigrama, Vacaciones, Clima y Pulso)

Rutas base (úsalas completas):
- S = /private/tmp/claude-501/-Users-victorjesusbravodelapena--claude-spares-spare-nFyuKU/d60ee1f5-bf5f-4d18-b2ce-ca51a448c069/scratchpad
- PLAN = $S/plan/PLAN.md (1436 líneas; NO lo leas entero: solo lo indicado abajo, con offset/limit)
- RESEARCH = $S/plan/research.md (patrones ya incorporados en el PLAN; no agregues patrones por tu cuenta)
- Capturas actuales ("antes"): $S/before/<herramienta>-<pantalla>-<claro|oscuro|375>.png y textos exactos en $S/before/_text/<pantalla>.txt. Ábrelas con Read para ver el estado real, datos y copy.
- Reglas del formato de lienzo: $S/artifact-files/f4047a1b-ea51-403b-acfc-db205567332e/artifact-type/reference/format.md, craft.md, diagrams.md
- Design system (hex exactos): $S/artifact-files/8aef3e06-ef63-4ebc-9671-0a293dece8e2/project/README.md y tokens.json
- RAÍZ DE SALIDA: ROOT = $S/canvas ; cada board se escribe en $S/canvas/project/people/<herramienta>/<Nombre>.dc.html

## Qué es esto
Rediseño de pantallas de un SaaS de RH mexicano. Cada board "después" es un archivo `.dc.html` autocontenido para un lienzo de Claude Design. Otro paquete hace los "antes" (capturas) y yo ensamblo y publico. Tú SOLO escribes los archivos de tu paquete.

## Antes de escribir
1. Invoca con la herramienta Skill las skills `frontend-design` y `impeccable` y aplica su criterio de calidad visual y UX; donde choquen con el PLAN o con el design system (un solo acento jade `primary`, tarjetas con borde y sin sombra, Figtree, calma y no alarma), manda el PLAN.
2. Lee con Read (offset/limit): sección B del PLAN (líneas 139-183), la sección C de TU herramienta (rangos abajo), la sección D completa (líneas 704-1248: tokens exactos, hijos compartidos, snippets, accesibilidad, contenido tipo) y E.4 (líneas 1365-1390, criterios de "hecho"). Lee también la A de tu herramienta si quieres el porqué (A.1 33, A.2 44, A.3 57, A.4 69).
   Rangos de C: C.1 Organigrama 194-258 · C.2 Vacaciones 259-438 · C.3 Clima 439-608 · C.4 Pulso 609-688.
3. Mira las capturas "antes" de TUS pantallas y sus textos en `before/_text/`.
4. Lee format.md (y diagrams.md si dibujas algo con conectores).

## Reglas duras (de PLAN E.4, resumidas)
- Nombre de archivo EXACTO del catálogo; no crees otros archivos (ni hijos, ni notas, ni canvas.json). No publiques nada. No toques el repo.
- Estructura: `<!doctype html><html lang="es">`, `<title>` propio, `<script src="./support.js"></script>` EXACTAMENTE así (línea fija del formato, también en subcarpetas), Figtree por `<link>` de Google Fonts en `<helmet>`, el bloque de tokens de D.1 pegado sin cambios, raíz con `class="{{themeClass}}"`, tweaks solo `theme` (claro|oscuro) y `variant` con los valores del catálogo; el copy es texto literal (no props).
- Hijos compartidos (sidebar, topbar, móvil) con `<dc-import>` por nombre de hermano, con las props y `hint-size` de D.3. Los escribe el paquete KIT en paralelo y se copian a tu carpeta; NO los escribas tú. Mientras no existan, ignora que falten.
- Cero colores crudos fuera del bloque de tokens (`grep -nE '#[0-9A-Fa-f]{3,8}|rgba?\(' archivo` solo debe dar líneas dentro de `.t-light`/`.t-dark`). Texto ≥ 4.5:1 en claro y oscuro; controles y foco ≥ 3:1; objetivos ≥ 44 px; botones/enlaces/inputs reales; sin emoji; a lo sumo 1 botón primario por estado, 3 KPI, 1 aviso; copy es-MX tuteando, "30 %" con espacio, "−" tipográfico, botones verbo+objeto.
- PAGE fluida para escritorio (sin ancho fijo en la raíz; `main` con `flex:999 1 560px;min-width:0`; tablas en `hm-tablewrap`); móviles de 375 px de ancho fijo.
- Datos: solo los de D.6 y los de `before/_text/` de esa pantalla; las únicas cifras ilustrativas son las que el PLAN declara. Privacidad: nada de nombres junto a respuestas, ni horas exactas, ni conteos bajo el mínimo; las celdas protegidas no dicen por qué ni cuántas.
- Todos los estados del catálogo de tu pantalla deben alcanzarse con `variant` (con datos / vacío / etc.). Haz cada board realmente pulido: jerarquía clara, aire, un foco por pantalla, el flujo principal resuelto en pocos pasos. No es un wireframe.
- Escribe un archivo, y pasa al siguiente. Sin Playwright ni navegador (el runtime del lienzo no corre local). Verificación permitida: lint estático local con python3 -I (etiquetas balanceadas, comillas, `{{holes}}` solo rutas con punto, sin expresiones) y los `grep` de colores crudos. Corrige lo que salga.

## Entrega final (breve, en español)
Lista de archivos escritos (ruta completa), altura estimada en px de cada board (el lienzo recorta si es menor: sé honesto), tweaks/variants declarados, cualquier desviación del PLAN o dato que faltó, y cualquier problema de formato que sospeches. No inventes verificaciones que no hiciste.
