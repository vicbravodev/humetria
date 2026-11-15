# Organigrama v2 — especificación común (los 3 agentes la leen entera)

## Problema (palabras de Victor)
"No noto la jerarquía, no sé quién es jefe de quién, no veo cómo se vería una organización con varios escalones.
La pantalla de admin no la entiendo ni cómo se usa; y ese es el sentido de Humetría." La v1 era una LISTA plana con
conteos: no es un organigrama. Hay que dibujar un ÁRBOL de verdad.

## Principios
1. Un organigrama es un árbol visible: jefe arriba, directos abajo, líneas en codo que los unen. Se lee sin instrucciones.
2. Lo ve cualquiera (solo lectura) y RH lo edita en la MISMA pantalla (modo "Editar"). La tabla es la vista accesible/de mantenimiento.
3. Para qué sirve, dicho en una línea: "De aquí salen quién aprueba las vacaciones de cada persona y los resultados de Clima y Pulso por jefe."
4. Calmo, no alarmante: un solo acento jade; ámbar solo para "requiere atención"; nada de rojo para datos incompletos.
5. Privacidad: el organigrama NO muestra salarios, evaluaciones, ausencias ni motivos. Solo nombre, puesto, área, centro, conteos.

## Datos de ejemplo (usa estos, no inventes otros)
Empresa "Demo NOM-035", 212 personas activas, 4+ niveles. Hay 3 personas sin jefe (cabezas): por eso el nodo raíz es
VIRTUAL "Demo NOM-035 · 212 personas" (tarjeta punteada, sin avatar) y de él cuelgan las 3 cabezas, con una cinta ámbar
"3 personas sin jefe: normalmente hay una" y acción "Asígnales jefe".
- Esther Alaniz Segundo — Almacenista · Operaciones · Oficina — 3 directos · 78 en total
  - Adriana Rivera Hijo — Supervisor · 4 directos · 30 en total
    - (4 personas de nivel 4, p.ej. Camila Soto — Analista; Diego Lara — Analista; Irene Paz — Auxiliar; Mateo Ríos — Auxiliar)
  - Alonso Banda — Analista · 3 directos · 24 en total
  - Ander Noriega — Analista · 3 directos · 21 en total
    - (3 de nivel 4: Paula Vega, Rubén Cano, Sofía Ibarra)
- Gonzalo Reyes Segundo — Analista · Administración · Sucursal — 3 directos · 66 en total
  - Dr. Hugo Verduzco — Supervisor · 17 directos · 38 en total  (¡17! → ámbar "17 directos")
  - Francisca Duran — Analista · 2 directos · 14 en total
  - Gabriel Esquibel — Analista · 1 directo · 11 en total
- Yeray Pulido — Operador · Almacén · Planta — 3 directos · 65 en total
  - Lic. Ana María Maya Tercero — Supervisor · 2 directos · 22 en total
  - Lic. Gael Cabán — Supervisor · 2 directos · 20 en total
  - Lorena Marín — Operadora · 2 directos · 20 en total
Niveles: Nivel 1 = 3 personas, Nivel 2 = 9, Nivel 3 = 36, Nivel 4 = 164. Mediana 4 directos por jefe.
Ramas CERRADAS se dibujan con una "pastilla" debajo de la tarjeta: `+4 · 30 en total ▾` (o `▸`). Ramas abiertas llevan `▴`.

## Tarjeta de persona (nodo) — spec
- 216×72 px, radio 12, borde 1px var(--border), fondo var(--card), padding 12; avatar circular 36 con iniciales (fondo var(--secondary));
  nombre 14/600 una línea con elipsis; puesto 12/400 var(--muted-foreground) una línea; abajo a la derecha o en pastilla el conteo "N directos".
- Estados: normal; hover (borde var(--input)); seleccionada (borde 2px var(--primary) + sombra suave var(--shadow-float)); en el camino de la búsqueda
  (borde primary, fondo var(--accent)); ámbar "atención" (fondo var(--warning), texto var(--warning-foreground)) para jefe con >15 directos o sin jefe;
  atenuada (opacity .45) cuando la búsqueda marca otro camino.
