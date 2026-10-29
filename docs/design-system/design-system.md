Humetría ayuda a aplicar la NOM-035-STPS-2018 sin complicarse. Las empresas y los consultores cargan a su personal, la plataforma invita a cada trabajador a contestar las guías oficiales de forma confidencial y RH recibe un diagnóstico listo para actuar y presentar ante la STPS.

La personalidad es **cálida como una psicóloga y moderna como una startup**. Nunca debe verse infantil, frío, gris corporativo, cargado de información ni demasiado colorido.

Se implementa con **Tailwind CSS v4 + shadcn/ui (Radix)**. Los nombres de los tokens coinciden 1:1 con las variables de shadcn; la tabla completa con contrastes, el `globals.css` y los ajustes a los componentes están en **Tokens para shadcn/ui**. Por qué se eligió cada cosa está en **Decisiones**.

## Principios

1. **Una cosa a la vez.** Cada pantalla responde una pregunta: ¿cómo va mi campaña?, ¿qué contesto aquí? Máximo 3 KPIs por pantalla y una acción primaria por vista.
2. **Confidencialidad visible.** La encuesta dice que es confidencial antes de la primera pregunta, en la cabecera y al pie. Nunca muestres nombres junto a respuestas.
3. **Calma, no alarma.** Ni los riesgos altos usan rojo saturado: el nivel se comunica con texto, posición y un terracota sobrio.
4. **El espacio ordena.** La jerarquía sale del espacio, los bordes y dos niveles de profundidad, no del color.
5. **Para todos los dedos.** Toda área táctil mide 44 px o más, y el texto nunca baja de 16 px en la encuesta.

## Voz y texto

- Español de México, siempre. Fechas como “14 de noviembre” o “14 nov 2026”; cifras con espacio antes del porcentaje (“91 %”).
- **Panel de RH:** tutea. Es una herramienta de trabajo moderna. “Faltan 44 respuestas”, “Envía un recordatorio”, “Entrégalo a tu cliente”.
- **Encuesta del trabajador:** usa “usted”, como las guías oficiales, para que la interfaz y las preguntas suenen a una sola voz. “Sus respuestas se guardan en cada paso. Nadie de su empresa puede ver lo que contesta.”
- Los enunciados e instrucciones de las guías se copian tal cual de la norma, sin reescribirlos.
- Botones: verbo + objeto (“Enviar recordatorio”, “Ver reporte”). Nunca “Aceptar” u “OK” solos.
- Errores: di cómo corregir, sin culpar. “Escribe un correo válido, por ejemplo nombre@empresa.mx”.
- Confirmaciones con el resultado concreto: “Recordatorio enviado · llegará a 44 personas”. Sin signos de exclamación, sin emoji.
- El nombre se escribe **Humetría**, con acento y mayúscula, en el texto. El logotipo va en minúsculas.
- En mayúsculas solo van las etiquetas en estilo `caption`.

## Color

- **Un solo acento:** `primary` (`#0B7A6F` claro / `#3CCFBD` oscuro). Va en la acción principal, la opción elegida, el avance, los enlaces y el foco. No hay un segundo color de marca.
- Sobre `primary` el texto es `primary-foreground`: blanco en claro y `#0F1716` en oscuro, porque el jade de oscuro es luminoso.
- Los neutros llevan un tinte jade muy leve derivado del texto del logo (`#14211F`). Fondo de página `background` casi blanco; superficies `card` blancas con borde `border`.
- Texto principal en `foreground`; secundario en `muted-foreground`, que pasa 4.5:1 sobre `background`, `card`, `muted`, `secondary`, `accent` y `sidebar`.
- Contornos de controles (inputs, select, checkbox, radio, botón outline) en `input`, ≥ 3:1. `border` es solo para divisores y tarjetas.
- `destructive` es un rojo terracota desaturado (`#B0463C` / `#EC8F84`). Su texto encima es `on-destructive`.
- `brand-soft` y `brand-deep` solo existen dentro del símbolo. Nunca texto, fondo ni serie de gráfica.
- Gráficas: `chart-1` a `chart-3` son jades en valores distintos; `chart-4` arena y `chart-5` tierra son los neutros cálidos. Todos ≥ 3:1 sobre `card`. Con más de 5 series, agrupa.
- Riesgo: tokens `risk-*` (ver Escala de riesgo, abajo).

