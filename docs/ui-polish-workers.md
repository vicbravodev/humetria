# Afinaciones de UI: pantallas de la persona trabajadora

Sesión de revisión manual de las pantallas sin cuenta (portal `/me/{slug}`, encuestas por token, kiosco y dispositivo de
asistencia, canal de denuncias, reportes por enlace). Victor reporta, Claude confirma o descarta contra el código y,
cuando se pueda, contra el navegador. Nada de esto está arreglado todavía: es la lista de lo que hay que afinar.

Veredictos: **Confirmado** (reproducible por código o captura), **No confirmado** (no lo pude reproducir),
**Por verificar** (hipótesis sin comprobar en el navegador).

Criterios que se revisan en cada pantalla: móvil primero (375 px), «usted», áreas táctiles ≥ 44 px, contraste (texto
≥ 4.5:1, controles ≥ 3:1), claro y oscuro, estados vacío / error / cargando / enlace usado o vencido / campaña cerrada /
sin permiso / otra empresa (404, no 403). **Cualquier cosa que ligue a una persona con una respuesta anónima se marca
como crítica.**

| # | Reporte | Veredicto |
|---|---------|-----------|
| 1 | Crear llave (A1): tras «Crear llave» sale «No hay conexión con el servidor» aunque sí hay conexión | Confirmado: el servidor respondió 500 **después** de guardar la llave y la pantalla lo llama «sin conexión»; este 500 lo causó Valkey caído en el entorno local, pero el mensaje y el acoplamiento son del producto |
| 2 | Pestaña «DEMO» pegada al borde derecho encima de las tarjetas y del texto (celular) | **Propuesta aprobada.** Confirmado (captura de la encuesta: corta «con» de «calma y con sinceridad»); solo en la demo, que es la herramienta de venta |
| 3 | Inicio del portal: «Salir» como única barra fija abajo, pegada a la barra del navegador; pedido de un menú inferior como el de RH | **Propuesta aprobada.** Confirmado como incoherencia de jerarquía; se rediseña con 3 pestañas (Inicio, Pendientes, Cuenta) solo en primer nivel |
| 4 | «Este enlace ya no sirve» (y pantallas de estado parecidas): el contenido queda pegado arriba y media pantalla vacía | **Propuesta aprobada.** Confirmado (captura iPhone, oscuro): el marco alinea todo arriba; se centra en vertical, icono de 80 px y texto centrado |
| 5 | Bienvenida de la encuesta NOM-035: demasiado texto, todo al mismo peso | **Propuesta aprobada.** Confirmado: seis bloques de texto a 17–18 px sin agrupar; el contenido lo exige C-17, solo cambia la jerarquía |
| 6 | Guía I, pregunta 5 de 6 sale como «Amenazas?, o», parece incompleta | **Decidido: opción B.** El texto es literal de la norma (el «, o» es el conector de la lista del anexo); se agrupa la sección I en una pantalla |
| 7 | Al terminar la Guía I solo la marca «Enviada»: sin confirmación ni siguiente paso | **Propuesta aprobada.** Confirmado en captura y código: se remonta la bienvenida y cambia una etiqueta; se agrega una pantalla de paso entre guías |
| 8 | La interfaz de la persona se siente seca: sin movimiento | **Propuesta aprobada.** Confirmado en el código; se aplica el movimiento que ya define el sistema de diseño, sin dependencias nuevas |
| 9 | Pulso y clima: bienvenida con el mismo exceso y botones distintos a la NOM-035 | **Propuesta aprobada.** El aviso de la IA sale de la bienvenida y se dice en la pregunta abierta (el procesamiento sigue) |
| 10 | Pregunta 0 a 10 del pulso: los números se parten 7 + 4, mal alineados, y las anclas viven dentro del texto | **Decidido: opción A** (dos filas iguales con anclas y eco) |
| 11 | Cierre del pulso: la pantalla de envío es una nota y un botón sobre media pantalla vacía | **Propuesta aprobada** («Listo para enviar», corregido) |
| 12 | Cierre real del pulso («Encuesta enviada») distinto al de la NOM-035 y con el arco vacío | **Propuesta aprobada** (un solo cierre para las dos encuestas) |
| 13 | Ley Silla: la página mezcla tres tareas (consentimiento y preguntas de salud, confirmar medidas, avisar de un asiento) y el consentimiento parece «todo» | **Propuesta aprobada** (una tarea por pantalla, botones del mismo tamaño) |
| 14 | Ley Silla: la cabecera dice «Confidencial» sobre datos de salud que ven RH y la Comisión | **Propuesta aprobada** («Solo RH y la Comisión» en lo identificado, «Anónimo» en el aviso del asiento) |
| 15 | Aviso de asiento dañado: «No guardamos quién avisa» junto al nombre de la persona y con el puesto guardado (puestos de 2 y 3 personas) | **Propuesta aprobada: no guardar el puesto.** La ley no fija anonimato ni un mínimo; proporcionalidad de la LFPDPPP |
| 16 | Asistencia, Marcar: las acciones fijas ocupan 42 % de la pantalla y el contenido queda detrás | Confirmado (medido a 390 × 844); propuesta pendiente |
| 17 | Asistencia, Mi semana: hueco grande, «Volver a Marcar» como botón en el cuerpo y sin camino de vuelta al portal | Confirmado en el navegador; propuesta pendiente |
| 18 | Asistencia, acuerdo de registro: texto legal largo en una tarjeta con scroll bajo acciones que ocupan mucho | Confirmado; **propuesta aprobada** |
| 19 | Asistencia, «Verificando»: pantalla de carga sin diseño (el indicador chico, hueco grande y los pasos pegados abajo) | Confirmado; **propuesta aprobada** |
| 20 | Asistencia, «Entrada registrada»: demasiados bloques y desorden | Confirmado; **propuesta aprobada**. Incluye un error de fecha en el cambio de día |
| 21 | Asistencia, «Su semana»: mucha información, tres botones de navegación apilados y «Me faltó una marca» repetido por día | Confirmado; **propuesta aprobada** |
| 22 | Asistencia, corrección y objeción: formulario en línea que se desborda | Confirmado; **propuesta aprobada** |
| 23 | Zonas horarias: toda la plataforma usa una sola zona (Ciudad de México); Cancún, Hermosillo o Tijuana salen con la hora y el retardo equivocados | **Confirmado, crítico para el valor probatorio de la jornada.** Diseño decidido en `docs/superpowers/specs/2026-11-17-timezones-design.md`; se construye antes del pulido de asistencia |
| 24 | El 404 del checador (sin activar o revocado) usa el texto de las encuestas sobre «el mensaje que recibió» | Confirmado; prioridad baja; propuesta ✅ aprobada |
| 25 | Pantalla del checador (kiosco) en escritorio: buena base; el reloj sale del dispositivo | Confirmado en captura; ajustes menores pendientes |
| 26 | Marcar con el QR de otro centro dice «Ese código ya venció»: no distingue vencido de «otro centro»; y la demo manda a todos al checador de Oficinas | **Confirmado, causa raíz hallada** (registro de ngrok y base); propuesta pendiente |
| 27 | Marcar por primera vez pide la huella 3 veces y parece un error | **Propuesta aprobada** (aceptar y marcar en una firma, no gastar huella en lo que no procede, comprobante del escaneo de 3 minutos) |
| 28 | El QR del checador es genérico (negro sobre blanco, sin marca) | **Propuesta aprobada**, sujeta a la prueba de escaneo en varios teléfonos |
| 29 | Inicio del portal real: ocho tarjetas con descripciones de 3 y 4 líneas (más de 3 700 px) y «Salir» fijo a media página | **Propuesta aprobada** (Victor, 2026-11-17) |
| 30 | «Confirme que es usted» (prueba reciente): el botón principal va dentro de una tarjeta y el fijo es «Volver» | **Propuesta aprobada** (Victor, 2026-11-17) |
| 31 | Política de la empresa: 12 100 px de texto seguido y el botón al final | **Propuesta aprobada** (Victor, 2026-11-17) |
| 32 | Mis vacaciones: el mismo dato dos veces, formulario en línea y botón apagado | **Propuesta aprobada** (Victor, 2026-11-17) |
| 33 | Estados vacíos del portal (teletrabajo, solicitudes del equipo, resultados) | **Propuesta aprobada** (Victor, 2026-11-17) |
| 34 | Acuse de Ley Silla: el resultado (lo importante) es una etiqueta chica, el botón no ocupa el ancho y compite con «Acepto, quiero contestar»; la prueba sale en un diálogo | **Propuesta aprobada** (Victor, 2026-11-17) |
| 35 | Canal de quejas (`/c/{code}`): inicio, cuatro pasos, recibida y seguimiento no siguen el patrón de acción fija ni el avance fijo | Confirmado en el recorrido completo; propuesta con 7 tableros, pendiente |
| 36 | Página de trabajadores (`/workers/{token}`) | Confirmado: bien; sin cambios |
| 37 | Reportes por enlace: la cabecera de Resultados ocupa casi una pantalla y no coincide con la de Compensación | Confirmado; propuesta de una cabecera común ✅ aprobada |
| 38 | Marcar en modo remoto («en campo»): la pantalla de marcar no cambia según la modalidad | Confirmado en código; propuesta pendiente |

---

## 1. Crear llave: «No hay conexión con el servidor» tras guardarse la llave

**Reporte (captura, iPhone, oscuro, vía ngrok, rama `main`):** en «Adriana Moreno Álvarez, cree su llave de acceso» se
pulsa «Crear llave», el teléfono pide la huella y la pantalla queda con el aviso rojo «No hay conexión con el servidor.
Revise su señal e intente de nuevo.», teniendo señal.

**Veredicto: Confirmado.** Dos cosas distintas:

1. **Causa de este 500 (entorno, no producto):** el contenedor `humetria-valkey-1` estaba detenido y Laravel no
   resolvía el host `valkey` (`RedisException: getaddrinfo for valkey failed`). Se levantó con `sail up -d valkey`.
2. **Lo que sí es del producto:**
   - Registro de ngrok: `POST /me/demo-alba-del-bajio/key/options` 200 y luego `POST …/key` **500**. El log muestra
     `worker_access.credential_registered` y `worker_access.key_registered` **antes** de la excepción de Redis: la
     llave ya estaba guardada y la persona vio que falló. Si reintenta, crea una segunda (tope de 3, luego «Ya tiene 3
     llaves»).
   - `resources/js/lib/worker-access/key.ts:48,83`: `BY_STATUS[response.status] ?? 'failed'`, y `failed` se pinta como
     «No hay conexión con el servidor» (`key.ts:115`). Cualquier 5xx se le dice a la persona como falta de señal. Pasa
     igual en `entry.ts:82,116` y `proof.ts:81`.

**Dónde:** `resources/js/lib/worker-access/key.ts`, `entry.ts`, `proof.ts`; el efecto que corre tras guardar la llave
(el aviso «No fui yo» por cola, ver README de WorkerAccess) **por ubicar**: la excepción sale después de
`key_registered`.

**Propuesta (no hecha):**
- Un fallo nuevo `server` para respuestas 5xx, con texto honesto en «usted»: «Algo salió mal de nuestro lado. Si ya ve su
  llave en el dispositivo, intente entrar con ella; si no, vuelva a intentarlo en unos minutos.»; `failed` queda solo
  para la falta de red real (`fetch` que lanza).