- Conectores: SVG/CSS en codo, 1.5px, color var(--input) (ya contrasta ≥3:1); el camino resaltado va en var(--primary) 2px.
- La tarjeta es un `<button>` (foco visible, ≥44px de alto efectivo con padding), nombre accesible "Esther Alaniz Segundo, Almacenista, 3 directos, 78 en total".
- Nodo raíz virtual: tarjeta punteada, icono `building`, "Demo NOM-035" y "212 personas".

## Lienzo (chart) — cómo se ve lo que hará React Flow
- Zona de dibujo con fondo var(--muted) y puntos sutiles (patrón de puntos 1px, 24px de paso, color var(--border)), bordes redondeados 12, borde 1px.
- Controles abajo a la izquierda en una pastilla blanca: `+`, `−`, `Ajustar a la pantalla` (iconos, 44px, con title).
- Minimapa abajo a la derecha 160×100: rectángulos pequeños del árbol + el recuadro del encuadre actual (borde primary).
- Barra superior del lienzo: búsqueda "Busca a una persona…", filtro "Centro de trabajo ▾", selector de profundidad "Mostrar: 3 niveles ▾" (1…todos), botón "Expandir todo / Contraer".
- Cursor "grab"; leyenda breve debajo a la izquierda del lienzo: "Arrastra para mover · Rueda para acercar · Clic en una persona para ver su equipo".
- Nota técnica para la implementación (NO dibujar): @xyflow/react + @dagrejs/dagre (dependencias nuevas: requieren aprobación de Victor).

## Panel de la persona (drawer derecho, 360px, sobre el lienzo)
Encabezado: avatar 48, nombre 18/600, puesto · área · centro, botón cerrar (44px).
Bloques: "Su jefe" (tarjeta pequeña clicable con flecha ↑), "Su equipo directo (3)" (lista de mini-tarjetas, cada una clicable; si son muchas, "Ver los 17" → lista), "Equipo total: 78 personas",
"Qué sale de aquí" (2 líneas: "Aprueba las vacaciones de 3 personas." "Sus resultados de Clima se muestran con 3 respuestas mínimas…" — NO: usa "Recibe las solicitudes de vacaciones de su equipo directo y tiene su propia lectura de Clima cuando su equipo llega al mínimo de respondientes (5)."),
botón secundario "Ver su ficha de empleado" y (solo en modo editar) "Cambiar jefe".

## Jerarquía y copy (tuteo en el panel de RH; es-MX)
Título de página: "Organigrama". Subtítulo/ficha con íconos: `users` 212 personas activas · `network` 4 niveles · `user-check` 209 con jefe directo.
Segmentado de vistas (tabs con rol): "Organigrama" | "Lista". Botón a la derecha: "Editar estructura" (solo RH; ícono `pencil`). En modo edición el botón pasa a "Listo".
Cinta (dismissible) bajo el título: "De aquí salen quién aprueba las vacaciones de cada persona y los resultados de Clima y Pulso por jefe." + enlace "Cómo funciona" (solo texto, a #).

## Entrega
Cada agente: escribe SOLO sus archivos .dc.html en `people/organigrama/` (nombres abajo), usa `<dc-import>` para Sidebar/Topbar/MobileTop/MobileTabs
como lo hacía `Organigrama.dc.html` (v1; léelo para copiar el cascarón y los tokens .t-light/.t-dark y la regla
`.hm button.hm-btn{padding:0 16px}`), props `theme` claro|oscuro (y variant donde se indique). Árbol con HTML+CSS puro (listas anidadas con pseudo-elementos
o posicionamiento absoluto + un <svg> de conectores). SIN generar con scripts. Íconos: SVG inline estilo lucide (stroke 1.75).
Verifica con el render local: `node S/render.mjs project/people/organigrama/<Archivo>.dc.html S/review/<nombre>.png 1440 [t-dark]`
(S = el scratchpad; primero `cp -R S/canvas/project/people/. S/render2/project/people/` para sincronizar). Mira el PNG: nada recortado, sin scroll horizontal,
tarjetas legibles, conectores unidos a sus tarjetas (una línea suelta es un defecto). Ajusta `$preview.height` al alto real.
Reporta: archivos, altura final, y defectos que no pudiste resolver.