## Escala de riesgo

Cinco niveles: **Nulo, Bajo, Medio, Alto y Muy alto**. Usa siempre `RiskLevel`. Muestra un medidor de 5 segmentos (los llenos indican el nivel), la etiqueta escrita y el color, así que nadie depende solo del color.

- Nulo y Bajo en jade (`risk-nulo`, `risk-bajo`), Medio en arena (`risk-medio`), Alto y Muy alto en terracota (`risk-alto`, `risk-muy-alto`). Todos desaturados y sin verde ni rojo de semáforo.
- Etiqueta: texto `risk-*-fg` sobre `risk-*-bg`, ≥ 4.5:1 en ambos modos. Segmento lleno: ≥ 3:1 sobre `card`.
- Con prefijo se escribe en minúscula: “Riesgo medio”.

## Tipografía

- Una familia: **Figtree** 400, 500 y 600 (`--font-sans`). Nada de cursivas ni pesos 700+.
- Cuerpo en `body` (16/24). Inputs en 16 px también en móvil, para que iOS no haga zoom.
- La encuesta usa `question` (20/30, 500) para el enunciado y `body-lg` (18/28) para opciones e instrucción. `small` y `caption` no se usan en la encuesta.
- Cifras en `kpi` con `font-variant-numeric: tabular-nums`; en tablas también con cifras tabulares, alineadas a la derecha.
- Títulos con tracking −0.01em; nunca más de una línea de `display` por pantalla.

## Espacio, forma y profundidad

- Escala de espacio de 4 px: `space-2` dentro de un grupo, `space-4` margen lateral móvil, `space-6` padding de tarjeta, `space-12` entre secciones (activas / cerradas).
- `touch-min` (44 px) es el alto mínimo de botones, inputs, filas de checkbox, elementos del sidebar y pestañas. El botón `sm` mide 36 px a la vista, pero su área táctil se extiende a 44.
- Radio base 10 px (`radius`): `radius-md` 8 px para botones e inputs, `radius-lg` 10 px para tarjetas y opciones, `radius-xl` 14 px para diálogos y `radius-full` para radios y barras. Es un eco de los remates redondos del logo, sin llegar a lo infantil.
- Profundidad en dos niveles. `shadow-raised`: el panel de la página, que se levanta sobre el marco del sidebar. `shadow-float`: lo que flota sobre la página (popover, select abierto, diálogo, tooltip, toast). Botones, inputs y tarjetas no llevan sombra: su borde basta. En oscuro la sombra no se ve, así que la profundidad sale de un filo superior iluminado y de un marco más oscuro que el panel.
- Foco: contorno sólido de 2 px en `ring` con 2 px de separación, ≥ 3:1 sobre cualquier superficie.

## Movimiento

El movimiento confirma lo que la persona hizo o muestra qué cambió; nunca decora ni se mueve solo.