- Que guardar la llave no falle si la cola está caída: lo que corre después del commit se reporta y se sigue
  (con prueba que simule la cola caída y exija 200 + llave creada).
- Aplicar lo mismo a entrar (`entry.ts`) y a la prueba reciente (`proof.ts`).

**Decisiones:** ✅ aprobada por Victor, 2026-11-17 (texto del mensaje 5xx nuevo, tal cual).

---

## 2. Pestaña «DEMO» sobre el borde de las tarjetas y del texto (celular)

**Reporte (dos capturas):** la pestaña vertical «DEMO» del borde derecho queda encima del extremo de las tarjetas y, en la
bienvenida de la encuesta, tapa letras del texto («calma y con|DEMO»).

**Veredicto: Confirmado.** `components/demo-mark.tsx`: `fixed top-1/2 right-0 z-50`, con ~24 px de ancho, sobre un
contenido que deja 16 px de margen (`px-4`): se montan ~8 px sobre lo que haya al borde derecho. El comentario del
componente dice que la pestaña va a media altura para no tapar encabezados y barras, pero no considera el texto del cuerpo.
Solo se ve en la demo, que es lo que se proyecta a clientes.

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):** reservar el ancho de la pestaña en la demo (en móvil, `padding-right` de la página
cuando `demo` es verdadero, desde `DemoMark`), o bajar la pestaña a un chip pequeño dentro del encabezado. Mi valor por
omisión: reservar el ancho, sin cambiar el aspecto.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): reservar el ancho, sin cambiar el aspecto.

---

## 3. Inicio del portal: «Salir» fijo abajo y navegación inferior

**Reporte (captura, iPhone, oscuro):** «Salir» sale como una barra ancha pegada a la barra del navegador; ¿tiene sentido el
icono y por qué así? Pedido de Victor: un menú como el de RH, una barra inferior con botones, en vez de todo en una
pantalla con scroll; y que en lo anónimo, o donde no haga falta, no se ponga.

**Veredicto: Confirmado como incoherencia de jerarquía, no como bug.** `PersonFrame` pega las acciones de la pantalla abajo
(`components/person-frame.tsx:37`, `sticky bottom-0`, regla del pulgar de los tableros P1 a P4). En el inicio la única acción
es «Salir» (`worker-access/leave-button.tsx`, `size="lg"`, `w-full`), así que una acción secundaria queda con el peso de la
principal y pegada a los controles del navegador. El icono `LogOut` con el texto es el estándar y está bien.

**Investigación (web, 2026-11-16):** Material: barra inferior para 3 a 5 destinos de primer nivel de igual importancia,
sin contenido que se desplace. Apple: prefiere la barra para las secciones más usadas y pide botones consistentes. Navegación
oculta: baja el descubrimiento (resúmenes que citan a NN/g; las cifras no se confirmaron contra la fuente), el combo de
visible + secundario oculto rinde mejor. Encuestas: la evidencia de «una pregunta por pantalla» es de proveedores; Pew
advierte de carga lenta; ninguna fuente probó ocultar la navegación. Portales de empleados para quien entra poco: sin
evidencia específica; esa parte es criterio.

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):**
- **Tres pestañas, las mismas para todas las personas:** Inicio (herramientas, con lo que espera acción primero),
  Pendientes (lo que espera acción de todas las herramientas; el dato ya existe en `section.pending`, sin contrato nuevo) y
  Cuenta (llaves, agregar este dispositivo y «Salir»).
- **Dónde aparece:** solo en los destinos de primer nivel (Inicio, Pendientes, Cuenta).
- **Dónde NO aparece:** pantallas de tarea (política, solicitud de vacaciones, checklist, prueba reciente, llave, enlace,
  entrada): llevan «Atrás» y su acción fija, nunca dos barras apiladas. **Nunca en lo anónimo** (encuestas por token,
  clima y pulso al contestar, Ley Silla al contestar, denuncias, kiosco, reportes por enlace): ni barra, ni nombre de la
  persona, ni enlaces al portal.
- Se quita de «Inicio» la barra fija de «Salir» y la tarjeta de la llave; pasan a Cuenta.

**Guardia de privacidad (crítica, con test):** una prueba que recorra las rutas de respuesta anónima y afirme que su
página no usa el marco con barra ni lleva el nombre de la persona.

**Referencia visual:** [lienzo de Claude Design](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt) (6 tableros en 375 px: hoy,
Inicio, Pendientes y Cuenta con barra, política como tarea y pregunta de encuesta anónima sin barra; el interruptor
«dark» de cada tablero muestra el modo oscuro). Los textos son de maqueta.

**Corrección (2026-11-16):** la barra de la persona usa la pieza de la barra móvil de RH (`mobile-tab-bar.tsx`, `glide-chips.tsx`): pastilla flotante de vidrio esmerilado con la cápsula primaria que se desliza; el lienzo se actualizó.

**Decisiones:** tomadas (3 pestañas y regla de dónde aparece, «Inicio», «Pendientes», «Cuenta»; barra como la de RH). Pendiente de afinar al
construir: el estado vacío de Pendientes («Está al día») y el conteo en la insignia de la pestaña.

---

## 4. Pantallas de estado: contenido arriba, media pantalla vacía

**Reporte (captura, iPhone, oscuro, `/me/{slug}/link/{token}` ya gastado):** «Este enlace ya no sirve» deja casi 60 % de la
pantalla vacía entre el texto y el botón; pide investigar cómo usar toda la pantalla y que estos avisos se vean naturales.

**Veredicto: Confirmado.** `PersonFrame` (`components/person-frame.tsx:34`) pone el contenido arriba
(`flex flex-1 justify-center … pt-6`: solo centra en horizontal) y la acción fija abajo; con poco contenido queda un hueco
muerto en medio. No es solo esa pantalla: nueve componentes usan el mismo patrón de estado con `ToneTile large`:
`worker-access/link-problem`, `link-continue`, `key-blocked`, `not-me-confirm`, `not-me-done` y
`attendance/person-problem`, `punch-done`, `kiosk-offline`, `week-screen` (los de asistencia están por revisar contra su
marco). También el cierre de la encuesta, `campaigns/survey/survey-done.tsx` («Gracias, terminó», captura de Victor del
2026-11-16: contenido arriba y la mitad inferior vacía) y su gemelo `engagement/survey/survey-done.tsx`.

