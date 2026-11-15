# Plan de rediseño · Organigrama, Vacaciones, Clima y Pulso

Fecha: 11 nov 2026. Alcance: solo las cuatro herramientas del PR #90. Entregable de esta fase: artboards `.dc.html`
(antes | después) en un lienzo de Claude Design. Este documento solo planea; no toca el repo.

Convenciones del documento: **[V]** = lo verifiqué yo (captura, volcado de texto `before/_text/*.txt` o código);
**[A]** = viene de `defects.md` sin que yo lo haya comprobado; **propuesta nueva** = no existe hoy en el backend.
"Hoy" en todos los boards es **miércoles 11 de noviembre de 2026**.

Fuentes leídas: `defects.md`, README y `tokens.json` del design system, `format.md` / `craft.md` / `diagrams.md`,
`research.md`, capturas y volcados de `before/`, `resources/js/pages|components|lib` de `organization`, `leave` y
`engagement`, `lib/nav-sections.ts`, `lib/app-navigation.ts`, `lib/tool-navigation.ts`, `lib/leave/queue.ts`,
`types/organization.ts`, `components/humetria/arc-meter.tsx`, `resources/css/app.css` (tokens extra), el spec
`docs/superpowers/specs/2026-11-09-vacations-orgchart-pulse-climate-design.md` y `app/Modules/Engagement` (umbrales).

Correcciones a `defects.md` según lo que vi:

- "NO hay árbol" en Organigrama es impreciso: **sí hay árbol** (`org-tree.tsx`, `role="tree"`, nodos que abren y
  cargan a sus reportes por página, foco con migas de cadena). El defecto real es que **abre cerrado**: tres raíces
  apiladas en 580 px, sin ningún nivel abierto ni conectores, y media pantalla vacía. [V]