- **Curvas:** el tema redefine `ease-out` como `cubic-bezier(0.23, 1, 0.32, 1)` e `ease-in-out` como `cubic-bezier(0.77, 0, 0.175, 1)`; `ease-drawer` (`cubic-bezier(0.32, 0.72, 0, 1)`) es para lo que se despliega, como el sidebar. Nada de `ease-in`, `ease-linear` ni rebotes.
- **Duraciones:** 100–150 ms para presionar y cambios de color; 150–200 ms para tooltips, menús y el chip del tema; 300 ms para el sidebar; 120 ms de salida y 220 ms de entrada entre páginas. Ninguna interfaz pasa de 300 ms.
- **Presionar:** todo `Button` baja a `scale(0.97)` mientras se presiona.
- **Entre páginas:** solo el contenido del panel cambia (View Transitions API): la página vieja se desvanece y la nueva sube 4 px. Envíos de formulario, recargas parciales y cambios dentro de la misma ruta no se animan (`lib/page-transition.ts`).
- **Menús, select y popovers:** crecen desde su disparador (`origin-(--radix-…-content-transform-origin)`), 200 ms al abrir y 100 ms al cerrar.
- **Calendario:** al cambiar de mes las semanas entran de lado y las anteriores salen hacia el otro.
- **Tooltips:** crecen desde su disparador (`origin-(--radix-tooltip-content-transform-origin)`); una vez abierto uno, los siguientes aparecen sin animación.
- Con `prefers-reduced-motion` desaparecen los desplazamientos y escalas; quedan los desvanecidos.

## Iconografía

- Lucide (`lucide-react`), el set por defecto de shadcn: trazo 1.75 px, remates redondos, 20 px en botones y navegación, 24 px sueltos.
- Los íconos acompañan al texto; solo los botones de ícono van solos, con `aria-label`.
- Color: `currentColor`. En navegación inactiva, `muted-foreground`; activa, `sidebar-primary`. El candado de confidencialidad va en `primary`.
- Sin emoji ni ilustraciones decorativas.

## Logo y white label

- Usa siempre los SVG del grupo de recursos **Logo**: `humetria-horizontal-color.svg` sobre claro y `humetria-horizontal-color-oscuro.svg` sobre oscuro (el componente `Logo` los cambia solo). Mínimo 96 px de ancho; por debajo, `humetria-simbolo-*.svg` o el favicon.
- Área de respeto: la altura del círculo (≈ 1/4 del símbolo) por lado.
- **White label:** el consultor solo cambia el logo. Colores, tipografía y disposición nunca cambian.
- En reportes, invitaciones y la encuesta, el logo del consultor va en el lugar principal (arriba a la izquierda, ≤ 48 px de alto en reportes y ≤ 32 px en la encuesta). Humetría aparece solo como el sello `humetria-sello-hecho-con-gris.svg` (`#6B7472`, 4.81:1 sobre blanco) a 16 px de alto, o la versión blanca sobre fondo oscuro.
- En el panel del consultor, el logo de Humetría va arriba del sidebar. Si el consultor activa su marca, su logo lo reemplaza.

## Patrones

- **Panel de RH** (`PanelRH`): encabezado con cliente y título, 3 `KpiCard`, “Campañas activas” en una rejilla de `CampaignCard` y, separadas por `space-12`, las “Campañas cerradas” en una `Table` con su `RiskLevel` final y la descarga del PDF.
- **Encuesta** (`EncuestaMovil`, `EncuestaEscritorio`): `SurveyShell` con el logo del consultor y “Confidencial”, progreso por sección, instrucción de entrada en un bloque `accent` y `SurveyQuestion` con 5 opciones verticales de 56 px. En móvil va una pregunta por pantalla; en escritorio, hasta 3 de la misma sección. “Siguiente” es explícito y nunca avanza solo.
- **Escala de riesgo** (`RiskLevel`, `RiskScale`).
- **Cabecera de reporte** (`ReportHeader`): logo del consultor, sello “hecho con humetría”, título, guía y ficha.

## Accesibilidad

- WCAG 2.x AA en los dos modos. Cada token de color indica su par y su contraste en **Tokens para shadcn/ui**.
- Nunca dependas solo del color: los estados llevan texto, la opción elegida lleva borde de 2 px y peso 500 y el riesgo lleva medidor y etiqueta.
- Formularios con label visible, error enlazado con `aria-describedby` y `aria-invalid`.
- La encuesta funciona con teclado (flechas dentro del grupo, tabulador entre grupos) y con lector de pantalla (`fieldset` + `legend`).
- Respeta `prefers-reduced-motion` y `prefers-color-scheme`. El tema oscuro se activa con la clase `.dark` en `<html>`.
