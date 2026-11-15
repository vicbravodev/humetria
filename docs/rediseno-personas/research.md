# Investigación de patrones UI/UX para el rediseño de Humetría

Nota de honestidad: la búsqueda devolvió sobre todo blogs, ayudas de producto y design systems; NN/g solo apareció en wizards, bulk actions y carga cognitiva. Las cifras de terceros (duraciones, umbrales) se marcan como "convención", no como estándar. Todo está redactado con mis palabras; ningún diseño propietario se copia. Las fuentes de producto (Timetastic, Dayforce, Pave…) son inspiración de patrón, no autoridad.

Filtro aplicado a todo: calma (sin rojo, sin parpadeo, sin contadores que angustien), tarjetas con borde y sin sombra, jade como único acento, terracota sobrio para riesgo, y privacidad (mínimo 5 respondentes, también tras filtrar).

---
## 1. Panel de inicio y "siguiente mejor acción"

**1.1 Una sola tarjeta "Siguiente paso" arriba, con el resto como ruta.**
- Qué es: el panel abre con UNA tarjeta destacada (borde jade tenue): verbo + resultado + tiempo estimado ("Lanza la Guía II para Planta Norte · 3 min"), un botón primario y un "Más tarde". Debajo, la ruta completa en lista corta.
- Por qué: un empty state/dashboard debe dar estado del sistema, pista de aprendizaje y camino directo a la tarea (resumen de las pautas de NN/g sobre empty states, citadas de segunda mano). Menos decisiones = más flujos completados.
- Se ve: tarjeta ancha + 3-5 filas de checklist con ícono de estado (hecho, actual, pendiente). Sin porcentaje enorme; una barra fina que ya arranca con lo hecho.
- Riesgo: si todo es "urgente" deja de ser siguiente. Regla: máximo una acción primaria; el vencimiento va en texto ("vence el 14 de dic"), no en rojo.
- Fuentes: https://www.newsletter.designproject.io/p/onboarding-ux-design-guide · https://produktly.com/guides/how-to-use-empty-states-for-onboarding · https://checklist.design/pages/onboarding

**1.2 Checklist corto, saltable y que desaparece al terminar.**
- Qué es: máximo ~5 pasos, cada uno saltable con "Ya lo hice por otro medio"; al completarse la tarjeta se reemplaza por la fase siguiente (de "Configura" a "Mantén").
- Por qué: una checklist terminada que sigue ahí es ruido; una larga intimida (guías de productled/appcues).
- Se ve: tarjeta con pasos; al 100% cambia a estado de logro sobrio ("Listo para la STPS") y se contrae.
- Riesgo: ocultar demasiado pronto un paso que se debe repetir (evaluación cada 2 años): al terminar, deja un recordatorio con fecha, no un check muerto.
- Fuente: https://productled.com/blog/5-best-practices-for-better-saas-user-onboarding

**1.3 Empty state con forma del contenido futuro + acción dentro.**
- Qué es: tabla/medidor "fantasma" (esqueleto con la forma real), frase de por qué está vacío y el botón de importar/crear dentro del área vacía; sin datos de ejemplo falsos.
- Por qué: enseña qué obtendrás y quita el paso de buscar el botón.
- Riesgo: datos demo engañosos; usa esqueleto neutro.
- Fuente: https://produktly.com/guides/how-to-use-empty-states-for-onboarding

**1.4 Barra de progreso que no arranca en cero.**
- Cuenta lo que Humetría ya sabe (centros cargados, plantilla importada) como avance. Fuente: https://www.newsletter.designproject.io/p/onboarding-ux-design-guide. Riesgo: inflarlo; que solo cuente pasos reales.

Descartado: gamificación (rachas, confeti, puntos): choca con la calma y trivializa cumplimiento legal.

---
## 2. Cumplimiento normativo con evidencia (NOM-035, NOM-037, Ley Silla)

