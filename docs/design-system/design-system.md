Humetría ayuda a aplicar la NOM-035-STPS-2018 sin complicarse, y hoy también NOM-037, Ley Silla, asistencia, clima y pulso, vacaciones, denuncias y compensación. Las empresas y los consultores cargan a su personal, la plataforma invita a cada trabajador a contestar de forma confidencial y RH recibe un diagnóstico listo para actuar y presentar ante la STPS.

**La app es la fuente de verdad.** Lo que funciona en la app está aprobado; si este documento o un artifact de diseño dice otra cosa, manda la app y se corrige el documento. Las ideas de artifacts que aún no están en la app, y los choques entre artifacts, viven aparte en **Propuestas y contradicciones** (`propuestas-y-contradicciones.md`): no son reglas.

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
- **Color por familia de herramientas** (sitio público): «Cumple la norma» `family-compliance` (azul cobalto), «Escucha a tu gente» `family-listening` (frambuesa) y «Personas y sueldos» `family-operations` (violeta), cada uno con su `-soft` de fondo. La clase `.family-*` fija `text-family` y `bg-family-soft` en la página de la herramienta, sus tarjetas, el menú y sus etiquetas. Las acciones (botones, enlaces, foco) siguen en `primary`.
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
- Ancho de la página: llena el marco hasta **1440 px** y, en un monitor más ancho, queda **centrada** (`data-slot="page-body"` en `AppSidebarLayout`). Ninguna página pone su propio tope de ancho: las de lista y de panel llenan el marco. Solo las de formulario o lectura se angostan a 880 px (960 un expediente), **alineadas a la izquierda**: `max-w-[880px]` sin `mx-auto`, así el contenido de todas empieza en la misma columna al navegar. El informe NOM-035 es la única excepción dentro del marco: conserva el ancho de su hoja (1120 px), también a la izquierda. Las páginas fuera del marco (encuesta, portal, canal público, enlaces compartidos, `SetupFrame`) sí se centran con su propio ancho. Lo hace cumplir `tests/Unit/PageWidthTest.php`, con la razón de cada excepción. El texto corrido lleva su propio `max-w` (≈ 620 px). Margen lateral: 16 px en móvil y 40 px desde `md`, el mismo en todas las páginas del marco.
- `touch-min` (44 px) es el alto mínimo de botones, inputs, filas de checkbox, elementos del sidebar y pestañas. El botón `sm` mide 36 px a la vista, pero su área táctil se extiende a 44.
- Radio base 10 px (`radius`): `radius-md` 8 px para botones e inputs, `radius-lg` 10 px para opciones y la tarjeta de la empresa en el sidebar, `radius-xl` 14 px para tarjetas (`Card` y la utilidad `card`), el panel de la página, estados vacíos y diálogos, y `radius-full` para radios, barras y badges. Es un eco de los remates redondos del logo, sin llegar a lo infantil.
- Profundidad en dos niveles. `shadow-raised`: el panel de la página, que se levanta sobre el marco del sidebar. `shadow-float`: lo que flota sobre la página (popover, select abierto, diálogo, tooltip, toast). Botones, inputs y tarjetas no llevan sombra: su borde basta. En oscuro la sombra no se ve, así que la profundidad sale de un filo superior iluminado y de un marco más oscuro que el panel.
- Foco: contorno sólido de 2 px en `ring` con 2 px de separación, ≥ 3:1 sobre cualquier superficie.

## Composición y espacio

Lo que hace que una pantalla se vea diseñada y no armada al azar. Valores tomados de las páginas de la app.

- **Contenedor de página** (dentro del marco): `flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16`. Entre
  bloques de una página, 32 px (`gap-8`); entre grupos de distinta naturaleza (activas / cerradas), 48 px (`space-12`).
  Un mismo valor para lo mismo: no se inventa un `gap-9` para una sola página.
- **Dentro de una tarjeta:** padding de 24 px (`p-6`); encabezado (título y una frase) arriba, el contenido a todo lo
  ancho, la acción al pie. Entre tarjetas de una rejilla, 16 px (`gap-4`). Entre campos de un formulario, 16 a 24 px.
