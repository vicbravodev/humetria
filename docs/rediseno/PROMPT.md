# Prompt para Claude Code: implementar el rediseño de Humetría

Copia todo lo que está debajo de la línea y pégalo en Claude Code, con este paquete descomprimido dentro del repo, en `docs/rediseno/`.

> **Ajuste del 4 nov 2026:** el trabajo va en un PR por módulo, no en la rama `rediseno-v2` (ver `LEEME.md`).
> Donde este prompt choque con `CLAUDE.md`, manda `CLAUDE.md`. El design system está en `docs/design-system/`.

---

Vas a implementar en el repo de Humetría el rediseño que está en `docs/rediseno/`. Humetría es un SaaS de RH para México: NOM-035, NOM-037, Ley Silla, quejas y denuncias, y la calculadora de compensación para empresas y sindicatos. El stack es el del repo: Laravel, Tailwind v4 y shadcn/ui; revísalo antes de suponer.

## Cómo vas a trabajar: subagentes en secuencia, nunca a la vez

1. Trabaja **por fases y con un subagente por fase**. Lanza un subagente, espera a que termine, revisa su reporte y solo entonces lanza el siguiente. **Nunca lances dos subagentes al mismo tiempo**: cada fase depende de la anterior (tokens, layout, componentes, permisos) y en paralelo se pisan archivos y migraciones.
2. Cada subagente recibe en su prompt:
   - la fase;
   - las pantallas que le tocan, según `MAPA.md`;
   - los docs que debe leer;
   - las reglas de este archivo;
   - lo que dejaron las fases anteriores (componentes creados, rutas y decisiones).
3. Cada subagente termina con un reporte de 4 partes:
   - qué hizo;
   - qué archivos tocó;
   - qué pruebas corrió;
   - qué quedó pendiente o en duda.
4. Tú, el agente principal, revisas el reporte. Corres las pruebas y el build. Abres las pantallas en el navegador si puedes. Haces commit al terminar cada fase, en la rama `rediseno-v2`. Después sigues con la siguiente fase.
5. **La fase 0 es obligatoria y te detienes al terminarla.** No implementes nada hasta que yo conteste las preguntas de `PREGUNTAS-FRONT-BACK.md`.

## Material

- `pantallas/png/`: captura de cada pantalla del diseño. Es la referencia visual exacta. El nombre del archivo es el id del board.
- `pantallas/fuente/*.dc.html`: el código del diseño, para copiar textos exactos, estados, datos de ejemplo y lógica de las pantallas interactivas. No es código para producción: rehazlo con los componentes del repo. Para verlo vivo, corre `sh ver-prototipos.sh` y abre `http://localhost:8765/V2-Panel.dc.html`. Agrega `?tema=oscuro` para el modo oscuro.
- `pantallas/fuente/canvas.json`: el título de cada board dice qué es y si es interactivo.
- `MAPA.md`: board → pantalla actual del repo (carpeta de captura) → fase → tipo de cambio.
- `docs/02-rediseno-rutas-guiadas.md`: principios, permisos, ciclo de vida, correos y documentos con preguntas.
- `docs/05-cobertura-y-cierre.md`: qué cubre cada board, los errores vistos en las capturas actuales y lo que hay que validar.
- `docs/design-system/` (`decisiones.md`, `design-system.md`, `tokens.json`, `tokens-shadcn.md`): el sistema visual vigente, ya en el repo. Los tokens mapean 1:1 a las variables de shadcn.
- Lienzo original, solo como referencia: https://claude.ai/artifact/Mabe2mH9pPgE8FKA2BvY6Q

## Reglas que no se negocian

- **Usabilidad primero:**
  - Cada pantalla tiene un solo siguiente paso claro.
  - Lo urgente va arriba, en "Antes de seguir" o "Atiende primero".
  - Los documentos se escriben con preguntas; subir un archivo es la opción secundaria.
- **NOM-035 es el módulo maduro.** Cambia solo lo que dice el mapa: canalización, difusión, quejas y compartir. La encuesta y el informe 7.7 no se tocan, salvo los errores listados.
- **Marca y estilo:**
  - Un solo acento jade.
  - Escala de riesgo jade → arena → terracota, sin semáforo.
  - Figtree 400, 500 y 600.
  - Contraste AA en claro y en oscuro.
  - Áreas táctiles de 44 px.
  - Sin emojis ni signos de exclamación.
  - A RH, a la Comisión y a quien atiende quejas se les habla de tú; a las personas trabajadoras, de usted.
- **Layout:**
  - Página centrada con un máximo de 1440 px.
  - Sidebar plegable con el orden del board 40 (`V4-Menu-Roles`).
  - Selector de tema siempre visible.
  - Las filas de lista y tabla se acomodan con **container queries** según el ancho de su tarjeta, no de la ventana.
  - La columna lateral baja debajo del contenido a partir de 1240 px.
  - Todo funciona a 390 px.
- **Gráficas** (reporte de compensación, como el informe de NOM-035):
  - Un solo tono con etiquetas, sin paleta categórica.
  - Se animan una vez al abrir, nunca con `prefers-reduced-motion` ni al imprimir.
  - Tooltip al pasar el cursor y al recibir foco.
  - Impresión limpia.