**2.1 Wizard "una cosa por pantalla" para configurar, formulario normal para revisar.**
- Qué es: preguntas en lenguaje de persona ("¿Cuántas personas trabajan en este centro?") que derivan qué guía aplica (≤15, 16-50, >50) y muestran la consecuencia al instante. Al final, pantalla de resumen editable.
- Por qué: una pregunta por pantalla reduce carga cognitiva y hace visibles los errores (GOV.UK, NN/g); además GOV.UK halló que el indicador de progreso apenas se mira y no cambió la finalización: basta "Paso 3 de 5" en texto, sin barra decorativa.
- Se ve: tarjeta centrada ~560 px, una pregunta, respuesta grande tipo opción, "Atrás" siempre visible, guardado automático.
- Riesgo: wizards largos para usuarios expertos; ofrece "Saltar al resumen". NN/g advierte que los wizards frenan a quien ya sabe qué quiere.
- Fuentes: https://www.nngroup.com/articles/wizards/ · https://designnotes.blog.gov.uk/2015/07/03/one-thing-per-page/ · https://nngroup.com/articles/4-principles-reduce-cognitive-load · https://userresearch.blog.gov.uk/2015/08/13/no-more-accordions-how-to-choose-a-form-structure/

**2.2 Checklist de evidencia con estado en palabras.**
- Qué es: cada requisito del numeral (p. ej. 7.7 informe) con estado textual: "Falta", "En curso", "Listo", "Vence en 40 días", fecha y quién lo cargó, y el archivo o enlace que lo respalda.
- Por qué: el usuario lo presenta ante la STPS; ver evidencia junto al requisito evita buscar. Estado por texto + posición es accesible y calmado.
- Se ve: tabla con cabecera muted: Requisito | Estado (chip con ícono) | Evidencia | Acción en menú de fila. Agrupa por numeral.
- Riesgo: parecer auditoría punitiva. Copy: "Te falta" en vez de "Incumple".

**2.3 Centros de trabajo en cuadrícula con semáforo calmado.**
- Qué es: una tarjeta por centro con nombre, norma aplicable, medidor de arco o anillo fino de avance y UNA línea de siguiente paso; el estado se comunica con ícono + texto ("Al día", "Por vencer", "Requiere atención") y orden de la cuadrícula (lo que requiere atención primero), con terracota solo en el borde izquierdo/ícono.
- Por qué: escaneas 20 centros en segundos; la posición hace el trabajo que el rojo hace en otros SaaS.
- Riesgo: daltonismo y alarma; nunca solo color, y máximo dos tonos (jade, terracota) más neutro. Para >30 centros, paginar y ofrecer vista tabla con resumen de conteos arriba.
- Fuente de patrón (agregar resumen + detalle): Carbon https://carbondesignsystem.com/patterns/filtering

**2.4 Resumen por estado antes del detalle.** Barra de conteos clicable ("12 al día · 3 por vencer · 1 requiere atención") que filtra la lista paginada; cumple tu regla de listas. Riesgo: ninguno relevante; verifica que cada conteo coincida con el filtro.

---
## 3. Asistencia y control de jornada

**3.1 Bandeja de excepciones: solo lo que pide decisión.**
- Qué es: el gerente ve únicamente faltantes de entrada/salida, retardos fuera de política y ediciones; lo normal queda pre-aprobado. Cada fila trae la corrección propuesta con motivo ya llenado; acciones: Aprobar / Corregir / Pedir aclaración.
- Por qué: reduce el trabajo a excepciones; un fichaje faltante bloquea aprobación y nómina, así que priorizar ese tipo es útil.
- Guardrails reutilizables: límite de horas por solicitud, ventana retroactiva (p. ej. 7 días) y motivo obligatorio con longitud mínima.
- Riesgo: tono de vigilancia; evita "falta" y "infracción", usa "Sin salida registrada".
- Fuentes: https://help.insperity.com/bps/wp-content/uploads/sites/3/2021/02/Exceptions-User-Guide.pdf · https://www.mangoapps.com/ai/agents/attendance · https://help.workeasysoftware.com/managing-employee-time-and-attendance-exceptions

**3.2 Tablero de semana con línea de tiempo por persona.**
- Una fila por persona, columnas lun-dom (o franjas horarias del día); barra jade de jornada cumplida, borde punteado para lo programado, marca terracota discreta en excepciones, ícono para permiso/vacaciones. Hover/tap abre tarjeta con detalle. Paginado y filtros como chips (centro, área).
- Riesgo: densidad en 375 px: en móvil colapsa a lista de personas con mini-barra de 7 puntos y detalle en hoja inferior.
- Fuente de patrón (calendario de equipo): https://humand.co/en/blog/time-off-calendar-a-unified-view-for-the-entire-team/