**Investigación (web, 2026-11-16; la evidencia es de guías de sistemas de diseño, no de estudios):**
- El patrón común de un estado vacío o de error es mensaje + icono o ilustración opcional + una acción siguiente; la
  ilustración nunca va sin mensaje; una acción principal, máximo dos ([Polaris](https://polaris.shopify.com/components/empty-state),
  [SAP Fiori](https://www.sap.com/design-system/fiori-design-ios/v25-4/components/feedback/illustrated-message/usage),
  [Salesforce](https://v1.lightningdesignsystem.com/guidelines/empty-state)).
- En móvil, la guía que encontré centra el grupo en su contenedor; SAP define el mensaje ilustrado para una página
  completa con tamaño propio ([Basis](https://design.basis.com/components/empty-state)). No hallé una fuente que mida el
  «centrado óptico» ni la zona del pulgar: eso es criterio.
- Tono: lenguaje humano, decir qué pasó y qué hacer, sin culpar ([NN/g](https://www.nngroup.com/articles/error-message-guidelines/)).
  El texto actual ya cumple («No pasó nada con sus datos»).
- GOV.UK no tiene un patrón de «enlace vencido»; su página «no encontrada» pide que las direcciones apunten a páginas que
  existen ([patrón](https://design-system.service.gov.uk/patterns/page-not-found-pages)).

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):**
- Una propiedad `centered` en `PersonFrame` y `WorkerFrame`: el grupo (icono, título, texto) se centra en vertical en el
  espacio libre, un poco por encima del centro, con el icono más grande (de 64 a 80 px) y el texto centrado. Solo la usan
  las pantallas de estado; las de lista o formulario siguen arriba. Un solo cambio en el marco y una línea por pantalla.
- La acción sigue fija abajo, donde llega el pulgar. Una sola acción principal.
- Prueba de humo por pantalla de estado: el grupo queda entre el encabezado y la acción (sin hueco mayor que el grupo).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): centrado vertical, icono de 80 px y texto centrado; se aplica también al cierre de la encuesta («Gracias, terminó»; tablero «Final propuesto» del lienzo). **Referencia visual:** tableros «Hoy» y «Propuesta» en el [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 5. Bienvenida de la encuesta NOM-035: demasiado texto, todo al mismo peso

**Reporte (dos capturas, iPhone, oscuro, `/survey/{token}`):** la bienvenida tiene mucho texto; está bien que esté por ser
instrucciones, pero se pide jerarquía para que sea digerible, entendible y ordenada.

**Veredicto: Confirmado.** `components/campaigns/survey/survey-intro.tsx` apila título, propósito, aviso de
confidencialidad, cuatro puntos con icono, la lista de guías y «Comenzar» con el mismo tamaño (17–18 px) y sin agrupar: en
375 px son unas 14 líneas de lectura seguidas antes de llegar a las guías. El **contenido lo exige el criterio C-17**
(`docs/normas/nom-035/criterios.md:249`: objetivo, confidencialidad, sin respuestas correctas, contestar todo con sinceridad,
dos últimos meses); no se quita nada, solo se ordena. La bienvenida de clima y pulso (`engagement/survey/survey-intro.tsx`)
tiene la misma estructura.

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):**
1. Encabezado: línea pequeña («Evaluación NOM-035 · 2 guías»), título y propósito.
2. La confidencialidad sigue como bloque destacado, segundo.
3. «Antes de empezar»: los puntos juntos en una tarjeta, cada uno con una frase corta en negrita y el detalle en gris
   (sin respuestas correctas, los dos últimos meses, contestar todo).
4. Fecha límite y **tiempo estimado** como línea de datos aparte (el tiempo es información nueva; sale de `minutes` de cada guía).
5. Las guías en tarjeta numerada con su estado; «Comenzar» fijo abajo. Misma estructura en clima y pulso.

**Investigación (web, 2026-11-16; guías de la industria, no estudios; no hallé fuente sobre dónde poner la confidencialidad):**
- Decir el propósito y el **tiempo estimado** arriba, y mantener la bienvenida corta ([BlockSurvey](https://blocksurvey.io/features/increase-survey-participation-engaging-welcome-screen),
  [Polling](https://blog.polling.com/?p=3440)).
- En celular cabe de un tercio a un cuarto de la información de una pantalla de escritorio: texto conciso para evitar scroll
  ([Alida](https://insightcommunity.help.alida.com/enus/adhoc/surveys_html/authoring/mobile_surveys_best_practices.html)).
- NHS/GOV.UK: la página de inicio no debe describir todo el servicio, demasiada información distrae
  ([NHS](https://service-manual.nhs.uk/design-system/patterns/start-page)); aquí el contenido lo exige C-17, así que se
  compacta (frase corta en negrita + detalle en gris), no se quita.
- Pew: enlace único por persona sin acceso por contraseña ([Pew](https://www.pewresearch.org/methods/2015/06/11/tips-for-creating-web-surveys-for-completion-on-a-mobile-device/markdown));
  instrucciones solo si son esenciales (Ipsos, citado por terceros); barra de avance sencilla
  ([SurveyMonkey](https://www.surveymonkey.com/learn/survey-best-practices/mobile-friendly-surveys/)).

**Resuelto (2026-11-16): No confirmado.** El botón «Comenzar»/«Siguiente» en oscuro mide `rgb(60, 207, 189)` = `#3CCFBD`, el token `primary`, con opacidad 1 (medido en el navegador a 390 px); en las capturas del teléfono se ve más pálido por el rendimiento de color de la captura, no por la app.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): estructura, tiempo estimado y ancho de la pestaña «DEMO». **Referencia visual:** tableros «Bienvenida hoy» y «Bienvenida propuesta» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 6. Guía I, pregunta 5 de 6: «Amenazas?, o»

**Reporte (captura, iPhone, oscuro):** la pregunta 5 de la sección I de la Guía I dice «Amenazas?, o»; ¿es correcto?, ¿está
incompleta?

**Veredicto: Confirmado, y es literal de la norma.** El anexo de la NOM-035 escribe la sección I como **una sola frase con
lista** («¿Ha presenciado o sufrido alguna vez, durante o con motivo del trabajo un acontecimiento como los siguientes:
accidente…?, asaltos?, actos violentos…?, secuestro?, amenazas?, **o** cualquier otro…?»). El «, o» es el conector antes del
último elemento. Está idéntico en el DOF (`docs/normas/nom-035/fuentes/dof-2018-10-23-nom-035.txt:654`), en el texto de
referencia (`docs/normas/nom-035/texto-de-referencia.md:24`) y en el código
(`app/Modules/Instruments/Support/Nom035/GuideOne.php:46`); el criterio C-21 exige cada pregunta igual al anexo y lo prueba
`tests/Feature/Instruments/Nom035DefinitionsTest.php`. El enunciado sí aparece, en el recuadro de arriba. Lo que falla no es
el texto sino la presentación: al mostrar un elemento de lista por pantalla, el conector queda colgando y parece cortado.

**Opciones (decisión de Victor):**
- **A. Dejarlo literal.** Cumple C-21 y la regla «copiar tal cual»; se queda la rareza en una pregunta.
- **B. Agrupar la sección I en una pantalla:** el enunciado y los seis renglones con Sí / No, como en el papel. El texto es
  100 % literal y el «, o» se lee natural. Va con lo que recomienda Pew (agrupar preguntas del mismo tema). Rompe la regla
  del sistema de diseño de una pregunta por pantalla en móvil, y son seis eventos de violencia a la vista a la vez (tono).
- **C. Quitar «, o» solo al mostrarlo.** No se recomienda: cambia el texto de la norma y obliga a tocar C-21 y su prueba.

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha): opción B**, solo para la sección I de la Guía I: el enunciado y los
seis renglones con Sí / No en una pantalla, con el texto literal. Excepción acotada a la regla de una pregunta por pantalla en
móvil (decisiones del sistema de diseño); va con una prueba que fije que el texto sigue igual al anexo (C-21).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): B. **Referencia visual:** tablero «Guía I, sección I en una pantalla» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 7. Al terminar la Guía I solo se marca «Enviada»

**Reporte (captura, iPhone, oscuro):** al terminar la Guía I la mandó a la bienvenida con la guía «Enviada», sin avisar que
terminó ni ofrecer seguir o no.

**Veredicto: Confirmado.** `survey-flow.tsx` se remonta tras enviar (su comentario lo dice: «so the person sees it marked
"Enviada" before going on») y el único cambio visible es la etiqueta de estado y el texto del botón («Continuar con la Guía
III»). No hay confirmación, ni qué sigue, ni cuánto falta, ni la opción de dejarlo para después. El cierre completo sí tiene
pantalla (`survey-done.tsx`, arco al 100 % y «Gracias, terminó»), pero el cierre de una guía no.

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):** una pantalla de paso entre guías, solo cuando la persona acaba de enviar una y quedan
otras: arco de avance (1 de 2), título «Guía I enviada», texto «Gracias. Le falta la Guía III (unos 15 minutos). Puede seguir
ahora o volver después con este mismo enlace.», botón principal «Continuar con la Guía III» y secundario «Seguir después».
Invariante de privacidad: las mismas palabras para todas las personas; nunca dice si alguien necesita atención clínica (como
`survey-done.tsx`).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16). **Referencia visual:** tablero «Guía enviada» (con animación al cargar) en el [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 8. La interfaz de la persona se siente seca: sin movimiento

**Reporte:** pide animaciones para que se sienta suave y de la calidad del resto de Humetría.

**Veredicto: Confirmado.** En las pantallas de la persona solo se anima el color y el fondo de las opciones
(`survey-question.tsx:51`, 150 ms). El avance entre preguntas, el progreso, el estado «Enviada» y las pantallas de estado
aparecen sin transición. El sistema de diseño ya define el movimiento (`docs/design-system/design-system.md`, «Movimiento»):
curvas fuertes sin rebote (`ease-out`), 100–150 ms al presionar, 220 ms de entrada y 120 de salida entre páginas, ninguna
interfaz sobre 300 ms, y con `prefers-reduced-motion` solo desvanecidos. Pero las transiciones entre páginas son solo del
panel (View Transitions) y el comportamiento dentro de una misma ruta, como las preguntas de la encuesta, no se anima.
`motion` y `tw-animate-css` ya están instalados (la barra móvil de RH y la landing usan `motion`; sin dependencias nuevas).

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha), con las reglas del sistema:**
- Cambio de pregunta: sale en 120 ms y entra en 220 ms (desvanecido y 4 px hacia arriba), `ease-out`.
- Barra de avance: la relleno crece en 300 ms.
- Opción elegida: marca que aparece en 150 ms; presionar con `scale(0.99)` en las opciones.
- «Enviada» y la pantalla de paso entre guías: el icono entra con desvanecido y escala desde 0.96 en 200 ms; el arco crece a su nuevo valor en ≤ 300 ms.
- Pantallas de estado: icono entra en 200 ms. Cierre de la encuesta: el arco (`ArcMeter`, el arco del logo) crece a 100 % en 300 ms. **Corrección al lienzo (2026-11-16):** el paso entre guías y el cierre usan el arco de marca de `ArcMeter` (con «1/2» dentro), no un círculo.
- Con `prefers-reduced-motion`: solo desvanecidos, sin desplazamientos ni escalas. `MotionConfig reducedMotion="user"`
  como en `mobile-tab-bar.tsx`; la CSP con nonce se respeta igual.

**Nota de coherencia con el hallazgo 3 (✅ aprobada por Victor, 2026-11-16: usar la pieza de RH; el lienzo ya lo muestra):** la barra móvil de RH (`mobile-tab-bar.tsx`) es una pastilla flotante de vidrio
esmerilado con una cápsula que se desliza entre pestañas (`glide-chips.tsx`). La barra de la persona debe usar esa misma
pieza, no la barra plana del lienzo (que se corrige).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16). **Referencia visual:** la nota «Movimiento» y el tablero «Guía enviada» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 9. Pulso y clima: igualar la bienvenida y las acciones a la NOM-035

**Reporte (captura, iPhone, oscuro, `/engagement/{token}`):** la bienvenida del pulso tiene el mismo problema de jerarquía;
igualarla a los lienzos nuevos con las mismas reglas.

**Veredicto: Confirmado** (captura y `components/engagement/survey/survey-intro.tsx` y `survey-navigation.tsx`; medido en el
navegador a 390 px). Diferencias con la NOM-035 que conviene cerrar:
- Es la misma pared de texto a un solo peso. La divulgación de que **un proveedor de inteligencia artificial procesa lo que se
  escriba** va como un punto más; es una divulgación de datos y merece estar a la vista.
- En la bienvenida salen «Anterior» (deshabilitado, opacidad 0.5, sin sentido en la primera pantalla) y «Siguiente»; la
  NOM-035 usa «Comenzar». La fecha dice «25 de noviembre» y la NOM-035 «4 dic 2026»: dos formatos.
- Las acciones van dentro del contenido y no fijas abajo como en la NOM-035; «Omitir pregunta» es un botón de contorno de
  ancho completo, con el mismo peso que «Siguiente» (tres acciones compitiendo).
- La cabecera dice «Confidencial» pero el texto evita prometer secreto (el comentario del componente: «no promise of
  secrecy»). **Por decidir:** el texto de la caja dice lo que sí es cierto (no guarda quién contestó, grupos de 5); ¿se
  conserva «Confidencial» en la cabecera?
- La pestaña «DEMO» vuelve a cortar texto («abiertas») a 390 px: hallazgo 2.

**Propuesta (por aprobar; no hecha):** la misma estructura del hallazgo 5: línea de contexto («Pulso · 10 preguntas»),
título, propósito en una línea, caja de anonimato destacada, tarjeta «Antes de empezar» con frases en negrita (la divulgación de
la IA como su propia fila: «Un proveedor de inteligencia artificial resume sus comentarios»), chips de tiempo y fecha
(«25 nov 2026»), «Comenzar» fijo abajo. En las preguntas: acciones fijas abajo, «Anterior» y «Siguiente» en una fila y
«Omitir pregunta» como acción de texto debajo, con menos peso. Mismo movimiento (hallazgo 8). Va igual en clima.

**Referencia visual:** tableros «Pulso: bienvenida propuesta» y «Pulso: pregunta de acuerdo» del
[lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**Decisiones (2026-11-16):** ✅ aprobada por Victor: la estructura y las acciones (igual que la NOM-035); la cabecera conserva
«Confidencial». **Aviso de la IA:** Victor pidió quitar el aviso de la bienvenida y decirlo en otro lugar; se interpreta como
**moverlo a la pregunta abierta**, donde la persona escribe («Un proveedor de inteligencia artificial resume los comentarios.
Por eso, no escriba su nombre ni datos que lo identifiquen.»; la segunda frase es copia nueva, ✅ aprobada por Victor, 2026-11-16). El procesamiento
sigue siendo real (`app/Modules/Engagement/Agents/CommentSummarizer.php` manda los comentarios abiertos al proveedor de
`humi.provider`), así que el aviso no desaparece: **el aviso de privacidad de la empresa también debe decirlo** (lo lleva el
dueño). Referencia: tablero «Pulso: pregunta abierta con el aviso de la IA» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 10. Pregunta 0 a 10 del pulso: números partidos 7 + 4 y anclas dentro del texto

**Reporte (captura, iPhone, oscuro, pregunta 4 de 10):** «están bien pero hay mejores formas»; pide investigar las mejores
prácticas de Typeform y otras herramientas de formularios.

**Veredicto: Confirmado.** `recommend-question.tsx` usa 11 píldoras de 44 px dentro de `PillChoice`, que se parten en filas de
7 y 4 según el ancho; las anclas («0 = nada probable, 10 = muy probable») van dentro del texto de la pregunta, no en la escala.
Las preguntas de acuerdo (cinco opciones en filas completas, `agreement-question.tsx`) ya siguen lo recomendado.

**Investigación (web, 2026-11-16; son foros, guías de proveedores y pocos estudios, ninguna fuente oficial de Typeform sobre
tamaños):**
- Typeform: en móvil la escala de 0 a 10 se parte en dos filas, y personas de su comunidad advierten que en algunos
  dispositivos parece que el 5 es el máximo; sin arreglo confirmado
  ([comunidad Typeform](https://community.typeform.com/build-your-typeform-7/labels-not-beneath-option-scale-anymore-on-mobile-2521)).
  Tampoco hay fuente oficial de Typeform sobre tamaños.
- Mobile: los proveedores recomiendan una sola columna vertical, sin cuadrículas anchas; no se acepta el scroll horizontal
  ([SurveyMonkey](https://www.surveymonkey.com/learn/survey-best-practices/mobile-friendly-surveys/),
  [Forsta](https://www.forsta.com/?p=34798)). La investigación sobre orientación (vertical contra horizontal) encuentra
  resultados parecidos ([Survey Practice](https://www.surveypractice.org/article/3027)). Un caso de estudio rechazó una
  lista vertical de NPS por apartarse de lo habitual y temer imprecisión (una sola prueba, anécdota)
  ([Medium](https://medium.com/@elizabethsthomas93/ui-uplift-of-the-in-app-nps-survey-design-for-moneyview-f4b77d8eedaa)).
- Anclas: sin etiquetas en los extremos los números son ambiguos, y en algunas herramientas se pierden en móvil
  ([Sogolytics](https://www.sogolytics.com/learning-center/surveys/survey-rating-scales/),
  [HubSpot](https://community.hubspot.com/t5/Customer-Feedback-Tool/Problem-with-feedback-survey-on-mobile-device/m-p/494453/highlight/true)).
- Orden: poner primero la opción más positiva sube cuánto se elige (81.7 % contra 78.8 %): conviene el orden creciente
  habitual ([Survey Practice](https://www.surveypractice.org/article/3027)).
- La escala 0 a 10 no se cambia si se quiere comparar con la industria (eNPS)
  ([Sogolytics](https://www.sogolytics.com/learning-center/surveys/survey-rating-scales/)).
- Tamaño de toque: el sistema de diseño ya fija 44 px de alto y de ancho (`touch-min`); una sola fila de 11 queda en ~31 px
  por botón en 375 px, así que no cumple.

**Opciones (por decidir):**
- **A. Dos filas iguales** (0 a 5 y 6 a 10), celdas de 52 px, con «0 Nada probable» arriba y «10 Muy probable» abajo y una
  línea de eco («Su respuesta: 9 de 10») para que nadie dude de lo elegido. Conserva la forma de los NPS; el riesgo que cita
  Typeform es leer el 5 como el máximo, y lo cubren las anclas y el eco.
- **B. Lista vertical de 11 filas**, de 0 a 10 de arriba abajo (orden creciente), con las anclas en el 0 y el 10. Sin duda de
  lectura y con áreas amplias, igual que las preguntas de acuerdo; es más larga y hay que bajar.
- En escritorio (desde `md`): una sola fila de 11 con las anclas a los extremos.
- En las dos: se mantiene 0 a 10, el texto de la pregunta sin los paréntesis (las anclas pasan a la escala; cambio de texto
  de producto, no de la norma), y la opción elegida con el color primario.

**Referencia visual:** tableros «Escala 0 a 10, opción A» y «opción B» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): **A**. El texto de la pregunta pierde los paréntesis; las anclas pasan a la escala.

---

## 11. Cierre del pulso: el paso de envío es una nota y un botón sobre media pantalla vacía

**Reporte (dos capturas, iPhone, oscuro):** el cierre es súper sencillo y no se ve de la calidad de Humetría.

**Veredicto: Confirmado.** Tras la pregunta 10 de 10 el paso `submit` (`components/engagement/survey/survey-step.tsx:36-41`)
muestra una `SurveyNote` («Ya terminó la encuesta. Cuando envíe sus respuestas ya no podrá cambiarlas. Si quiere revisar algo,
vuelva con «Anterior».») y «Anterior» / «Enviar mis respuestas» a media altura, con más de la mitad de la pantalla vacía. Es el
mismo problema de las pantallas de estado (hallazgo 4), en el momento más importante de la encuesta: el envío que ya no se puede
cambiar. Además la última pregunta (10 de 10) dice «Siguiente» aunque lo que sigue es el envío.

**Propuesta (por aprobar; no hecha):** un paso «Listo para enviar», centrado, con acciones fijas abajo (el tablero se corrigió el 2026-11-16: el arco usa la geometría real de `ArcMeter` —caja de 156 × 113 px, `viewBox` `0 5 64 46`, porcentaje y conteo dentro—; la primera versión desbordaba el arco):
- Arco de marca con cuánto contestó («90 %» y «9 de 10» dentro), que crece en 300 ms.
- Título «Listo para enviar». Texto: «Contestó 9 de 10 preguntas. Cuando envíe sus respuestas ya no podrá cambiarlas.»
- Una línea con candado: «El sistema no guarda quién contestó qué.»
- Si omitió preguntas, un enlace «Revisar la pregunta que omití».
- Abajo, fijo: «Anterior» y «Enviar mis respuestas» (con indicador de envío); la última pregunta pasa a decir «Revisar y enviar».
- Al enviar, el cierre «Gracias, terminó» del hallazgo 4 (tablero «Final propuesto»).
- Privacidad: el paso solo cuenta preguntas contestadas y omitidas; no muestra ni envía respuestas.

**Referencia visual:** tablero «Pulso: paso de envío propuesto» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16).

---

## 12. Cierre real del pulso («Encuesta enviada»): otro diseño que el de la NOM-035

**Reporte (captura, iPhone, oscuro):** hay un «Gracias» pero no se siente parte del diseño.

**Veredicto: Confirmado.** `components/engagement/survey/survey-done.tsx` usa `StatusMessage` (icono de check dentro de un arco
vacío, «Encuesta enviada», «Gracias. Sus respuestas ya se enviaron.») centrado en vertical; el cierre de la NOM-035
(`campaigns/survey/survey-done.tsx`) usa el arco lleno al 100 %, «Gracias, terminó» y va arriba. Son **dos cierres distintos para
lo mismo**, y el del pulso deja el arco vacío con un check chico, que se lee como inconcluso.

**Propuesta (por aprobar; no hecha):** un solo cierre para las dos encuestas (el tablero «Final propuesto»): arco lleno que
crece a 100 % con el check, título, texto y, en la NOM-035, el aviso de atención; el pulso conserva su texto («Encuesta enviada»
como línea de contexto) y no lleva el aviso de atención clínica.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16).

---

## 13. Ley Silla: tres tareas en una página y el consentimiento que parece «todo»

**Reporte (capturas, iPhone, oscuro, `/me/{slug}/seating`):** aparece una pantalla de «Acepto, quiero contestar» que parece
ser todo, pero más abajo hay otra tarea (avisar de un asiento dañado); pide separarlas o resolverlo con el criterio de la norma y
el nuevo diseño. Y, tras contestar, que la segunda pantalla siga el mismo diseño.

**Veredicto: Confirmado.** `pages/seating/portal.tsx` junta en una sola página: la confirmación de las medidas
(`PortalConfirmation`, solo si hay evaluación firmada), el consentimiento y las dos preguntas (`PortalIntro`, `PortalQuestions`,
`PortalSent`, con una máquina de estados `intro / questions / declined / sent`) y, debajo y siempre, el aviso de asiento
(`PortalSeatReport`). Son tareas distintas, con distinto carácter: las preguntas de salud son **identificadas y voluntarias**
(criterio C-18: aviso que explica para qué se usa y que es voluntario; si no contesta, la evaluación sigue sin esos datos); el
aviso del asiento es **anónimo**. Ver el consentimiento arriba y otra tarea debajo hace creer que lo de arriba es todo.
`PortalSent` además mezcla «Gracias, quedó enviado», «Volver a contestar» y el aviso del asiento en la misma pantalla.

**Investigación (web, 2026-11-16; guías de práctica y de privacidad, no estudios de UX):**
- El consentimiento por separado de la recolección, con un acto afirmativo distinto y sin casillas premarcadas
  ([guía de consentimiento de salud](https://terms.law/washington-ai-health-tool-consent-flow/)).
- Que lo opcional se vea opcional y que negarse no sea una fricción (un seminario sobre patrones engañosos en salud:
  [Clark Hill](https://media.clarkhill.com/wp-content/uploads/2026/07/05094943/Dark-Patterns-in-Healthcare-webinar.pdf)).
- Divulgación progresiva dentro de la misma pantalla y cuidado con el scroll interminable en consentimientos móviles
  ([ICF](https://www.icf.com/insights/technology/improving-smartphone-based-information-ux)). Hay un caso que advierte que
  mejorar el diseño de un consentimiento no siempre mejora que la persona entienda
  ([PETS 2022](https://petsymposium.org/popets/2022/popets-2022-0086.php)): el texto debe ser corto y claro, no solo bonito.
- Un centro de tareas con secciones «lo que necesita atención» y «dónde empezar» es el patrón de un portal de empleados
  ([Oneteam](https://help.oneteam.io/en/articles/9246175-what-is-the-hub-in-oneteam)); no hallé una fuente que decida entre una
  tarea por pantalla y pestañas.

**Propuesta (por aprobar; no hecha):**
- La página pasa a ser una **lista de tareas**, con el mismo patrón del inicio: «Espera su confirmación» (confirmar las medidas,
  con insignia, solo si hay) y «Usted decide» («Cuéntenos de su trabajo de pie · 2 preguntas de salud · Es voluntario» y «Avisar
  de un asiento dañado · Es anónimo»).
- Cada tarea abre su propia pantalla con «Atrás» y la acción fija abajo, sin barra: el aviso y el consentimiento, cada pregunta,
  «Gracias, quedó enviado» (centrado, arco con check, «Volver a Ley Silla» y «Cambiar mis respuestas») y el aviso del asiento.
- «Acepto, quiero contestar» y «Prefiero no contestar» del mismo tamaño, uno primario y el otro de contorno: negarse no debe
  costar más (hoy es un enlace de texto).
- El aviso de privacidad conserva el texto íntegro (lo edita la empresa, C-18), con jerarquía: título «Quién ve sus respuestas» y
  texto legible; no se resume.
- La fecha «16 nov 2026» (hoy «16 de noviembre de 2026», formato distinto del resto).

**Referencia visual:** fila «Ley Silla: una tarea por pantalla» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16).

---

## 14. Ley Silla: «Confidencial» sobre datos que ven RH y la Comisión

**Veredicto: Confirmado.** `PortalShell` pone «Confidencial» en la cabecera de toda la página de Ley Silla. Para las preguntas de
salud es engañoso: el aviso dice que las verán el área de Recursos Humanos y la Comisión de su centro; son datos identificados.
En el resto de Humetría «Confidencial» es de las encuestas anónimas.

**Propuesta (por aprobar):** en las pantallas identificadas la etiqueta dice **«Solo RH y la Comisión»**; en el aviso del asiento
(anónimo) dice **«Anónimo»**.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16).

---

## 15. Aviso de asiento dañado: «No guardamos quién avisa» y el puesto guardado

**Veredicto: Por verificar, posible crítico de privacidad.** Lo que sí es cierto en el código:
`app/Modules/Seating/Actions/ReportSeat.php` no guarda a la persona (la tabla `seat_reports` no tiene `employee_id`), y el log
`seating.seat_reported` lleva solo el id del reporte y el centro. Pero guarda el **puesto** de pie vigente de la persona
(`position_id`), y en la demo cada puesto tiene 2, 2 y 3 personas (mínimo por grupo del producto: 5): quien lee el reporte (RH y
la Comisión) puede acotar quién avisó a 2 o 3 personas, y a una sola si el puesto tiene un único titular. Además, la página del
aviso se ve con el nombre de la persona arriba (`Su trabajo de pie, <nombre>`).

**Base legal (el mínimo de 5 no es de la ley):** las Disposiciones de bipedestación (DOF 17-jul-2025, Sexta, E) dicen que la
persona debe «informar a la persona empleadora, **a través de la comisión de seguridad e higiene**, sobre anomalías del asiento
[…] con el fin de que se proporcione mantenimiento o se reemplace»; el criterio C-12 lo recoge. La norma **no exige que el aviso
sea anónimo ni fija un mínimo de personas**: el mínimo de 5 es una regla del producto para los resultados agregados
(CLAUDE.md, «Privacidad»), no de Ley Silla. Lo que sí rige es el principio de proporcionalidad de la LFPDPPP: guardar solo lo
necesario para el fin. El fin aquí es reparar o cambiar un asiento; para eso hace falta saber **qué asiento y en qué centro**, no
quién avisa ni de qué puesto es. (Esto no es asesoría legal: el criterio C-18 deja el aviso y la base legal al abogado de la
empresa.)

**Propuesta (✅ aprobada por Victor, 2026-11-16; no hecha):** dejar de guardar el puesto de quien avisa (`position_id` de `ReportSeat`); el reporte
conserva el centro y lo que la persona escribe (la pantalla ya pide «cuál asiento y qué le pasa»), y RH puede ligarlo a un asiento
(`seat_id` ya existe). Así la promesa «No guardamos quién avisa» es cierta sin depender de un número que escogimos, y no
hace falta un mínimo. Quitar el nombre de la pantalla del aviso (el tablero del lienzo ya no lo lleva). Con una prueba que fije que
el reporte del portal no guarda puesto ni persona. Alternativa: guardar el puesto y cambiar el texto a «La Comisión puede saber de
qué puesto se avisa» (la promesa se vuelve honesta pero se pierde el anonimato).

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-16): **no guardar el puesto** de quien avisa; sin mínimo de 5 (no es de la ley).

---

## 16. Asistencia, Marcar: las acciones ocupan casi la mitad de la pantalla

**Reporte:** abrió asistencia (`/a/{slug}`); se revisa a 390 × 844, oscuro, en el navegador.

**Veredicto: Confirmado.** La barra de acciones fijas mide **353 px de 844 (42 %)**: cuatro botones apilados («Marcar entrada» de 56 px y
«Salir a comer», «Regresar de comer» y «Marcar salida» de 52 px, todos a todo el ancho) más una nota. El contenido (aviso de ubicación y
tarjeta de la llave) queda con unos 300 px y pasa por debajo; el enlace **«Mi semana» queda escondido al final** de la página. A
375 × 667 la barra sería más de la mitad de la pantalla. Además los dos avisos son largos: el de la ubicación (una divulgación de datos)
y las instrucciones para quien aún no tiene llave se leen antes de poder marcar.

**Por qué hay cuatro botones:** la página no sabe quién es la persona hasta que firma con su llave (cada marca lleva su firma), así que
no puede mostrar solo la acción que sigue. La propuesta no depende de eso.

**Propuesta (por aprobar; no hecha):** acciones en cuadrícula 2 × 2 y sin repetir el peso: «Entrada» (primaria, con huella) y «Salida»
(tonal, borde primario) arriba, y «Salir a comer» y «Regresar de comer» (contorno) debajo, con la nota en una línea; la barra baja a unos
190 px. «Mi semana» sube a una tarjeta al inicio del contenido. El aviso de la ubicación y las instrucciones de la llave pasan a
secciones que se abren («Sobre su ubicación», «¿Aún no tiene su llave?») con el texto íntegro adentro, sin quitar nada. Mismo marco, cabecera y
movimiento que el resto.

**Aclaración tras leer el código (2026-11-16):** la pantalla medida es el estado **«nadie conocido en este navegador»**, el único en el
que se ofrecen los cuatro pasos. El servidor ya dice cuál sigue (`MarkType::allowedAfter`): el navegador recuerda 10 minutos quién marcó
último, «solo para ofrecer su siguiente paso» (`MarkController`), y entonces `PunchReady` muestra el saludo, el estado del día, sus marcas y
**un botón grande con el paso que sigue** y los demás más chicos. Así que la mayoría de las veces (la primera marca del día) se ve la pantalla
de cuatro botones. Las pantallas de **cada acción** están en `punch-screen.tsx`: progreso de la marca (`PunchProgress`), marca hecha
(`PunchDone`), problema (`PunchProblemView`) y acuerdo (`PunchAgreement`); por revisar una por una.

**Investigación (web, 2026-11-16; documentación de proveedores y blogs, ningún estudio):**
- Patrón común: estado a la vista arriba («Dentro», «Fuera», «En descanso») y **una sola acción grande según el estado**, con las demás
  secundarias o que aparecen solo cuando tienen sentido ([TimeCloud](https://help.jointimecloud.com/how-to-use-the-time-clock),
  [Sumo Payroll](https://docs.sumopayroll.com/docs/1.0/create-timeclock-mobile)). ExakTime desactiva «Parar» si no detecta una entrada, para
  evitar errores. Pantalla mínima de acciones: entrada, comida y salida ([ServiceTitan](https://help.servicetitan.com/docs/use-simple-timekeeping-in-the-servicetitan-field-mobile-app)).
- Confirmación tras marcar: mostrar el detalle de lo marcado y dejarla un tiempo con opción de cerrar
  ([Odoo](https://www.odoo.com/documentation/20.0/applications/hr/attendances/kiosks.html)); a quienes marcan por QR y por teléfono se les
  avisa del riesgo de duplicar ([Humanforce](https://help.humanforce.com/hc/en-au/articles/8387498374159-Clock-in-at-a-kiosk-using-a-QR-code-in-Humanforce-WFM-Web)); el color de éxito/error va con texto.
- Ubicación: pedirla solo al tocar una acción, explicar antes por qué y qué pasa si no se da, y planear la recuperación si se bloquea
  ([web.dev](https://web.dev/articles/permissions-best-practices)). La pantalla ya la pide al marcar; lo que falta es el aviso corto antes.
- Sin evidencia sólida sobre cuánto debe durar la confirmación ni sobre accesibilidad del QR: esa parte es criterio.

**Propuesta refinada (por aprobar; no hecha):** (1) estado «nadie conocido»: cuadrícula 2 × 2 como en el lienzo (se conserva, por ser la
primera marca del día); (2) estado «conocido»: saludo, estado del día («Dentro desde las 8:03»), **un botón grande con el siguiente paso** y
los demás como acciones de texto; (3) cada acción con su pantalla de progreso, de marca hecha y de problema en el mismo diseño y movimiento
(hallazgos 4 y 8), y el aviso de ubicación como paso previo corto. Las pantallas (2) y (3) se mockean con las capturas de Victor.

**Referencia visual:** tablero «Asistencia: marcar, acciones en 2×2» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**Decisiones:** pendientes.

---

## 17. Asistencia, Mi semana: hueco, botón de regreso en el cuerpo y sin camino al portal

**Veredicto: Confirmado** (`/a/{slug}/week` en el navegador, 390 × 844, oscuro). Es una pantalla de estado: una tarjeta de texto y
«Volver a Marcar» como botón de contorno en el cuerpo, con más de la mitad vacía, y «Ver mi semana» fijo abajo (hallazgo 4). Las páginas de
asistencia llevan «Registro de jornada» en la cabecera, pero **ninguna enlaza de vuelta al portal** (la persona llegó desde la tarjeta
«Marcar»). **Por verificar:** si se puede volver al portal desde estas páginas (no encontré enlace).

**Propuesta (por aprobar):** centrar el grupo (icono grande de huella, título y texto), «Marcar» como «Atrás» en la cabecera y «Ver mi semana» fijo abajo;
y un camino de vuelta al portal en la cabecera de Marcar. Con la semana vista (tras la firma): confirmar u objetar y pedir una corrección
quedan para revisar con tu captura.

**Cambio en el lienzo (2026-11-17):** la cabecera de Marcar ya trae «← Mi portal» como camino de vuelta; el tablero lo muestra.

**Decisiones:** pendientes.

---

## 18 a 22. Asistencia: acuerdo, verificación, marca registrada, semana y corrección

**Reporte (capturas, iPhone, oscuro, 2026-11-16 y 17):** el acuerdo debe adecuarse al orden del nuevo diseño; la pantalla de carga que
sale antes del acuerdo (ubicación) hay que actualizarla; la pantalla de marca registrada y «Su semana» tienen muchísima información y
área de opciones, con bastante desorden; investigar quién ya resolvió esto y usar criterio propio.

**Datos que quedaron en la demo:** una marca de **entrada** de Adriana (2026-11-17 00:00 hora de México, «fuera de zona») y la
confirmación de su semana (2 a 8 nov, a las 00:02). Se borran al reiniciar la demo.

**18. Acuerdo de registro (confirmado).** `PunchAgreement`: el texto legal (seis secciones: cómo se registra, qué se guarda, quién lo
ve, cómo se corrige, cuánto tiempo se guarda y valor de prueba, art. 132 XXXIV de la LFT; `app/Modules/Attendance`) va dentro de una tarjeta
con su propio desplazamiento, bajo una barra fija de acciones. **Propuesta:** el texto íntegro (no se resume ni se oculta: es un acuerdo
con valor de prueba) como lista de secciones con título, fuera de la tarjeta, con «Acuerdo de registro · versión 1» como línea de contexto;
«Acepto» y «Ahora no» del mismo tamaño y peso visual (consentimiento: igualdad entre aceptar y rechazar,
[CNIL/EDPB citados por fuentes del sector](https://cookie-script.com/guides/ux-patterns-for-high-consent-rates/amp); la evidencia sobre cómo hacer legible un texto legal largo es escasa y es criterio mío).

**19. Verificando (confirmado).** Sin diseño de estado: un indicador pequeño arriba a la izquierda, un hueco de media pantalla y la lista de
pasos pegada al borde de abajo. **Propuesta:** el arco de marca como indicador (se dibuja y recorre), título y texto centrados, y los tres
pasos («Leyendo su ubicación», «Confirme con su huella, su rostro o su PIN», «Comprobando el código y la zona») en una tarjeta con su estado
escrito (hecho, en curso, pendiente) además del icono; las guías de pasos recomiendan etiquetas de texto y vertical con etiquetas largas
([Red Hat](https://ux.redhat.com/elements/progress-steps/guidelines), [UXPin](https://www.uxpin.com/studio/blog/design-progress-trackers/)).
Con movimiento reducido: quieto.

**20. Marca registrada (confirmado).** Hoy son nueve bloques seguidos (icono, título, hora, fecha, evidencia, aviso de fuera de zona, aviso
de que no se borra, «Su día», «Listo» y «Ver mi semana»). **Propuesta:** el icono, el título, la hora grande y la fecha en un solo grupo; la
evidencia como una etiqueta; **un solo aviso** (fuera de zona) con el tono de advertencia; «Su día» en una tarjeta; el pie «Su registro no se puede
borrar. Si hay un error, pida una corrección» como una línea con enlace; «Listo» como única acción principal y «Ver mi semana» como enlace. Detalle
de la confirmación según otros sistemas: mostrar lo marcado y dejarla un tiempo con opción de cerrar
([Odoo](https://www.odoo.com/documentation/20.0/applications/hr/attendances/kiosks.html)).
**Error encontrado:** la fecha de la pantalla («Lunes 16 de noviembre») sale del reloj del teléfono, que se actualiza cada 30 s
(`useNow(30_000)` en `punch-ready.tsx`), mientras que la hora es la de la marca (`00:00:07` del 17): al marcar justo al cruzar la medianoche
dice el día anterior con la hora del nuevo. Debe tomar la fecha de la marca. Con una prueba que cruce la medianoche.

**21. Su semana (confirmado).** Una página con: encabezado con el nombre otra vez, total con barras y leyenda de colores, «Su respuesta»
(estado y texto), «Sus horas» (cuatro filas, tres en cero), «Sus marcas» con **cada día abierto** (fecha, horas, evidencia, entrada y salida con
lápiz de unos 16 px y un botón «Me faltó una marca» **por día**), tres botones apilados de navegación («Semana anterior», «Semana siguiente»,
«Volver a Marcar») y, fijas abajo, «Confirmar mi semana» y «No estoy de acuerdo». **Propuesta:** una barra de semana con ‹ y › de 44 px en
lugar de tres botones; «Volver a Marcar» sube a la cabecera; el estado («Sin confirmar») va junto al título; el total en una tarjeta con las
barras; «Sus horas» se reduce a una línea («45 h ordinarias · 0 h extra») y solo se detalla lo que no es cero; los días plegados, con el
rango de horas y el total, y adentro las marcas con «Corregir» y **una sola** «Me faltó una marca»; fijas abajo «Confirmar mi semana» y
«No estoy de acuerdo» del mismo tamaño. Los sistemas de nómina usan desgloses diarios que se abren y se cierran
([Ajera](https://help.deltek.com/Product/Ajera/Mobile/Tiimesheets_Screen.html), [Workday](https://doc.workday.com/admin-guide/en-us/human-capital-management/time-tracking/reviewing-and-approving-time/nla1675283926780.html));
no hallé una fuente sobre el flujo de que la propia persona confirme su semana.

**22. Corrección y objeción (confirmado).** El formulario se abre dentro de la lista y se desborda (el campo de hora pasa el borde de la
tarjeta, y la tarjeta de objeción aparece bajo «Confirmada»). **Propuesta:** una hoja inferior (`FormSheet` del sistema de diseño) con una marca
a la vez, como en las herramientas de nómina que piden fecha, tipo y hora juntos y una nota
([Paycom](https://www.ymcamidtn.org/sites/default/files/2022-12/23-paycom-request-punch-change.pdf), [Empeon](https://help.empeon.com/ess-hub-how-to-request-a-missed-punch));
el tipo de marca en cuatro opciones de 48 px, la hora y el motivo; después de enviar, la solicitud queda a la vista con su estado
([FingerCheck](https://help.fingercheck.com/en/articles/11787454-employees-how-to-submit-a-punch-request-on-mobile-app)). La objeción de la
semana usa la misma hoja.

**Referencia visual:** fila «Asistencia: acuerdo, marca y semana» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt) (cinco tableros).

**Revisión de jerarquía de «Sus marcas» (2026-11-17, captura de Victor de un día abierto):** las filas «Entrada · 06:00 · Corregir» y «Salida ·
14:00 · Corregir» tenían el mismo peso, y «Me faltó una marca» quedaba suelto. Se rehízo: la **hora** en 19 px y negrita a la izquierda con
un punto y una línea de tiempo, la etiqueta («Entrada») en gris, el **lápiz de 44 px** a la derecha, la evidencia como línea pequeña
arriba y «Me faltó una marca» como un botón de contorno con «+» (una acción distinta, no una fila más). La etiqueta «Sin confirmar» pasó a la
derecha del título y a una sola línea.

**La «fuerza de la evidencia» (2026-11-17, captura de Victor del día abierto):** «Evidencia media» con tres puntos, flotando arriba de la
línea de tiempo, se sentía fuera de lugar y en desorden. Es una abstracción del reporte para la autoridad (criterio 5.3 de jornada: cada
marca guarda **cómo se hizo**, y el reporte de RH lo muestra con su leyenda, `report-legend.tsx`), no algo que la persona necesite para
confirmar su semana ni sobre lo que pueda actuar; y «media» sin explicación suena a que su marca vale menos. **Propuesta (por aprobar, ya en el
lienzo):** para la persona se quita la medida de puntos y se dice **cómo se hizo cada marca, en palabras y en gris, debajo de su etiqueta**
(«Entrada · Con QR», «Salida · Con ubicación», «Corregida por RH», «Remoto»); lo que sí pide atención sigue como aviso (fuera de zona). En
«Marca registrada» se quita la etiqueta con puntos y el dato pasa a la línea de «Su día». El reporte de RH conserva los puntos y su leyenda.

**Doble revisión de los tableros (2026-11-17, a 375 px, claro y oscuro, medido en el navegador):** sin desborde horizontal; áreas táctiles de 44 px o más
en los siete, salvo un enlace en línea de 40 px («pida una corrección», corregido con área ampliada); «45 h ordinarias · 0 h extra» se partía
(corregido en dos líneas); el aro del paso en curso era casi invisible en oscuro (corregido); la hoja de corrección quedaba a 7 px del borde
inferior (corregido). La barra de acciones de Marcar baja de 353 a **193 px**.

**Plan de estrés del error de la fecha (para el trabajo de implementación; las pruebas se escriben primero):** el fallo es que la fecha y el
saludo salen del reloj del teléfono (`useNow(30_000)`), no de la marca ni del servidor. Casos a cubrir, con una prueba cada uno: (1) marca a las
23:59:59 y a las 00:00:07 con el último «tick» del reloj 20 s antes; (2) reloj del teléfono adelantado o atrasado horas y días; (3) zona horaria
del teléfono distinta a la de la empresa; (4) **turno nocturno**: entrada 22:00 y salida 06:00 del día siguiente (`PunchDay` ya retrocede al inicio del
turno; falta que la pantalla y «Su semana» lo traten como un solo turno del día en que empezó); (5) entrada el domingo 23:xx y salida el lunes (cruce de
semana), y 31 de diciembre a 1 de enero (cruce de año y de semana); (6) la zona horaria de la empresa con cambio de horario (días de 23 y 25 horas);
(7) la corrección «fue después de medianoche, al día siguiente»; (8) doble toque y reintento tras «Verificando» sin duplicar la marca; (9) la fecha
de la confirmación de la semana y de la objeción. Quien las construya parte de que la fecha de «Marca registrada» sale de `marked_at`.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-17): las cinco propuestas y la corrección de la fecha de la marca (con el plan de estrés de arriba).

---

## 23. Zonas horarias: cada centro de trabajo con la suya

**Reporte (Victor, 2026-11-17):** ¿cómo se manejan las zonas horarias de México (clientes de Cancún, Monterrey, Tijuana)? Su idea: guardar
en UTC y convertir a la zona de la empresa o del centro.

**Veredicto: la idea es la correcta, y hoy se cumple solo la mitad.** Se guarda en UTC (`timestampTz`) y la hora de la marca la pone el
servidor, pero **la conversión usa una sola zona global** (`app.display_timezone`, `America/Mexico_City`, 197 usos; los 25 comandos
programados corren a esa hora) y ni `work_centers` ni `tenants` tienen zona. Evidencia en el código: `WorkedDay::lateMinutes` arma la hora del
turno con la zona global y la compara con el instante de la marca, así que una persona de **Tijuana** que entra a su hora sale con **120 minutos
de retardo**, y una de **Cancún**, con 60 de adelanto; `PunchDay`, `PersonWeek` y los reportes parten el día a la medianoche de Ciudad de
México; `attendance:close-days` cierra días y abre la confirmación de la semana con los cortes de Ciudad de México (en Tijuana la abre el domingo a las 22:15). Es crítico porque la jornada es un registro con valor
probatorio.

**Solución propuesta (completa en el diseño):** UTC para todo instante; **zona IANA por centro de trabajo** (con una por omisión en la empresa);
**cada marca guarda la zona con la que se tomó** (son de solo inserción y son el registro legal); horas trabajadas calculadas con los instantes
y no con el reloj de pared (días de 23 y 25 horas); fechas sin hora leídas en la zona del centro; programación por centro; **sin codificar el
horario de verano**: se confía en tzdata, porque desde la Ley de los Husos Horarios de 2022 solo hay horario estacional en la frontera norte y
las fuentes no coinciden en los municipios. Se elige por ciudad, no por estado.

**Decisiones (✅ Victor, 2026-11-17):** zona por centro de trabajo elegida sola desde el estado y el municipio (siempre visible y editable); zona guardada
en cada marca, la del lugar donde se marcó, y **marcar fuera de su centro por un viaje de trabajo no es un retardo**; lista de zonas de México; programación con
despachador cada 15 minutos e idempotencia por centro y día local; y todo **antes** del pulido de asistencia. Detalle en el diseño.

---

## 24. El 404 de un checador sin activar usa el texto de las encuestas

**Veredicto: Confirmado** (capturas de Victor, localhost y ngrok, `/a/{slug}/kiosk` sin la cookie de la pantalla). `lib/error-page.ts` usa los
mensajes «de encuesta» para las páginas de personas sin cuenta: «Puede que el enlace esté incompleto. Ábralo de nuevo desde el mensaje que recibió.»
Quien mira un checador sin activar o revocado no recibió ningún mensaje. **Propuesta (por aprobar):** para el checador, «Esta pantalla no está
activa. Pida a Recursos Humanos un enlace nuevo para activarla.» **Decisiones:** ✅ aprobada por Victor, 2026-11-17.

---

## 25. Pantalla del checador (kiosco), `/a/{slug}/kiosk`

**Reporte (captura de Victor, escritorio, oscuro, 2026-11-17):** se activó con un enlace nuevo emitido desde el panel de RH (revocar y emitir).

**Veredicto: Confirmado, es una buena base.** Dos columnas: el centro y el reloj arriba; a la izquierda «Escanee para marcar», la
instrucción y una tarjeta con «Cambia en 2 s · Un código nuevo cada 30 segundos» y su barra; a la derecha el QR de unos 540 px en una tarjeta;
abajo «Conectada» y el sello. Contraste y tamaños correctos para leer desde lejos. Ajustes menores:
- **El reloj sale del dispositivo** (`useNow(250)` de la tableta, con el formato de la zona del servidor): una tableta con la hora mal muestra una
  hora que no coincide con la de las marcas. Debe corregirse con la diferencia respecto a la hora del servidor, y la zona debe ser la del centro
  (diseño de zonas horarias). Entra al plan de estrés de ese diseño.
- **Falta revisar** otros tamaños: tableta vertical, proyector y pantalla de 1366 × 768 (el QR debe seguir ocupando lo que necesita sin cortarse).
- El contador «Cambia en N s» es informativo; si distrae, bajarle el peso (lo decide Victor al verlo en una pantalla real).
- **Por probar con la persona:** escanear, marcar con un código vigente, y con uno vencido (pantalla de problema).

**Decisiones:** pendientes.

---

## 26. «Ese código ya venció» cuando el código es de otro centro

**Reporte (Victor, iPhone, oscuro, 2026-11-17):** marcar «Salir a comer» con el código del checador sale con «Ese código ya venció», aunque lo hizo rápido.

**Veredicto: Confirmado, y no es un código vencido.** El registro de ngrok muestra tres intentos, cada uno con `GET /a/…/punch?q=…`, `POST …/punch/options` y
`POST …/punch` a los 4 a 8 segundos de abrir la página (dentro de la ventana de 30 s más una anterior de `config/attendance.php`), todos con **422** y el cuerpo
`{"reason":"qr_invalid"}`. La causa: el código del checador es de **Oficinas Querétaro** y Adriana es de **Planta Querétaro**. El código es un HMAC
del centro y de la ventana de tiempo (`QrToken`), así que no valida en otro centro, y el servidor responde la misma razón `qr_invalid` para «venció»
y para «no es de su centro». La pantalla (`punch-problem.ts:101`) dice solo «Ese código ya venció». En un intento se reabrió la misma dirección con el mismo
`q` (código ya viejo): ahí sí era un vencimiento.

**Qué falla:**
1. **El mensaje miente:** `qr_invalid` junta dos causas que se arreglan distinto (volver a escanear, o ir al checador de su centro). La persona escanea y reescanea sin
   resolverlo; y el segundo botón la manda a «Pedir una corrección», pensada para después de trabajar.
2. **Trampa al volver:** «Entendido» regresa a Marcar con el `?q=` viejo en la dirección: los botones vuelven a fallar con el mismo código.
3. **La demo:** el manual (`docs/operations/demo.md`, §3) dice que cada quien marca frente al checador de Oficinas Querétaro, pero las 40 personas de la puerta
   están repartidas en 4 centros: las de los otros tres **no pueden marcar** con él.
4. **Cruce con zonas horarias:** el diseño (§3, puntos 4 y 9) dice que una marca con el QR de **otro centro de la misma empresa** (viaje de trabajo) debe
   aceptarse, señalada como «fuera de su centro» y con la zona de ese centro; hoy se rechaza.

**Propuesta (por aprobar; no hecha):**
- El servidor distingue la razón: `qr_expired` (código de su centro, fuera de ventana), `qr_other_center` (válido en otro centro de la misma empresa) y `qr_invalid`
  (ninguno). Con política «permitir y señalar», `qr_other_center` **se acepta y se marca «fuera de su centro»** (sin retardo, con la zona de ese centro); con política
  que bloquea, se rechaza con «Ese código es de otro centro (Oficinas Querétaro). Escanee el de su centro.».
- Pantalla de problema: título según la causa, **«Escanee de nuevo»** como acción principal (que quita el `q` viejo de la dirección) y «Pedir una corrección» como
  acción secundaria y de texto; centrada, como el resto de las pantallas de estado (hallazgo 4).
- Demo: las 40 personas de la puerta en un solo centro con checador, o un checador por centro; el manual lo dice.

**Decisiones:** pendientes.

---

## 27. La primera marca pide la huella tres veces

**Reporte (Victor, iPhone, 2026-11-17, con Juan Carlos de Oficinas):** al hacer la marca el teléfono pide la llave de acceso 3 veces, es molesto y parece un error;
¿es el comportamiento esperado?

**Veredicto: Confirmado, es el diseño actual, y se puede mejorar bastante.** Línea de tiempo del registro de ngrok de su primera marca (cada «options + POST» es un aviso de la huella):
1. 00:41:54 `POST /punch` → 422 `agreement_not_accepted` (la huella sirve para saber quién es la persona; la respuesta trae el acuerdo).
2. 00:42:03 `POST /agreement` → 200 (**segunda huella**: aceptar el acuerdo con su propia firma, criterio 5.1).
3. 00:42:09 `POST /punch` → 422 `day_not_open` (**tercera huella, desperdiciada**: la persona había tocado «Salir a comer», que sin entrada no procede; `retry` repite lo que
   se tocó aunque la respuesta ya dijo `next: ["start"]`).
4. 00:42:20 `POST /punch` → 200 (la entrada, **cuarta huella**); 00:42:30 → 422 `qr_invalid` (el código de la página ya era viejo); 00:42:46 → 200 (la salida a comer).
**De la segunda marca en adelante es una sola huella.** Los tres avisos de la primera vez: identificarse, aceptar el acuerdo y marcar otra vez.

**Causas:** (a) la página no sabe quién es hasta que se firma, así que el primer aviso solo sirve para descubrir que falta el acuerdo; (b) aceptar el acuerdo y marcar son
dos firmas separadas; (c) a una persona desconocida se le ofrecen los cuatro pasos, y se puede tocar uno que no procede, que cuesta una firma; (d) el código QR de la dirección dura de 30 a
60 s (`config/attendance.php`), y con tres o cuatro avisos de la huella (unos 8 s cada uno, contando la ubicación) se vence a mitad del flujo, lo que se parece al fallo del hallazgo 26.

**Propuesta (por aprobar; no hecha), con la regla de que el acuerdo se acepta con su propia firma:**
1. **Aceptar y marcar en una sola firma.** Tras mostrar el acuerdo, «Acepto y marcar entrada» firma un reto que cubre la aceptación y la marca; el servidor guarda la aceptación (texto, versión, llave, momento)
   y después la marca. La primera marca pasa de tres huellas a **dos**.
2. **No gastar una huella en lo que no procede.** Si la respuesta trae `next` y el paso tocado no está, no se reintenta: se explica («Primero marque su entrada») y se ofrece el paso que sí.
3. **El QR no caduca a mitad del flujo.** El código de la dirección se valida **al abrir la página** y el servidor entrega un comprobante de ese escaneo, ligado a esa sesión, que vale unos 3 minutos para
   completar la marca (aceptar, marcar); mostrar «Escaneó a las 00:36, tiene 3 minutos». Con la misma ventana de 30 s contra fotos del código.
4. **Opción a futuro:** aceptar el acuerdo desde el portal, al crear la llave, para que la primera marca sea de una huella.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-17).

---

## 28. QR de marca en el checador

**Reporte (Victor, 2026-11-17):** le gusta el QR pero no debe ser el genérico; que se vea de Humetría; ver cómo lo hacen las empresas del mercado.

**Veredicto:** hoy `QrImage` dibuja un QR estándar, negro sobre blanco con su margen, en SVG (`bacon-qr-code`, ya instalado). **Propuesta (por aprobar, con una prueba de escaneo):** QR de marca, en el
lienzo («Checador: el QR con la marca de Humetría»): módulos y ojos redondeados, todo en la tinta de la marca (`#14211F`) sobre blanco, **nivel de corrección H** y el arco de Humetría al centro
(un quinto del ancho del código), margen intacto; el contador pasa a ser el arco de marca que se vacía en 30 s; la hora con la zona del centro; los tres pasos numerados. La librería ya trae
módulos y ojos redondeados y nivel H; no hace falta una dependencia nueva.

**Investigación (web, 2026-11-17; guías de proveedores, sin estudios):**
- Nivel H y logo de 10 a 20 % del código (la cifra varía por fuente); margen de al menos 4 módulos sin tocar; lo negociable es el color y la forma del módulo, no el contraste ni los
  ojos; probar en varios teléfonos y con poca luz ([QR Code Generator](https://www.qr-code-generator.com/blog/qr-code-design-rules/),
  [Bitly](https://bitly.com/blog/creative-qr-code-design/), [guía de errores y logos](https://dev.to/bitran/the-complete-guide-to-qr-codes-error-correction-logos-and-best-practices-54o5)).
  No hallé una fuente que cubra la forma redondeada de los módulos: hay que probarla.
- Checadores del mercado: el código cambia cada 25 a 30 s ([Kenjo](https://help.kenjo.io/en/articles/597655-attendance-kiosk-devices-and-technical-details), un módulo de Odoo), se escanea en vivo y no se imprime. No hallé un
  contador visible como patrón en esas fuentes: es de Humetría.
- **Antes de construirlo:** escanear el código del lienzo con varios teléfonos (iPhone, Android, de baja gama), de lejos (1 a 2 m) y con la pantalla a poco brillo; si alguno falla, módulos cuadrados con los
  ojos redondeados.

**Prueba de escaneo (Victor, 2026-11-17): aprobada.** Escaneó el QR de marca del lienzo con su teléfono y leyó «Prueba del QR de marca de Humetria»: los módulos y los
ojos redondeados con el arco al centro y el nivel H escanean bien. Falta repetirla con un teléfono Android de gama baja, de 1 a 2 m y con poco brillo antes de construirlo.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-17). Sujeta a la prueba de escaneo del tablero con varios teléfonos.

---

## Revisión del lienzo hecha por Claude (2026-11-17, sin pedirle a Victor que revise a mano)

Se renderizaron los 32 tableros en claro y oscuro (64 páginas, a 375 px y el checador a 1280 × 800) y se midió: sin desborde horizontal, áreas táctiles de 44 px o más,
contraste de texto de 4.5:1 (3:1 en texto grande) calculado sobre el fondo real, y se miraron por tandas. Corregido: la línea de maqueta «Cuando no tenga nada pendiente aquí
dirá…» en Pendientes (no es texto del producto); la tarjeta de un solo punto en la bienvenida del pulso (ahora una fila); el rótulo «(pregunta de ejemplo)» dentro de la pregunta
abierta; la etiqueta «10 Muy probable» que no quedaba bajo el 10; y la cabecera de Marcar sin camino al portal. Resultado final: 64 de 64 páginas sin hallazgos automáticos.
Lo que esta revisión no cubre: el movimiento (se ve al cargar), el cambio de pregunta en la app (no es un tablero) y el escaneo del QR en otros teléfonos.

---

## 29 a 33. Revisión con una persona simulada (2026-11-17)

**Cómo:** un script de Playwright **fuera del repositorio** (`scratchpad/review/review.cjs`) con un **autenticador virtual** (passkey simulada, CDP `WebAuthn`) entra con el
enlace de un solo uso de una persona de Oficinas Querétaro (Lucía Herrera Cruz), crea su llave y recorre el portal y asistencia a 375 px en oscuro, con 19 capturas
(`scratchpad/review/shots/`). Es la forma de revisar sin la huella de Victor: sirve para todos los pasos de A6 en adelante. Dejó en la demo una llave, una entrada y una semana vista de Lucía
(se borran al reiniciar).

**29. Inicio del portal.** Ocho tarjetas (Marcar, Su evaluación NOM-035, Pulso, Mis vacaciones, Mi equipo, Mi puesto de pie, Avisos y resultados de su centro, Quejas y denuncias) con descripciones
de 3 y 4 líneas: la página mide **3 758 px a 375 de ancho** y «Salir» queda fijo a media página, tapando una tarjeta. **Propuesta:** una línea de descripción por tarjeta (≤ 36 caracteres), las que esperan
respuesta arriba (NOM-035 y Pulso con su insignia) y la barra inferior de la propuesta aprobada con «Salir» en Cuenta. Tablero «Inicio (con barra)» ya con las etiquetas reales.

**30. Prueba reciente** (`/me/{slug}/proof`, al abrir lo delicado, p. ej. «mensaje de apoyo»). La acción principal «Confirmar con mi huella» va dentro de una tarjeta con un icono suelto y la fija abajo es
«Volver a mi portal»: la jerarquía está al revés, con un hueco de media pantalla. **Propuesta:** pantalla de estado centrada con la huella como acción fija y «Atrás» en la cabecera (tablero «Prueba reciente»).

**31. Política de la empresa.** La política íntegra (compromisos, responsables, recursos y 8 principios) se pinta seguida: **12 104 px** a 375 de ancho, con «Usted confirmó el 1 de mayo de 2026…» al final.
**Propuesta:** versión y fecha como línea de contexto, el texto íntegro en secciones plegables (no se resume ni se oculta: es la política firmada por la empresa) y «Confirmar que la leí» fijo abajo (tablero «Política»).

**32. Mis vacaciones.** «Tiene 21 días disponibles» y «Periodo 2026 · 21 disponibles» dicen lo mismo en dos bloques; el formulario de pedir días (tipo, dos fechas, nota) ocupa media página con
«Enviar solicitud» apagado hasta elegir fechas; la constancia (que espera confirmación) está arriba sin insignia. **Propuesta:** una tarjeta de resumen, la constancia como pendiente con insignia, «Sus solicitudes» y
«Pedir días» fijo abajo que abre una hoja inferior con un resumen en vivo («5 días hábiles. Le quedarían 16») (tableros «Vacaciones»).

**33. Estados vacíos** (teletrabajo, solicitudes del equipo, resultados del equipo): un cuadro punteado con icono, título y texto arriba y media pantalla vacía. Son pantallas de estado: centrado vertical como en el
hallazgo 4. «Personal» en la cabecera (un candado) no se entiende en estas pantallas; en las demás de lo identificado dice «Solo RH y la Comisión» (hallazgo 14): usar una etiqueta por tipo de dato.

**Aprobación:** ✅ aprobadas por Victor, 2026-11-17 (29 a 33). **Referencia visual:** fila «Portal de la persona: vacaciones, prueba reciente y políticas» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

---

## 34. El acuse de la evaluación de Ley Silla

**Cómo se revisó:** con la persona simulada (Juan Carlos Castillo Morales, de Oficinas Querétaro) y una passkey virtual: entrar con su enlace, abrir `/me/{slug}/seating`, tocar «Confirmo que me informaron»,
dar la prueba y volver (5 capturas en `scratchpad/review/acuse/`). **Quedó en la demo:** su acuse confirmado del 17 de noviembre (ya no está pendiente en su teléfono) y una llave más. Se borra al reiniciar.

**Veredicto: Confirmado. El flujo funciona con una sola huella** (el diálogo de prueba reciente y luego el envío con «Gracias. Quedó registrado que le informaron.»). Lo que falla es la presentación (`seating/portal-acknowledgment.tsx`):
1. **El dato importante es lo más pequeño:** el nivel de riesgo («Bajo») es una etiqueta de unos 24 px y el icono del escudo se ve de 16 px, dentro de una tarjeta del mismo peso que el aviso de privacidad.
2. **Dos botones primarios en la misma página:** «Confirmo que me informaron» (a ~80 % del ancho, dentro de la tarjeta) y, más abajo, «Acepto, quiero contestar»; la persona no sabe cuál es la tarea.
3. **La prueba sale en un diálogo centrado** con el título «Confirme que es usted» y la página detrás; el aviso de éxito aparece como un toast sobre el texto del aviso de privacidad.
4. **No dice qué significa confirmar** (el criterio de la norma: que le informaron del resultado, no que acepte el nivel de riesgo).
5. Fecha: «9 de agosto de 2026» y «17 de noviembre de 2026» (el resto del producto usa «17 nov 2026»).

**Propuesta (por aprobar; no hecha):** su propia pantalla (la del hallazgo 13: «Confirme las medidas de su puesto» en la página de tareas): título «Resultado de su evaluación», quién y cuándo, el **nivel de riesgo grande** con su medidor de cinco
segmentos (`RiskLevel`), las medidas que le aplican o «Su empresa le dirá qué medidas toma», una línea que dice que confirmar es dejar registrado que le informaron, y **«Confirmo que me informaron» fijo abajo**, a todo el ancho,
con una nota «Le pediremos su huella, su rostro o su PIN». La prueba reciente pasa a **hoja inferior** en toda la app (`recent-proof-dialog.tsx`) y el éxito a una pantalla centrada «Gracias, quedó registrado» (tableros
«Ley Silla: resultado de la evaluación y acuse», «Prueba reciente como hoja inferior» y «Ley Silla: acuse registrado» en el [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt)). Con nivel «Alto»: tono sobrio, sin rojo de alarma.

**Decisiones:** tomadas (✅ aprobada por Victor, 2026-11-17).

---

## 35 a 37. Páginas públicas: denuncias, trabajadores y reportes por enlace (2026-11-17)

**Cómo:** Playwright a 375 px, en oscuro, contra el túnel, sin sesión (son páginas públicas); para los reportes se generaron dos enlaces temporales de demostración desde las acciones
`CreateReportShare` (15 minutos el de Resultados, 1 día el de Compensación). No se envió ninguna queja. Capturas en `scratchpad/review/pub/`.

**35. Canal de quejas (corregido el 2026-11-17: Victor lo siente poco alineado con el lienzo).** Recorrido completo con una queja de prueba **«[QA] Prueba de revisión de pantallas…» presentada en la demo** (se borra al reiniciar). Lo que
tiene bien: un asistente de 4 pasos, opciones en tarjetas, el mensaje de privacidad («No guardamos su dirección de internet ni su navegador»), la pantalla de referencia y clave con su aviso. Lo que no sigue el patrón del resto:
- **«Continuar» y «Enviar queja» van al final del contenido, no fijos:** en el paso 2 (2 316 px a 375 de ancho) y en el 1 (1 808 px) el botón queda bajo el pliegue.
- **«Atrás» y «Paso N de 4» con su barra se van con el desplazamiento** (no quedan fijos como en las encuestas).
- **El relato** (paso 3) es una caja de unos 435 px que ocupa la pantalla; con el contador («0 de 5000 caracteres»).
- **Recibida:** el icono, el título y el aviso de guardar ocupan lo primero; la **referencia y la clave quedan en la segunda mitad**; la acción es «Ver cómo va» (de contorno): lo que hay que hacer —copiar la clave— no tiene botón principal.
- **Inicio:** dos botones al final de la página; a 812 px el principal queda justo en el pliegue.
- **Seguimiento («Ver cómo va su queja»):** el botón deshabilitado se ve apagado y deja media pantalla vacía (es un formulario).
**Propuesta (por aprobar; no hecha), con los textos del producto sin cambios:** acción fija abajo en el inicio y en todos los pasos; «Atrás» en la cabecera y el avance fijo debajo (la barra crece en 300 ms); opciones como en la encuesta
(la elegida con el color primario); el relato en 8 filas con su contador; **«Recibida»: primero la referencia y la clave (a la vista sin bajar), después el aviso de guardarla, y «Copiar referencia y clave» como acción principal** con «Ver cómo va» como enlace;
«Ver mi queja» fijo abajo. Siete tableros en la fila «Canal de quejas» del [lienzo](https://claude.ai/artifact/7vVq11FxMGJWFMS4EaZtKt).

**36. Página de trabajadores.** «¿En qué centro de trabajo está?», buscador y la lista de los cuatro centros; pie «no guardamos quién visita esta página». Sin cambios.

**37. Reportes por enlace.** El de **Resultados** (`/report/{token}`, 10 624 px) gasta casi una pantalla antes del título: el logo, «Imprimir o guardar PDF» (botón principal a un tercio del ancho) bajo el logo, una tarjeta con un icono suelto sobre el aviso «compartió este informe
contigo» y «hecho con humetría» en una línea aparte. El de **Compensación** (`/compensation-report/{token}`, 5 381 px) está bien resuelto (logo centrado, aviso, título, pestañas que se deslizan con «Próxi» cortado como señal,
tarjetas con la cifra grande y barras) pero su cabecera es otra. **Propuesta (por aprobar; no hecha):** una cabecera común para los reportes por enlace: barra de 60 px con el logo a la izquierda e **«Imprimir»** a la derecha, el aviso de vigencia en una sola
línea con su icono al lado («Vence el 17 nov 2026, 01:26», con la zona del centro o de la empresa: diseño de zonas), el título primero, los datos del periodo en dos columnas y las pestañas (tablero «Reporte compartido: cabecera común»). Son documentos para
terceros (consultores, autoridad) que se imprimen: lo impreso no cambia.

**Decisiones:** ✅ aprobada por Victor, 2026-11-17 (cabecera común de reportes por enlace).

---

<!-- Plantilla por hallazgo:

## N. Título

**Reporte:** lo que dijo Victor (y captura).

**Veredicto: Confirmado | No confirmado | Por verificar.** Una frase.

**Evidencia:** archivo:línea, medida, captura.

**Propuesta (no hecha):** …

**Decisiones:** pendientes / tomadas («✅ aprobada por Victor, fecha»).

-->

**38. Marcar en modo remoto, «en campo» (2026-11-17).** En el producto **no existe un modo llamado «En campo»**: lo que hay es `WorkMode::Remote` (horario en «Remoto»: marca sin QR ni geocerca,
evidencia básica, criterio 4.3), el personal de confianza con navegador (`TrustedBrowser`, criterio 4.4) y la presencial. Quien trabaja fuera del centro (ventas, reparto, obra) hoy cae en uno de los dos primeros.
Confirmado en `punch-hint.tsx`, `location-note.tsx` y `punch-ready.tsx`: **la pantalla de marcar es la misma para todas las modalidades.**
- A una persona remota le dice «Si su centro tiene una pantalla QR, escanee su código…»: **una instrucción que no le toca y que puede llevarla a buscar un QR que no existe.**
- La nota de ubicación explica «si trabaja en un centro, la usamos…; si no, se descarta»: **condicional que la persona no puede resolver**; el servidor ya sabe cuál es su caso (`schedule.work_mode`).
- No dice con qué evidencia queda su marca, ni que desde casa o la calle no se le pedirá estar en una zona.
**Propuesta (por aprobar, no hecha):** el servidor manda la modalidad de la persona al abrir `/a/{slug}`; para remoto, la pista pasa a «Marque desde donde esté: no se le pedirá un QR ni estar en una zona» y la nota de
ubicación se quita (no se guarda); para presencial queda como hoy. Si «en campo» debe ser un modo propio (personal móvil con zonas de visita), es **decisión de producto** antes de diseñar: hoy la ley no lo distingue y
el criterio 4.3 lo cubre como remoto. Sin cambio de privacidad: el servidor ya descarta la posición del remoto.
**Decisión (2026-11-17): ✅ aprobada por Victor: «En campo» es solo remoto, sin modo propio.** Tablero «Asistencia: marcar en remoto» junto a «Marcar» en el lienzo.