- **Ritmo, no monotonía:** lo relacionado va junto (8 a 12 px), lo distinto se separa (32 a 48 px). Si todo tiene el
  mismo espacio, nada se lee como grupo.
- **Sin huecos muertos.**
  - Una fila de tarjetas se reparte según cuántas hay: si una opcional falta, la otra ocupa todo el ancho y pone su
    contenido en una fila (`componentes/Card.md`). Nunca una columna vacía «para equilibrar».
  - Dos columnas solo cuando las dos tienen contenido propio y se leen juntas (resumen y detalle, lista y su panel). Una
    columna lateral nunca existe solo para el título de una tarjeta.
  - Listas y tableros llenan el marco hasta 1440 px; formularios y lectura van a 880 px a la izquierda. Una tabla de
    tres columnas no se estira a 1440 px: se reduce el contenedor o se agregan datos que sirvan.
  - Con pocos datos, el estado vacío (`EmptyState`) con su siguiente paso ocupa el lugar, en vez de una tabla casi vacía.
- **Jerarquía por pantalla:** un título (`PageHeader`), una acción primaria, como máximo 3 KPI. Lo que pide atención
  (`UrgencyList`, `Notice`) va arriba del resto; el detalle, a un clic.
- **Tarjetas solo donde ayudan:** una entidad con su estado y su acción. Nunca tarjetas dentro de tarjetas, ni rejillas
  de tarjetas idénticas para todo (ver `PRODUCT.md`, anti-referencias). Lo que se compara va en tabla.
- **Alineación:** el contenido de todas las páginas empieza en la misma columna (el margen del marco); cifras a la
  derecha con `tabular-nums`; acciones de una fila al final.
- **Celular primero en el portal, escritorio primero en el panel:** el panel se revisa a 1024 y 1350 px antes que a
  375; el portal, al revés. Ninguno se rompe en el otro.

## Movimiento

El movimiento confirma lo que la persona hizo o muestra qué cambió; nunca decora ni se mueve solo.

- **Curvas:** el tema redefine `ease-out` como `cubic-bezier(0.23, 1, 0.32, 1)` e `ease-in-out` como `cubic-bezier(0.77, 0, 0.175, 1)`; `ease-drawer` (`cubic-bezier(0.32, 0.72, 0, 1)`) es para lo que se despliega, como el sidebar. Nada de `ease-in`, `ease-linear` ni rebotes.
- **Duraciones:** 100–150 ms para presionar y cambios de color; 150–200 ms para tooltips, menús y el chip del tema; 300 ms para el sidebar; 120 ms de salida y 220 ms de entrada entre páginas. Ninguna interfaz pasa de 300 ms.
- **Presionar:** todo `Button` baja a `scale(0.97)` mientras se presiona.
- **Entre páginas:** solo el contenido del panel cambia (View Transitions API): la página vieja se desvanece y la nueva sube 4 px. Envíos de formulario, recargas parciales y cambios dentro de la misma ruta no se animan (`lib/page-transition.ts`).
- **Diálogos:** entran desde `scale(0.96)` con un desvanecido en 200 ms y salen en 150 ms, centrados.
- **Menús, select y popovers:** crecen desde su disparador (`origin-(--radix-…-content-transform-origin)`), 200 ms al abrir y 100 ms al cerrar.
- **Calendario:** al cambiar de mes las semanas entran de lado y las anteriores salen hacia el otro.
- **Tooltips:** crecen desde su disparador (`origin-(--radix-tooltip-content-transform-origin)`); una vez abierto uno, los siguientes aparecen sin animación.
- **Gráficas del informe (la única excepción a los 300 ms):** al abrir el informe, una sola vez, las barras crecen desde su eje (`animate-report-grow`, 850 ms, escalonadas 70 ms por fila), los cuadros del resumen aparecen uno tras otro (`animate-report-pop`), cada bloque sube a su lugar (`animate-report-rise`) y los KPI cuentan hacia arriba en 1.2 s (`useCountUp`). Nada de esto pasa con `prefers-reduced-motion` ni al imprimir.
- Con `prefers-reduced-motion` desaparecen los desplazamientos y escalas; quedan los desvanecidos.

## Iconografía