**3.3 Fichaje móvil/kiosco de un toque.** Pantalla con un solo botón gigante, confirmación con hora y persona, y deshacer 10 s. (No hubo fuente sólida en la búsqueda sobre kioscos/geocerca; es práctica general, valídese con pruebas de usabilidad propias.) Riesgo: geocerca invasiva: pídela solo si el cliente la configura y dilo en texto.

---
## 4. Quejas y casos confidenciales

**4.1 Lista/bandeja como vista de trabajo; kanban solo como resumen.**
- Las fuentes favorecen una cola con vencimiento visible, estados de espera explícitos y línea de tiempo por caso; el kanban sirve como vista de estado, no para trabajar. Una lista oculta la inactividad: muestra "sin actividad hace 11 días" como texto en cada fila.
- Se ve: tabla con Folio | Etapa | Esperando a (RH / Persona / Terceros) | Plazo | Última actividad; menú de fila.
- Riesgo: datos sensibles en la fila; muestra folio y categoría, nunca nombres en lista, y respeta rol.
- Fuentes: https://www.rezolve.ai/blog/hr-case-management-grows-up · https://help.dayforce.com/r/documents/HR-Service-Delivery-Guide/HR-Case-Management · https://www.dovetailsoftware.com/hr-case-management

**4.2 Línea de tiempo del caso, solo-agregar.** Cronología vertical de eventos con fecha y responsable; sirve para auditoría y disputas. Encaja con la bitácora de solo inserción de Humetría. Riesgo: mostrar a quien no debe; la línea de tiempo hereda el rol.
- Fuente: https://www.dovetailsoftware.com/hr-case-management

**4.3 Plazo que cambia de unidad al acercarse (días, luego horas).** Texto "Quedan 3 días hábiles", sin cuenta regresiva animada. Fuente: Dayforce (arriba). Riesgo: ansiedad; no animar, no rojo; terracota solo al vencer.

**4.4 Etapa visible como paso (stepper horizontal sobrio)** con "Esperando a…" para evitar el "agujero negro" de bandeja compartida: https://www.freshworks.com/explore-ex/why-hr-case-management-replacing-inbox/

---
## 5. Clima y pulso (resultados anónimos)

**5.1 Heatmap por grupo con celdas "protegidas".**
- Cuadrícula grupos × factores con escala de un solo matiz jade (claro a oscuro) y el valor en número dentro de cada celda (no solo color). Celdas con <5 respondentes muestran candado + "Grupo reservado (menos de 5 respuestas)", sin cifra.
- Privacidad avanzada que sí aplican los proveedores: supresión complementaria (ocultar también el siguiente grupo más chico para que no se pueda restar del total) y aplicar el mínimo por factor/pregunta con los elegibles, no solo por segmento; el umbral se evalúa después de filtrar. Humetría ya exige lo segundo; añade lo primero.
- Fuentes: https://www.culturemonkey.io/kb/read-a-heatmap/ · https://support.worktango.com/hc/en-us/articles/26561274937875 · https://support.dialog.nl/hc/en-gb/articles/5421568752274 · https://help.leapsome.com/hc/en-us/articles/7652069033757-Working-with-an-Anonymity-Threshold
- Riesgo: rojo/verde para bueno/malo; usa un solo matiz y etiquetas "Fortaleza / Oportunidad".

**5.2 Factores ordenados por impacto, con lectura en frase.** Lista de drivers: nombre, puntaje, diferencia vs línea base, y una frase ("Carga de trabajo es donde más puede mejorar el clima"). Prioriza dos o tres, no veinte. Riesgo: correlación leída como causa; incluye nota "asociación, no causa".

**5.3 Tendencia con sparkline y línea base.** Sparkline fino en cada fila de factor, punto actual en jade, línea punteada de la encuesta anterior; solo si ≥2 mediciones cada una con n≥5. Riesgo: sparkline sin ejes engaña con escala distinta por fila; usa mismo eje o muestra el delta en número.