- La supresión complementaria que propone `research.md` **ya existe** en el backend
  (`Engagement/Support/Results/CutReport.php`, `Thresholds.php`, README del módulo: "un solo atributo de corte a la
  vez y supresión complementaria"; comentarios con mínimo 10). No hay que construirla; hay que **dibujarla bien**. [V]
- "Tenía días · del periodo 2026" sin número **es decisión del spec** (línea 292: "cuántos días y si los tenía
  disponibles"), no un bug: `lib/leave/queue.ts` solo recibe `hadBalance`, `periods` y `shortBy`. [V]
- La etiqueta de tendencia tiene tres valores en código (`Subió`, `Bajó`, `Igual`, `lib/engagement/trend.ts`); el
  problema es que 72 → 71 % se rotula "Bajó" sin magnitud ni banda de ruido. [V]

---

## A. Evaluación

### A.1 Organigrama

| Prio | Pendiente | Por qué importa |
|---|---|---|
| P0 | El árbol abre cerrado: 3 tarjetas de raíz en columna de 580 px, sin primer nivel, sin conectores, ~55 % del ancho vacío. [V] | Es la única razón de ser de la pantalla: ver la forma de la empresa de un vistazo. Hoy hay que dar 3 clics para ver 12 personas. |
| P0 | KPIs que no llevan a nada: "Niveles 4", "Mediana 4.0"; el único accionable ("Jefes por revisar 1") pesa igual y no es un enlace. [V] | RH no sabe qué hacer con una mediana; sí con "1 jefe con 17 directos" y "3 personas hasta arriba". |
| P1 | Barra con 4 controles + "Calidad de datos" como texto suelto + "Imprimir" en contorno; ninguna acción primaria clara. [V] | La tarea real es buscar a alguien; el buscador debe mandar. |
| P1 | La tarjeta de nodo solo ofrece ">" : no distingue abrir la rama de "ver desde aquí"; el aviso `few/many` y `absent_today` existen en datos pero casi no se ven. [V código] | El tramo de control es la señal que RH busca. |
| P1 | Calidad de datos: tabla con un "Guardar" gris por fila; pestañas "Sin jefe 0 · Raíces 3 · Jefe dado de baja 0" con ceros al frente. [V texto] | Es una lista de pendientes; debe leerse como "3 por resolver" y resolverse en un paso por persona. |
| P2 | A 375 px, tarjetas largas sin sangría que indique nivel. [A] | En el celular la jerarquía se pierde. |

### A.2 Vacaciones

| Prio | Pendiente | Por qué importa |
|---|---|---|
| P0 | Resumen: 4 KPI de igual peso ("0", "7", "133", "4340"), dos avisos apilados (franja arena + línea suelta) y "Detalle" abre en "Fuera hoy 0" con un vacío sin siguiente paso. [V] | La pregunta es "¿qué me toca hoy?". La respuesta (4 solicitudes escaladas) está enterrada como subtexto gris. |
| P0 | Solicitudes: una escalada y una pendiente solo difieren en subtexto gris; la decisión exige abrir "Ver" fila por fila; columna "Días" con texto, no dato; 6 pestañas del mismo peso. [V] | Aprobar es el flujo más repetido de la herramienta. Hoy son 3 pasos por solicitud y sin ver quién más falta. |
| P0 | Calendario: 25 filas alfabéticas por página con 1 sola barra; 9 páginas; cuadrícula cortada a la derecha (el día 29–30 no cabe); barra gris sin distinguir tipo. [V] | Un calendario que hay que paginar 9 veces para ver 3 ausencias no responde "quién falta". |
| P1 | Saldos: "Expediente" ×25; "Disponibles" como texto sin medidor; orden por nombre; selección sin barra masiva; 2174 px. [V altura / A resto] | La tarea real es encontrar a quien necesita algo (sin fecha de ingreso, sin acuse, ventana por cerrar). |
| P1 | Expediente de la persona: un KPI es texto ("Política: Personal de confianza"); movimientos y solicitudes sin pestañas; "Ajustar" suelto. [V texto] | Es la pantalla donde RH explica un saldo a una persona; la cuenta debe leerse sola. |
| P1 | Políticas: tarjetas sin tramos por antigüedad; "Asignada a 1 grupo" sin número de personas; "Nueva política" + 2 "Editar" + 3 selects + "Asignar" a la vista. [V] | Nadie puede comparar dos políticas sin entrar a editarlas. |
| P1 | Editar política: 2317 px, 9 renglones de rango idénticos, "Guardar" hasta abajo, sin vista previa de días por antigüedad. [V] | Un error aquí cambia los días de todo un grupo. |
| P2 | Configuración (tipos De la ley / De la empresa iguales) e Importar (asistente con 900 px vacíos). [A] | Uso esporádico; se resuelven con el kit sin board propio (ver C.4). |

### A.3 Clima

| Prio | Pendiente | Por qué importa |
|---|---|---|
| P0 | Resultados (2450 px): dos porcentajes compitiendo arriba (62 % compromiso en texto, 79 % participación en el arco); "−27" sin escala ni lectura; "Dónde actuar primero" y "Qué mueve el compromiso" son dos listas del mismo tema separadas; 14 barras iguales; "Por grupo" al fondo. [V] | El tablero debe dar **una** lectura: cómo estamos, dónde actuar, qué sigue. Hoy RH tiene que armarla. |
| P0 | Avance de la encuesta activa: el arco muestra "94" sobre una escala que representa 44 %; "Cerrar encuesta" junto a "Recordar a 118"; el corte por grupo son botones, no pestañas; 600 px vacíos. [V] | En curso, la única tarea es subir la participación sin romper el anonimato. |
| P1 | Inicio: tarjeta activa de 640 px con "Ver avance" en contorno siendo lo principal; historial de una fila. [V] | Es la puerta: debe decir qué está pasando y qué sigue (compromisos del clima anterior). |
| P1 | Por pregunta (4078 px): 30+ filas sin filtro por tema ni orden; barras apiladas sin leyenda. [V altura / A leyenda] | Sirve para profundizar en un tema; hoy es un rollo. |
| P1 | Compromisos: 4 acciones de texto por tarjeta ("Editar · Reabrir · Marcar cumplido · Descartar"); "Ver resultados" y "Nuevo compromiso" compiten; "Descartado 0". [V texto] | Es el cierre del ciclo y lo que ve la gente en el portal. |
| P1 | Jefes: tabla de una columna con "Ver retroalimentación"; el detalle sí tiene sustancia (pregunta por pregunta contra la empresa) pero son 1794 px de filas iguales. [V] | Es la entrega más delicada: debe leerse en fortalezas y oportunidades, sin ranking. |
| P2 | Comentarios: resumen por temas bien concebido pero con ícono de chispas y lista sin orden ni tema; hay duplicados textuales en los datos sembrados. [V texto] | El resumen ya es la lectura; la lista es evidencia. |

### A.4 Pulso

| Prio | Pendiente | Por qué importa |
|---|---|---|
| P0 | Tendencia (2786 px): columnas que no parten de cero (62 % y 71 % casi iguales); recomendación −27…−13 se ve plana; "Subió/Bajó" sin puntos; 10 tarjetas "Por tema" con 2–3 columnas del mismo jade, sin línea base. [V] | La tendencia **es** el producto de Pulso. Hoy no se puede leer cuánto cambió nada. |
| P0 | Inicio: abre con un vacío grande "Todavía no hay un pulso" aunque hay 3 cerrados y uno programado para el 15 de noviembre; el selector de modo ocupa la pantalla; "Ver tendencia" en contorno. [V] | El primer vistazo dice "no hay nada" cuando hay cuatro mediciones. |
| P1 | Resultados de un pulso: repite el patrón de Clima; "Sin respuestas suficientes" en 3 de 5 temas sin explicar; "Mejoras vistas 71 %" con frase en minúscula. [V texto] | Un pulso tiene pocas preguntas: la pantalla debe ser corta y comparada contra la ola anterior. |
| P2 | Jefes y Compromisos de un pulso: mismos defectos que en Clima. El encabezado de `pulso-jefes` dice "Resultados de clima". [V texto] | Se resuelven con los boards de Clima (mismos componentes en `components/engagement`). |

### A.5 Separación explícita

**(i) Defectos de diseño** (los arregla el rediseño): todo lo de las tablas A.1–A.4, más los transversales:
acciones de texto repetidas por fila; pestañas con conteo 0 al frente; vacíos sin siguiente paso; más de 3 KPI;
encabezados sin estado ni ficha; listas por nombre en vez de por urgencia; objetivos táctiles menores a 44 px
(pestañas 38 px a 375 px [A]); "30%" sin espacio [A].

**(ii) Problemas de producto o de datos que el diseño no arregla** (van como tareas aparte en la implementación):

1. **Migas sin familia** [V capturas nuevas]: en main (#93) solo Organigrama dice "Ordena tu operación › Organigrama".
   Vacaciones dice "Vacaciones › Resumen" y Clima "Clima › Clima · junio 2026 › Resultados". El diseño las dibuja
   siempre con familia; en código es `setLayoutProps({ breadcrumbs })` de cada página o `navTrail`.
2. **Duplicado "Paternidad"** [V texto]: "Paternidad" (De la ley) y "Permiso de paternidad" (De la empresa). Es dato
   sembrado o falta una validación de nombre contra los tipos de ley.
3. **"Tenía días" sin número**: decisión del spec, no bug (ver arriba). Mostrar el saldo es decisión abierta 2.
4. **El arco muestra un conteo sobre escala de porcentaje** (`ArcMeter value=94 max=212` con "94" adentro). Hay que
   decidir qué número va adentro; el diseño propone el porcentaje adentro y el conteo debajo.
5. **Nombres de centro con "(N personas)" pegado** [V]: "Planta (100 personas)" aparece junto a "31 de 78". El sufijo
   es parte del nombre sembrado y contradice a los invitados. En los boards se usa el nombre corto ("Planta").
6. **Conteos que no cuadran entre Resumen y Solicitudes** [V]: Resumen dice "Por responder 7 · 4 escaladas a RH";
   Solicitudes dice "Por atender 4" y "En espera del jefe 7" (11 solicitadas). O el KPI cuenta solo las del jefe o
   la etiqueta engaña. El diseño usa "4 esperan tu respuesta" y "7 esperan a su jefe".
7. **"Bajó" para 72 → 71 %** [V]: sin banda de ruido, un punto se rotula como caída. Falta definir el umbral de
   "Igual" (hoy parece ser 0). Recomendación: ±2 pts es "Sin cambio".
8. **"Sin respuestas suficientes" sin decir cuántas faltan** [V]: **no** se debe decir cuántas (revelaría n < 5).
   El texto correcto es "Menos de 5 respuestas: no se muestra". Es copy, no dato.
9. **"1 día fijos cada año"** [V]: falta el singular ("1 día fijo cada año").
10. **Texto de Pulso que dice "Resultados de clima"** en la lista de jefes de un pulso [V texto].
11. **"0 días · Al corriente"** (Alicia Ayala) [V texto]: "Al corriente" con saldo 0 confunde; es regla de estado.
12. **"Asignada a 1 grupo" sin personas** [V]: el backend no manda cuántas personas cubre cada política.
13. **Pulso abre con "Todavía no hay un pulso"** habiendo historial: la condición del vacío es "no hay activo ni
    borrador", no "no hay pulsos". Es lógica de `pages/engagement/index.tsx`, no solo estilo.
14. **Comentarios duplicados** y sin resumen de IA en pulsos ni por jefe en los datos sembrados: no se dibujan.
15. Constancia: "30% sobre los días" [V texto] y bloque "Sin acuse" separado. Es un documento imprimible; queda
    fuera del catálogo y entra como corrección de copy en la implementación.

**(iii) Decisiones abiertas para Victor** (5, con recomendación):

1. **Mapa por grupo en Clima (grupos × temas) con celdas protegidas.** Toca la regla de privacidad. El backend ya
   aplica mínimo 5, un solo atributo de corte a la vez y supresión complementaria. Falta decidir cómo se ve: la
   celda del grupo bajo el mínimo y la del vecino suprimido por complemento se dibujan **idénticas** (candado +
   "Protegido"), sin decir cuál es cuál ni cuántas respuestas hubo, y el mapa nunca cruza dos atributos.
   *Recomendación: sí, con esas tres reglas, y un test que afirme que las props no distinguen los dos motivos.*
2. **Mostrar el saldo en la solicitud** ("Sí le alcanza · le quedarían 20 días") en vez de "Tenía días". Cambia una
   decisión del spec. RH ya ve ese saldo en Saldos, así que no abre información nueva.
   *Recomendación: sí para RH; para el jefe en el portal, mantener solo "le alcanza / no le alcanza".* Exige un
   campo nuevo en el DTO de la fila (saldo tras aprobar).
3. **Cobertura del equipo al aprobar** (propuesta nueva): en la tarjeta de decisión, quién más del mismo jefe está
   fuera esos días, solo "ausente", nunca el motivo. *Recomendación: sí, por jefe directo (ya existe la relación en
   Organization vía contrato), sin mínimo de personal configurable en esta entrega.* Exige una consulta nueva y su
   presupuesto de consultas.
4. **Calendario: por defecto solo quienes tienen ausencia en el mes**, con el resto a un clic ("Ver a las 212").
   Cambia qué pagina el servidor. *Recomendación: sí; hoy 211 de 212 filas están vacías.* Exige un filtro nuevo en
   el paginador (`with_absence=1` por defecto) y su caso en `PaginatedListsTest`.
5. **Organigrama como árbol de lista indentada con conectores (2 niveles abiertos al entrar)**, no como lienzo con
   pan/zoom. *Recomendación: lista. Es accesible (`role="tree"` ya existe), funciona a 375 px, imprime bien y no
   pide dependencia nueva.* Exige que el servidor mande el primer nivel de hijos de cada raíz en la primera carga
   (hoy los pide al abrir).

---

## B. Principios de rediseño compartidos

Dirección visual (una sola, para las cuatro): **"expediente en calma"**. Mucho blanco, bordes finos, cifra grande
en Figtree 600 con números tabulares, un solo jade, y el arco de la "h" del logo como firma de marca en cada
tablero. Lo atractivo sale de la composición (una pieza protagonista por pantalla, asimetría 2/3 + 1/3) y de la
precisión tipográfica, no de sombras, degradados ni color extra. Los agentes de dibujo cargan las skills
`frontend-design` e `impeccable` antes de escribir; lo que sigue son las reglas que esas skills no pueden adivinar.

1. **Una pregunta por pantalla, respondida en una frase.** El encabezado lleva título, píldora de estado y una
   ficha de 2–4 datos con ícono. Debajo, una **frase de lectura** en `h3` que ya contiene la respuesta ("4
   solicitudes esperan tu respuesta; la más próxima empieza en 7 días").
2. **Jerarquía de KPI 1 + 2.** Máximo 3. Uno protagonista (cifra `kpi` 32/40 o arco) y dos de apoyo (cifra 24/32).
   Un KPI que vale 0 no ocupa tarjeta: baja a la línea de apoyo ("Nadie está fuera hoy").
3. **Una acción primaria por vista** (botón `primary`). Las demás son `outline` o van al menú de fila. Las acciones
   destructivas o raras (Cerrar encuesta, Archivar, Descartar) viven en el menú "Más" del encabezado.
4. **Pestañas con conteo, ordenadas por lo que pide acción**; las de conteo 0 se quedan pero atenuadas y al final;
   nunca son la pestaña que abre.
5. **Tabla densa**: cabecera `muted` en `caption` mayúsculas, fila ≥ 56 px, cifras a la derecha con `tabular-nums`,
   **toda la fila es el enlace** al detalle, y un botón de ícono "Más acciones" (44×44) para lo demás. Como mucho un
   botón de texto por fila, y solo si es **la** tarea de esa fila (p. ej. "Responder").
6. **Orden por urgencia**, declarado en una línea sobre la lista ("Ordenado por fecha de inicio: lo más próximo
   arriba"). Nunca alfabético por defecto en una bandeja.
7. **Medidor de arco** (la "h" del logo) solo para "cuánto llevamos de un total" y siempre con el **porcentaje
   adentro** y el conteo debajo. Un arco por pantalla.
8. **Barra con referencia**: toda barra de resultado lleva una marca vertical de referencia (promedio de la empresa
   o la ola anterior) y el delta en texto ("+16 pts"). Las columnas de tendencia **parten de cero**.
9. **Barra de acciones masivas**: aparece pegada abajo del contenedor de la tabla al seleccionar ("3 seleccionadas ·
   Enviar acuse · Quitar selección"); nunca botones masivos permanentes.
10. **Estado vacío con siguiente paso**: ícono en losa `accent`, título que dice qué pasó, una línea de qué sigue y
    **un** botón. Si el vacío es bueno ("Nadie está fuera hoy"), no lleva botón y se reduce a una línea.
11. **Una sola tarjeta de aviso por pantalla** (fondo `warning`). Si hay dos avisos, se funden en una con dos
    renglones y un enlace cada uno. Nunca franja + línea suelta.
12. **Privacidad visible**: toda pantalla de resultados lleva en la ficha el candado y "Grupos de 5 o más". Una
    celda o fila protegida muestra candado y "Protegido", nunca un número ni cuántas faltan.
13. **Color**: jade para avance y selección; arena (`warning`) para "pide atención"; terracota (`risk-alto-*`) solo
    para "venció" o "por debajo", siempre con texto. Sin verde/rojo. `brand-soft` y `brand-deep` solo dentro del
    logo.
14. **Voz**: es-MX, tutea, verbo + objeto en botones, sin signos de exclamación ni emoji. Porcentaje con espacio
    ("30 %"), signo menos tipográfico ("−27"), fechas "18 al 20 de noviembre" o "18 nov 2026", "pts" para deltas.
15. **Móvil (375 px)**: la tabla pasa a tarjetas apiladas; filtros en una fila con desplazamiento horizontal; una
    acción primaria fija abajo cuando la pantalla tiene una; todo objetivo ≥ 44 px.
16. **Movimiento**: ninguno en los boards salvo `transition: 150ms` en color y fondo.

---

## C. Catálogo del "después"

19 de escritorio + 6 móviles. Todos bajo `people/<herramienta>/`. El "antes" de cada uno es
`<Nombre>-antes.dc.html` en la misma carpeta (lo arma otra persona con la captura indicada). Todas las capturas
están en `scratchpad/before/`; `X` significa `X-claro.png` (y `X-oscuro.png`, `X-375.png`).

Reglas comunes a todos los boards de escritorio: frame de 1440 de ancho, PAGE con `"expand": "fill"`, raíz fluida,
`$preview` igual al frame, tweak `theme` (`claro` | `oscuro`, por defecto `claro`). `variant` solo donde se indica.
Los móviles son frames fijos de 375 de ancho, también con `theme`.

### C.1 Organigrama

**O1 · `people/organigrama/Organigrama.dc.html` — 1440×1300 — antes: `orgchart-index`** (P0)

- Tarea: encontrar a una persona y ver su línea de reporte; de paso, detectar lo que hay que corregir.
- Migas: `Ordena tu operación › Organigrama`. Sidebar `active="organigrama.organigrama"`.
- Encabezado: "Organigrama"; ficha: `users` "212 personas activas" · `network` "4 niveles" · `user` "209 con jefe
  directo". Acción primaria: ninguna de botón; el protagonista es el **buscador** ("Busca a una persona", 480 px,
  a la izquierda). A su derecha, filtros "Todos los centros" y "Todas las áreas"; menú "Más" con "Imprimir".
- Frase de lectura: "Tu empresa tiene 4 niveles. Hay 3 personas hasta arriba; normalmente solo hay una."
- Franja "forma de la empresa" (propuesta de dibujo, datos reales): cuatro bloques en fila con el ancho
  proporcional al logaritmo del conteo y la cifra adentro: Nivel 1 · 3 · Nivel 2 · 9 · Nivel 3 · 36 · Nivel 4 · 164.
- Columna principal (2/3): el árbol como lista indentada con conectores (borde izquierdo + codo), **dos niveles
  abiertos**. Raíces reales: Esther Alaniz Segundo (Almacenista · Operaciones · Oficina · 3 directos · 78 en total),
  Gonzalo Reyes Segundo (Analista · Administración · Sucursal · 3 directos · 66 en total), Yeray Pulido (Operador ·
  Almacén · Planta · 3 directos · 65 en total). Bajo cada raíz, 3 filas de reporte directo: los nombres del segundo
  nivel **no están en los datos de ejemplo**; se dibujan con nombres del padrón que sí aparecen en capturas
  (Adriana Rivera Hijo, Alonso Banda, Ander Noriega bajo Esther; Dr. Hugo Verduzco, Francisca Duran, Gabriel
  Esquibel bajo Gonzalo; Lic. Ana María Maya Tercero, Lic. Gael Cabán, Lorena Marín bajo Yeray) y la nota del
  lienzo dice "asignación de segundo nivel ilustrativa". Una fila lleva la píldora arena "17 directos" (el jefe por
  revisar real). El punto "Fuera hoy" no se dibuja: hoy nadie está fuera.
- Fila de nodo: avatar de iniciales, nombre, puesto · área · centro, a la derecha "3 directos · 78 en total", botón
  de ícono para abrir o cerrar la rama (`chevron-down`/`chevron-right`, 44×44, con `aria-expanded`) y menú de fila
  con "Ver desde aquí" y "Ver en Empleados".
- Columna lateral (1/3), tarjeta "Por revisar": "3 personas hasta arriba" → "Asígnales jefe" (enlace a Calidad de
  datos); "1 jefe con 17 directos" → "Ver a quién"; debajo, en `small`, "Mediana: 4 reportes directos por jefe. Con
  1–2 o más de 15 es una señal, no una regla."
- Estados: (a) con datos (el de arriba); (b) `variant="vacio"`: nadie tiene jefe: vacío con "Todavía nadie tiene
  jefe directo", "Asígnalo al editar a cada persona o súbelo en el CSV de empleados" y botón "Ir a Empleados";
  (c) `variant="busqueda"`: persona enfocada (Lic. Gael Cabán) con su cadena hacia arriba en migas de cadena, sus
  reportes abiertos y "Quitar filtro".
- Cambia respecto al antes: el árbol abre con dos niveles y conectores; el buscador manda; los 3 KPI se reducen a
  ficha + una tarjeta "Por revisar" accionable; "Imprimir" baja al menú; la media pantalla vacía desaparece.
- Tweaks: `theme`, `variant` (`datos` | `vacio` | `busqueda`).
- Backend que exige: primer nivel de hijos en la carga inicial (decisión 5); los conteos por nivel (3, 9, 36, 164)
  hoy no viajan como prop (`OrgIndicators` trae `levels`, mediana y `managers`): **propuesta nueva**, un arreglo
  corto `people_by_level`.

**O2 · `people/organigrama/CalidadDeDatos.dc.html` — 1440×1000 — antes: `orgchart-quality`** (P1)

- Tarea: dejar la línea de reporte sin huecos.
- Migas: `Ordena tu operación › Organigrama › Calidad de datos`. Sidebar `active="organigrama.calidad"`.
- Encabezado: "Calidad de datos" + píldora arena "3 por revisar". Ficha: "212 personas activas".
- Pestañas por lo que pide acción: "Hasta arriba 3" (activa), "Sin jefe 0", "Jefe dado de baja 0" (las de 0
  atenuadas).
- Aviso único: "Hay 3 personas hasta arriba. Normalmente solo hay una: elige quién le reporta a quién."
- Lista de tres tarjetas-fila (Esther Alaniz Segundo, Gonzalo Reyes Segundo, Yeray Pulido): persona + "Operaciones ·
  Oficina" + selector "Elige a su jefe directo" (combobox con búsqueda en servidor) + botón "Asignar" que **solo
  se pinta primario en la fila que ya tiene elección** (dibujar la primera con "Gonzalo Reyes Segundo" elegido y
  botón primario; las otras dos con el botón deshabilitado en contorno) + enlace "Es quien dirige la empresa" como
  acción secundaria de texto (**propuesta nueva**: marcar una raíz como legítima; exige una columna o bandera).
- Estados: (a) con 3 pendientes; (b) `variant="al-dia"`: "Todo en orden. Las 212 personas tienen su línea de
  reporte completa." con enlace "Ver organigrama".
- Cambia: un botón vivo en vez de tres grises; pestañas ordenadas por acción; el aviso explica qué hacer; estado
  "al día" con cierre.
- Tweaks: `theme`, `variant` (`pendientes` | `al-dia`).

**O3 · `people/organigrama/Organigrama-movil.dc.html` — 375×1500 — antes: `orgchart-index-375`**

- Mismo contenido que O1 en una columna: buscador a todo el ancho, filtros en fila desplazable, tarjeta "Por
  revisar" compacta arriba del árbol, árbol con sangría de 16 px por nivel y línea guía, nodo en dos renglones
  (nombre / puesto · centro) con "3 · 78" a la derecha y botón de rama de 44 px. Barra inferior
  `MobileTabs active="mas"`.
- Tweaks: `theme`.

### C.2 Vacaciones

**V1 · `people/vacaciones/Resumen.dc.html` — 1440×1300 — antes: `vacaciones-index`** (P0, pantalla principal)

- Tarea: saber en 5 segundos qué toca hoy y resolverlo sin salir.
- Migas: `Ordena tu operación › Vacaciones › Resumen`. Sidebar `active="vacaciones.resumen"`.
- Encabezado: "Vacaciones"; ficha: `calendar-days` "Miércoles 11 de noviembre" · `users` "212 personas" · "Todo en
  días, nunca en dinero". Acción primaria: "Responder solicitudes" (a la bandeja).
- Frase de lectura: "4 solicitudes esperan tu respuesta. La más próxima empieza el 18 de noviembre."
- KPI 1 + 2: protagonista "4 esperan tu respuesta" (con "7 más esperan a su jefe"); apoyo "133 ventanas por cerrar"
  (periodos cuyo plazo de disfrute está por vencer o venció) y "4,340 días por disfrutar" (198 periodos · ninguno
  por prescribir). "Fuera hoy 0" no es tarjeta: es la línea "Nadie está fuera hoy ni esta semana" en la ficha.
- Aviso único (dos renglones): "3 personas no tienen fecha de ingreso: sin ella no se les otorgan días. →
  Completar" y "198 constancias anuales sin acuse. → Ver quiénes".
- Columna principal (2/3), "Por responder": las 4 solicitudes escaladas como tarjetas de decisión, ordenadas por
  fecha de inicio: Iván Castañeda · 18 al 20 de noviembre · 3 días laborables · "Su jefe (Dña Helena Guillen) no
  respondió en 3 días hábiles" · píldora arena "Empieza en 7 días"; Gonzalo Reyes Segundo · 30 de noviembre al 2 de
  diciembre · 3 días · "Sin jefe activo"; Úrsula Ayala · 9 al 11 de diciembre · 3 días · "Su jefe (Manuela Fuentes)
  no respondió en 3 días hábiles"; Esther Alaniz Segundo · 21 al 24 de diciembre · 3 días laborables · "Sin jefe
  activo". Cada tarjeta: "Le alcanza · del periodo 2026", botón "Aprobar" (primario solo en la primera; contorno
  en las demás) y "Rechazar" en el menú de fila. Pie: "Ver las 11 solicitadas".
- Columna lateral (1/3), "Los próximos 14 días": tira de 14 celdas (11 al 24 de noviembre) con el número de
  personas fuera por día tomado de la captura del calendario (17, 18, 19 y 20 nov: 1; el resto: 0), y debajo
  "Ventanas por cerrar: 133 → Ver saldos".
- Estados: (a) con datos; (b) `variant="primera-vez"`: sin políticas ni saldos: tres pasos numerados ("Revisa tu
  política", "Carga los saldos iniciales", "Comparte el portal") con un solo botón "Empezar por la política";
  (c) `variant="al-dia"`: nada por responder: "Estás al día. Nadie espera respuesta." y la tira de 14 días sube.
- Cambia: de 4 KPI iguales a 1 + 2; la bandeja se resuelve desde el resumen; dos avisos en una tarjeta; el vacío
  "Nadie está fuera hoy" deja de ser lo primero; aparece la vista de 14 días.
- Tweaks: `theme`, `variant` (`datos` | `primera-vez` | `al-dia`).
- Backend: la tira de 14 días es una consulta nueva acotada (**propuesta nueva**, conteo por día, sin nombres).

**V2 · `people/vacaciones/Solicitudes.dc.html` — 1440×1200 — antes: `vacaciones-solicitudes`** (P0)

- Tarea: responder una solicitud con contexto y pasar a la siguiente sin recargar.
- Migas: `Ordena tu operación › Vacaciones › Solicitudes`. Sidebar `active="vacaciones.solicitudes"`.
- Encabezado: "Solicitudes" + píldora arena "4 por atender". Acción primaria: ninguna en el encabezado; "Registrar
  a nombre de alguien" pasa a contorno (la primaria está en el panel de decisión).
- Pestañas: "Por atender 4" · "En espera del jefe 7" · "Aprobadas 8" · "Disfrutadas 6" · "Rechazadas 4" ·
  "Canceladas 1". Buscador + "Todos los centros" + "Todas las áreas".
- Composición lista + panel: a la izquierda (58 %) la tabla de las 4 con columnas Persona · Fechas · Días · Espera
  desde; fila seleccionada con fondo `accent` (Iván Castañeda). A la derecha (42 %) el **panel de decisión** de la
  fila seleccionada: nombre, "Vacaciones · 18 al 20 de noviembre de 2026 · 3 días laborables", "Le alcanza · del
  periodo 2026", "Su jefe, Dña Helena Guillen, no respondió en 3 días hábiles"; bloque "Su equipo esos días"
  (decisión 3, **propuesta nueva**): tira de 3 días con "Nadie más de su equipo está fuera"; botones "Aprobar"
  (primario) y "Rechazar" (contorno; abre el motivo).
- Estados: (a) con datos; (b) `variant="sin-resultados"`: búsqueda sin coincidencias, con "Quitar filtros";
  (c) `variant="al-dia"`: "No hay solicitudes por atender." + enlace "Ver las que esperan a su jefe (7)".
- Cambia: decisión en un panel sin navegar; "Ver" ×N desaparece; el motivo de la escalada es un dato de la fila,
  no subtexto gris; pestañas ordenadas por acción; una sola primaria.
- Tweaks: `theme`, `variant` (`datos` | `sin-resultados` | `al-dia`).

**V3 · `people/vacaciones/Calendario.dc.html` — 1440×1300 — antes: `vacaciones-calendario`** (P0)

- Tarea: ver quién falta cada día del mes y detectar empalmes.
- Migas: `Ordena tu operación › Vacaciones › Calendario`. Sidebar `active="vacaciones.calendario"`.
- Encabezado: "Calendario del equipo"; navegación de mes "Noviembre de 2026" con anterior/siguiente y "Hoy";
  filtros de centro y área; conmutador "Con ausencias" (activo) | "Las 212" (decisión 4).
- Renglón superior "Fuera cada día": 30 celdas con el conteo; los días con alguien llevan fondo `accent` y cifra
  500 (17, 18, 19, 20, 25, 26 y 27 de noviembre: 1). Hoy (miércoles 11) marcado con borde `primary`.
- Cuadrícula de 30 columnas que **cabe completa** (columna de persona de 220 px + 30 × `minmax(0,1fr)`), fines de
  semana sombreados en `muted`. Filas reales: Alonso Villanueva, barra jade "Vacaciones" 17–18 nov; Sra. Sara
  Gutiérrez, barra jade 19–20 nov ("Viaje familiar" no se muestra: el motivo nunca va en el calendario); Iván
  Castañeda, barra punteada "Por responder" 18–20 nov. La ausencia del 25 al 27 no tiene nombre en los datos de
  ejemplo: su fila se omite (el conteo del renglón superior sí la muestra).
- Leyenda: Vacaciones (jade sólido) · Permiso (`chart-4` arena sólido) · Por responder (contorno punteado `input`).
- Pie: "3 personas con ausencias en noviembre · Ver a las 212".
- Estados: (a) con datos; (b) `variant="vacio"`: mes sin ausencias: cuadrícula con cabecera y una fila fantasma,
  "Nadie tiene ausencias en diciembre" y "Ir a solicitudes".
- Cambia: de 25 filas vacías a las que importan; la cuadrícula ya no se corta; color por tipo con leyenda; hoy
  marcado; el conteo por día se lee como mapa.
- Tweaks: `theme`, `variant` (`datos` | `vacio`).

**V4 · `people/vacaciones/Saldos.dc.html` — 1440×1400 — antes: `vacaciones-saldos`** (P1)

- Tarea: encontrar a quien necesita algo y resolverlo en lote.
- Migas: `Ordena tu operación › Vacaciones › Saldos`. Sidebar `active="vacaciones.saldos"`.
- Encabezado: "Saldos"; ficha "212 personas · 4,340 días por disfrutar". Primaria: ninguna fija; aparece en la
  barra masiva.
- Pestañas: "Constancia sin acuse 198" (activa en el board) · "Sin fecha de ingreso 3" · "Todos 212" · "Bajas por
  finiquitar 0".
- Tabla (10 filas reales de la captura, mismos nombres y cifras): columnas casilla · Persona (con política debajo)
  · Disponibles (cifra + barra con referencia: días disponibles sobre los otorgados del periodo cuando se conoce;
  si no, solo la cifra) · Próximo periodo · menú de fila ("Ver expediente", "Ajustar días", "Enviar constancia").
  Filas: Aaron Ruíz 19 · 1 mar 2027 (Personal de confianza); Abril Jáquez 25 · 1 may 2027 (Contrato colectivo);
  Adam Candelaria Tercero 23 · 1 sep 2027; Adam Concepción 25 · 1 abr 2027; Adriana Rivera Hijo 25 · 1 jun 2027;
  Adrián Sauceda 15 · 1 jun 2027; Ainara Cortés 25 · 1 jul 2027; Alba Gonzáles 27 · 1 jun 2027; Alberto Vaca 19 ·
  1 dic 2026 (píldora arena "Nuevo periodo en 20 días"); Alicia Ayala 0 · 1 feb 2027 (píldora "Sin días").
- Tres filas seleccionadas y **barra masiva** visible: "3 seleccionadas · Enviar constancia para acuse · Quitar
  selección".
- Paginación: "1–10 de 198 · Página 1 de 20".
- Estados: (a) con selección; (b) `variant="sin-fecha"`: pestaña "Sin fecha de ingreso 3" con el campo de fecha en
  la fila y "Guardar" por fila solo cuando hay valor.
- Cambia: abre en lo que pide acción; "Expediente" ×25 se vuelve fila-enlace + menú; el saldo se ve como barra;
  aparece la barra masiva; 10 filas por página.
- Tweaks: `theme`, `variant` (`acuse` | `sin-fecha`).

**V5 · `people/vacaciones/Expediente.dc.html` — 1440×1400 — antes: `vacaciones-empleado`** (P1)

- Tarea: explicarle a una persona su saldo, movimiento por movimiento.
- Migas: `Ordena tu operación › Vacaciones › Saldos › Sra. Sara Gutiérrez`. Sidebar `active="vacaciones.saldos"`.
- Encabezado: "Sra. Sara Gutiérrez" + píldora "Activa"; ficha: `briefcase` Supervisor · Finanzas · `calendar-days`
  "Ingreso: 1 de noviembre de 2016" · `file-text` "Política: Personal de confianza". Primaria: "Registrar
  ausencia"; menú "Más": "Ajustar días".
- Protagonista (2/3): tarjeta "Periodo 2026" con arco: **92 %** adentro, "23 de 25 días disponibles" debajo; a su
  lado una línea de tiempo horizontal de tres hitos: "Otorgado · 1 nov 2026 · 25 días" → "A disfrutar antes del 1 de
  mayo de 2027" → "Prescribe el 1 de mayo de 2028". Apoyo (1/3): "0 adelantados" y "Constancia 2026 · sin acuse →
  Enviar para acuse".
- Pestañas: "Movimientos 3" (activa) · "Solicitudes 1" · "Constancias 1".
- Movimientos (tabla): 11 nov 2026 · Días tomados · Periodo 2026 · Tramo legal · −2 días; 11 nov 2026 · Periodo
  otorgado · Tramo adicional · +3 días; 11 nov 2026 · Periodo otorgado · Tramo legal · +22 días. Columna final
  "Saldo" con el acumulado (22, 25, 23, de abajo hacia arriba).
- Estados: (a) con datos; (b) `variant="sin-fecha"`: persona sin fecha de ingreso: en vez del arco, vacío con
  "Falta su fecha de ingreso" y el campo para capturarla.
- Cambia: el KPI de texto baja a la ficha; el periodo se lee como arco + línea de tiempo; pestañas con conteo; la
  columna de saldo corrido explica la cuenta.
- Tweaks: `theme`, `variant` (`datos` | `sin-fecha`).

**V6 · `people/vacaciones/Politicas.dc.html` — 1440×1300 — antes: `vacaciones-politicas`** (P1)

- Tarea: comparar políticas y saber a quién le toca cada una.
- Migas: `Ordena tu operación › Vacaciones › Políticas`. Sidebar `active="vacaciones.politicas"`.
- Encabezado: "Políticas"; ficha: "Una política solo puede mejorar la ley". Primaria: "Nueva política".
- Comparador: tabla de 3 columnas activas con la ley como referencia fija a la izquierda: Ley (12 días el primer
  año · prima 25 % · no se puede editar) | Contrato colectivo (12 días · prima 25 % · 1 día fijo cada año ·
  "Sindicalizados") | Personal de confianza (14 días · prima 30 % · 1 día fijo cada año · "Toda la empresa").
  Renglones: Días el primer año, Prima vacacional, Días fijos, A quién aplica. Cada cifra que mejora la ley lleva
  el delta ("+2 días", "+5 pts"). Debajo de cada columna una mini-gráfica de **días por antigüedad** (años 1, 2,
  3, 4, 5, 6–10) como columnas desde cero con la ley como marca de referencia: para la ley usar el mínimo legal
  12, 14, 16, 18, 20, 22; para las políticas, el valor de ley más su mejora del primer año. Menú por columna:
  "Editar", "Archivar".
- "A quién le toca cada una": lista de 3 renglones como frase ("Toda la empresa sigue Personal de confianza",
  "Sindicalizados siguen Contrato colectivo", "Personal de confianza sigue la Ley (sin política)") con "Cambiar" en
  el menú de fila, y la regla "Gana lo más específico: nivel de puesto, luego grupo, luego toda la empresa y, al
  final, la ley." El tercer renglón lleva píldora arena "Revisa: el grupo se llama igual que una política".
- Archivadas: una línea plegada "1 política archivada: Política 2024 (anterior)".
- Estados: (a) con datos; (b) `variant="solo-ley"`: sin políticas propias: columna de la ley + vacío "Tu empresa
  sigue la ley tal cual" + "Crear la primera política".
- Cambia: las tarjetas sueltas se vuelven comparador contra la ley; se ven los tramos; una sola primaria; la
  asignación se lee como frases; la archivada no ocupa tarjeta.
- Tweaks: `theme`, `variant` (`datos` | `solo-ley`).
- Backend: número de personas por política (**propuesta nueva**); sin él, el board muestra solo el grupo.

**V7 · `people/vacaciones/PoliticaEditar.dc.html` — 1440×1500 — antes: `vacaciones-politica-editar`** (P1)

- Tarea: cambiar una política viendo el efecto antes de guardar.
- Migas: `Ordena tu operación › Vacaciones › Políticas › Personal de confianza`. Sidebar
  `active="vacaciones.politicas"`.
- Dos columnas: formulario (60 %) en tres secciones con título ("Lo básico": Nombre, Prima vacacional 30 % con
  ayuda "Mínimo 25 % (LFT art. 80)", Semana laboral "De lunes a viernes"; "Días extra": meses para usarlos, tope
  que pasa al año siguiente, casilla "Permitir adelantar vacaciones"; "Días por antigüedad": tabla compacta de
  rangos Del año · Al año · Días en total · Mínimo legal (12, 16, 20, 22, 24, 26, 28, 30, 32 según la captura) con
  "Agregar rango de años" y "Agregar días fijos"). Vista previa fija (40 %): "Así quedan los días" con columnas por
  antigüedad contra la marca de la ley, y la línea "Los periodos ya otorgados conservan los términos con que se
  otorgaron."
- **Barra de guardado fija** abajo del contenido: "Sin cambios por guardar" / "2 cambios sin guardar" + "Cancelar"
  + "Guardar política" (primario). Dibujar el estado con 2 cambios.
- Estados: (a) editando con cambios; (b) `variant="error"`: un rango por debajo de la ley, con el campo en
  `destructive`, mensaje "Escribe 16 o más: es el mínimo legal para ese rango" y la barra diciendo "Corrige 1
  campo para guardar".
- Cambia: 9 renglones largos pasan a tabla; aparece la vista previa; guardado siempre a la vista; error con cómo
  corregir.
- Tweaks: `theme`, `variant` (`cambios` | `error`).

**V8 · `people/vacaciones/Resumen-movil.dc.html` — 375×1900 — antes: `vacaciones-index-375`**
Lo de V1 en una columna: frase de lectura, KPI protagonista a todo el ancho y los dos de apoyo en una fila de
dos, aviso único, las 4 tarjetas de decisión apiladas (botón "Aprobar" de 44 px a todo el ancho en la primera),
tira de 14 días con desplazamiento horizontal. `MobileTabs active="mas"`. Tweaks: `theme`.

**V9 · `people/vacaciones/Solicitudes-movil.dc.html` — 375×1800 — antes: `vacaciones-solicitudes-375`**
Pestañas desplazables de 44 px, buscador, filtros en una fila, 4 tarjetas; la primera abierta como hoja de
decisión con "Su equipo esos días" y los botones "Rechazar" | "Aprobar" fijos abajo de la tarjeta.
`MobileTabs active="mas"`. Tweaks: `theme`.

**V10 · `people/vacaciones/Calendario-movil.dc.html` — 375×1500 — antes: `vacaciones-calendario-375`**
Selector de mes; **semana** como unidad (tira de 7 días con el conteo de fuera por día, semana del 16 al 22 de
noviembre seleccionada) y debajo la lista de quienes faltan esa semana con sus fechas en texto (Alonso Villanueva
17–18; Iván Castañeda 18–20, "Por responder"; Sra. Sara Gutiérrez 19–20). Sin cuadrícula de 30 columnas.
`MobileTabs active="mas"`. Tweaks: `theme`.

### C.3 Clima

**C1 · `people/clima/Resultados.dc.html` — 1440×1900 — antes: `clima-resultados`** (P0, pantalla principal)

- Tarea: entender cómo está la empresa, dónde actuar primero y pasar a comprometerse.
- Migas: `Escucha a tu gente › Clima › Clima · junio 2026 › Resultados`. Sidebar `active="clima"`.
- Encabezado: "Clima · junio 2026" + píldora "Cerrada"; ficha: `calendar-check` "Cerró el 22 de junio de 2026" ·
  `users` "168 de 212 contestaron · 79 %" · `lock` "Grupos de 5 o más". Primaria: "Escribir compromisos" (hay 3;
  si ya existen, "Ver compromisos"). Menú "Más": "Exportar CSV". Sub-navegación en pestañas: "Resumen" (activa) ·
  "Por pregunta" · "Comentarios 93" · "Por jefe 13" · "Compromisos 3".
- Frase de lectura (h2): "6 de cada 10 respuestas sobre compromiso son favorables. Donde más puedes mover la aguja
  es Reconocimiento."
- Bloque protagonista (2/3 + 1/3): a la izquierda el **Índice de compromiso** con arco: **62 %** adentro,
  "respuestas favorables" debajo, y "Quieren seguir un año más: 63 %". A la derecha el **Índice de recomendación**:
  "−27" con su escala dibujada (regla de −100 a 100 con marca en −27 y el cero señalado), la barra de tres tramos
  "46 % no lo recomendarían (0 a 6) · 35 % indiferentes (7 y 8) · 19 % lo recomendarían (9 y 10)" y la línea "Se
  calcula restando: 19 − 46 = −27." La participación (79 %) vive solo en la ficha: un solo porcentaje protagonista.
- "Dónde actuar primero" fusionado con "Qué mueve el compromiso": **una** lista de temas ordenada por prioridad,
  cada fila con nombre, píldora de prioridad, barra con referencia (marca en 62 %, el promedio de la empresa) y
  cifra. Datos reales: Reconocimiento · Alta · 31 % y 36 % (se reporta por pregunta: dos barras finas con su
  enunciado); Trabajo en equipo · Media · 81 % y 86 %; Voz y trato justo · Media · 64 %; Autonomía · Media · 72 % y
  74 %; Claridad y alineación · Media · 63 %; y, plegados bajo "Otros 5 temas": Confianza en la dirección 60 %,
  Comunicación y cambios 47 %, Crecimiento 48 % y 42 %, Recursos y procesos 62 %, Compensación y prestaciones
  68 %. Nota al pie: "Prioridad es asociación con el compromiso, no causa."
- "Fortalezas y oportunidades": dos columnas de 3 (86, 81, 74 % / 31, 36, 42 % con sus enunciados reales).
- "Por grupo" sube y se vuelve **mapa de grupos × temas** (decisión 1): selector de un solo atributo (Centro de
  trabajo activo; Área, Antigüedad, Jefe), filas = grupos, columnas = los 5 temas prioritarios, celda con la cifra
  y fondo en un solo matiz jade por tramos. Fila "Toda la empresa" con cifras reales (Reconocimiento 34 %, Trabajo
  en equipo 84 %, Voz y trato justo 64 %, Autonomía 73 %, Claridad y alineación 63 %; los dos temas que se
  reportan por pregunta usan el promedio simple de sus dos preguntas). Filas Planta y Sucursal con **cifras
  ilustrativas** (no hay cifras por grupo en los datos de ejemplo: la nota del lienzo lo declara) y dos filas
  "Protegido" con candado en todas sus celdas, idénticas entre sí. Debajo: "Algunos grupos no se muestran para
  proteger el anonimato."
- Estados: (a) con datos; (b) `variant="sin-minimo"`: menos de 5 respuestas en toda la encuesta: sin cifras, vacío
  "Todavía no hay suficientes respuestas para mostrar resultados" + "Se muestran a partir de 5" + botón "Ver
  avance"; (c) `variant="activa"`: encuesta en curso: "Los resultados se abren al cerrar la encuesta" + "Ver
  avance".
- Cambia: un solo número protagonista; "−27" con escala y cómo se calcula; dos listas de temas fundidas y
  ordenadas; cada barra con referencia; "Por grupo" visible como mapa; primaria = siguiente paso del ciclo.
- Tweaks: `theme`, `variant` (`datos` | `sin-minimo` | `activa`).

**C2 · `people/clima/Inicio.dc.html` — 1440×1000 — antes: `clima-index`** (P1)

- Tarea: saber qué está pasando con el clima y qué sigue.
- Migas: `Escucha a tu gente › Clima`. Sidebar `active="clima"`.
- Encabezado: "Clima"; ficha: `lock` "Respuestas anónimas · resultados por grupos de 5 o más".
- Tarjeta protagonista a todo el ancho, "Clima · noviembre 2026" + píldora "En curso": arco **44 %** con "94 de 212
  contestaron"; "Cierra el 24 de noviembre de 2026 · quedan 14 días"; primaria "Ver avance"; contorno "Recordar a
  118".
- Segunda fila, dos tarjetas: "Del clima anterior" (Clima · junio 2026 · 62 % favorable · −27 · "Ver resultados")
  y "Compromisos" (3: 1 por hacer · 1 en marcha · 1 cumplido · "Ver compromisos").
- Historial: tabla con filas-enlace: Clima · junio 2026 · cerró el 22 de junio de 2026 · 168 de 212 · 79 % · 62 %
  favorable.
- Estados: (a) con encuesta activa; (b) `variant="primera-vez"`: sin encuestas: vacío con los 3 pasos ("Revisa las
  preguntas", "Elige a quién invitar", "Lanza y sigue el avance") y un botón "Preparar la primera encuesta";
  (c) `variant="sin-activa"`: solo historial: la tarjeta protagonista es el último resultado y la primaria es
  "Nueva encuesta".
- Cambia: la tarjeta activa ocupa el ancho y lleva el arco correcto; la primaria es primaria; el ciclo completo
  (medir → resultados → compromisos) se ve en una pantalla; historial como filas-enlace.
- Tweaks: `theme`, `variant` (`activa` | `primera-vez` | `sin-activa`).

**C3 · `people/clima/Avance.dc.html` — 1440×1100 — antes: `clima-detalle-activa`** (P0)

- Tarea: subir la participación antes del cierre sin saber quién contestó.
- Migas: `Escucha a tu gente › Clima › Clima · noviembre 2026`. Sidebar `active="clima"`.
- Encabezado: "Clima · noviembre 2026" + píldora "En curso"; ficha: `calendar-clock` "Cierra el 24 de noviembre de
  2026 · quedan 14 días" · `lock` "Aquí ves cuántas personas contestaron, nunca qué ni quién". Primaria: "Recordar a
  118". Menú "Más": "Cerrar encuesta" (con confirmación).
- Protagonista (1/3): arco **44 %**, "94 de 212 contestaron", "Faltan 118". Debajo, marca de referencia en texto:
  "El clima de junio cerró con 79 %."
- Participación por grupo (2/3) con **pestañas** "Centro de trabajo" (activa) · "Área" · "Antigüedad": barras con
  referencia (marca en 44 %, el global): Planta · 31 de 78 · 40 %; Sucursal · 9 de 23 · 39 %; y la línea "Los
  grupos de menos de 5 personas no se muestran." Sin lista de pendientes por persona (no existe ni debe existir).
- Estados: (a) en curso; (b) `variant="recordado"`: confirmación "Recordatorio enviado · llegará a 118 personas" y
  el botón en contorno "Recordar de nuevo mañana" deshabilitado; (c) `variant="cerrada"`: 168 de 212 · 79 % y la
  tarjeta "Los resultados están listos" con primaria "Ver resultados" (datos de `clima-detalle-cerrada`).
- Cambia: porcentaje dentro del arco; "Cerrar encuesta" sale de la vista; pestañas reales; referencia contra el
  global y contra junio; el hueco de 600 px desaparece.
- Tweaks: `theme`, `variant` (`en-curso` | `recordado` | `cerrada`).

**C4 · `people/clima/PorPregunta.dc.html` — 1440×1800 — antes: `clima-resultados-preguntas`** (P1)

- Tarea: profundizar en un tema.
- Migas: `Escucha a tu gente › Clima › Clima · junio 2026 › Por pregunta`. Sidebar `active="clima"`. Pestaña de
  sub-navegación "Por pregunta" activa.
- Dos columnas: índice de temas a la izquierda (lista de 11 con su cifra: Compromiso, Claridad y alineación 63 %,
  Confianza en la dirección 60 %, Comunicación y cambios 47 %, Reconocimiento, Crecimiento, Autonomía, Recursos y
  procesos 62 %, Trabajo en equipo, Voz y trato justo 64 %, Compensación y prestaciones 68 %; "Comunicación y
  cambios" seleccionado) y a la derecha las preguntas del tema seleccionado, ordenadas de menor a mayor: "Recibo de
  otras áreas la información que necesito para hacer mi trabajo." 43 % · 164 respuestas; "Cuando cambia la forma de
  hacer mi trabajo, entiendo qué debo hacer diferente." 45 % · 158; "Sé a quién acudir cuando necesito información
  para mi trabajo." 48 % · 158; "Me entero a tiempo de los cambios que afectan mi trabajo." 52 % · 163. Cada una
  con barra con referencia (marca en 47 %, el tema) y la distribución en tres tramos con **leyenda** fija arriba
  (En desacuerdo · Ni de acuerdo ni en desacuerdo · De acuerdo). Como la distribución por tramo no está en los
  datos de ejemplo, solo se dibuja el tramo favorable con su cifra real y los otros dos sin cifra.
- Selector "Ordenar: de menor a mayor" y conmutador "Todas las preguntas" para la vista larga.
- Estados: (a) tema con datos; (b) `variant="protegida"`: una pregunta con menos de 5 respuestas: fila con candado
  y "Menos de 5 respuestas: no se muestra".
- Cambia: 4078 px pasan a tema por tema; orden por lo que pide atención; leyenda; referencia del tema.
- Tweaks: `theme`, `variant` (`datos` | `protegida`).

**C5 · `people/clima/Comentarios.dc.html` — 1440×1200 — antes: `clima-comentarios`** (P2)

- Migas: `Escucha a tu gente › Clima › Clima · junio 2026 › Comentarios`. Sidebar `active="clima"`.
- Encabezado de sección: "Comentarios sobre la empresa"; ficha: `lock` "Sin quién lo escribió · se muestran con 10
  o más".
- Resumen por temas como dos columnas de barras horizontales desde cero: "Lo que valoran": Buen ambiente entre
  compañeros 31 · Prestaciones y estabilidad 18 · Aprendizaje continuo 9. "Lo que cambiarían": Más comunicación de
  la dirección 27 · Reconocimiento al desempeño 21 · Claridad en el puesto 11. Etiqueta "Resumen hecho con IA al
  cerrar la encuesta. Son temas, no comentarios de nadie." con ícono `info` (sin chispas).
- Pestañas "Lo mejor de trabajar aquí" (activa) · "Lo que cambiarían"; lista de 10 comentarios reales de la
  captura como citas sin autor, en una columna de 720 px; paginación "1–10 de 93 · Página 1 de 10".
- Estados: (a) con datos; (b) `variant="sin-minimo"`: menos de 10 comentarios: "Hay menos de 10 comentarios: no se
  muestran para proteger a quien escribió."
- Cambia: los conteos se vuelven barras comparables; el ícono de chispas se va; ancho de lectura; estado protegido
  con explicación.
- Tweaks: `theme`, `variant` (`datos` | `sin-minimo`).

**C6 · `people/clima/Compromisos.dc.html` — 1440×1400 — antes: `clima-compromisos`** (P1)

- Tarea: convertir lo que dijo la gente en compromisos con responsable y fecha, y darles seguimiento.
- Migas: `Escucha a tu gente › Clima › Clima · junio 2026 › Compromisos`. Sidebar `active="clima"`.
- Encabezado: "Nos dijeron / Haremos" + píldora "Publicado en el portal"; ficha: "3 compromisos · un grupo tiene a
  lo más 3". Primaria: "Nuevo compromiso" (deshabilitada con la razón "Toda la empresa ya tiene 3", porque el
  límite está alcanzado).
- Tablero de tres columnas por estado: "Por hacer 1" · "En marcha 1" · "Cumplido 1" ("Descartado 0" como enlace de
  texto al pie). Tarjetas reales: (Por hacer) Comunicación y cambios · Nos dijeron "Que los cambios se enteran por
  pasillo." · Haremos "Boletín quincenal y junta mensual con la dirección." · Rodrigo Salas · 6 de diciembre de
  2026 · píldora arena "Vence en 25 días"; (En marcha) Reconocimiento · "Que el trabajo bien hecho no se reconoce."
  · "Programa mensual de reconocimiento por área, con anuncio de la dirección." · Ana Dueña · 21 de diciembre de
  2026; (Cumplido) Crecimiento · "Que no se ve cómo crecer dentro de la empresa." · "Mapa de carreras y 2 cursos
  por persona al año." · Ana Dueña · 9 de febrero de 2027. Cada tarjeta: **un** botón con el siguiente estado
  ("Marcar en marcha" / "Marcar cumplido" / ninguno) y menú de fila ("Editar", "Reabrir", "Descartar").
- "Ideas para empezar": plegado, con los temas de prioridad alta primero (Reconocimiento · 2 ideas reales).
- Estados: (a) con 3; (b) `variant="vacio"`: sin compromisos: "Todavía no hay compromisos" + "Empieza por
  Reconocimiento, tu prioridad alta" + "Escribir el primero"; (c) `variant="sin-publicar"`: píldora "Sin publicar"
  y primaria "Publicar en el portal" (como en `pulso-compromisos`).
- Cambia: de lista con 4 acciones de texto a tablero con una acción por tarjeta; el vencimiento se ve; el límite
  de 3 se explica; "Ver resultados" es la sub-navegación, no un botón.
- Tweaks: `theme`, `variant` (`datos` | `vacio` | `sin-publicar`).

**C7 · `people/clima/Jefe.dc.html` — 1440×1500 — antes: `clima-jefe`** (P1)

- Tarea: preparar la conversación con un jefe sobre lo que dijo su grupo.
- Migas: `Escucha a tu gente › Clima › Clima · junio 2026 › Por jefe › Adriana Rivera Hijo`. Sidebar
  `active="clima"`.
- Encabezado: "Adriana Rivera Hijo"; ficha: "Resultado de quienes le reportan directamente" · `lock` "Grupos de 5 o
  más" · "Aquí no hay ranking". Navegación "Jefe anterior / siguiente" por nombre.
- Frase de lectura: "Su grupo la reconoce por escuchar y tratar con respeto. La oportunidad está en cumplir
  acuerdos y destrabar obstáculos."
- Gráfica de mancuerna (una fila por pregunta, ordenadas por diferencia): punto de la empresa (contorno) y punto
  del grupo (jade sólido) sobre una regla de 0 a 100 %, con el delta a la derecha. Datos reales: escucha mis ideas
  85 % · +22 pts; explica los criterios para repartir tareas 83 % · +22 pts; me trata con respeto 85 % · +17 pts;
  me aclara las prioridades 83 % · +16 pts; me dice qué puedo mejorar 69 % · +1 pt; me orienta sobre qué aprender
  62 % · −1 pt; me ayuda a resolver obstáculos 46 % · −14 pts; cumple los acuerdos 50 % · −16 pts. Los enunciados
  completos van como en la captura.
- "Comentarios del grupo": 11 (≥ 10, se muestran), 5 visibles + paginación.
- Estados: (a) con datos; (b) `variant="protegido"`: grupo bajo el mínimo: "Su grupo tuvo menos de 5 respuestas: no
  se muestra para proteger a quien contestó."
- Cambia: 8 bloques iguales pasan a una gráfica ordenada; delta legible; frase de lectura; navegación entre jefes
  sin volver a la tabla de una columna; protegido con explicación.
- Tweaks: `theme`, `variant` (`datos` | `protegido`).

**C8 · `people/clima/Resultados-movil.dc.html` — 375×3200 — antes: `clima-resultados-375`**
Sub-navegación como pestañas desplazables de 44 px; frase de lectura; arco 62 %; recomendación −27 con regla y
barra de tres tramos apilada con leyenda vertical; lista única de temas (5 prioritarios + "Otros 5 temas"
plegado); fortalezas y oportunidades apiladas; mapa por grupo convertido en **lista por grupo** (un grupo abierto
con sus 5 temas, los protegidos como fila con candado). Botón fijo abajo "Escribir compromisos".
`MobileTabs active="mas"`. Tweaks: `theme`.

### C.4 Pulso

**P1 · `people/pulso/Tendencia.dc.html` — 1440×1500 — antes: `pulso-tendencia`** (P0, pantalla principal)

- Tarea: ver si la empresa mejora entre una medición y otra, y en qué.
- Migas: `Escucha a tu gente › Pulso › Tendencia`. Sidebar `active="pulso"`.
- Encabezado: "Tendencia"; ficha: "4 mediciones · jun a sep 2026" · `lock` "Una flecha solo con 30 respuestas o más
  en las dos olas y la misma pregunta". Primaria: ninguna (pantalla de lectura); contorno "Ver el último pulso".
- Frase de lectura: "El compromiso subió 9 puntos desde junio y se mantiene. La recomendación mejora: de −27 a
  −13."
- Tres tarjetas de índice (1 + 2): **Índice de compromiso** protagonista (ocupa 1/2): columnas desde cero 62 %
  (jun, clima) · 69 % (jul) · 72 % (ago) · 71 % (sep), línea base punteada en 62 % rotulada "Clima de junio", delta
  "+9 pts desde junio · −1 pt contra agosto (sin cambio)". **Índice de recomendación** (1/4): columnas que cuelgan
  del cero hacia abajo en eje −50 a 0: −27, −19, −21, −13; delta "+14 pts desde junio". **Mejoras vistas** (1/4):
  60 % (jul) · 73 % (ago) · 71 % (sep); delta "+11 pts desde julio".
- "Por tema": **una tabla** en vez de 10 tarjetas: Tema (con su pregunta debajo) · mini-gráfica (plot SVG de 120×32
  con eje común 0–100 y línea base) · Primera medición · Última · Cambio (flecha + pts). Ordenada por cambio.
  Datos reales: Autonomía 72 → 84 % (+12); Voz y trato justo 61 → 73 % (+12); Reconocimiento 31 → 42 → 41 % (+10);
  Recursos y procesos 65 → 75 % (+10); Trabajo en equipo 86 → 82 → 94 % (+8); Claridad y alineación 62 → 69 % (+7);
  Comunicación y cambios 52 → 59 % (+7); Compensación y prestaciones 69 → 74 % (+5); Confianza en la dirección
  68 → 63 % (−5); Crecimiento 48 → 43 % (−5). Las dos que bajan llevan píldora terracota "Bajó 5 pts".
- Estados: (a) con datos; (b) `variant="vacio"`: una sola medición: "Con el siguiente pulso verás aquí la
  tendencia" + "El siguiente sale el 15 de noviembre de 2026" + "Ver el pulso programado"; (c)
  `variant="sin-flecha"`: una fila con menos de 30 respuestas: sin flecha y "Menos de 30 respuestas: sin
  comparación".
- Cambia: columnas desde cero con línea base; deltas en puntos; recomendación en eje propio; 10 tarjetas → tabla
  ordenada por cambio; 2786 px → 1500.
- Tweaks: `theme`, `variant` (`datos` | `vacio` | `sin-flecha`).
- Nota: el delta "desde la primera medición" es cálculo de `lib/engagement/trend.ts`, no dato nuevo. La banda "sin
  cambio" depende del punto 7 de A.5 (ii).

**P2 · `people/pulso/Inicio.dc.html` — 1440×1100 — antes: `pulso-index`** (P0)

- Tarea: saber cuándo sale el siguiente pulso, cómo va el clima y lanzar uno si hace falta.
- Migas: `Escucha a tu gente › Pulso`. Sidebar `active="pulso"`.
- Encabezado: "Pulso" + píldora "Mensual · día 15"; ficha: `lock` "Respuestas anónimas · resultados por grupos de 5
  o más". Primaria: "Ver tendencia". Contorno: "Crear un pulso ahora".
- Tarjeta protagonista (2/3) "Siguiente pulso": "Sale el 15 de noviembre de 2026 · en 4 días" · "Seguimiento · 9
  preguntas" · línea de qué es Seguimiento ("Pregunta de nuevo por las prioridades que dejó el último clima, para
  ver si mejoran.") · botones "Cambiar" y, en el menú, "Pausar". Lateral (1/3) "Cómo vamos": mini-columnas del
  compromiso 62 · 69 · 72 · 71 % con "+9 pts desde junio" y enlace "Ver tendencia".
- Historial como filas-enlace: Pulso · septiembre 2026 · Recorrido · 22 de septiembre de 2026 · 157 de 212 · 74 %;
  Pulso · agosto 2026 · 24 de agosto de 2026 · 139 de 212 · 66 %; Pulso · julio 2026 · 22 de julio de 2026 · 151 de
  212 · 71 %. Columna de participación con barra con referencia (marca en el promedio 70 %). Solo el modo de
  septiembre está en los datos de ejemplo; en agosto y julio la celda de modo queda vacía.
- Estados: (a) programado + historial; (b) `variant="crear"`: el selector de modo como **paso** en un panel
  lateral ("¿Qué quieres mirar en este pulso?": Seguimiento, Recorrido, Jefe, con sus textos reales) y primaria
  "Crear borrador"; (c) `variant="primera-vez"`: sin pulsos ni programa: vacío "Todavía no hay un pulso" + qué es
  + "Preparar el primero".
- Cambia: el vacío falso desaparece; lo primero es el siguiente pulso y la tendencia; crear es un paso, no la
  pantalla; historial como filas-enlace con el modo.
- Tweaks: `theme`, `variant` (`programado` | `crear` | `primera-vez`).

**P3 · `people/pulso/Resultados.dc.html` — 1440×1600 — antes: `pulso-resultados`** (P1)

- Tarea: leer un pulso contra el anterior.
- Migas: `Escucha a tu gente › Pulso › Pulso · septiembre 2026 › Resultados`. Sidebar `active="pulso"`.
- Encabezado: "Pulso · septiembre 2026" + píldoras "Cerrada" y "Recorrido"; ficha: "Cerró el 22 de septiembre de
  2026" · "157 de 212 contestaron · 74 %" · `lock` "Grupos de 5 o más". Primaria: "Escribir compromisos" (hay 2 sin
  publicar: "Ver compromisos"). Sub-navegación igual a Clima.
- Frase de lectura: "El compromiso se mantiene en 71 % (−1 pt contra agosto). La recomendación mejoró a −13."
- Tres índices 1 + 2: arco **71 %** con "−1 pt frente a Pulso · agosto 2026 · sin cambio"; "−13" con regla y barra
  36 % · 40 % · 24 % (aquí **no** se escribe la resta: 24 − 36 da −12 por redondeo y confundiría); "Mejoras vistas
  71 %": "Ha visto mejoras que la empresa presentó tras la
  encuesta anterior · −2 pts".
- Temas de este pulso (lista única con referencia = ola anterior cuando existe): Voz y trato justo · Alta ·
  "Protegido" (candado); Recursos y procesos · Media · "Protegido"; Trabajo en equipo · Media · 94 %; Autonomía ·
  84 %; Compensación y prestaciones · "Protegido". Texto único bajo la lista: "Menos de 5 respuestas en un tema: no
  se muestra." "Quieren seguir un año más" con el mismo trato.
- Fortalezas (94 %, 84 %) y oportunidades (73 %, 74 %) con sus enunciados reales.
- Estados: (a) con datos; (b) `variant="sin-minimo"`: como C1.
- Cambia: comparación contra la ola anterior al frente; "Sin respuestas suficientes" ×3 se vuelve un solo patrón
  "Protegido" con una explicación; frase con mayúscula; pantalla corta.
- Tweaks: `theme`, `variant` (`datos` | `sin-minimo`).

**P4 · `people/pulso/Tendencia-movil.dc.html` — 375×2600 — antes: `pulso-tendencia-375`**
Frase de lectura; las tres tarjetas de índice apiladas con columnas desde cero; "Por tema" como lista de 10 filas
de dos renglones (tema + cambio a la derecha; mini-gráfica de 96×28 debajo). De 5206 px a 2600.
`MobileTabs active="mas"`. Tweaks: `theme`.

### C.5 Lo que queda fuera del catálogo y por qué

- Vacaciones · Configuración e Importar: uso esporádico; se implementan con el kit (pestañas, tabla con menú,
  píldoras "De la ley" neutra y "De la empresa" jade; asistente de 3 pasos centrado a 720 px con el botón
  deshabilitado hasta que haya archivo). Sin board.
- Vacaciones · Nueva política: mismo board que V7.
- Vacaciones · Constancia: documento imprimible; solo correcciones de copy.
- Clima · lista de jefes: se sustituye por la sub-navegación "Por jefe 13" + navegación anterior/siguiente de C7;
  la lista necesaria se implementa como tabla estándar (nombre, enlace de fila) con la nota "N jefes no aparecen
  para proteger el anonimato" **sin** N si N permite deducir algo (decisión de implementación: no mostrar N).
- Pulso · Jefes y Compromisos: mismos componentes que C6 y C7.
- Portales de la persona y del jefe: capturados pero sin revisar; fuera de esta ronda.

---

## D. Kit compartido (interfaces fijas)

Nada de lo que sigue se cambia por board. Si un agente necesita algo que no está aquí, lo resuelve dentro de su
propio archivo con los mismos tokens y lo reporta al cerrar; no edita el kit.

### D.1 Tokens: bloque CSS completo para `<helmet><style>`

Se pega **idéntico** en todos los boards y en los hijos del kit. Los hex son los de `tokens.json`. Los tres
marcados "repo" no están en `tokens.json`: vienen de `resources/css/app.css` (`--track`, `--warning`,
`--warning-foreground`) y `warning` coincide con `risk-medio-bg/fg`.

```css
.t-light{
  --background:#FBFDFC;--foreground:#14211F;--card:#FFFFFF;--card-foreground:#14211F;
  --popover:#FFFFFF;--popover-foreground:#14211F;--primary:#0B7A6F;--primary-foreground:#FFFFFF;
  --secondary:#EDF3F1;--secondary-foreground:#14211F;--muted:#F1F5F4;--muted-foreground:#56635F;
  --accent:#E5F1EE;--accent-foreground:#14211F;--destructive:#B0463C;--on-destructive:#FFFFFF;
  --border:#DCE5E2;--input:#7E8C88;--ring:#0B7A6F;
  --chart-1:#0B7A6F;--chart-2:#3F9A8F;--chart-3:#1D5C55;--chart-4:#A08A70;--chart-5:#5C5248;
  --sidebar:#F5F8F7;--sidebar-foreground:#14211F;--sidebar-primary:#0B7A6F;--sidebar-primary-foreground:#FFFFFF;
  --sidebar-accent:#E5F1EE;--sidebar-accent-foreground:#14211F;--sidebar-border:#DCE5E2;--sidebar-ring:#0B7A6F;
  --brand-soft:#7FD3C6;--brand-deep:#064A43;
  --risk-nulo:#5A9C91;--risk-nulo-bg:#E9F4F1;--risk-nulo-fg:#0A5A52;
  --risk-bajo:#2B7F74;--risk-bajo-bg:#DCEFEA;--risk-bajo-fg:#0A6A60;
  --risk-medio:#A47F2C;--risk-medio-bg:#F6EEDC;--risk-medio-fg:#7A5714;
  --risk-alto:#BC6A48;--risk-alto-bg:#F8E7DF;--risk-alto-fg:#9A4527;
  --risk-muy-alto:#9A3B26;--risk-muy-alto-bg:#F3D8CF;--risk-muy-alto-fg:#8A2E1C;
  --track:#E3EAE7;--warning:#F6EEDC;--warning-foreground:#7A5714; /* repo */
  --shadow-float:0 8px 24px rgba(20,33,31,.08);
}
.t-dark{
  --background:#0F1716;--foreground:#F1FAF8;--card:#141E1C;--card-foreground:#F1FAF8;
  --popover:#18241F;--popover-foreground:#F1FAF8;--primary:#3CCFBD;--primary-foreground:#0F1716;
  --secondary:#1E2B28;--secondary-foreground:#F1FAF8;--muted:#1A2523;--muted-foreground:#9DAEAA;
  --accent:#213330;--accent-foreground:#F1FAF8;--destructive:#EC8F84;--on-destructive:#0F1716;
  --border:#26332F;--input:#62756F;--ring:#3CCFBD;
  --chart-1:#3CCFBD;--chart-2:#2A9C8E;--chart-3:#9EDDD2;--chart-4:#C2AE94;--chart-5:#8A7E70;
  --sidebar:#121B1A;--sidebar-foreground:#F1FAF8;--sidebar-primary:#3CCFBD;--sidebar-primary-foreground:#0F1716;
  --sidebar-accent:#213330;--sidebar-accent-foreground:#F1FAF8;--sidebar-border:#26332F;--sidebar-ring:#3CCFBD;
  --brand-soft:#1E8C7F;--brand-deep:#B8F5EC;
  --risk-nulo:#6FB5A9;--risk-nulo-bg:#16302B;--risk-nulo-fg:#A6E6DA;
  --risk-bajo:#3CBFAE;--risk-bajo-bg:#123A34;--risk-bajo-fg:#6FDCCB;
  --risk-medio:#D2AE62;--risk-medio-bg:#33291A;--risk-medio-fg:#E9C886;
  --risk-alto:#E08A66;--risk-alto-bg:#3B241C;--risk-alto-fg:#F2A686;
  --risk-muy-alto:#F07A62;--risk-muy-alto-bg:#4A1F18;--risk-muy-alto-fg:#F7A08C;
  --track:#24312E;--warning:#33291A;--warning-foreground:#E9C886; /* repo */
  --shadow-float:0 8px 24px rgba(0,0,0,.32);
}
.hm{font-family:"Figtree",ui-sans-serif,system-ui,sans-serif;font-size:16px;line-height:24px;font-weight:400;
  color:var(--foreground);background:var(--background);font-variant-numeric:tabular-nums;
  -webkit-font-smoothing:antialiased;box-sizing:border-box}
.hm *,.hm *::before,.hm *::after{box-sizing:border-box}
.hm h1,.hm h2,.hm h3,.hm h4,.hm p,.hm ul,.hm ol{margin:0;padding:0}
.hm ul,.hm ol{list-style:none}
.hm a{color:inherit;text-decoration:none}
.hm button{font:inherit;color:inherit;background:none;border:0;padding:0;cursor:pointer}
.hm :focus-visible{outline:2px solid var(--ring);outline-offset:2px}
```

En `<helmet>`, antes del `<style>`:

```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600&display=swap">
```

Tema: la raíz de cada board es `<div class="{{themeClass}}" …>`. En `renderVals()`:

```js
const theme = this.props.theme ?? 'claro';
return { theme, themeClass: theme === 'oscuro' ? 'hm t-dark' : 'hm t-light' /* , …variantes */ };
```

`data-props` mínimo de todo board (los que tienen variantes agregan `variant`):

```html
<script type="text/x-dc" data-dc-script data-props='{"theme":{"editor":"enum","options":["claro","oscuro"],"default":"claro","section":"Tema"},"$preview":{"width":1440,"height":1300}}'>
```

`variant` es siempre `{"editor":"enum","options":[…los del catálogo…],"default":"<el primero>","section":"Estado"}`
y cada estado va en su `<sc-if value="{{ esDatos }}" hint-placeholder-val="{{ true }}">` con una bandera booleana
calculada en `renderVals()` (los huecos no aceptan expresiones). Ningún color depende de un hueco: todos salen de
`var(--token)`.

### D.2 Tipografía, espacio y radios

```css
.hm-display{font-size:36px;line-height:44px;font-weight:600;letter-spacing:-.01em}
.hm-h1{font-size:30px;line-height:38px;font-weight:600;letter-spacing:-.01em}
.hm-h2{font-size:24px;line-height:32px;font-weight:600}
.hm-h3{font-size:20px;line-height:28px;font-weight:600}
.hm-h4{font-size:18px;line-height:26px;font-weight:500}
.hm-body{font-size:16px;line-height:24px}
.hm-strong{font-size:16px;line-height:24px;font-weight:500}
.hm-small{font-size:14px;line-height:20px}
.hm-caption{font-size:13px;line-height:18px;font-weight:500;letter-spacing:.02em;text-transform:uppercase}
.hm-kpi{font-size:32px;line-height:40px;font-weight:600;letter-spacing:-.01em}
.hm-kpi-2{font-size:24px;line-height:32px;font-weight:600;letter-spacing:-.01em}
.hm-muted{color:var(--muted-foreground)}
```

- Mínimo de texto: 13 px (solo `caption`). Nada de 700, cursivas ni otra familia.
- Espacio (px): 4, 8, 12, 16, 20, 24, 32, 48, 64. Dentro de un grupo 8; padding de tarjeta 24; entre tarjetas 24;
  entre bloques 32; entre secciones 48. Siempre con `gap`, nunca con márgenes por elemento.
- Radios: 6 (píldoras, casillas), 8 (botones, inputs), 10 (tarjetas), 14 (diálogos, panel de contenido),
  9999 (avatares, barras).
- Sombra: ninguna, salvo `var(--shadow-float)` en menús abiertos.

### D.3 Hijos compartidos (los escribe el paquete KIT)

Fuente de verdad en `people/`. Como `<dc-import name="X">` monta un archivo **hermano** y no está verificado que
acepte rutas, el paquete KIT **copia** los cuatro hijos, idénticos, a cada carpeta de herramienta. Los boards
importan siempre por nombre de hermano: `name="Sidebar"`, `name="Topbar"`, `name="MobileTop"`, `name="MobileTabs"`.
Nadie más edita esos archivos ni sus copias.

| Archivo fuente | Copias | Tamaño raíz | Props |
|---|---|---|---|
| `people/Sidebar.dc.html` | `people/{organigrama,vacaciones,clima,pulso}/Sidebar.dc.html` | 264 px de ancho, `align-self:stretch`, `min-height:900px` | `active` (texto), `theme` (`claro`\|`oscuro`) |
| `people/Topbar.dc.html` | ídem `/Topbar.dc.html` | 100 % × 64 px | `crumbs` (texto), `theme` |
| `people/MobileTop.dc.html` | ídem `/MobileTop.dc.html` | 375 × 64 px | `heading` (texto del título; no se usa `title` ni `name`), `theme` |
| `people/MobileTabs.dc.html` | ídem `/MobileTabs.dc.html` | 375 × 72 px | `active` (`panel`\|`normas`\|`quejas`\|`mas`), `theme` |

Cada hijo incluye el bloque de tokens de D.1 y pone su propia clase de tema en su raíz (no depende de heredar
variables del padre).

**Sidebar** (según la captura nueva `before/orgchart-index-claro.png`): fondo `--sidebar`, sin borde propio.
De arriba abajo: logo horizontal (`public/brand/humetria-horizontal-color.svg` en claro,
`humetria-horizontal-color-oscuro.svg` en oscuro, incrustado como SVG, 28 px de alto: único lugar con
`brand-soft`/`brand-deep`); selector de empresa (tarjeta `card` con borde, avatar "DN", "Demo NOM-035"); sin
encabezado: Panel (`house`), Centros de trabajo (`factory`), Empleados (`users`), Usuarios (`user-cog`);
**CUMPLE LA NORMA**: NOM-035 (`clipboard-check`, insignia "2", flecha), NOM-037 (`laptop`, flecha), Ley Silla
(`armchair`), Asistencia (`clock`, flecha); **ESCUCHA A TU GENTE**: Quejas (`message-square-warning`, flecha),
Clima (`gauge`), Pulso (`activity`); **ORDENA TU OPERACIÓN**: Vacaciones (`calendar-days`, flecha), Organigrama
(`network`, flecha), Compensación (`calculator`); tarjeta "Siguiente obligación · La próxima evaluación cierra **en
9 días**" con barra al 40 %; pie con avatar "AD", "Ana Dueña", "owner@demo-nom035.test" y `settings`. Cada
elemento mide 44 px de alto; ícono 20 px en `--muted-foreground`; texto `strong`. Encabezados de familia en
`caption` `--muted-foreground`.

Claves de `active` (exactas) y qué pintan:

| `active` | Elemento activo | Subpáginas abiertas |
|---|---|---|
| `organigrama.organigrama` | Organigrama › Organigrama | Organigrama, Calidad de datos |
| `organigrama.calidad` | Organigrama › Calidad de datos | ídem |
| `vacaciones.resumen` | Vacaciones › Resumen | Resumen, Solicitudes, Calendario, Saldos, Políticas, Configuración, Importar |
| `vacaciones.solicitudes` | Vacaciones › Solicitudes | ídem |
| `vacaciones.calendario` | Vacaciones › Calendario | ídem |
| `vacaciones.saldos` | Vacaciones › Saldos | ídem |
| `vacaciones.politicas` | Vacaciones › Políticas | ídem |
| `vacaciones.configuracion` | Vacaciones › Configuración | ídem |
| `vacaciones.importar` | Vacaciones › Importar | ídem |
| `clima` | Clima | ninguna (no tiene) |
| `pulso` | Pulso | ninguna (no tiene) |

Elemento activo sin subpáginas (Clima, Pulso): fondo `--card`, borde `--border`, radio 10, ícono en
`--sidebar-primary`. Herramienta con subpáginas abierta: título en `strong` 600 con ícono `--sidebar-primary` y
`chevron-down`; subpáginas con sangría de 44 px, línea guía de 1 px `--sidebar-border`; la activa con fondo
`--sidebar-accent`, radio 8, texto 500 y una marca vertical de 2 px `--sidebar-primary` a la izquierda. El hijo
calcula en `renderVals()` una bandera por clave (`esVacResumen`, …) y usa `<sc-if>`; la lista se escribe como
marcado literal, no con `<sc-for>`.

**Topbar**: fila de 64 px, `padding:0 24px 0 32px`, sin fondo propio. Izquierda: botón de ícono `chevrons-left`
(44×44, `aria-label="Mostrar u ocultar la barra lateral"`), luego las migas: `crumbs` se parte por `" › "` en
`renderVals()`; todos los segmentos en `--muted-foreground` 16/24 salvo el último en `--foreground` 500;
separador `chevron-right` de 16 px. Derecha: campo "Buscar o ir a…" de 240×40 con borde `--input`, `search` e
insignia "⌘K"; conmutador de tema de tres botones de ícono (`sun`, `moon`, `monitor`) con el activo en `--card`.
Formato exacto de `crumbs`: segmentos separados por espacio + `›` + espacio, p. ej.
`crumbs="Ordena tu operación › Vacaciones › Solicitudes"`. Siempre empieza con la familia.

**MobileTop**: 64 px; botón `menu` (44×44), título `h4` con `heading`, botones de ícono `search` y `monitor` con
borde `--input`. **MobileTabs**: 72 px, borde superior `--border`, cuatro enlaces de igual ancho con ícono 24 px y
etiqueta 13 px: Panel (`house`), Normas (`clipboard-check`), Quejas (`message-square-warning`), Más (`menu`); el
activo en `--primary` con 500.

**Esqueleto de un board de escritorio** (copiar tal cual; cambia `active`, `crumbs` y el contenido):

```html
<div class="{{themeClass}}" style="display:flex;flex-wrap:wrap;min-height:100vh;background:var(--sidebar)">
  <dc-import name="Sidebar" active="vacaciones.resumen" theme="{{theme}}" hint-size="264px,900px"></dc-import>
  <main style="flex:999 1 560px;min-width:0;display:flex;flex-direction:column;margin:8px 8px 8px 0;background:var(--background);border:1px solid var(--border);border-radius:14px">
    <dc-import name="Topbar" crumbs="Ordena tu operación › Vacaciones › Resumen" theme="{{theme}}" hint-size="100%,64px"></dc-import>
    <div class="hm-page"><!-- contenido --></div>
  </main>
</div>
```

```css
.hm-page{display:flex;flex-direction:column;gap:32px;padding:24px 40px 64px;width:100%;max-width:1176px}
.hm-split{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}
.hm-split>.hm-main{flex:2 1 520px;min-width:0;display:flex;flex-direction:column;gap:24px}
.hm-split>.hm-side{flex:1 1 300px;min-width:0;display:flex;flex-direction:column;gap:24px}
```

**Esqueleto de un board móvil** (frame fijo):

```html
<div class="{{themeClass}}" style="width:375px;min-height:812px;display:flex;flex-direction:column">
  <dc-import name="MobileTop" heading="Solicitudes" theme="{{theme}}" hint-size="375px,64px"></dc-import>
  <div style="flex:1;display:flex;flex-direction:column;gap:24px;padding:8px 16px 32px"><!-- contenido --></div>
  <dc-import name="MobileTabs" active="mas" theme="{{theme}}" hint-size="375px,72px"></dc-import>
</div>
```

Sin barra de estado del sistema ni teclado dibujados.

### D.4 Snippets exactos

CSS de componentes (se pega después del bloque de D.1 y D.2):

```css
/* íconos */
.hm-i{width:20px;height:20px;flex:none;fill:none;stroke:currentColor;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
.hm-i-16{width:16px;height:16px}.hm-i-24{width:24px;height:24px}
/* botones: 44 px de alto */
.hm-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 16px;border-radius:8px;font-size:16px;line-height:24px;font-weight:500;white-space:nowrap;transition:background-color 150ms,color 150ms}
.hm .hm-btn-primary{background:var(--primary);color:var(--primary-foreground)}
.hm .hm-btn-outline{background:var(--card);color:var(--foreground);border:1px solid var(--input)}
.hm .hm-btn-ghost{color:var(--primary)}
.hm .hm-btn[disabled]{background:var(--muted);color:var(--muted-foreground);border-color:var(--border);cursor:not-allowed}
.hm .hm-iconbtn{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:8px;color:var(--muted-foreground)}
/* tarjeta */
.hm-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:24px;display:flex;flex-direction:column;gap:16px;min-width:0}
/* encabezado de página */
.hm-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:16px}
.hm-head-text{display:flex;flex-direction:column;gap:8px;min-width:0;flex:1 1 480px}
.hm-head-title{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.hm-head-actions{display:flex;flex-wrap:wrap;gap:8px}
.hm-facts{display:flex;flex-wrap:wrap;gap:8px 20px;color:var(--muted-foreground);font-size:14px;line-height:20px}
.hm-fact{display:inline-flex;align-items:center;gap:6px}
.hm-lead{font-size:20px;line-height:28px;font-weight:600;max-width:760px;text-wrap:pretty}
/* píldora de estado */
.hm-pill{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 10px;border-radius:9999px;font-size:13px;line-height:18px;font-weight:500;white-space:nowrap;background:var(--secondary);color:var(--secondary-foreground)}
.hm-pill::before{content:"";width:6px;height:6px;border-radius:9999px;background:currentColor}
.hm-pill--activo{background:var(--accent);color:var(--primary)}
.hm-pill--atencion{background:var(--warning);color:var(--warning-foreground)}
.hm-pill--vencido{background:var(--risk-alto-bg);color:var(--risk-alto-fg)}
.hm-pill--plain::before{display:none}
/* KPI */
.hm-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(240px,100%),1fr));gap:24px}
.hm-kpi-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:24px;display:flex;flex-direction:column;gap:8px;min-width:0}
.hm-kpi-card--lead{border-color:var(--primary);grid-column:span 1}
.hm-kpi-label{display:flex;align-items:center;gap:8px;color:var(--muted-foreground)}
/* pestañas con conteo */
.hm-tabs{display:flex;gap:4px;padding:4px;background:var(--muted);border:1px solid var(--border);border-radius:10px;overflow-x:auto}
.hm .hm-tab{display:inline-flex;align-items:center;gap:8px;min-height:44px;padding:0 14px;border-radius:8px;font-size:14px;line-height:20px;font-weight:500;color:var(--muted-foreground);white-space:nowrap}
.hm .hm-tab[aria-selected="true"]{background:var(--card);color:var(--foreground);box-shadow:inset 0 0 0 1px var(--border)}
.hm-count{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:20px;padding:0 6px;border-radius:9999px;font-size:13px;line-height:18px;font-weight:500;background:var(--secondary);color:var(--secondary-foreground)}
.hm-tab[aria-selected="true"] .hm-count{background:var(--accent);color:var(--primary)}
.hm-tab--cero{opacity:1;color:var(--muted-foreground)}
/* tabla */
.hm-tablewrap{background:var(--card);border:1px solid var(--border);border-radius:10px;overflow-x:auto}
.hm-table{width:100%;border-collapse:collapse;font-size:16px;line-height:24px}
.hm-table th{background:var(--muted);color:var(--muted-foreground);font-size:13px;line-height:18px;font-weight:500;letter-spacing:.02em;text-transform:uppercase;text-align:left;padding:12px 16px;white-space:nowrap}
.hm-table td{padding:12px 16px;border-top:1px solid var(--border);vertical-align:middle;height:64px}
.hm-table .hm-num{text-align:right}
.hm-table tr[aria-selected="true"] td{background:var(--accent)}
.hm-person{display:flex;align-items:center;gap:12px;min-width:0}
.hm-avatar{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;flex:none;border-radius:9999px;background:var(--secondary);color:var(--secondary-foreground);font-size:13px;line-height:18px;font-weight:600}
/* medidor de arco: la "h" del logo */
.hm-arc{--p:0;position:relative;width:196px;height:141px;flex:none;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:8px}
.hm-arc::before{content:"";position:absolute;inset:0;border-radius:98px 98px 0 0;padding:16px 16px 0;
  background:conic-gradient(from 270deg at 50% 100%,var(--primary) calc(var(--p)*1.8deg),var(--track) 0 180deg,transparent 0);
  -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;
  mask:linear-gradient(#000 0 0) content-box exclude,linear-gradient(#000 0 0)}
.hm-arc-value{position:relative;font-size:32px;line-height:40px;font-weight:600;letter-spacing:-.01em}
.hm-arc-label{position:relative;font-size:14px;line-height:20px;color:var(--muted-foreground);text-align:center;max-width:150px}
/* barra con referencia */
.hm-bar{--p:0;--ref:0;position:relative;height:8px;border-radius:9999px;background:var(--track)}
.hm-bar::before{content:"";position:absolute;inset:0 auto 0 0;width:calc(var(--p)*1%);border-radius:9999px;background:var(--primary)}
.hm-bar::after{content:"";position:absolute;top:-5px;bottom:-5px;left:calc(var(--ref)*1%);width:2px;border-radius:1px;background:var(--foreground)}
.hm-bar--sinref::after{display:none}
.hm-bar--bajo::before{background:var(--risk-alto)}
.hm-barrow{display:flex;flex-direction:column;gap:8px}
.hm-barrow-top{display:flex;align-items:baseline;justify-content:space-between;gap:16px}
/* columnas desde cero (tendencia) */
.hm-cols{position:relative;display:flex;align-items:flex-end;gap:16px;height:160px;border-bottom:1px solid var(--input)}
.hm-col{--v:0;flex:1 1 0;min-width:0;height:calc(var(--v)*1%);background:var(--primary);border-radius:6px 6px 0 0}
.hm-col--base{background:var(--chart-4)}
.hm-cols-ref{--ref:0;position:absolute;left:0;right:0;bottom:calc(var(--ref)*1%);border-top:2px dashed var(--foreground)}
.hm-cols-labels{display:flex;gap:16px}
.hm-cols-labels>span{flex:1 1 0;text-align:center;font-size:14px;line-height:20px}
/* celda del mapa por grupo */
.hm-heat{display:grid;gap:4px}
.hm-cell{display:flex;align-items:center;justify-content:center;min-height:44px;border-radius:6px;font-weight:500;background:var(--muted);color:var(--foreground)}
.hm-cell--2{background:var(--accent)}
.hm-cell--3{background:var(--chart-3);color:var(--background)}
.hm-cell--protegida{background:transparent;border:1px dashed var(--input);color:var(--muted-foreground);gap:6px;font-size:14px}
/* aviso único */
.hm-notice{display:flex;flex-direction:column;gap:8px;padding:16px;border-radius:10px;background:var(--warning);color:var(--warning-foreground)}
.hm-notice-row{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px}
.hm-notice a{font-weight:500;text-decoration:underline;text-underline-offset:3px}
/* estado vacío */
.hm-empty{display:flex;flex-direction:column;align-items:center;gap:12px;padding:48px 24px;border:1px dashed var(--input);border-radius:10px;text-align:center}
.hm-empty-icon{display:flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:10px;background:var(--accent);color:var(--primary)}
.hm-empty p{max-width:480px;color:var(--muted-foreground)}
/* barra de acciones masivas */
.hm-bulk{display:flex;flex-wrap:wrap;align-items:center;gap:12px;padding:12px 16px;border-top:1px solid var(--border);background:var(--accent)}
/* menú de fila abierto (solo se dibuja abierto en una fila por board) */
.hm-menu{display:flex;flex-direction:column;min-width:200px;padding:4px;border-radius:8px;background:var(--popover);color:var(--popover-foreground);border:1px solid var(--border);box-shadow:var(--shadow-float)}
.hm .hm-menu-item{display:flex;align-items:center;gap:8px;min-height:44px;padding:0 12px;border-radius:6px;text-align:left}
```

Contraste de los pares no triviales (de `tokens.json`): `primary` sobre `accent` 4.51 (claro) / 6.86 (oscuro);
`warning-foreground` sobre `warning` 5.7 / 8.9; `risk-alto-fg` sobre `risk-alto-bg` 5.4 / 7.3; `background` sobre
`chart-3` 7.6 / 11. El mapa por grupo usa **tres tramos** y siempre con la cifra escrita: `.hm-cell` (hasta 49 %),
`.hm-cell--2` (50 a 69 %) y `.hm-cell--3` (70 % o más). `chart-2` no se usa como fondo de texto (3.4:1 en claro).

El arco (`.hm-arc`) usa máscara con `content-box`; el paquete KIT lo comprueba en su prueba de humo en claro y
oscuro. Si la máscara no pinta en el visor del lienzo, el respaldo acordado es un semicírculo:
`border-radius:98px 98px 0 0` con el mismo `conic-gradient` y un `::after` del color de la tarjeta como hueco; lo
avisa a todos antes de que nadie lo cambie por su cuenta.

Marcado:

```html
<!-- Encabezado de página: título + estado + ficha con íconos + una primaria -->
<header class="hm-head">
  <div class="hm-head-text">
    <div class="hm-head-title">
      <h1 class="hm-h1">Clima · junio 2026</h1>
      <span class="hm-pill">Cerrada</span>
    </div>
    <div class="hm-facts">
      <span class="hm-fact"><svg class="hm-i hm-i-16" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>Cerró el 22 de junio de 2026</span>
      <span class="hm-fact"><svg class="hm-i hm-i-16" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>168 de 212 contestaron · 79 %</span>
      <span class="hm-fact" style="color:var(--primary)"><svg class="hm-i hm-i-16" viewBox="0 0 24 24" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Grupos de 5 o más</span>
    </div>
  </div>
  <div class="hm-head-actions">
    <button type="button" class="hm-iconbtn" aria-label="Más acciones"><svg class="hm-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button>
    <a class="hm-btn hm-btn-primary" href="Compromisos.dc.html">Escribir compromisos</a>
  </div>
</header>
<p class="hm-lead">6 de cada 10 respuestas sobre compromiso son favorables. Donde más puedes mover la aguja es Reconocimiento.</p>

<!-- KPI: 1 protagonista + 2 de apoyo -->
<div class="hm-kpis">
  <div class="hm-kpi-card hm-kpi-card--lead">
    <span class="hm-kpi-label hm-small"><svg class="hm-i hm-i-16" viewBox="0 0 24 24" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>Esperan tu respuesta</span>
    <span class="hm-kpi">4</span>
    <span class="hm-small hm-muted">7 más esperan a su jefe</span>
  </div>
  <div class="hm-kpi-card">
    <span class="hm-kpi-label hm-small">Ventanas por cerrar</span>
    <span class="hm-kpi-2">133</span>
    <span class="hm-small hm-muted">Periodos cuyo plazo de disfrute está por vencer o venció</span>
  </div>
  <div class="hm-kpi-card">
    <span class="hm-kpi-label hm-small">Días por disfrutar</span>
    <span class="hm-kpi-2">4,340</span>
    <span class="hm-small hm-muted">198 periodos · ninguno por prescribir</span>
  </div>
</div>

<!-- Pestañas con conteo -->
<div class="hm-tabs" role="tablist" aria-label="Solicitudes por estado">
  <button type="button" class="hm-tab" role="tab" aria-selected="true">Por atender <span class="hm-count">4</span></button>
  <button type="button" class="hm-tab" role="tab" aria-selected="false">En espera del jefe <span class="hm-count">7</span></button>
  <button type="button" class="hm-tab hm-tab--cero" role="tab" aria-selected="false">Bajas por finiquitar <span class="hm-count">0</span></button>
</div>

<!-- Tabla con cabecera muted, fila-enlace y menú de fila -->
<div class="hm-tablewrap">
  <table class="hm-table">
    <caption style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)">Solicitudes por atender, la más próxima arriba</caption>
    <thead><tr><th scope="col">Persona</th><th scope="col">Fechas</th><th scope="col" class="hm-num">Días</th><th scope="col">Espera desde</th><th scope="col"><span style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)">Acciones</span></th></tr></thead>
    <tbody>
      <tr aria-selected="true">
        <td><a class="hm-person" href="#decision"><span class="hm-avatar" aria-hidden="true">IC</span><span><span class="hm-strong" style="display:block">Iván Castañeda</span><span class="hm-small hm-muted">Vacaciones · Jefe: Dña Helena Guillen</span></span></a></td>
        <td>18 al 20 de noviembre</td>
        <td class="hm-num">3</td>
        <td><span class="hm-pill hm-pill--atencion">Su jefe no respondió</span></td>
        <td class="hm-num"><button type="button" class="hm-iconbtn" aria-label="Más acciones de Iván Castañeda"><svg class="hm-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg></button></td>
      </tr>
    </tbody>
  </table>
  <div class="hm-bulk"><span class="hm-strong">3 seleccionadas</span><button type="button" class="hm-btn hm-btn-primary">Enviar constancia para acuse</button><button type="button" class="hm-btn hm-btn-ghost">Quitar selección</button></div>
</div>

<!-- Medidor de arco: --p es el porcentaje (0 a 100) -->
<div class="hm-arc" style="--p:44" role="img" aria-label="44 % de participación: 94 de 212 contestaron">
  <span class="hm-arc-value">44 %</span>
  <span class="hm-arc-label">94 de 212 contestaron</span>
</div>

<!-- Barra con marca de referencia: --p valor, --ref referencia -->
<div class="hm-barrow">
  <div class="hm-barrow-top"><span class="hm-strong">Planta</span><span><span class="hm-strong">40 %</span> <span class="hm-small hm-muted">31 de 78</span></span></div>
  <div class="hm-bar" style="--p:40;--ref:44" role="img" aria-label="Planta: 40 %, 4 puntos por debajo del total de 44 %"></div>
</div>

<!-- Columnas desde cero con línea base -->
<div class="hm-cols-labels"><span class="hm-strong">62 %</span><span class="hm-strong">69 %</span><span class="hm-strong">72 %</span><span class="hm-strong">71 %</span></div>
<div class="hm-cols" role="img" aria-label="Índice de compromiso: 62 % en junio, 69 % en julio, 72 % en agosto y 71 % en septiembre de 2026">
  <div class="hm-cols-ref" style="--ref:62"></div>
  <div class="hm-col hm-col--base" style="--v:62"></div><div class="hm-col" style="--v:69"></div><div class="hm-col" style="--v:72"></div><div class="hm-col" style="--v:71"></div>
</div>
<div class="hm-cols-labels hm-muted"><span>jun 2026</span><span>jul 2026</span><span>ago 2026</span><span>sep 2026</span></div>

<!-- Píldoras de estado -->
<span class="hm-pill">Cerrada</span>
<span class="hm-pill hm-pill--activo">En curso</span>
<span class="hm-pill hm-pill--atencion">Empieza en 7 días</span>
<span class="hm-pill hm-pill--vencido">Bajó 5 pts</span>

<!-- Celda protegida del mapa por grupo -->
<div class="hm-cell hm-cell--protegida"><svg class="hm-i hm-i-16" viewBox="0 0 24 24" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Protegido</div>

<!-- Aviso único con dos renglones -->
<div class="hm-notice" role="status">
  <div class="hm-notice-row"><svg class="hm-i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg><span><strong style="font-weight:600">3 personas no tienen fecha de ingreso.</strong> Sin ella no se les otorgan días.</span><a href="Saldos.dc.html">Completar</a></div>
  <div class="hm-notice-row"><span style="width:20px"></span><span>198 constancias anuales sin acuse.</span><a href="Saldos.dc.html">Ver quiénes</a></div>
</div>

<!-- Estado vacío con siguiente paso -->
<div class="hm-empty">
  <span class="hm-empty-icon"><svg class="hm-i hm-i-24" viewBox="0 0 24 24" aria-hidden="true"><rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/></svg></span>
  <h2 class="hm-h3">Todavía nadie tiene jefe directo</h2>
  <p>Asígnalo al editar a cada persona o súbelo en el CSV de empleados. Con eso se arma el organigrama.</p>
  <a class="hm-btn hm-btn-primary" href="#">Ir a Empleados</a>
</div>
```

El arco usa `conic-gradient` enmascarado: no hay `<svg>` de gráfica. Excepción permitida por `diagrams.md`: el
**plot** de la mini-gráfica de tendencia (P1) puede ser un `<svg>` de 120×32 con una `polyline` y una línea base,
`aria-hidden`, con las cifras fuera como texto. El árbol del organigrama **no** es un diagrama de cajas y flechas:
es una lista anidada (`<ul>`/`<li>`) en flex con conectores hechos con bordes; no se usa el host absoluto.

Conectores del árbol (O1):

```css
.hm-tree,.hm-tree ul{display:flex;flex-direction:column;gap:8px}
.hm-tree ul{margin-left:26px;padding-left:24px;border-left:1px solid var(--input)}
.hm-node{position:relative;display:flex;align-items:center;gap:12px;min-height:64px;padding:8px 8px 8px 16px;background:var(--card);border:1px solid var(--border);border-radius:10px}
.hm-tree ul>li>.hm-node::before{content:"";position:absolute;left:-25px;top:50%;width:24px;border-top:1px solid var(--input)}
.hm-node-text{display:flex;flex-direction:column;min-width:0;flex:1}
```

**Íconos** (Lucide, tomados del paquete instalado en el repo). Siempre
`<svg class="hm-i" viewBox="0 0 24 24" aria-hidden="true">…</svg>`; solo se usan estos:

| Nombre | Contenido del `<svg>` |
|---|---|
| house | `<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>` |
| factory | `<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/>` |
| users | `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>` |
| user | `<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>` |
| user-cog | `<circle cx="18" cy="15" r="3"/><circle cx="9" cy="7" r="4"/><path d="M10 15H6a4 4 0 0 0-4 4v2"/><path d="m21.7 16.4-.9-.3"/><path d="m15.2 13.9-.9-.3"/><path d="m16.6 18.7.3-.9"/><path d="m19.1 12.2.3-.9"/><path d="m19.6 18.7-.4-1"/><path d="m16.8 12.3-.4-1"/><path d="m14.3 16.6 1-.4"/><path d="m20.7 13.8 1-.4"/>` |
| clipboard-check | `<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>` |
| laptop | `<path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/>` |
| armchair | `<path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v1.5a.5.5 0 0 1-.5.5h-9a.5.5 0 0 1-.5-.5V11a2 2 0 0 0-4 0z"/><path d="M5 18v2"/><path d="M19 18v2"/>` |
| clock | `<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>` |
| message-square-warning | `<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M12 7v2"/><path d="M12 13h.01"/>` |
| gauge | `<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>` |
| activity | `<path d="M22 12h-2.48a2 2 0 0 0-1.93 1.46l-2.35 8.36a.25.25 0 0 1-.48 0L9.24 2.18a.25.25 0 0 0-.48 0l-2.35 8.36A2 2 0 0 1 4.49 12H2"/>` |
| calendar-days | `<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/>` |
| calendar-check | `<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/>` |
| calendar-clock | `<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><path d="M17.5 17.5 16 16.3V14"/><circle cx="16" cy="16" r="6"/>` |
| network | `<rect x="16" y="16" width="6" height="6" rx="1"/><rect x="2" y="16" width="6" height="6" rx="1"/><rect x="9" y="2" width="6" height="6" rx="1"/><path d="M5 16v-3a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3"/><path d="M12 12V8"/>` |
| calculator | `<rect width="16" height="20" x="4" y="2" rx="2"/><line x1="8" x2="16" y1="6" y2="6"/><line x1="16" x2="16" y1="14" y2="18"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/>` |
| chevron-right | `<path d="m9 18 6-6-6-6"/>` |
| chevron-down | `<path d="m6 9 6 6 6-6"/>` |
| chevron-left | `<path d="m15 18-6-6 6-6"/>` |
| chevrons-left | `<path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/>` |
| search | `<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>` |
| ellipsis | `<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>` |
| plus | `<path d="M5 12h14"/><path d="M12 5v14"/>` |
| minus | `<path d="M5 12h14"/>` |
| arrow-right | `<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>` |
| arrow-up | `<path d="m5 12 7-7 7 7"/><path d="M12 19V5"/>` |
| arrow-down | `<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>` |
| check | `<path d="M20 6 9 17l-5-5"/>` |
| x | `<path d="M18 6 6 18"/><path d="m6 6 12 12"/>` |
| lock | `<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>` |
| circle-alert | `<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>` |
| info | `<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>` |
| send | `<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>` |
| download | `<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>` |
| printer | `<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>` |
| pencil | `<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>` |
| hourglass | `<path d="M5 22h14"/><path d="M5 2h14"/><path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"/><path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"/>` |
| inbox | `<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>` |
| trending-up | `<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>` |
| settings | `<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>` |
| menu | `<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>` |
| file-text | `<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>` |
| briefcase | `<path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/>` |
| sun | `<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>` |
| moon | `<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>` |
| monitor | `<rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/>` |

### D.5 Accesibilidad y nombres de archivo

- `<html lang="es">`; `<title>` propio por board ("Vacaciones · Resumen").
- Controles reales: `<button type="button">`, `<a href>`, `<input>` con `<label>` visible. Nada de `div` con
  `onClick`. Enlaces entre boards con `href` relativo al hermano (`Solicitudes.dc.html`); a otra herramienta,
  `../clima/Resultados.dc.html`.
- Todo objetivo ≥ 44×44 px (botones, pestañas, filas de menú, botón de rama, casillas con su fila).
- Texto ≥ 4.5:1 (≥ 3:1 desde 24 px); bordes de controles en `--input` (≥ 3:1); `--border` solo en divisores y
  tarjetas. `--muted-foreground` nunca sobre el tramo `.hm-cell--3` del mapa.
- Nunca solo color: estado con texto, prioridad con texto, tipo de ausencia con leyenda y patrón (sólido contra
  punteado).
- Botón de solo ícono con `aria-label`; SVG decorativo con `aria-hidden="true"`; gráficas hechas con `div` llevan
  `role="img"` y `aria-label` con las cifras.
- Tablas con `<th scope="col">` y `<caption>` (oculto visualmente).
- Foco: no se anula; el bloque de D.1 lo define.
- Sin emoji, sin barra de estado ni teclado falsos, sin sombras salvo en `.hm-menu`.
- Nombres: `people/<herramienta>/<Nombre>.dc.html` con `<Nombre>` en PascalCase sin acentos ni espacios, exactamente
  los del catálogo; móviles con sufijo `-movil`; "antes" con sufijo `-antes` (y `-movil-antes`). Un board no crea
  ningún otro archivo.

### D.6 Contenido tipo (para que las pantallas sean una sola empresa)

- Empresa: **Demo NOM-035**, 212 personas activas. Usuaria: **Ana Dueña** (owner@demo-nom035.test). Hoy: miércoles
  11 de noviembre de 2026.
- Centros (nombre corto, sin el "(N personas)" del dato sembrado): Planta, Sucursal, Oficina, Bodega, Taller.
- Organigrama: raíces Esther Alaniz Segundo (Almacenista, Operaciones, Oficina; 3 directos, 78 en total), Gonzalo
  Reyes Segundo (Analista, Administración, Sucursal; 3, 66), Yeray Pulido (Operador, Almacén, Planta; 3, 65).
  4 niveles: 3, 9, 36, 164 personas. 209 con jefe. Mediana 4 reportes. 1 jefe con 17 directos.
- Otras personas que sí aparecen en capturas: Iván Castañeda, Úrsula Ayala, Dña Helena Guillen, Manuela Fuentes,
  Miriam Fierro, Sra. Sara Gutiérrez (Supervisor, Finanzas, ingreso 1 nov 2016), Lic. Gael Cabán (14 años, desde el
  1 dic 2011), Alonso Villanueva, Rodrigo Salas, Adriana Rivera Hijo, Alonso Banda, Ander Noriega, Dr. Hugo
  Verduzco, Francisca Duran, Gabriel Esquibel, Lic. Ana María Maya Tercero, Lorena Marín, Lucas Lucas, Aaron Ruíz,
  Abril Jáquez, Adam Candelaria Tercero, Adam Concepción, Adrián Sauceda, Ainara Cortés, Alba Gonzáles, Alberto
  Vaca, Alicia Ayala. No se inventan nombres nuevos.
- Vacaciones: 198 periodos; 4,340 días por disfrutar; 133 ventanas por cerrar; 0 por prescribir; 0 fuera hoy y 0
  esta semana; 30 solicitudes: 4 por atender (escaladas a RH), 7 en espera del jefe, 8 aprobadas, 6 disfrutadas,
  4 rechazadas, 1 cancelada; 3 sin fecha de ingreso; 198 constancias sin acuse. Políticas: Ley (12 días, 25 %),
  Contrato colectivo (12 días, 25 %, 1 día fijo cada año), Personal de confianza (14 días, 30 %, 1 día fijo cada
  año), Política 2024 (anterior), archivada.
- Clima · junio 2026 (cerrada el 22 de junio de 2026): 168 de 212, 79 %; compromiso 62 %; quieren seguir un año
  más 63 %; recomendación −27 (46 / 35 / 19 %); 93 comentarios; 13 jefes con resultado; 3 compromisos.
- Clima · noviembre 2026 (en curso, cierra el 24 de noviembre de 2026, quedan 14 días): 94 de 212, 44 %, faltan
  118; Planta 31 de 78 (40 %), Sucursal 9 de 23 (39 %).
- Pulso: julio (151 de 212, 71 %), agosto (139, 66 %), septiembre (157, 74 %, Recorrido). Compromiso 62 → 69 → 72
  → 71 %. Recomendación −27, −19, −21, −13. Mejoras vistas 60, 73, 71 %. Siguiente: 15 de noviembre de 2026,
  Seguimiento, 9 preguntas, mensual.
- Formato: "30 %"; "−27" con signo menos tipográfico; "+9 pts", "−1 pt"; miles con coma ("4,340"); fechas largas
  "18 al 20 de noviembre de 2026" y cortas "18 nov 2026".
- Cifras ilustrativas autorizadas (únicas): segundo nivel del organigrama (O1) y las filas de grupo del mapa
  (C1). Se declaran en la nota de título de su fila en el lienzo. Todo lo demás sale de los datos de arriba o de
  `before/_text/<pantalla>.txt`, que cada agente debe leer antes de dibujar su pantalla.

---

## E. Plan de ejecución

### E.1 Paquetes y archivos (sin solapes)

| Paquete | Escribe | Lee |
|---|---|---|
| **KIT** | `people/Sidebar.dc.html`, `people/Topbar.dc.html`, `people/MobileTop.dc.html`, `people/MobileTabs.dc.html` y sus 16 copias idénticas en las cuatro carpetas | D.1–D.5, logos de `public/brand/`, `before/orgchart-index-claro.png` y `before/vacaciones-index-claro.png` (sidebar nuevo) |
| **ORGANIGRAMA** | `people/organigrama/{Organigrama,CalidadDeDatos,Organigrama-movil}.dc.html` | C.1, D, `before/_text/orgchart-*.txt` |
| **VACACIONES** | `people/vacaciones/{Resumen,Solicitudes,Calendario,Saldos,Expediente,Politicas,PoliticaEditar,Resumen-movil,Solicitudes-movil,Calendario-movil}.dc.html` | C.2, D, `before/_text/vacaciones-*.txt` |
| **CLIMA** | `people/clima/{Resultados,Inicio,Avance,PorPregunta,Comentarios,Compromisos,Jefe,Resultados-movil}.dc.html` | C.3, D, `before/_text/clima-*.txt` |
| **PULSO** | `people/pulso/{Tendencia,Inicio,Resultados,Tendencia-movil}.dc.html` | C.4, D, `before/_text/pulso-*.txt` |
| **ANTES** (ya asignado a otra persona) | todos los `*-antes.dc.html` | capturas de `before/` |
| **ENSAMBLE** | `canvas.json` | E.3 |

Vacaciones tiene 10 boards: si se quiere más paralelismo, se parte en dos agentes con esta división fija:
VACACIONES-A = Resumen, Solicitudes, Calendario y sus tres móviles; VACACIONES-B = Saldos, Expediente, Politicas,
PoliticaEditar.

### E.2 Dependencias reales

- **En paralelo desde el minuto cero**: KIT, ORGANIGRAMA, VACACIONES, CLIMA, PULSO y ANTES. Los paquetes de
  herramienta no esperan al KIT porque la interfaz de los hijos (nombre, props, tamaño, `hint-size`) está fijada en
  D.3: mientras el hijo no exista, el board muestra el hueco del `hint-size`.
- **Secuencial de verdad**: (1) el KIT hace primero una prueba de humo de 5 minutos: un board mínimo en
  `people/organigrama/` que importe `Sidebar` y cargue `support.js`, para confirmar la ruta de `support.js` desde
  una subcarpeta (el mismo `src` que usen los boards "antes"); si la ruta correcta no es `./support.js`, lo avisa
  de inmediato y es un reemplazo de una línea por board. (2) ENSAMBLE va al final, cuando existan todos los
  archivos. (3) La revisión visual en oscuro y a 375 px va después de que el KIT entregue.
- Los agentes de herramienta no se hablan: las cifras compartidas están en D.6 y los enlaces entre boards usan los
  nombres del catálogo.
- Cada agente de dibujo carga las skills `frontend-design` e `impeccable` antes de escribir y aplica de
  `research.md` solo lo ya incorporado en C (no agrega patrones por su cuenta).

### E.3 Armado del lienzo

Una fila por herramienta; pares antes | después de izquierda a derecha en el orden del catálogo; 80 px entre
columnas; 120 px entre filas; móviles al final de la fila. Columna de escritorio: `x = 1520 × n`. Los "después" de
escritorio llevan `"expand": "fill"`; los "antes" y los móviles no. `title` = el nombre de la pantalla con el
sufijo " · antes" o " · después". La altura de cada "antes" es la de su captura (los móviles, la mitad de los
píxeles de la captura a 2×).

Notas de título (una por fila, a la izquierda, 80 px arriba de la fila): "Organigrama · antes | después ·
asignación de segundo nivel ilustrativa", "Vacaciones · antes | después", "Clima · antes | después · cifras por
grupo del mapa ilustrativas", "Pulso · antes | después". Si el formato no tiene notas, se omiten y el texto va en
el `title` del primer board de la fila.

| Archivo (bajo `people/`) | x | y | w | h |
|---|---|---|---|---|
| *Nota de título «organigrama»* | 0 | -80 | 1440 | 56 |
| `organigrama/Organigrama-antes.dc.html` | 0 | 0 | 1440 | 1231 |
| `organigrama/Organigrama.dc.html` | 1520 | 0 | 1440 | 1300 |
| `organigrama/CalidadDeDatos-antes.dc.html` | 3040 | 0 | 1440 | 1231 |
| `organigrama/CalidadDeDatos.dc.html` | 4560 | 0 | 1440 | 1000 |
| `organigrama/Organigrama-movil-antes.dc.html` | 6080 | 0 | 375 | 1254 |
| `organigrama/Organigrama-movil.dc.html` | 6535 | 0 | 375 | 1500 |
| *Nota de título «vacaciones»* | 0 | 1540 | 1440 | 56 |
| `vacaciones/Resumen-antes.dc.html` | 0 | 1620 | 1440 | 1451 |
| `vacaciones/Resumen.dc.html` | 1520 | 1620 | 1440 | 1300 |
| `vacaciones/Solicitudes-antes.dc.html` | 3040 | 1620 | 1440 | 1451 |
| `vacaciones/Solicitudes.dc.html` | 4560 | 1620 | 1440 | 1200 |
| `vacaciones/Calendario-antes.dc.html` | 6080 | 1620 | 1440 | 1673 |
| `vacaciones/Calendario.dc.html` | 7600 | 1620 | 1440 | 1300 |
| `vacaciones/Saldos-antes.dc.html` | 9120 | 1620 | 1440 | 2174 |
| `vacaciones/Saldos.dc.html` | 10640 | 1620 | 1440 | 1400 |
| `vacaciones/Expediente-antes.dc.html` | 12160 | 1620 | 1440 | 1451 |
| `vacaciones/Expediente.dc.html` | 13680 | 1620 | 1440 | 1400 |
| `vacaciones/Politicas-antes.dc.html` | 15200 | 1620 | 1440 | 1451 |
| `vacaciones/Politicas.dc.html` | 16720 | 1620 | 1440 | 1300 |
| `vacaciones/PoliticaEditar-antes.dc.html` | 18240 | 1620 | 1440 | 2317 |
| `vacaciones/PoliticaEditar.dc.html` | 19760 | 1620 | 1440 | 1500 |
| `vacaciones/Resumen-movil-antes.dc.html` | 21280 | 1620 | 375 | 1534 |
| `vacaciones/Resumen-movil.dc.html` | 21735 | 1620 | 375 | 1900 |
| `vacaciones/Solicitudes-movil-antes.dc.html` | 22190 | 1620 | 375 | 1467 |
| `vacaciones/Solicitudes-movil.dc.html` | 22645 | 1620 | 375 | 1800 |
| `vacaciones/Calendario-movil-antes.dc.html` | 23100 | 1620 | 375 | 1891 |
| `vacaciones/Calendario-movil.dc.html` | 23555 | 1620 | 375 | 1500 |
| *Nota de título «clima»* | 0 | 3977 | 1440 | 56 |
| `clima/Resultados-antes.dc.html` | 0 | 4057 | 1440 | 2450 |
| `clima/Resultados.dc.html` | 1520 | 4057 | 1440 | 1900 |
| `clima/Inicio-antes.dc.html` | 3040 | 4057 | 1440 | 1137 |
| `clima/Inicio.dc.html` | 4560 | 4057 | 1440 | 1000 |
| `clima/Avance-antes.dc.html` | 6080 | 4057 | 1440 | 1137 |
| `clima/Avance.dc.html` | 7600 | 4057 | 1440 | 1100 |
| `clima/PorPregunta-antes.dc.html` | 9120 | 4057 | 1440 | 4078 |
| `clima/PorPregunta.dc.html` | 10640 | 4057 | 1440 | 1800 |
| `clima/Comentarios-antes.dc.html` | 12160 | 4057 | 1440 | 1285 |
| `clima/Comentarios.dc.html` | 13680 | 4057 | 1440 | 1200 |
| `clima/Compromisos-antes.dc.html` | 15200 | 4057 | 1440 | 1697 |
| `clima/Compromisos.dc.html` | 16720 | 4057 | 1440 | 1400 |
| `clima/Jefe-antes.dc.html` | 18240 | 4057 | 1440 | 1794 |
| `clima/Jefe.dc.html` | 19760 | 4057 | 1440 | 1500 |
| `clima/Resultados-movil-antes.dc.html` | 21280 | 4057 | 375 | 3492 |
| `clima/Resultados-movil.dc.html` | 21735 | 4057 | 375 | 3200 |
| *Nota de título «pulso»* | 0 | 8175 | 1440 | 56 |
| `pulso/Tendencia-antes.dc.html` | 0 | 8255 | 1440 | 2786 |
| `pulso/Tendencia.dc.html` | 1520 | 8255 | 1440 | 1500 |
| `pulso/Inicio-antes.dc.html` | 3040 | 8255 | 1440 | 1459 |
| `pulso/Inicio.dc.html` | 4560 | 8255 | 1440 | 1100 |
| `pulso/Resultados-antes.dc.html` | 6080 | 8255 | 1440 | 2000 |
| `pulso/Resultados.dc.html` | 7600 | 8255 | 1440 | 1600 |
| `pulso/Tendencia-movil-antes.dc.html` | 9120 | 8255 | 375 | 5206 |
| `pulso/Tendencia-movil.dc.html` | 9575 | 8255 | 375 | 2600 |

Capturas de origen de cada "antes" (en `before/`, sufijo `-claro.png`; los móviles `-375.png`):
Organigrama ← `orgchart-index`; CalidadDeDatos ← `orgchart-quality`; Resumen ← `vacaciones-index`; Solicitudes ←
`vacaciones-solicitudes`; Calendario ← `vacaciones-calendario`; Saldos ← `vacaciones-saldos`; Expediente ←
`vacaciones-empleado`; Politicas ← `vacaciones-politicas`; PoliticaEditar ← `vacaciones-politica-editar`;
clima/Resultados ← `clima-resultados`; clima/Inicio ← `clima-index`; Avance ← `clima-detalle-activa`; PorPregunta ←
`clima-resultados-preguntas`; Comentarios ← `clima-comentarios`; Compromisos ← `clima-compromisos`; Jefe ←
`clima-jefe`; Tendencia ← `pulso-tendencia`; pulso/Inicio ← `pulso-index`; pulso/Resultados ← `pulso-resultados`.
Si la recaptura con el sidebar nuevo ya terminó, se usa esa; el contenido no cambió.

Si un "después" queda más alto que su `h`, el agente lo reporta y el ensamblador sube ese `h`; las `y` de las
filas siguientes solo cambian si supera el máximo de su fila (organigrama 1500, vacaciones 2317, clima 4078,
pulso 5206).

### E.4 Criterios de "hecho" por board

1. El archivo existe con el nombre exacto del catálogo y no se creó ningún otro.
2. `<html lang="es">`, `<title>` propio, Figtree por `<link>` en `<helmet>`, bloque de tokens de D.1 pegado sin
   cambios.
3. Raíz con `class="{{themeClass}}"`; tweak `theme` con `claro` y `oscuro`; `variant` solo con los valores del
   catálogo; ningún otro tweak; el copy es texto literal, no prop.
4. Cero colores crudos fuera del bloque de tokens: `grep -nE '#[0-9A-Fa-f]{3,8}|rgba?\(' archivo` solo da líneas
   dentro de `.t-light`/`.t-dark` (y el logo en los hijos del KIT).
5. Se ve bien en oscuro: ninguna superficie blanca, ningún texto ilegible, el arco y las barras usan `--track`.
6. Contraste: texto ≥ 4.5:1 en ambos temas; controles y foco ≥ 3:1.
7. Todo objetivo interactivo ≥ 44×44 px; controles reales; botones de ícono con `aria-label`.
8. Como mucho un `hm-btn-primary` visible por estado; como mucho 3 KPI; como mucho una `hm-notice`; un arco.
9. Layout con flex/grid + `gap`; sin `position:absolute` salvo en los seudo-elementos del kit; ninguna gráfica
   como `<svg>` único salvo el plot de la mini-gráfica de tendencia; íconos solo de la lista de D.4.
10. Escritorio: PAGE fluida (sin ancho fijo en la raíz, `main` con `flex:999 1 560px;min-width:0`), tablas dentro
    de `hm-tablewrap`; a 375 px de ventana no hay desplazamiento horizontal de página.
11. Copy es-MX, tutea, "30 %" con espacio, "−" tipográfico, sin emoji ni signos de exclamación, botones verbo +
    objeto.
12. Cada cifra y nombre está en D.6 o en el `before/_text/` de esa pantalla; las únicas ilustrativas son las
    declaradas.
13. Privacidad: ningún nombre junto a una respuesta; ninguna hora; ningún conteo bajo el mínimo; las celdas
    protegidas no dicen por qué ni cuántas.
14. Migas con familia; `active` del sidebar con una clave de la tabla de D.3.
15. Todos los estados del catálogo existen y se alcanzan con `variant`.

---

## F. Del rediseño a la implementación (siguiente paso, no ahora)

**Un PR** ("Rediseño de Organigrama, Vacaciones, Clima y Pulso"), commits atómicos (test + código), TDD. Orden
recomendado, de menor a mayor riesgo:

1. **Design system primero** (`components/humetria`, sin tocar módulos): variante de `ArcMeter` con porcentaje
   adentro; `BarMeter` con marca de referencia; `ColumnChart` desde cero con línea base; `CountTabs` con orden por
   acción; `PageHeader` con ficha de íconos; menú de fila; barra de acciones masivas; celda protegida. Cada uno
   con su test; color nuevo, a `DesignSystemTest` con su par. Nada de dependencias nuevas.
2. **Correcciones de producto de A.5 (ii)** que no dependen del rediseño, un commit cada una: migas con familia en
   Vacaciones, Clima y Pulso; "1 día fijo"; título de jefes en Pulso; condición del vacío de Pulso; "30 %" en la
   constancia; copy de "Menos de 5 respuestas".
3. **Pulso** (Tendencia e Inicio): casi todo es presentación y `lib/engagement/trend.ts` (delta desde la primera
   medición y banda "sin cambio", con Vitest).
4. **Clima** (Avance, Inicio, Resultados, Por pregunta, Comentarios, Compromisos, Jefe). El mapa por grupo entra
   solo si Victor aprueba la decisión 1, con un test de Feature que afirme que las props no distinguen "bajo el
   mínimo" de "suprimido por complemento" y que no hay cruce de dos atributos.
5. **Organigrama**: primer nivel de hijos en la carga inicial y `people_by_level`, con
   `expectsDatabaseQueryCount`; el árbol sigue paginando por rama.
6. **Vacaciones**, lo más grande: Resumen y Solicitudes (panel de decisión), luego Calendario, Saldos, Expediente,
   Políticas y editor.

Reglas del repo que más pesan aquí: páginas delgadas y componentes de ≤ 300 líneas / 100 por función (el panel de
decisión, el comparador de políticas y el mapa se parten en varios archivos); lógica en `lib/` con Vitest ≥ 90 %;
ningún `as` ni `!`; cada lista sigue llegando como paginador y entra en `PaginatedListsTest` (el mapa por grupo y
los arreglos cortos nuevos necesitan su razón en `tests/Unit/PaginatedListsTest.php`); cada página conserva su
test de navegador con `visitPage()`, axe y CSP, y se revisa en claro, oscuro y 375 px; colores solo por tokens
(`DesignTokensOnlyTest`).

Riesgos de backend:

- **Privacidad** (el mayor): el mapa por grupo y la participación por grupo multiplican cortes; todo debe salir
  de `SmallGroups`/`CutReport`, un atributo a la vez. El conteo de jefes ocultos y el de respuestas faltantes no se
  exponen.
- **Cobertura del equipo al aprobar** (decisión 3): Leave necesita leer la línea de reporte de Organization por
  contrato, sin joins; cuidar N+1 en la bandeja (una consulta por página, no por fila).
- **Saldo tras aprobar** (decisión 2): cálculo por fila en la bandeja; debe salir de la misma acción que valida la
  aprobación para no divergir.
- **Calendario "con ausencias"** (decisión 4): cambia el conjunto paginado; requiere índice por fechas y conserva
  la opción de ver a todos.
- **Tira de 14 días y personas por política**: agregados nuevos; acotar el número de consultas del Resumen.
- **Raíz legítima del organigrama** ("Es quien dirige la empresa"): bandera nueva en Organization con su
  migración y su regla en Calidad de datos.
- **Umbral "sin cambio" de la tendencia**: es decisión de producto antes de escribir el test.