- Lucide (`lucide-react`), el set por defecto de shadcn: trazo 1.75 px, remates redondos, 20 px en botones y navegación, 24 px sueltos.
- Los íconos acompañan al texto; solo los botones de ícono van solos, con `aria-label`.
- Color: `currentColor`. En navegación inactiva, `muted-foreground`; activa, `sidebar-primary`. El candado de confidencialidad va en `primary`.
- Sin emoji ni ilustraciones decorativas.

## Logo y white label

- El logo de Humetría usa siempre los SVG del grupo de recursos **Logo**: `humetria-horizontal-color.svg` sobre claro y `humetria-horizontal-color-oscuro.svg` sobre oscuro (el componente `Logo`, `components/humetria/logo.tsx`, los cambia solo; `kind`: `logo`, `symbol` o `seal`). Mínimo 96 px de ancho; por debajo, el símbolo o el favicon.
- Área de respeto: la altura del círculo (≈ 1/4 del símbolo) por lado.
- **White label (PR #171, fusionado).** La empresa (o el consultor) cambia su logo (claro y oscuro) y su color de acento (`brand_color`). Tipografía y disposición nunca cambian. El acento pasa por `BrandPalette` (`app/Modules/Tenancy/Data/BrandPalette.php`), que lo ajusta hasta leerse a 4.5:1 en claro y en oscuro, también como texto sobre su fondo suave; `lib/brand-accent.ts` lo pone en la página. Se edita en la consola (`components/tenancy/tenant-brand-color-field.tsx`).
- **Quién firma la página:** usa siempre `CompanyMark` (`components/company-mark.tsx`), nunca `Logo` directo, en el sidebar, el portal, la encuesta, los reportes y el membrete de impresión. Dentro de una empresa pinta su logo (`CompanyLogoImage`) o, sin logo, sus iniciales y nombre (`CompanyInitialsMark`); fuera de una empresa, el logo de Humetría.
- Humetría aparece en las páginas de una empresa solo como el sello «hecho con humetría» (`Logo kind="seal"`), gris `#6B7472` (4.81:1 sobre blanco) a 16 px de alto, o blanco sobre oscuro. En reportes el logo de la empresa va arriba a la izquierda (≤ 48 px de alto); en la encuesta, ≤ 32 px.

## Marcos

Toda página vive en uno de estos marcos; `layouts/frames.ts` decide cuál. Una pantalla nueva entra en el marco de su audiencia y no inventa otro.

### Panel de la empresa (`/admin/{tenant}`)

`layouts/app/app-sidebar-layout.tsx` arma `AppShell` (el `SidebarProvider`, que recuerda si está plegado en la cookie `sidebar_state`), `AppSidebar`, `AppContent`, `MobileTabBar`, `CommandPalette`, el dock de primeros pasos y el de Humi.

- **Panel de la página:** el sidebar es `variant="inset"`; desde `md` el contenido va en un panel `bg-background` con `rounded-xl`, borde `sidebar-border` y `shadow-raised`, sobre el fondo `sidebar`. El cuerpo llena hasta 1440 px y se centra (ver «Espacio, forma y profundidad»).
- **Sidebar** (`components/app-sidebar.tsx`), de arriba abajo: `CompanyMark` (al staff fuera de una empresa, además la pastilla «Consola» en `warning`); `TenantSwitcher`, la tarjeta de 56 px con iniciales y nombre de la empresa, que abre la lista cuando la persona tiene dos o más; la navegación; al pie `NextObligationCard` (solo con una evaluación en curso) y `NavUser` (fila de 56 px que abre el menú de la cuenta hacia arriba). La lista se desvanece en sus bordes (`scroll-fade`) en vez de mostrar barra.
- **Grupos** (`lib/nav-sections.ts`, en este orden): sin título (Panel, Centros de trabajo, Empleados, Accesos), Administración, **Cumple la norma**, **Escucha a tu gente**, **Ordena tu operación** y, solo para staff, Consola, Ventas y Plataforma. Cada grupo con nombre es `NavGroup` (`components/nav-group.tsx`): una fila plegable de 44 px con el ícono de la familia, texto 600 y chevron, que empieza abierta cuando contiene la página actual y, plegada, suma los contadores de sus herramientas. Una herramienta con páginas propias (NOM-037) es `NavSubmenu` (ver `componentes/Sidebar.md`).
- **Fila activa:** fondo `card`, anillo de 1 px en `sidebar-border`, peso 500 e ícono en `sidebar-primary` (`SidebarMenuButton` en `components/ui/sidebar.tsx`). El contador de una fila (evaluaciones en curso de NOM-035) va a la derecha.
- **Plegado a íconos:** 66 px de ancho, botones de 44 × 44 con el nombre en tooltip; se pliega con el botón de la barra superior o ⌘B. Desaparecen los títulos de grupo y la tarjeta de obligación; el padre de un submenú enlaza a su primera página y la tarjeta de la empresa queda en sus iniciales.
- **Barra superior** (`AppSidebarHeader`, 64 px, fija al desplazar, con divisor cuando el contenido pasa debajo): a la izquierda el botón de plegar (tooltip «Contraer menú ⌘B», desde `md`) y las migas (`AppBreadcrumbs`: la empresa es el inicio; en el celular solo la última); a la derecha «Buscar o ir a… ⌘K» (`CommandPalette`; solo ícono bajo `lg`) y el tema (`ThemeSwitch` de tres opciones desde `sm`, `ThemeMenu` en el celular). El menú de la cuenta vive en el sidebar, no aquí. Al suplantar, `ImpersonationBanner` va encima.
- **Celular (< 768 px):** el panel pierde margen, radio y sombra; la navegación es `MobileTabBar` (`components/mobile-tab-bar.tsx`, pestañas de `lib/mobile-tabs.ts`: Panel, Normas, Operación cuando aplica, Quejas y Más), una píldora esmerilada flotando abajo. «Más» abre el sidebar como `Sheet`.
- **Ajustes de la cuenta** (`settings/*`) van dentro del panel con su propio submarco (`layouts/settings/layout.tsx`).

### Portal de la persona trabajadora (`/me/{slug}`)

Sin sidebar ni migas: una columna para el celular. Lo pinta `PersonFrame` (`components/person-frame.tsx`):

- Encabezado de 60 px: `CompanyMark` a la izquierda y, a la derecha, «Mi portal» o un enlace «Atrás» (`back`). Al pie, el sello. `centered` centra las pantallas de estado; `actions` fija la acción abajo.
- `WorkerFrame` (`components/worker-access/worker-frame.tsx`) envuelve `PersonFrame` con «Mi portal» y una columna `max-w-md`; en escritorio es la misma columna con más aire.
- **Barra inferior:** `PortalTabBar` (`components/worker-access/portal-tab-bar.tsx`) con Inicio, Marcar, Pendientes y Cuenta. Marcar es un enlace a la página de marcar y desaparece si la empresa no tiene asistencia (quedan tres). La pestaña actual es la cápsula `primary` que se desliza (`GlideChips`); Pendientes lleva su conteo. La barra solo aparece en el inicio (`HomeScreen`): nunca en Marcar, en una tarea ni en una pantalla anónima. No confundir con `MobileTabBar`, que es la del panel de RH.
- **Inicio** (`components/worker-access/home-start.tsx`): saludo según la hora, con fecha y centro; con asistencia, la tarjeta del día y las marcas de hoy; luego «Esperan su respuesta» y «Sus herramientas» en listas de tarjetas; al final, la oferta de avisos y `InstallGuide`.
- **Tareas** (vacaciones, Ley Silla, políticas…): «Atrás» arriba y una acción fija abajo, sin barra; lo que pide datos sube como hoja desde abajo (`components/leave/request-sheet.tsx`, `components/attendance/correction-sheet.tsx`). Las pantallas de estado usan `StateMessage` centrado.
- **Pantallas anónimas** (encuestas, canal de denuncias) no llevan barra, nombre ni enlace al portal: `SurveyShell` (`components/campaigns/survey/survey-shell.tsx`) y `components/complaints-public/public-shell.tsx`.
- El portal trata de «usted», como la encuesta.

### Consola de superadmin

Es el mismo marco del panel, sin empresa: `CompanyMark` muestra el logo de Humetría con la pastilla «Consola» y los grupos son Consola (Resumen, Empresas, Cuentas), Ventas (Solicitudes de demo, Sala de demo) y Plataforma (`lib/staff-navigation.ts`). Sus pantallas usan las mismas piezas que el panel: `PageHeader`, `CountTabs` y `SearchField` sobre tablas paginadas (`components/tenancy/tenants-toolbar.tsx`, `tenants-table.tsx`), `OverviewCard` en el resumen y diálogos (no paneles laterales) para crear, restringir o cobrar.

### Fuera del marco

Autenticación (`layouts/auth-layout.tsx`, con el panel de promesas en `layouts/auth/auth-promises.tsx`), primeros pasos (`SetupFrame`), reportes por enlace temporal (`ReportLinkBar`), el canal público de denuncias y la página de error (`StatusMessage`). Se centran con su propio ancho.

## Piezas para armar una pantalla

Las pantallas completas son las de la app (las maquetas de `pantallas/` se retiraron el 2026-11-15; siguen en el historial de git) y son el estándar: toda pantalla nueva parte de la más parecida y se arma con estas piezas. Todas viven en `components/humetria/` salvo que se diga otra cosa; los nombres de tablero en sus comentarios (V2-Panel, V4-Comp-Reporte…) apuntan al diseño del que salieron.

| Necesitas | Usa | Cuándo |
|---|---|---|
| Encabezado de página | `PageHeader` | Toda página del panel: caption opcional, título con su estado (`Badge`), una frase o una fila de datos con íconos, acciones a la derecha con la primaria al final. |
| Migas | `AppBreadcrumbs` (`components/`) | Las pone el marco; la página solo pasa `breadcrumbs`. |
| Vistas de una lista con conteo | `CountTabs` | «Pendientes 42 · Contestaron 18 · Todas 60»; cada una es un enlace y el servidor pagina. |
| Partes de un reporte largo | `SectionTabs` | Pestañas fijas arriba que saltan a cada parte. |
| Buscar y filtrar una lista | `SearchField`, `ListToolbar` (`components/`), `Pagination` | Toda lista se pagina y busca en el servidor. |
| Tabla | `Table` (`components/ui/`) con cabecera `muted`; `StackedTable` si en el celular cada fila debe volverse tarjeta | Muchas entidades que se comparan (ver `componentes/Table.md`). |
| Acciones de una fila | `RowMenu` | Botón de 44 px «Más acciones»; la destructiva al final. |
| Acciones sobre filas elegidas | `BulkBar` | Solo mientras hay selección. |
| Tarjeta | `Card` (`components/ui/`) o la utilidad `card` | Pocas entidades con su estado y su acción (ver `componentes/Card.md`). |
| Avance | `ArcMeter` (el arco de la «h»), `ProgressBar`, `StageBar` | Siempre junto a la cifra escrita. |
| El siguiente paso | `NextStepCard`, `UrgencyList` | Una sola acción recomendada; lo que no puede esperar, cada uno con su acción. |
| Algo que revisar, sin alarma | `Notice` | El dato en negritas primero y la acción a la derecha. `ReadOnlyNotice` cuando el rol no puede cambiar nada; `PlanLimitNotice` para el límite del plan. |
| Lista vacía | `EmptyState` | Ícono, título, qué la llena y el botón al siguiente paso. |
| Pantalla de error o bloqueo | `StatusMessage` | 403, 404, enlace vencido, sin acceso. |
| Un ajuste | `SettingRow` | Ícono en cuadro suave, nombre con estado, una frase y su acción a la derecha. |
| Elegir de una lista corta | `ChoiceField` (tarjetas), `OptionRows` (filas de 52 px, radio o casilla), `PillChoice` (Sí / No en fila) | En pantallas nuevas no se rehace el estilo de tarjeta elegida: se usa una de estas. |
| Elegir de una lista larga | `Combobox` (con `serverSource` si crece) | |
| Fechas, horas, teléfono, cifras | `DatePicker`, `DateRangePicker`, `MonthPicker`, `TimePicker`, `PhoneInput`, `NumberStepper`, `FileDropZone` | |
| Campo de texto con label y error | `FormField` (`components/form-field.tsx`), `InputError` | |
| Alta o edición de un registro | `FormSheet` | Panel lateral derecho (ver `componentes/Sheet.md`). |
| Confirmar o acción corta | `ConfirmDialog`, `FormDialog` | Lo que no se deshace, o uno o dos campos (ver `componentes/Dialog.md`). |
| Una pregunta tras otra | `FlowProgress`, `FlowNav`, `QuestionHeading`, `DocumentPreview` | «Siguiente» explícito; nunca avanza solo. |
| Persona o empresa sin foto | `Initials` | Personas redondas, empresas cuadradas. |
| Riesgo | `RiskLevel` | Siempre; nunca un `Badge` de color. |
| Resultados | `LevelBar`, `LevelLegend`, `ShareMeter`, `BarMeter`, `ProtectedCell` | Ver «Resultados» abajo. |
| Gráficas de cifras | `BarMeter`, `ColumnChart`, `WaterfallChart`, `WeekRibbon` | En el jade de las gráficas, con su valor escrito. |
| Ícono de una herramienta | `ToolIcon` | El mismo dibujo dondequiera que se nombre la herramienta. |
| Imprimir | `PrintButton`, `PosterPage`, `PrintLetterhead` (`components/`) | |

Patrones de pantalla que ya existen y se copian:

- **Panel de inicio** (`pages/dashboard.tsx`): lo urgente («Atiende primero»), las rutas de cada norma con su `StageBar` y el siguiente paso recomendado, con la semana a un lado.
- **Encuesta** (`SurveyShell` + `SurveyQuestion`, en `components/campaigns/survey/` y `components/engagement/survey/`): `CompanyMark` y «Confidencial», progreso por sección, instrucción de entrada en un bloque `accent` y 5 opciones verticales de 56 px. En móvil va una pregunta por pantalla; en escritorio, hasta 3 de la misma sección. «Siguiente» es explícito y nunca avanza solo.
- **Escala de riesgo** (`RiskLevel`, y `RiskScale` en `components/results/`).
- **Resultados** (`LevelBar`, `LevelLegend`, `ShareMeter`): primero una frase que dice cuántos quedan en riesgo alto o muy alto y qué dominio pesa más, luego la calificación final como una sola barra con su leyenda, y los dominios, categorías y áreas ordenados del peor al mejor, cada uno como barra; los conteos quedan en una tabla a un clic (en el informe, a la vista). Las preguntas van de la más desfavorable a la menos, con un `ShareMeter` por pregunta. Un grupo bajo el mínimo muestra `ProtectedCell`.
- **Cabecera de reporte** (`ReportHeader`, `components/results/report-header.tsx`): `CompanyMark` y el sello «hecho con humetría», la norma y la guía en `primary`, título, empresa y centro, y la ficha (periodo, cuestionarios, instrumento, elaboró).
- **Informe del numeral 7.7** (`components/results/`): `SectionTabs` con Resumen, Focos, Dominios, Áreas, Informe 7.7 y Anexo, y `PrintButton`; aviso de lo que falta capturar (`risk-medio-bg`, nunca se imprime); resumen ejecutivo (`ReportKpi`, un cuadro por cuestionario y tres hallazgos), los 3 dominios donde actuar primero, la gráfica divergente categoría → dominio (a la izquierda nulo a medio, a la derecha alto o muy alto), las áreas en tres niveles con las protegidas contadas aparte, los ocho incisos del 7.7 y los anexos. Se imprime siempre en claro, con un salto de página antes de Focos, Dominios, Informe 7.7 y Anexo.
- **Humi** (`components/assistant/`): ver `humi/03-ui-spec.md`.

## Accesibilidad

- WCAG 2.x AA en los dos modos. Cada token de color indica su par y su contraste en **Tokens para shadcn/ui**.
- Nunca dependas solo del color: los estados llevan texto, la opción elegida lleva borde de 2 px y peso 500 y el riesgo lleva medidor y etiqueta.
- Formularios con label visible, error enlazado con `aria-describedby` y `aria-invalid`.
- La encuesta funciona con teclado (flechas dentro del grupo, tabulador entre grupos) y con lector de pantalla (`fieldset` + `legend`).
- Respeta `prefers-reduced-motion` y `prefers-color-scheme`. El tema oscuro se activa con la clase `.dark` en `<html>`.