**5.4 Compromisos como seguimiento.** Cada hallazgo se convierte en "Compromiso" con responsable y fecha; en la siguiente encuesta se muestra "Lo que dijiste y lo que hicimos". Cierra el ciclo y sube la participación futura. Riesgo: prometer públicamente; borrador visible solo para RH hasta publicar.

Descartado: rankings de gerentes/equipos, "quién respondió/falta" por persona, desglose cruzado de varias dimensiones sin recalcular el mínimo.

---
## 6. Vacaciones

**6.1 Cobertura antes de aprobar.** En la tarjeta de solicitud, mini-calendario del equipo en esas fechas con quién más falta y alerta textual "2 de 6 ausentes ese día" (mínimo de personal configurable). Es el patrón más repetido entre proveedores: ver choques antes de decidir.
- Fuentes: https://mta-sts.timetastic.co.uk/features/ · https://soon.works/product/leave-management · https://absentify.com/en/features
- Riesgo: sesgar la decisión por motivo; no muestres el motivo de otros permisos, solo "ausente".

**6.2 Bandeja de aprobaciones ordenada por urgencia (fecha de inicio), con aprobación en lote** y digest diario por correo con contador. Fuente: https://www.whosoff.com/tour/ . Riesgo: aprobar en lote sin ver cobertura: la barra masiva exige que ninguna seleccionada choque.

**6.3 Saldos con medidor simple.** Anillo/barra "8 de 12 días disponibles" con "proyección a fin de año" en texto; en México, antigüedad y prima vacacional explicadas en una línea. Riesgo: confundir días tomados/programados; usa dos segmentos.

**6.4 Solicitud en 3 toques móvil:** (1) elegir fechas en rango, (2) ver resultado inmediato "Te quedan 6 días; 1 colega ausente", (3) enviar. Motivo opcional. Riesgo: calendario de rango difícil con teclado; usa el selector nativo de fecha como alternativa.

Calendario de equipo: heatmap anual (densidad de ausencias) + vista Gantt por persona para el mes. Fuente: https://humand.co/en/blog/time-off-calendar-a-unified-view-for-the-entire-team/

---
## 7. Organigrama

