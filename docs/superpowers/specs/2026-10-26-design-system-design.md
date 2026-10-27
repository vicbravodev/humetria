# Design system de Humetría: adopción en la app

Fecha: 2026-10-26. Estado: aprobado. Se actualizó al recibir el paquete de Claude Design.

## Intención

Humetría tiene que verse como su propia marca antes de que se construya cualquier pantalla de negocio. Hoy la app
es el starter kit de Laravel: tema neutro de shadcn, Instrument Sans y el logo de Laravel. El design system se
diseñó en Claude Design (`humetria-design-system.zip`). Este proyecto lo trae al repo y lo aplica a lo que ya existe.

Públicos, personalidad ("cálida como una psicóloga, moderna como una startup") y anti-objetivos (infantil, frío,
cargado, muy colorido): ver `docs/design-system/design-system.md`.

## Fuente de verdad

El paquete, copiado a `docs/design-system/`, manda en todo lo visual:

| Archivo | Contenido |
|---|---|
| `design-system.md` | Manual: principios, voz (el panel tutea; la encuesta trata de "usted"), color, tipografía, espacio, iconos, white label y accesibilidad. |
| `tokens-shadcn.md` | 49 tokens en claro y oscuro con su par de contraste, escala tipográfica y los 5 ajustes obligatorios a shadcn. |
| `decisiones.md` | El porqué de cada decisión. |
| `componentes/*.md` | Guía por componente. |
| `capturas/*.png` | Cómo debe verse cada componente y patrón, en claro y oscuro. |
| `tokens.json` | Los mismos valores como datos, para PDF y correos. |

No se copian la implementación de referencia (`referencia/componentes/`: React + CSS `hm-`, que el propio paquete
dice no copiar) ni su `CLAUDE.md`. Sus reglas se resumen en el `CLAUDE.md` del proyecto (sección "Design system").

Contrastes: Claude verificó por su cuenta los 49 tokens contra sus pares, más `primary` sobre `card` y `accent` y
`destructive` sobre `background`. Todos pasan AA en los dos modos.

## Objetivos

1. Los tokens del paquete en `resources/css/app.css`, claro y oscuro, con un test que impida que un cambio
   rompa el contraste.
2. Figtree 400/500/600 en lugar de Instrument Sans.
3. Los 5 ajustes obligatorios aplicados a los componentes shadcn que ya existen en `resources/js/components/ui/`.
4. La marca en la app: componente `Logo`, SVG oficiales, favicons y "Humetría" como nombre.
5. Las reglas del paquete disponibles para cada sesión futura (`docs/design-system/` + `CLAUDE.md`).

## No objetivos

- Los componentes propios del sistema (`RiskLevel`, `RiskScale`, `KpiCard`, `CampaignCard`, `SurveyQuestion`,
  `SurveyShell`, `ReportHeader`). Cada uno se construye en el PR del módulo que lo usa por primera vez, a partir
  de su guía y su captura. Construirlos ahora sería código sin consumidor.
- Componentes shadcn que aún no están instalados (RadioGroup, Tabs, Table, Progress). Varios piden dependencias
  Radix nuevas: se resuelven en el PR que los necesite, con aprobación o con su equivalente nativo.
- La landing de `/`, el formulario de demo y las páginas legales: es el proyecto siguiente. Hasta entonces `/`
  queda como está y fuera de los tests de este proyecto.
- Traducir al español todos los textos del starter kit: va en su propio PR.

## Restricciones

- Sin dependencias nuevas. Figtree se carga con el plugin de fuentes que ya está en `vite.config.ts`
  (Bunny, autoalojada en el build), no con `@fontsource` ni con Google Fonts como sugiere el paquete.
- El modo oscuro existente (`HandleAppearance`, `use-appearance`, clase `.dark`) se conserva.
- "Humetría" con acento en la interfaz; `humetria` en código, rutas y archivos.
- TDD: cada cambio lo exige un test.

## Cambios respecto a la versión anterior de este documento

| Antes | Ahora (manda el paquete) |
|---|---|
| `components/ui/*` no se edita | Se edita: el paquete exige 5 ajustes, porque sin ellos el foco y los bordes no llegan a 3:1. |
| Logo como SVG inline con colores de tokens | Componente `Logo` que muestra los SVG oficiales y cambia entre `color` y `color-oscuro` con la clase `.dark`. El paquete prohíbe redibujarlos. |
| Encuesta agrupada por sección | Una pregunta por pantalla en móvil y hasta 3 de la misma sección en escritorio. |
| Inputs de 16 px solo en móvil | Controles de 44 px y 16 px en todas las anchuras. |

## Avisos para módulos futuros

- **Guardado parcial de la encuesta:** la captura dice "Sus respuestas se guardan en cada paso". Guardar en el
  servidor respuestas a medio contestar ligadas al token de la invitación rompería el invariante de anonimato.
  El módulo Responses decide cómo se hace (por ejemplo, solo en el dispositivo) antes de usar ese texto.
- **`destructive-foreground`:** el paquete no lo define. `alert.tsx` y `dropdown-menu.tsx` lo usan hoy y pasan a
  `destructive` / `on-destructive`.

## Cómo se prueba

- **Contraste:** test unitario de Pest que lee los tokens hex de `:root` y `.dark` en `resources/css/app.css` y
  exige ≥ 4.5:1 en los pares de texto y ≥ 3:1 en los de controles, foco y marcas, con los mismos pares que
  `tokens-shadcn.md`.
- **Tipografía y marca:** tests de feature sobre el HTML de `/login`: precarga Figtree y no Instrument Sans,
  `<title>` dice "Humetría", los `<link>` de favicon apuntan a archivos que existen en `public/`.
- **Navegador:** Pest Browser sobre login, registro y dashboard, en claro y oscuro, móvil y escritorio:
  sin problemas de accesibilidad graves ni errores de JavaScript, y con el logo visible.