- **Privacidad:**
  - Ningún correo de quejas lleva el contenido del caso.
  - Los conteos menores a 5 se muestran como "menos de 5" y nunca por centro.
  - En canalización no se guardan respuestas ni diagnósticos.
  - Abrir datos confidenciales deja bitácora.
  - Nadie ve una queja en su contra.
- **Textos:**
  - Copia los del diseño.
  - Lo legal (plantillas, políticas, documentos de apoyo) va marcado con `TODO-LEGAL`, para que lo revise un abogado. No inventes redacción legal nueva.
- **Pruebas:**
  - Cada fase agrega o ajusta pruebas de feature y de permisos para lo que toca.
  - Sin pruebas verdes no se pasa a la siguiente fase.

## Fases (en este orden, una por subagente)

**Fase 0 · Auditoría, sin cambios de código.** Recorre rutas, controladores, modelos, migraciones, policies, jobs, notificaciones y vistas. Compara contra cada board de `MAPA.md`. Llena `PREGUNTAS-FRONT-BACK.md` con:
- **A. El diseño necesita algo que el back no tiene.** Por cada caso: board, qué falta (campo, endpoint, job, permiso, correo), cuánto cuesta (S, M o L) y la pregunta "¿lo agregamos ya?".
- **B. El back tiene algo que el diseño no muestra.** Por ejemplo: canales de invitación por WhatsApp o SMS, muestra de la Guía III, solicitudes de demo, conversaciones de Humi, doble factor, verificación de correo, detalle de una revisión de teletrabajo, acta de la Comisión. Por cada caso: qué es, dónde vive y la pregunta "¿le diseñamos o adaptamos UI?". Propón dónde iría según los patrones del diseño.
- **C. Errores de las capturas actuales** (están en `docs/05`): confirma cada uno y dónde se arregla.

Verifica también las sospechas que ya listé en ese archivo. **Al terminar, detente y espera mis respuestas.**

**Fase 1 · Fundaciones.**
- Tokens en Tailwind v4 y shadcn: tema claro y oscuro, y dos niveles de sombra.
- Layout con sidebar por rol (board 40), migas de pan, contenedor de 1440 px, container queries y barra inferior en celular.
- Componentes compartidos:
  - tarjeta de siguiente paso;
  - lista de urgencias;
  - ruta de 6 etapas con su barrita;
  - opciones en fila (radio y checkbox de 52 px);
  - asistente de preguntas con vista previa;
  - chips de estado;
  - banner de confidencialidad;
  - estados vacíos;
  - primitivas de gráficas (barras horizontales, cascada, columnas, tooltip, animar una vez).
- Ninguna pantalla nueva todavía, salvo una página interna de muestra de componentes.

**Fase 2 · Panel, ¿te aplica? y primera vez.** Boards 1, 8, 14, 17, 18, 24 y 25. Incluye:
- el cálculo del avance por etapa de cada norma y centro;
- las urgencias de todos los módulos, incluida la queja por acusar, sin su contenido;
- las constancias de "no aplica".

**Fase 3 · NOM-037.** Boards 2, 3, 9, 11, 13, 15, 16, 19 y 23, más los v1 que se quedan (`N037-Persona`, `N037-Revision` y `N037-Portal`). Las pantallas de ajustes, capacitación y lista de verificación se quedan como están, con estos ajustes:
- entrar desde su etapa de la ruta;
- zona de arrastre en lugar del campo de archivo nativo;
- estado vacío real en lugar de "Nadie coincide".

**Fase 4 · Ley Silla.** Board 4, más los v1 que se quedan (`LS-Puesto`, `LS-Evaluacion`, `LS-Plan` y `LS-Portal`). Asientos, reglamento, encuesta, documentos y reportes se quedan, con los mismos ajustes de la fase 3.

**Fase 5 · NOM-035, solo lo que falta.** Boards 5, 6, 7, 12, 30, 31, 32, 33, 39 y 42 (cartel de la página).

**Fase 6 · Quejas y denuncias.** Boards 26, 27, 28, 29, 43 y 45, más el cartel de quejas del 42. Incluye:
- el ruteo por "contra quién";
- la persona externa con enlace;
- la clave elegida por la persona;
- los plazos.

**Fase 7 · Compensación y sindicato.** Boards 34 a 38 y 44. Incluye:
- el costo en vivo (35), con un endpoint rápido o con el cálculo en el cliente; decide en la fase 0;
- el reporte con análisis (36), con su vista compartida por enlace que vence;
- la vista del sindicato como cuenta separada.

**Fase 8 · Roles, consultor, correos y estados.** Boards 10, 20, 21, 22, 41 y 24. Incluye los permisos nuevos de quejas y compensación (board 20) en policies y pruebas.

**Fase 9 · Calidad.**
- Recorrido completo en claro y oscuro, a 1440, 1100 y 390 px.
- Teclado y foco.
- Contraste AA.
- `prefers-reduced-motion`.
- Impresión del reporte, los carteles y el expediente.
- Los errores de `docs/05` corregidos.
- Una lista final de lo que quedó con `TODO-LEGAL` o sin validar.

## Al terminar
Entrégame un resumen por fase y la lista de preguntas abiertas. Avísame qué quedó detrás de una bandera, si alguna parte no se pudo terminar.