**7.1 Abrir colapsado a 2 niveles, con expandir por nodo y "ajustar a pantalla".** Más de ~20-30 nodos requiere colapsar. Fuentes: https://creately.com/blog/hr-planning/how-to-make-a-large-org-chart-readable/ · https://www.syncfusion.com/diagram-sdk/organizational-charts
**7.2 Búsqueda que enfoca y resalta la ruta hasta la persona; "usar como raíz".** Fuentes: https://kb.pigment.com/docs/navigate-org-chart · https://www.functionly.com/help/how-to/org-chart/view
**7.3 Tarjeta de nodo con span de control** (reportes directos, indirectos, capas debajo) y chip textual si pasa de un rango configurable; mismo patrón de https://www.functionly.com/help/how-to/org-chart/view y validación de https://help.anaplan.com/180df665-9aac-4ca4-a5d8-51bddd3a7238
**7.4 Vista "calidad de datos"** como lista de pendientes (sin jefe, jefe circular, vacantes sin área) que lleva a corregir; no es un grafo. Minimapa solo en pantallas grandes (ej.: https://relation-graph-site.pages.dev/examples/business-unit-organizational-chart). Accesibilidad: navegación con flechas (arriba/abajo hermanos, izq/der colapsar): https://admin-docs-ai5.pages.dev/projects/accessible-org-chart/best_practice . En 375 px, lista indentada colapsable en lugar de lienzo.
Riesgo global: lienzo con pan/zoom inaccesible y costoso; empieza con el árbol de lista.

---
## 8. Compensación

**8.1 Modelador con costo vivo y delta contra línea base** (en pesos y %), entradas con sliders + campo numérico, presets (sin incremento, inflación, propuesta). Fuentes: https://www.deel.com/blog/compensation-management-employee-scenarios/ · https://www.anaplan.com/content/dam/anaplan/assets/documents/datasheet/anaplan-compensation-planning-and-modeling-datasheet.pdf
**8.2 Escenarios aislados de los datos reales, comparados lado a lado** (tarjetas A/B con filas alineadas), con "Aplicar este escenario" explícito. Fuentes: https://docs.oracle.com/cd/F13810_02/hcm92pbr29/eng/hcm/hhps/task_ForecastingCompensation-3e3dac.html · https://support.pave.com/articles/equity-slider-and-valuation-options-c0l6EAf8za
**8.3 Resultado en varios niveles, no un solo número** (p. ej. incrementos de 3/5/7%) en tabla. Riesgo: el costo en vivo salta de forma nerviosa: anima el número 150 ms y redondea; guarda escenario con nombre.

---
## 9. Transversales

**9.1 Tablas densas con acciones en menú de fila.** Con menos de 3 acciones, déjalas visibles; con más, menú de overflow persistente (no solo al hover, por táctil/teclado); la barra superior para acciones globales. Filtro activo siempre visible como indicador. Fuente: https://carbondesignsystem.com/components/data-table/style · https://carbondesignsystem.com/patterns/filtering
**9.2 Barra de acciones masivas contextual.** Aparece al seleccionar, muestra "3 seleccionados" y acciones; en móvil colapsa a un menú "Acciones". Fuentes: https://www.nngroup.com/videos/bulk-actions-design-guidelines/ · https://playbook.ebay.com/design-system/patterns/bulk-editing · https://www.saasui.design/blog/saas-bulk-actions-ux-patterns
**9.3 Filtros como chips removibles + pestañas con conteo.** Conteo de la pestaña = resultado real (con mínimo de 5 aplicado en datos anónimos). Fuente: Carbon filtering (arriba).
**9.4 Movimiento sobrio.** Micro-feedback ~100-150 ms, UI general ≤200 ms, salidas más cortas que entradas, respeta prefers-reduced-motion. Material usa pocas duraciones y curvas fijas como tokens; las cifras exactas vienen de design systems de terceros (convención, no norma): https://m1.material.io/motion/duration-easing.html · https://github.com/material-components/material-components-android/blob/master/docs/theming/Motion.md
**9.5 Estados cuidados:** esqueleto con forma real al cargar (no spinner para listas), éxito con texto y "Deshacer", vacío con siguiente paso. (No obtuve la fuente NN/g de skeleton vs spinner; confirmar en nngroup.com antes de citar.)
**9.6 Móvil 375 px:** tablas a tarjetas apiladas, acciones en hoja inferior, objetivos táctiles ≥44 px, un solo botón primario fijo abajo.

---
## Los 12 patrones con mejor relación impacto/esfuerzo para Humetría (ordenados)

1. **Tarjeta única "Siguiente paso" en el panel** (1.1): una tarjeta, un botón, estimado de tiempo. Poco código, el mayor efecto en completar flujos.
2. **Resumen de conteos clicable sobre toda lista paginada** (2.4 + 9.3): reusa tu paginador; da panorama y filtro en un clic.
3. **Empty states con forma + acción dentro** (1.3): transversal y barato; cierra pantallas "sin diseñar".
4. **Estado en texto + ícono + posición, un solo tono de riesgo** (2.2, 2.3): estandariza el "semáforo calmado" en chips reutilizables.
5. **Heatmap jade con celda protegida <5 y supresión complementaria** (5.1): diferenciador de confianza y de privacidad.
6. **Bandeja "solo excepciones" con corrección propuesta** (3.1): convierte asistencia en una cola corta.
7. **Cobertura del equipo dentro de la tarjeta de aprobación de vacaciones** (6.1): decide sin cambiar de pantalla.
8. **Wizard de una pregunta por pantalla para configurar centros/normas, con "saltar al resumen"** (2.1): cambia formularios por conversación.
9. **Menú de ffila + barra masiva contextual** (9.1, 9.2): una pieza compartida que sirve a las 9 áreas.
10. **Casos: lista con "esperando a" y "última actividad" + línea de tiempo solo-agregar** (4.1, 4.2): confianza y auditoría.
11. **Saldos con medidor y solicitud móvil en 3 toques** (6.3, 6.4): valor diario visible para la persona trabajadora.
12. **Modelador con delta vivo y escenarios lado a lado** (8.1, 8.2): alto valor percibido; esfuerzo medio, déjalo al final.

Descartados globalmente: gamificación, cuentas regresivas animadas, rojo/verde de clima, rankings de gerentes, cualquier vista que permita deducir una respuesta individual.
