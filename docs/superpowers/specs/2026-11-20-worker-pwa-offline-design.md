# App de la persona trabajadora: instalable, lector de QR, avisos push y marcas sin conexión

*   **Autor:** Victor Jesus Bravo de la Peña
*   **Estado:** Borrador
*   **Fecha:** 2026-11-20
*   **Contexto de origen:** `2026-11-20-worker-pwa-offline-brief.md` (misma carpeta), decisiones 1 a 28 del dueño del producto (2026-11-20). La decisión 28 es la delegación de las decisiones de arquitectura («en esta y siguientes seguiré tu recomendación»). Con ella este diseño cambia cinco decisiones anteriores (13 en lo que toca a la justificación de falta, 14, 19, 21 y 27). Están listadas con lo que se gana y lo que se pierde en «Decisiones pendientes», y el dueño puede revertir cualquiera.
*   **Verificación:** los hechos del repo se verificaron contra el árbol de `claude/pwa-offline-qr-reader-0077c1` el 2026-11-20. Lo que no se pudo verificar dice «NO VERIFICADO» y qué falta comprobar.
*   **Conciliación con main (2026-11-24, `f3833c86`, tras #158, #159, #147 y #162):** el diseño se revisó contra los 263 commits que main sumó desde la base `6a30e13f`. Cambios que entraron aquí: (1) migraciones nuevas con prefijo `2027_01_30_*` (main llegó a `2027_01_29_100000`); (2) el horario ahora tiene vigencia por fecha (`ScheduleTimeline`, criterio 2.6): `WeekCompleteness` lee cada día con el horario vigente ese día; (3) quien deja la empresa ya pierde sus llaves de acceso (`RevokeOnDeactivation`, motivo `left_company`): las llaves del dispositivo y las suscripciones push también (entrada 25b de WorkerAccess); (4) rutas `tenant.*` con permiso por ruta (#162): las tres de verificación caen en `tenant.attendance.* → manage-attendance`, sin tocar `RoutePermissions`; (5) texto de `CLAUDE.md` rebasado sobre el de main (`Messaging`, «WhatsApp sale solo por `Messaging`»); (6) `RosterEntry` ya trae `mobile`, `hiredOn` va después; (7) referencias de línea corregidas donde main las movió; (8) Alternativa AN: el push no va por `Messaging`. Lo que no cambió: el contrato de red, el reto v1, la regla de tiempo, las banderas, el orden de commits. **#161 (Humi) sigue abierto y va a resolver conflictos con main; si toca archivos de este diseño, se vuelve a conciliar solo ese archivo.**

## Resumen

Humetría es una plataforma de herramientas de RH. La persona trabajadora no tiene cuenta: entra con una llave de acceso (WebAuthn) o con un enlace de un solo uso. Este diseño convierte su entrada en una **app instalable** que sigue sirviendo sin conexión, y entrega cuatro cosas en un solo PR (decisión 1):

1.  **App instalable** bajo `/me/{slug}`: manifest y service worker por empresa. Marcar y Mi semana se mudan ahí desde `/a/{slug}`, y el ícono abre Marcar.
2.  **Lector de QR dentro de la app** para el código de la pantalla del centro.
3.  **Avisos push** con Web Push y llaves VAPID, en un módulo `Notifications` propio (decisión 10).
4.  **Marcas sin conexión.** La persona marca sin conexión; la marca se firma con su llave de acceso (o con la llave del dispositivo), se guarda en el teléfono y se entrega al volver la conexión. **Cuenta en las horas** con una sola regla de tiempo: la hora declarada, acotada por lo que el servidor puede comprobar, mientras el intervalo probado no pase de 72 h. Queda «Sin conexión · por verificar» y Recursos Humanos la verifica después con un registro aparte, nunca editando la marca.

El QR solo ayuda cuando la pantalla del centro tiene conexión y el teléfono no: el quiosco genera el código en cada petición, así que en un apagón del centro tampoco hay QR y la marca va sin él. La medición separa marcas con QR y sin QR para ver cuánto aporta.

Dos piezas más entran porque dependen de lo anterior:

*   **Semana sin días sin cubrir** (decisión 13): una semana no se puede confirmar si a un día que le tocaba trabajar le faltan marcas y no lo cubre el horario, un festivo, la fecha de alta ni una ausencia aprobada en `Leave`. La persona lo resuelve con una corrección o pidiendo a Recursos Humanos que registre la ausencia como permiso en `Leave`. Afecta a toda empresa y tiene un interruptor para apagarlo. Una marca sin conexión que llega a una semana ya confirmada la reabre.
*   **Plan B** (decisión 15) para la persona cuya llave de acceso no firma sin conexión (en las pruebas: una llave de Google Password Manager en Chrome de iPhone): una llave del dispositivo más un pase sin conexión de 24 h.

### Glosario

| Término | Qué es |
|---|---|
| RH | Recursos Humanos: las personas con cuenta del panel que administran asistencia. En el texto se escribe «Recursos Humanos». |
| Pantalla del centro | El quiosco (`KioskController`): la pantalla del centro de trabajo que muestra el QR. |
| Sin conexión | La única expresión del documento para «sin señal», «sin internet» o «sin red». En los textos de la persona la app dice «sin internet» porque se entiende mejor; en todo lo demás, «sin conexión». |
| Marca | Un registro de entrada, salida o pausa (`start`, `break_start`, `break_end`, `end`) en `attendance_marks`, de solo inserción. |
| Marca efectiva | La marca que cuenta hoy: una marca sin corrección, o la corrección vigente que la reemplaza (`EffectiveMarks`). |
| Marca sin conexión | Una marca hecha sin conexión y entregada después. Es una marca real (métodos `offline_qr` u `offline_declared`), no una solicitud de corrección. |
| Entrega | El envío al servidor de las marcas guardadas en el teléfono, en lotes de hasta 20. Cada marca entregada con boleto y firma válidos deja una fila en `attendance_offline_marks`, aceptada o rechazada. |
| Entrega rechazada | Una marca con boleto y firma válidos que el servidor no pudo aceptar (por ejemplo, un acuerdo nunca aceptado). Se guarda con su motivo, no es una marca y no cuenta horas. |
| «No aceptada» | El estado, en el teléfono, de una marca que el servidor no aceptó (con fila o sin ella). Se queda en el teléfono hasta que la persona toque «Entendido» o «Pedir corrección». |
| Boleto | Un comprobante firmado por el servidor («emitido a las 07:40 para esta persona») que el teléfono guarda. Su hora de emisión es la cota inferior de la marca y el boleto entra en el reto de la firma. Un boleto sirve para una sola marca. |
| Clave del boleto | La llave HMAC del servidor que firma boletos y pases: `APP_KEY`, y al verificar también las de `APP_PREVIOUS_KEYS`. No la tiene el teléfono. |
| Recibo | El instante en que el servidor recibe la marca (`received_at`). Es la cota superior. |
| Intervalo probado | El tramo entre la emisión del boleto y el recibo. Su ancho es `received_at − ticket_issued_at`. |
| Hora declarada | `declared_at`: la hora que la app guarda al marcar, tomada del reloj del teléfono en ese momento; sin QR, la persona puede ajustarla antes de firmar. No prueba nada: solo se usa acotada. |
| Hora contada | `counted_at`, que se guarda como `marked_at` de la marca: la hora declarada acotada por la regla de tiempo (sección 3). |
| QR del centro | El código que muestra la pantalla del centro. Cambia cada 30 s (`config/attendance.php`) y lo firma el servidor con un HMAC de empresa, centro y ventana. Sin conexión prueba que la marca no fue **antes** de esa ventana; no prueba la hora exacta. |
| Llave de acceso | La credencial WebAuthn de la persona (iCloud, Google Password Manager u otro proveedor), la misma con la que entra a `/me`. |
| Llave del dispositivo | Una llave ECDSA P-256 no extraíble que la app genera y guarda en el teléfono. Solo firma marcas sin conexión, en el plan B. |
| Pase sin conexión | Un permiso firmado por el servidor, de 24 h, que autoriza a una llave del dispositivo a firmar marcas sin conexión. |
| «No fui yo» | El enlace firmado que llega a la persona cuando se crea una llave nueva a su nombre: la anula, anula sus enlaces y avisa a la empresa (`WorkerAccess`). No es el botón «¿No es usted?» de Marcar, que solo cierra la sesión del teléfono. |
| Verificar | Lo que hace Recursos Humanos con una marca sin conexión: un registro en `attendance_mark_reviews` con la decisión «correcta» (`verified`) o «incorrecta» (`incorrect`), quién y una nota. No es otra marca y «correcta» no cambia la hora. En el código el registro se llama *review*; en la interfaz siempre se dice «verificar». |
| Banderas | Señales que el servidor guarda con una marca sin conexión aceptada (columna `review_flags`: `wide_interval`, `old_window`, `no_position`, `outside_zone`, `declared_adjusted`). Ordenan la lista de Recursos Humanos; no cambian la hora. |
| «Sin conexión · por verificar» | La etiqueta de Recursos Humanos para toda marca sin conexión sin verificación. Con verificación, «Sin conexión · verificada» o «Sin conexión · incorrecta». |
| Día sin cubrir | Un día laborable sin marcas (o con jornada incompleta) que no cubre el horario, un festivo, la fecha de alta ni una ausencia aprobada. |
| Mi semana | La pantalla donde la persona revisa su semana y la confirma u objeta con su firma (criterio 5.2). Estados: pendiente → confirmada u objetada; una marca tardía devuelve una semana confirmada a pendiente; una objetada sigue objetada. |
| `MarkLock` | El candado de Postgres por persona con que Attendance ordena las escrituras de marcas. |
| Humi | El asistente de IA del panel (módulo `Assistant`). Lee conteos y estados, nunca personas ni respuestas. |

## El problema

Hoy marcar asistencia exige hablar dos veces con el servidor: pedir el reto de la firma y enviar la marca. Sin conexión, la página dice «Revise su conexión» y no se guarda nada; la solicitud de corrección tampoco funciona. Una persona en un sótano, una bodega, un apagón o con el saldo agotado no puede dejar constancia de que llegó. No existen manifest, service worker, lector de QR dentro de la app ni push.

Esto choca con tres hechos del código y la norma:

*   **El criterio 3.3** de `docs/normas/jornada/criterios.md` dice que la hora de cada marca la pone siempre el servidor. Una marca sin conexión no tiene servidor presente y no hay hora del teléfono que no se pueda manipular; solo sirve una hora que el servidor pueda **acotar**: no antes de la emisión del boleto (ni de la ventana del QR) y no después del recibo.
*   **`attendance_marks` es de solo inserción** (trigger de Postgres, criterio 5.4). «Recursos Humanos verifica» no puede ser un cambio sobre la marca.
*   **La ceremonia de firma actual** solo acepta un reto que el servidor emitió y guardó 60 s (`config/worker_access.php`, `webauthn.challenge_seconds`). No puede verificar una firma hecha sin conexión.

La propuesta resuelve cada uno: una enmienda de la norma (3.3.1), una tabla de verificaciones de solo inserción y una verificación de firma sin caché con un reto que lleva un boleto del servidor.

**Qué se probó en teléfonos reales (2026-11-20, decisión 18).** Fueron pruebas cortas, con una página de prueba y no con la app, en un iPhone del dueño y un Android de otra persona (`concepts_brief.md`, «Resultados»):

| Qué | Plataforma y contexto | n | Resultado |
|---|---|---|---|
| Firma sin conexión con llave de iCloud | Un iPhone (iOS 18.7), app instalada, modo avión | Al menos 2 firmas con dos llaves; 1 validada contra su llave pública | Firmó (2.2 s la validada). |
| Firma sin conexión con llave de Google Password Manager en Chrome de iPhone | El mismo iPhone, dos llaves | 2 llaves | No firmó sin conexión: con conexión la hoja pide el PIN de Google Password Manager; sin ella la hoja se cierra de inmediato (`NotAllowedError`). Causa probable no confirmada (la recuperación de claves de Google). Obliga al plan B. |
| Firma sin conexión con Google Password Manager en Android | Un Android, Chrome 157, **en una pestaña** (no instalada) | 5 intentos | Firmó 3 de 5 (2.1 a 2.9 s); los 2 fallos fueron `NotAllowedError`. |
| Crear una llave sin conexión en Android | El mismo Android, pestaña y app instalada | 2 en la pestaña, 2 en la app instalada | En la pestaña creó 1 de 2; en la app instalada, 0 de 2 (`NotAllowedError` a los 1.9 s y 1.3 s). No se intentó firmar en la app instalada. |
| App instalada abre en frío sin conexión | El iPhone y el Android | 1 apertura en cada uno | Abrió en ambos. |
| Lector de QR dentro de la app instalada | El iPhone, con y sin conexión | 3 lecturas | Leyó 3 de 3 en 1.2 a 1.5 s. |
| Firma sin conexión desde la app instalada de Android; Android viejo; contador de marcas tras cerrar la app | — | 0 | No probado: lo decide la compuerta del commit 0 («Decisiones pendientes»). |

## Metas

*   **M1. Instalar.** La app se instala en iPhone (manualmente desde Safari) y en Android, abre en frío sin conexión directamente en Marcar, y muestra una guía de instalación y un aviso «Ábralo en Safari o Chrome» dentro de los navegadores internos de correo.
*   **M2. Leer el código del centro.** Dentro de la app instalada, la cámara lee el QR de la pantalla del centro. Toda página de `/me/{slug}` permite pedir cámara y ubicación; solo Marcar y el cascarón las piden.
*   **M3. Marcar sin conexión.** La marca se entrega al volver la conexión y cuenta en horas, horas extra y retardos a su **hora declarada, acotada** (emisión del boleto, ventana del QR, marcas vecinas y recibo) si el intervalo probado es de hasta 72 h; con más, cuenta la cota probada hasta que Recursos Humanos la corrija. Hay **dos tratamientos** de evidencia: con un QR válido y reciente (`offline_qr`, media) y sin él (`offline_declared`, básica).
*   **M4. Ninguna marca aceptable por el servidor se pierde en silencio.** El teléfono borra una marca solo cuando el servidor confirma que la tiene. Una marca que el servidor no acepta se queda en el teléfono «No aceptada» con su motivo hasta que la persona la atienda; si tenía boleto y firma válidos, además queda en el servidor con su motivo. Un elemento mal formado no bloquea a los demás. Excepción conocida: un teléfono cuyos datos se borran antes de entregar (decisión 22).
*   **M5. Plan B.** La persona cuya llave no firma sin conexión marca con la llave del dispositivo y un pase, con la misma regla de tiempo y la misma etiqueta. Una práctica de firma sin conexión durante el alta (hasta 3 intentos) le sugiere qué camino tiene su teléfono; el resultado solo aconseja.
*   **M6. Verificación por Recursos Humanos.** Toda marca sin conexión queda «Sin conexión · por verificar» hasta que Recursos Humanos la verifique en la pestaña «Marcas sin conexión» de Correcciones, una por una o varias a la vez. La verificación es un registro aparte que no cambia la hora ni la evidencia. «Necesita tu atención» y Humi cuentan las marcas con banderas y las que llevan más de 7 días sin verificar.
*   **M7. Semana sin días sin cubrir.** No se confirma una semana con un día sin cubrir; la app tampoco deja confirmar con marcas de la persona sin entregar. Se resuelve con una corrección o con un permiso registrado en `Leave`; objetar sigue disponible. El bloqueo tiene un interruptor que lo deja solo en el log. Una marca sin conexión que llega a una semana confirmada la reabre; una objetada sigue objetada.
*   **M8. Avisos push.** Un aviso sin contenido, con texto fijo en pantalla bloqueada, cuando Recursos Humanos verifica una marca sin conexión (correcta o incorrecta) y cuando resuelve una corrección.
*   **M9. Norma y Humi al corriente.** Enmienda de `docs/normas/jornada/criterios.md` como primer commit de código; versión nueva del acuerdo de registro; lector de Humi y criterios actualizados, con `ModuleCoverageTest` en verde.
*   **M10. Medirlo con lo que ve el servidor.** Marcas entregadas por día (con QR y sin QR), proporción rechazada por motivo, ancho del intervalo (p50 y p95), edad en la cola al entregar, y el tamaño y la edad de las colas que los teléfonos informan al pedir boletos. Desde los logs del servidor, sin personas y sin PostHog, con umbrales iniciales en `docs/operations/pwa.md`.

## Fuera de alcance

*   Encuestas anónimas, canal de denuncias y página de trabajadores dentro de la app o de su service worker.
*   El panel `/admin` y la pantalla del centro como app instalada.
*   Guardar en caché páginas o respuestas con datos de una persona (solo el cascarón estático y los archivos del build).
*   Usar el reloj del teléfono como hora de una marca en línea, o como prueba en una marca sin conexión: la hora declarada sale de él, pero solo cuenta acotada (decisión 25).
*   Probar que la hora declarada es cierta: se declara «no probada» y se acota.
*   Relevo de la marca por la pantalla del centro (`screen_relay` del brief anterior).
*   Aviso push de «falta marcar salida».
*   Contenido en los avisos push.
*   Una solicitud nueva de justificación de falta y declarar la ausencia dentro de la confirmación de la semana («Mejoras futuras»).
*   Una página propia del panel para verificar marcas y su panel de contexto («Mejoras futuras»).
*   Marcar desde una computadora y el portal en escritorio: trabajo aparte, después de integrar este.
*   Leer Mi semana sin conexión (el teléfono guarda solo la cola de marcas, los boletos, el pase y el contexto).
*   Conservar las direcciones viejas `/a/{slug}` de Marcar y Mi semana: no hay clientes y devuelven 404.

## Decisiones pendientes

Lo que sigue abierto. Las decisiones de arquitectura están tomadas (decisión 28) y están en el plan técnico.

| Decisión | Qué bloquea | Dueño | Para cuándo |
|---|---|---|---|
| **Compuerta de Android (la única).** Criterio: la llave de acceso firma sin conexión en **≥ 4 de 5 intentos en la app instalada, en ≥ 2 teléfonos Android, uno viejo o con Play services desactualizado**. Se mide también si reintentar una vez ante un `NotAllowedError` rápido (menos de 2 s) lo resuelve. Si no se alcanza, la ruta de llave de acceso sin conexión sale y en Android la llave del dispositivo es el único firmante sin conexión (Alternativa AE). Esto confirma lo que dejó la decisión 18 (lo no verificado se revisa antes de integrar): solo adelanta esa revisión al commit 0. | El commit 0; el cliente sin conexión (commit 19) se construye según el resultado. | Victor (la prueba la hace otra persona con una guía paso a paso, decisión 17) | Antes del commit 1. |
| **Confirmar los cambios hechos por delegación** a las decisiones 13, 14, 19, 21 y 27 (tabla siguiente). | Nada se detiene: el diseño ya los sigue; revertir uno cambia los commits 11, 13 o 14. | Victor | Antes de integrar. |
| **Umbrales:** intervalo ancho 240 min, ventana vieja del QR 30 min, hora declarada cuenta hasta 72 h, horizonte del boleto 35 días, marcas viejas sin verificar 7 días. Son valores de configuración (sección 11). | Ninguno; se ajustan sin código. | Victor | Antes de desplegar. |
| **Umbrales de la medición** en `docs/operations/pwa.md`. Valores iniciales: proporción rechazada mayor de 5 % en un día con 20 entregas o más; ancho del intervalo p95 mayor de 240 min en una semana; edad en la cola p95 mayor de 24 h; algún teléfono que informa una cola con la marca más vieja de más de 48 h; `ticket_invalid` o `signature_invalid` en el log más de 3 veces al día. Responden a «nadie reporta que no pudo marcar» así: una cola vieja o un rechazo alto son personas cuya marca no llegó. | La sección de medición de `docs/operations/pwa.md`. | Victor | Antes de desplegar. |
| **Texto del acuerdo** (criterio 5.1): la frase nueva está en la sección 10; el aviso de privacidad (push y almacenamiento en el teléfono) se redacta en su commit (20). | Los commits 6 y 20. | Victor | Antes de cada commit. |
| **Textos de la persona** en cada estado (sección 8). | Las pantallas de Marcar y Mi semana. | Victor | Antes de la revisión en navegador. |
| **Ícono** de 192 px y maskable. | El commit 3. | Victor | Antes de ese commit. |
| **Llaves VAPID** en Dokploy. | El despliegue (sin llaves se registra `push.unconfigured` y no se envía). | Victor | Antes de desplegar. |

**Cambios hechos por delegación que el dueño puede revertir:**

| Decisión del brief | Qué decía | Qué hace este diseño | Qué se gana | Qué se pierde |
|---|---|---|---|---|
| 14 · tres tramos | Más de 4 h contaba la cota probada (el recibo si abría un tramo, la emisión si lo cerraba) hasta verificar. | La hora declarada acotada cuenta si el intervalo es de hasta 72 h; con más, la cota probada; más de 240 min enciende `wide_interval`. | Un día de apagón entregado de noche cuenta bien sin que Recursos Humanos verifique a toda la planta; retardos y horas extra salen bien. | Una mentira sin bandera puede ganar hasta 240 min, y con bandera hasta 72 h contadas hasta que Recursos Humanos la verifique. |
| 13 · justificación de falta (pregunta 15) | Una solicitud nueva de la persona que Recursos Humanos aprueba. | Corrección, o permiso registrado por Recursos Humanos en `Leave`; el bloqueo tiene interruptor. | Cero tablas y pantallas nuevas. | Una falta sin justificar se vuelve un «Permiso especial sin goce» aprobado; la persona depende de que Recursos Humanos lo registre. |
| 19 · marcas nunca verificadas | Siguen contando; aviso a Recursos Humanos de las viejas. | Siguen contando; «Necesita tu atención» y Humi cuentan las de más de 7 días; el correo queda para después. | Sin correo nuevo. | Recursos Humanos solo se entera si abre el panel o pregunta a Humi. |
| 21 · QR sin verificación | Una marca con QR era hora exacta (±30 s) y no necesitaba verificación; un QR de más de 72 h pasaba a solicitud. | Toda marca sin conexión queda por verificar; el QR solo es cota inferior; un QR leído más de 30 min antes de la hora declarada cuenta como marca sin QR (básica). | Una foto reenviada del código ya no da hora exacta ni evidencia media. | Más marcas por verificar, también las honestas con QR. |
| 27 · medidas de éxito | Intentos contra entregas, edad de lo no entregado, proporción rechazada, ancho del intervalo. | Solo lo que ve el servidor, más la cola que el teléfono informa al pedir boletos. | Ningún dato contado en el teléfono ni en PostHog. | Un teléfono que nunca vuelve a conectarse no se ve. |

**Otras decisiones tomadas por delegación que el dueño puede revertir:** la pestaña dentro de Correcciones en lugar de una página propia; con una cuarta llave del dispositivo se reemplaza la más vieja (la de uso más antiguo); una persona sin horario no tiene días sin cubrir; una marca tardía en una semana objetada la deja objetada y Recursos Humanos ve la marca nueva; en un teléfono compartido, el aviso push llega solo a la última persona que se suscribió.

## Recorridos críticos (CUJ)

### CUJ 1: Marcar sin conexión leyendo el QR del centro

*   **Dado**: Ana tiene la app instalada, marcó en línea ayer (tiene boletos y el contexto guardados), aceptó la versión vigente del acuerdo y su llave firma sin conexión. Está en el centro: la pantalla del centro tiene conexión, su teléfono no.
*   **Cuando**: Abre la app (arranca en frío en Marcar, desde el cascarón), lee el QR con la cámara, toca «Entrada» y confirma con rostro o huella a las 08:01.
*   **Entonces**: La app dice «Guardada sin conexión, 08:01» y el contador sube a «1 marca sin enviar». Al volver la conexión (08:20) la marca se entrega sola; la ventana del QR empieza a las 08:00:30, así que cuenta 08:01. Es `offline_qr` (media); el teléfono dice «Recibida» y la borra de la cola. Recursos Humanos la ve «Sin conexión · por verificar»; sin banderas queda al final de la lista.

### CUJ 2: Un día completo sin conexión, sin QR (apagón)

*   **Dado**: Luis recibió boletos ayer a las 17:00. Hoy hay apagón en el centro: ni pantalla ni conexión en todo el día. Su llave firma sin conexión.
*   **Cuando**: Marca sin QR «Entrada» a las 08:02 (escribe, si quiere, «Se fue la luz»), «Inicio de pausa» a las 14:00, «Fin de pausa» a las 14:45 y «Salida» a las 17:05. Las cuatro se entregan a las 19:00 desde su casa.
*   **Entonces**: El intervalo probado es de 26 h (menos de 72 h), así que cada marca cuenta a su hora declarada: 08:02, 14:00, 14:45 y 17:05. La jornada suma 8 h 18 min y las horas extra y retardos se calculan con esas horas. Las cuatro son `offline_declared` (básica) y llevan `wide_interval` (más de 240 min), así que salen primero en la lista. Recursos Humanos ve la emisión, el recibo, la hora declarada, la nota y la pista del reloj del teléfono, selecciona las cuatro y las verifica de una vez; ninguna hora cambia.
*   **Variante con una salida**: Luis marcó la entrada en línea a las 08:00 (con boletos nuevos emitidos a las 08:00) y la salida sin conexión a las 17:00, entregada a las 19:00. Cuenta 17:00. Si hubiera declarado 07:30, el punto de referencia es 08:00 (la hora declarada llevada a la emisión del boleto), la marca anterior es la entrada, y la salida cuenta en el primer instante después de ella, con `declared_adjusted`.
*   **Variante de cinco días**: si las marcas de un apagón de cinco días se entregan al sexto día, el intervalo pasa de 72 h: cada marca cuenta en su cota probada (la emisión del boleto o la marca anterior) con `wide_interval` y `declared_adjusted`, y Recursos Humanos, si la hora declarada es la buena, la corrige con el flujo de corrección. Con ocho boletos y cuatro marcas al día, el teléfono avisa «Pocos boletos» al tercer día y no deja marcar sin conexión al agotarlos.

### CUJ 3: Plan B, la llave no firma sin conexión

*   **Dado**: Marta guardó su llave en Google Password Manager en Chrome de iPhone. En el alta, la práctica de firma sin conexión falló tres veces. Con conexión, la app registró la llave del dispositivo de Marta y le dio un pase de 24 h, que se renueva cada vez que abre `/me` con sesión.
*   **Cuando**: Sin conexión, toca Marcar; la hoja de la llave no devuelve firma; la app le ofrece «Marcar con este teléfono».
*   **Entonces**: La marca se firma con la llave del dispositivo, queda guardada y se entrega después, con la misma regla de tiempo y la etiqueta «firmada con la llave del dispositivo». Si el pase venció, la app dice que hace falta abrirla con internet. Si Marta toca «Salir», la llave del dispositivo sigue viva y sus marcas guardadas se entregan.

### CUJ 4: El servidor no puede aceptar una marca entregada

*   **Dado**: Una marca guardada de ayer, y la persona nunca aceptó el acuerdo vigente en la hora del boleto (o la marca choca con otra, o el boleto pasó del horizonte de 35 días).
*   **Cuando**: La app la entrega.
*   **Entonces**: El servidor guarda la entrega rechazada con su motivo (no una marca) y Recursos Humanos la ve en la pestaña. El teléfono la conserva «No aceptada» con el motivo y ofrece «Entendido» y «Pedir corrección». Un error de red, 429 o 5xx deja el lote pendiente; un 422 o 413 hace que el teléfono reintente elemento por elemento y deje solo el elemento malo «No aceptada». Si la llave de la persona se revocó, la marca se acepta solo si su hora declarada es anterior a la revocación.

### CUJ 5: Recursos Humanos verifica

*   **Dado**: Hay marcas sin conexión por verificar de un apagón en un centro.
*   **Cuando**: Recursos Humanos abre Correcciones, pestaña «Marcas sin conexión». Ve primero las que tienen banderas; puede filtrar «sin banderas», seleccionar las de una página y tocar «Verificar seleccionadas», o abrir una.
*   **Entonces**: Cada decisión es un registro nuevo enlazado a la marca, con su entrada de bitácora. «Correcta» solo inserta el registro. «Incorrecta» solo existe junto con su corrección (una hora que escribe Recursos Humanos, o anular la marca), en una sola transacción. La persona recibe un aviso push en ambos casos y ve el estado en Mi semana.

### CUJ 6: Semana con un día sin cubrir

*   **Dado**: Una semana terminó; al miércoles le faltan marcas, no era descanso ni festivo, y no hay ausencia aprobada.
*   **Cuando**: La persona abre Mi semana para confirmar.
*   **Entonces**: «Confirmar» está apagado y dice qué día falta. La pantalla le ofrece pedir una corrección (agregar la marca que falta) o pedir a Recursos Humanos que registre la ausencia como permiso. Con la ausencia aprobada, el día queda cubierto y puede confirmar. «Objetar» sigue disponible. Con marcas suyas sin entregar en el teléfono, la app también apaga «Confirmar». Con el interruptor del bloqueo apagado, la semana se confirma y el servidor solo registra el día sin cubrir en el log.

### CUJ 7: Una marca tardía reabre una semana confirmada

*   **Dado**: Ana confirmó la semana; después llega la entrega de una marca sin conexión de uno de esos días.
*   **Cuando**: El servidor acepta la marca.
*   **Entonces**: La semana vuelve a pendiente y Ana la ve de nuevo para confirmar. Si estaba objetada, sigue objetada y Recursos Humanos ve la marca nueva.

### CUJ 8: Instalar y abrir en frío sin conexión

*   **Dado**: Una persona con iPhone o Android recibe el enlace de acceso.
*   **Cuando**: Lo abre en Safari o Chrome (si es el navegador interno de un correo, la app le pide abrirlo en el navegador), instala la app y, ya sin conexión y con la app cerrada por completo, la abre desde el ícono.
*   **Entonces**: Abre en Marcar desde el cascarón guardado, muestra su estado sin conexión y permite marcar si tiene boletos. Ninguna página con datos de personas está guardada.

### CUJ 9: El dueño revisa si la función sirve

*   **Dado**: La función lleva una semana en producción en una empresa.
*   **Cuando**: El dueño consulta los logs del servidor (`attendance.offline_mark_received`, `attendance.offline_mark_rejected` y `attendance.offline_queue_reported`) con las consultas de `docs/operations/pwa.md`.
*   **Entonces**: Ve marcas entregadas por día (con QR y sin QR), la proporción rechazada por motivo, el ancho del intervalo (p50 y p95), la edad en la cola al entregar y las colas viejas que los teléfonos informan, cada una contra su umbral inicial. Ninguna medida nombra a una persona.

## Plan técnico (arquitectura)

### 1. Decisiones de arquitectura

Cada una dice cuándo falla; las opciones descartadas están en «Alternativas consideradas». La numeración D1 a D10 se conserva porque el plan de implementación la cita; D7 ya no existe (se descartó como Alternativa AB).

| # | Decisión | Elegido | Cuándo falla |
|---|---|---|---|
| D1 | Cota inferior de tiempo y reto de la firma sin conexión | **Boleto firmado sin tabla** (opción 1 de cuatro; las otras son las Alternativas A, B y C): HMAC con empresa, persona, hora de emisión y un valor aleatorio del servidor; un boleto, una marca (índice único). Clases propias `OfflineTicket` y `OfflinePasses`, que rotan con `APP_KEY` y `APP_PREVIOUS_KEYS` como `PageLink` (Alternativa AH). | El servidor no sabe cuál boleto del lote es el más nuevo: quien tiene conexión puede usar uno viejo (riesgo con cifras en «Riesgos y mitigaciones»). |
| D2 | Dónde viven las marcas sin conexión | `attendance_marks` gana dos métodos; **una tabla de entregas** `attendance_offline_marks` y **una tabla de verificaciones** `attendance_mark_reviews`, ambas de solo inserción. | Un `JOIN` más en el panel; dos filas en una transacción. |
| D3 | Cómo se entrega | **Solo con la firma**, sin sesión ni CSRF, en un grupo de rutas con `withoutMiddleware` como `routes/complaints-public.php`, con `RejectCrossSiteRequests` por ruta (el middleware se mueve a `App\Http\Middleware`). La entrega sale de la página, nunca del service worker. | Sin CSRF la defensa es la firma, el boleto de un solo uso, el rechazo de peticiones de otro sitio y los límites de tasa. |
| D4 | Plan B | **Llave del dispositivo** (WebCrypto ECDSA P-256, no extraíble) registrada en línea tras una prueba reciente, más un **pase sin conexión** (HMAC, 24 h) que se renueva con la sesión de `/me`. «Salir» no la revoca; la revocan «No fui yo», reemitir y Recursos Humanos. | No pide rostro: quien tenga el teléfono desbloqueado puede firmar. Se pierde si se borran los datos de la app. Quien usa el plan B debe abrir `/me` con sesión al menos cada 24 h. |
| D5 | Qué se firma y cómo se verifica | **Reto canónico v1** (contrato de red) y un `verifyChallenge` nuevo en `WorkerSignatures`, sin caché ni contador; la repetición se evita con el valor del boleto. | Si PHP y JS arman la cadena distinta, nada verifica: la fijan los vectores de `tests/Fixtures/attendance/offline-challenge-v1.json`. |
| D6 | Evidencia | **La sale de la base de tiempo**, por `MarkMethod::evidence()`: `offline_qr` media, `offline_declared` básica, cualquiera que sea el firmante. El firmante se guarda y se muestra aparte. | Una marca sin conexión nunca llega a fuerte. |
| D8 | Cámara y ubicación | **Una regla de cabecera:** toda respuesta GET de documento bajo `/me/{slug}/*` lleva `Permissions-Policy: camera=(self), geolocation=(self)`; el resto de la app sigue con `camera=()`. La política solo permite: el navegador sigue pidiendo permiso, y una página que no llama a la cámara no lo pide. | Una página nueva de `/me` que pidiera cámara sin necesitarla: lo frena la revisión de código, no la política. |
| D9 | Service worker | Ruta de Laravel por empresa (`/me/{slug}/sw.js`) que sirve un archivo compilado aparte, escrito a mano en TypeScript, sin Workbox; cascarón estático aparte. El worker de apagado lo genera el controlador. | Un despliegue deja teléfonos con el worker viejo: lo cubren la versión en el nombre de la caché y `PWA_SW_KILL`. |
| D10 | Push | Módulo `Notifications` propio (decisión 10) que envía avisos **sin contenido** con `minishlink/web-push ^11.0` detrás de un cliente PSR-18 sobre `Http` de Laravel (se prueba con `Http::fake()`). Sin contrato público: sus listeners llaman a la clase emisora; se agrega uno cuando otro módulo necesite avisar. `qr` y `minishlink/web-push` los aprobó el dueño por nombre (decisión 12). | Sin llaves VAPID no se envía y se registra `push.unconfigured`. |

**Por qué la entrega y los boletos van sin sesión.** La sesión de Laravel (cookie que valida el CSRF) dura 120 min (`config/session.php`, `SESSION_LIFETIME`); la sesión de la persona trabajadora (`config/worker_access.php`) es una regla de `WorkerAccess` encima de ella: vence a los 30 min sin uso y a las 8 h. Tras un apagón cualquiera de las dos puede no existir o haber vencido, y quien solo usa Marcar (que hoy no abre sesión) nunca la tiene. Además, los navegadores sin Fetch Metadata (iOS anterior a 16.4) no mandan `Sec-Fetch-Site`, así que no se puede contar con él para pasar el CSRF sin token. Por eso la credencial es la firma: un boleto no sirve sin ella, y uno más reciente solo estrecha la cota. El manifest, `sw.js` y el cascarón van en el mismo grupo sin sesión: con `SESSION_DRIVER=database` (`config/session.php:23`) cada petición sin cookie abriría una fila en `sessions`, y los teléfonos instalados revisan el worker en cada navegación.

### 2. Flujo de extremo a extremo

```text
CON CONEXIÓN                                    SIN CONEXIÓN                                 AL VOLVER LA CONEXIÓN
────────────                                    ────────────                                 ─────────────────────
Marca en línea, semana, corrección o            Ícono → cascarón (service worker) en Marcar
acuerdo (respuesta firmada), o                     └─ lee QR (opcional) y toma posición
POST /me/{slug}/tickets con un boleto                 └─ gasta el boleto más nuevo
   └─ {tickets (lote de 8), context}                     └─ arma el RETO v1 = SHA-256(cadena)
      └─ el teléfono los guarda (IndexedDB,                  └─ la llave de acceso firma el reto
         tope de 16)                                            (o la llave del dispositivo, plan B)
Abrir /me con sesión → renueva el PASE (24 h)                     └─ la marca entra a la COLA
                                                                     «Guardada sin conexión»

                                                                                  POST /me/{slug}/punch/offline  (≤ 20, ≤ 64 KB)
                                                                                    ├─ sobre: 422 / 413 → el teléfono parte el lote
                                                                                    ├─ boleto (HMAC, empresa)
                                                                                    ├─ idempotencia por el valor del boleto
                                                                                    ├─ firma contra el reto reconstruido
                                                                                    ├─ persona, centro, pase, acuerdo, horizonte
                                                                                    ├─ hora contada (MarkLock, vecinos, 72 h)
                                                                                    └─ marca + entrega (con banderas), o solo
                                                                                       la entrega rechazada
                                                                                  Respuesta por marca + {tickets, context}
                                                                                  El teléfono borra lo que el servidor tiene;
                                                                                    lo demás queda «No aceptada»
Recursos Humanos ── Correcciones › Marcas sin conexión ── verifica (registro nuevo) ── aviso push a la persona
```

### 3. Tiempo y evidencia de una marca sin conexión

**Una sola regla de tiempo.** Toda marca sin conexión trae su hora declarada (`declared_at`). Dentro de la transacción, bajo `MarkLock` de la persona, el servidor calcula:

```text
ref        = min(max(declared_at, ticket_issued_at), received_at)
anterior   = marca efectiva de la persona inmediatamente antes de ref
siguiente  = marca efectiva de la persona inmediatamente después de ref
lower      = max(ticket_issued_at, window_start si hay QR válido, anterior + 1 µs)
upper      = min(received_at, siguiente − 1 µs)
si lower > upper: se rechaza solo esa marca con sequence_conflict
si received_at − ticket_issued_at ≤ 72 h: counted_at = clamp(declared_at, lower, upper)
si no:                                    counted_at = lower
```

No hay cuenta distinta para marcas que abren o cierran un tramo. `TimeBasis` (`qr_window` | `declared_interval`) es la etiqueta de la base que acotó la hora y decide el método. Ejemplos de aceptación: entrada en línea 08:00, salida sin conexión declarada 17:00 y entregada 19:00 ⇒ cuenta 17:00 con `wide_interval`. Entrada en línea 08:00, boleto emitido 08:00, salida declarada 07:30 ⇒ `ref` = 08:00, la anterior es la entrada y cuenta 08:00:00.000001 con `declared_adjusted`.

**Dos tratamientos:**

| Tratamiento | Cuándo | Cota inferior que aporta | Método y evidencia |
|---|---|---|---|
| Con QR | El QR trae un HMAC válido de la empresa y el centro para su ventana, y `declared_at − window_start` ≤ 30 min | El inicio de la ventana: la marca no fue antes de ella | `offline_qr`, media |
| Sin QR | Sin QR, con un QR cuyo HMAC no verifica (se trata como si no hubiera), o con un QR leído más de 30 min antes de la hora declarada (sigue sumando su ventana a `lower` y lleva `old_window`) | La emisión del boleto (y la ventana, si el QR verificó) | `offline_declared`, básica |

**El token del QR cambia de formato.** Hoy el quiosco muestra el HMAC en hexadecimal sin la ventana (`QrToken`). Pasa a `{windowIndex}.{hmac}`: así el servidor verifica una sola ventana sin buscarla. En línea, `QrToken::verify` lee el nuevo formato y sigue aceptando solo la ventana actual y la anterior; no hay QR impresos ni clientes, así que el formato viejo deja de aceptarse sin transición (Alternativa AK).

**Banderas** (columna `review_flags`, conjunto cerrado):

*   `wide_interval`: el intervalo probado pasa de 240 min.
*   `old_window`: con QR, `declared_at − window_start` pasa de 30 min (la marca se trata como sin QR).
*   `no_position`: la marca no trae posición.
*   `outside_zone`: la posición cae fuera de la zona del centro.
*   `declared_adjusted`: la hora contada difiere de la declarada.

**Toda** marca sin conexión sigue «Sin conexión · por verificar» hasta que Recursos Humanos la verifique. Las banderas deciden el orden de la lista; «Necesita tu atención» y Humi cuentan las marcas con bandera y las que llevan más de 7 días sin verificar.

*   **Cuánto puede ganar una mentira:** sin bandera, hasta 240 min (un boleto de menos de 4 h y una hora declarada falsa dentro del intervalo); con `wide_interval`, hasta 72 h contadas hasta que Recursos Humanos la verifique; con más de 72 h, nada: cuenta solo la cota probada. Siempre dentro de las marcas vecinas y nunca después del recibo.
*   **El boleto vale hasta el horizonte de la corrección:** 35 días. Más allá se rechaza con `ticket_expired`.
*   **El reloj del teléfono no es prueba.** La hora declarada sale de él y solo cuenta acotada. El teléfono manda además `device_clock_at` (sin firmar): la hora del reloj al entregar, que Recursos Humanos ve como pista de si el reloj estaba mal.
*   **Evidencia (D6):** sale de `MarkMethod::evidence()`; ninguna verificación la cambia.

### 4. Boleto, reto y verificación

*   **Boleto** (formato en el contrato de red): el payload lleva `tenant_id`, `employee_id`, la hora de emisión y un valor aleatorio de 16 bytes; HMAC-SHA256 con `APP_KEY`, verificado también con cada llave de `APP_PREVIOUS_KEYS`, así que rotar `APP_KEY` con su anterior en esa lista conserva boletos y pases.
*   **Quién da boletos y contexto:** un solo middleware de Attendance (`AttachOfflineTickets`) agrega `{tickets, context}` a la respuesta de toda acción verificada por firma (marcar, leer o confirmar la semana, corregir, aceptar el acuerdo) y a la de la entrega; el controlador marca la petición tras verificar la firma, también en las respuestas 409 y 422 posteriores. Además, `POST /me/{slug}/tickets` **sin sesión** recibe un boleto válido de la persona (de cualquier edad dentro del horizonte) y devuelve un lote nuevo y el contexto; el teléfono lo llama al abrir la app, al volver a primer plano y al reconectar, con al menos 30 s entre llamadas. El cuerpo puede traer `queue: {count, oldest_age_minutes}`, que solo se registra en el log `attendance.offline_queue_reported`. No se entregan por las propiedades de las páginas de `/me`: eso haría depender a `WorkerAccess` de Attendance.
*   **Agotamiento:** lote de 8, hasta 16 guardados. Cada marca gasta el boleto **más nuevo** que quede (la cota más estrecha) y lo descarta. Con 2 o menos, Marcar avisa «Pocos boletos»; con 0, «Aún no está listo: ábrala con internet» y no deja marcar sin conexión.
*   **Reto canónico v1:** la cadena y su codificación están en el contrato de red. `$expected` son los 32 bytes crudos del SHA-256 de la cadena UTF-8. Ningún campo puede contener `|` (se valida). El prefijo `v1` permite cambiar el formato sin romper entregas pendientes.
*   **`WorkerSignatures::verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): ?SignedBy`:** verifica la aserción contra el reto, con el origen, la verificación de la persona y el indicador de respaldo, **sin consumir caché y sin comprobar ni actualizar el contador** (las llaves sincronizadas reportan 0 y una entrega fuera de orden rompería el contador de las atadas al dispositivo). Una credencial revocada en `$ticketIssuedAt` o antes no verifica. `SignedBy` gana `revokedAt: ?CarbonImmutable`.
*   **Llave del dispositivo:** `DeviceKeySignatures::verify(string $expected, string $signature, string $pass, CarbonImmutable $ticketIssuedAt): ?DeviceSigned`, con `DeviceSigned = {deviceKeyId, employeeId, passId, passIssuedAt, passExpiresAt, revokedAt}`, y `DeviceKeySignatures::passId(string $pass): ?string`, que lee el id de un pase con HMAC válido de la empresa actual sin mirar su vencimiento (Attendance lo necesita para armar el reto antes de verificar). La firma ECDSA es sobre los 32 bytes del reto: WebCrypto y `openssl_verify` aplican su propio SHA-256 a ese mensaje.
*   **Revocación:** si `revokedAt` no es nulo y `declared_at > revokedAt`, la marca se responde `signature_invalid`. Las marcas declaradas antes de la revocación siguen valiendo, así que reemitir o «No fui yo» no pierden lo que el teléfono ya firmó.
*   **Registro de la llave del dispositivo, solo JSON:** `POST …/device-key/options` → `POST …/device-key` con la prueba de posesión (la llave firma los 32 bytes crudos del reto) responde 201 `{deviceKeyId, pass, passId, passExpiresAt}`; renovar el pase (`worker.device-key.pass`, con sesión) responde la misma forma. Sin prueba reciente responde 403 `{proofRequired: true}` y el cliente reutiliza el diálogo de prueba reciente. Con tres llaves activas, una cuarta reemplaza la de uso más antiguo (`replaced`).

### 5. Modelo de datos

*   **`attendance_marks`** (existente, solo inserción): `method` se ensancha a `varchar(20)` (`offline_declared` mide 16) y su CHECK suma `offline_qr` y `offline_declared`. `RecordOfflineMark` llena las mismas columnas que una marca en línea (posición, precisión, zona, distancia, credencial si firmó la llave de acceso, método); el plan de implementación las lista.
*   **`attendance_offline_marks`** (nueva, solo inserción por trigger; `BelongsToCurrentTenant`): una fila por marca entregada con boleto y firma válidos, aceptada o rechazada: motivo, marca creada, centro (y `away_center_id`), base de tiempo, firmante, valor del boleto (único por empresa), emisión, recibo, hora declarada, ventana del QR, hora contada, `review_flags` (jsonb), `device_clock_at`, `seq`, la nota opcional de la persona (`justification`, hasta 500 caracteres, sin firmar, solo para Recursos Humanos), versión del acuerdo y hashes del reto y la firma (idempotencia). La nota puede contener datos de salud; se acepta guardarla en una tabla de solo inserción porque el criterio 7.1 ya obliga a conservar el registro de asistencia, y nunca llega a logs.
*   **`attendance_mark_reviews`** (nueva, solo inserción): `mark_id`, `decision` (`verified` | `incorrect`), quién, nota y `seq` (`bigserial`): la decisión vigente es la de mayor `seq`.
*   **`worker_device_keys`** (nueva, `WorkerAccess`): llave pública (SPKI), etiqueta, fechas de uso y de revocación con su motivo. Hasta 3 activas por persona.
*   **`push_subscriptions`** (nueva, `Notifications`): `employee_id`, `endpoint` (único por empresa, solo https, host de una lista cerrada), `p256dh`, `auth`, fechas. Hasta 5 por persona. Si `minishlink/web-push` v11 exige `p256dh` y `auth` con cuerpo vacío es NO VERIFICADO; se comprueba al implementar.
*   Los esquemas exactos están en las entradas de migración del plan de implementación y mandan sobre esta lista. Todas las tablas con empresa llevan `tenant_id` y prueba de aislamiento entre empresas A y B. Las migraciones son aditivas y solo hacia adelante.

### 6. Reglas de `RecordOfflineMark` (acción nueva, separada de `RecordMark`)

El sobre se valida una vez; después cada marca se resuelve por separado y su resultado va en `results` (contrato de red). Pasos por marca:

1.  **Sobre** (`OfflinePunchRequest`): cuerpo de hasta 64 KB (si no, 413), forma de los campos y lote de hasta 20 (si no, 422). Un lote es de una sola persona: con boletos de dos personas, 422 `mixed_people`. `seq` es local del teléfono: identifica la marca en la respuesta y entra en el reto, pero no ordena nada en el servidor.
2.  **Boleto:** formato, HMAC con `APP_KEY` o una anterior, y empresa actual. Si no verifica: `rejected` con `ticket_invalid`, log y **sin fila**.
3.  **Idempotencia:** se busca por (empresa, valor del boleto). Mismo reto y misma firma devuelven `duplicate` con lo guardado (`stored_status` y `reason`), sin volver a verificar; reto o firma distintos devuelven `conflict` con `reason: ticket_reused`, sin fila.
4.  **Firma:** se reconstruye el reto v1 y se verifica con `verifyChallenge` o `DeviceKeySignatures::verify`. Si no verifica, nombra a otra persona que el boleto o su `declared_at` es posterior a `revokedAt`: `rejected` con `signature_invalid`, log y **sin fila** (no hay identidad confiable a quien atribuirla, y el boleto queda sin gastar para la marca auténtica).
5.  **Persona, centro y pase:** boleto más viejo que 35 días (`ticket_expired`); persona inactiva (`person_inactive`); `center_id` distinto del centro de la persona (`wrong_center`); en el plan B, `passId` distinto del `pass_id` del reto, `declared_at > passExpiresAt` o `passIssuedAt > received_at` (`pass_expired`).
6.  **Acuerdo:** cuenta la versión vigente en la hora de emisión del boleto (`agreement_not_accepted` si la persona nunca la aceptó). Una versión publicada mientras estaba sin conexión no rechaza una marca anterior.
7.  **QR:** si trae `qr` (`{windowIndex}.{hmac}`), se verifica el HMAC de esa ventana para el centro de la persona y, si no verifica, para los demás centros activos de la empresa. Si verifica para otro centro, se guarda `away_center_id` (donde estuvo físicamente). Un HMAC que no verifica para ningún centro se trata como si no hubiera QR. No hay búsqueda de ventanas.
8.  **Tiempo y secuencia:** `OfflineTime::decide` recibe la emisión del boleto, la ventana, los vecinos alrededor de `ref`, el recibo y la hora declarada, y devuelve la hora contada, la base y las banderas (sección 3). Si `lower > upper`, o el tipo no es coherente con los vecinos, se rechaza **solo esa marca** con `sequence_conflict`. **Marca ya registrada en línea:** si el choque es contra una marca en línea efectiva de la misma persona y del mismo tipo, creada después de la emisión del boleto y a no más de 10 min de `declared_at`, la respuesta es `duplicate` con `stored_status: received`, **sin fila**, y el log `attendance.offline_mark_already_recorded`; el teléfono dice «Ya estaba registrada» y la borra. Es el caso de una marca en línea cuya respuesta se perdió y su copia en la cola.
9.  **Posición:** sirve solo para las banderas y **nunca bloquea** una marca sin conexión. `MarkPolicyCheck` no se usa (con QR válido devolvería `QrAndGeofence`, que es fuerte).
10. **Inserción:** la marca (`marked_at = counted_at`) junto con la fila `accepted`, o solo la fila `rejected` con su motivo. Un choque del índice único del boleto dentro de la transacción (dos entregas a la vez) exige un savepoint (`DB::transaction` anidado) o capturarlo tras el rollback, y se resuelve como en el paso 3; se prueba.
11. **Eventos y registro:** dentro de la transacción se despacha `OfflineMarkAccepted` (el listener `ReopenOnOfflineMark` reabre las semanas con `MarkLock` ya tomado, como `MarkCorrected`). Después del commit: logs `attendance.offline_mark_received` y `attendance.offline_mark_rejected` (motivo, base, banderas, ancho del intervalo, `queue_age_minutes`, tipo de firmante; nunca boleto, QR, posición, nota ni credencial) y la bitácora `RecordAudit` de cada aceptación.

**Motivos guardados** (los lee la pestaña de Recursos Humanos): `ticket_expired`, `person_inactive`, `agreement_not_accepted`, `sequence_conflict`, `wrong_center`, `pass_expired`. **Solo de respuesta**, sin fila: `ticket_invalid`, `signature_invalid`, `ticket_reused` (el `conflict`) y `malformed` (lo asigna el teléfono a un elemento que el servidor rechazó a solas con 422 o 413).

**Límites:** el limitador `attendance-offline` cuenta **peticiones**: 600 por minuto por IP (un centro entero detrás de una NAT). No hay límite por persona: cada marca gasta un boleto de un solo uso y exige una firma nueva, así que el suministro de boletos ya es el freno. El de boletos, `attendance-tickets`, es aparte: 300 por minuto por IP, y no comparte el cubo del quiosco (`attendance-device`).

### 7. La semana y la verificación de Recursos Humanos

*   **Un solo cálculo de «lo que falta»:** `WeekCompleteness` (usada por `PersonWeek` y `ConfirmWeek::blocker`) cubre un día cuando: es de descanso en el horario **vigente ese día** (`ScheduleTimeline`, criterio 2.6, que main agregó el 2026-11-23: un horario nuevo no borra el anterior, así que una semana con cambio a mitad se lee día por día); es festivo (método nuevo `holidaysBetween` en `Leave\Contracts\Absences`, respaldado por `Leave\Support\Holidays`, que devuelve los festivos de ley del art. 74 más los de la empresa, no por centro; el contrato lo anota porque amplía «quién falta» a «qué días no se trabaja»); es anterior a la fecha de alta (`RosterEntry` gana `hiredOn`, que hoy no tiene; `EmployeeData` sí la trae); o tiene una ausencia aprobada (`Absences` solo cuenta solicitudes aprobadas o tomadas). La baja no hace falta: una persona dada de baja ya recibe 404 en Mi semana (`WeekController`). Una persona sin horario vigente ese día (sin ninguno, o un día anterior a su primer horario) no tiene días sin cubrir.
*   **`ConfirmWeek` rechaza** con `uncovered_days` y la lista de fechas. Hoy `ConfirmWeek::blocker` solo bloquea `no_marks` y una última jornada sin fin («Día abierto»); el bloqueo nuevo se suma y **afecta a toda empresa**. Lo controla `attendance.week.block_uncovered_days` (env `ATTENDANCE_WEEK_BLOCK_UNCOVERED`, encendido por omisión); apagado, el servidor calcula los días, registra `attendance.week_uncovered_days_computed` y no bloquea. «Objetar» sigue disponible. El servidor no sabe de marcas sin entregar: ese bloqueo lo hace la app, y el servidor se protege con el digest (`week_changed`) y con la reapertura.
*   **Cómo se cubre un día:** Mi semana ofrece pedir una corrección (agregar la marca que falta) o pedir a Recursos Humanos que registre la ausencia como permiso en `Leave`. Verificado contra el repo: Recursos Humanos puede registrar una solicitud de un día que ya pasó (`RequestLeave` rechaza `in_the_past` solo cuando la pide la persona); existe el tipo sin saldo «Permiso especial sin goce» (`LeaveType`, que `LeaveDefaults::ensure()` inserta); hace falta aprobarla para que cuente; `Leave` rechaza días festivos o fuera de la semana laboral (`no_working_days`) y solicitudes que se traslapan (`overlaps`).
*   **Reapertura:** `ReopenOnOfflineMark` llama a `ReopenWeeks` con el id de la marca; una semana objetada queda objetada.
*   **Mi semana** se muda de `/a/{slug}` a `/me/{slug}` sin cambiar su modelo de identidad (una firma por lectura y por acción).
*   **Pestaña «Marcas sin conexión»** en Correcciones: lista paginada en el servidor, las marcas con banderas primero, filtro «sin banderas», detalle con emisión, recibo, hora declarada, pista del reloj y nota. «Verificar seleccionadas» manda hasta 25 ids (una página) en un POST: una fila de verificación y una entrada de bitácora por marca. «Incorrecta» solo existe junto con su corrección (`CorrectMark`: nueva hora o anular), en una sola transacción. Las entregas rechazadas se ven en la misma pestaña; no hay un contador aparte en el resumen ni en Humi.

### 8. Aplicación en el teléfono

*   **Manifest** por empresa en `/me/{slug}/manifest.webmanifest` (`application/manifest+json`; nombre comercial; `scope` = `/me/{slug}`; `id` = `/me/{slug}/punch` y `start_url` = `/me/{slug}/punch` (si la empresa no tiene Asistencia, `/me/{slug}`: Marcar respondería 404), para que el ícono abra Marcar y no la entrada con llave; `lang: es-MX`; íconos de 180, 192, 512 y uno maskable) y `<link rel="manifest">` solo en el marco de `/me`.
*   **Service worker** en `/me/{slug}/sw.js` (detalle en el contrato de red): archivos del build primero de caché; el cascarón solo ante un error de red de una navegación dentro del alcance, nunca en lugar de un 4xx o 5xx; nada de HTML de Inertia, datos de personas ni peticiones que no sean GET. Con `PWA_SW_KILL`, el controlador sirve un worker que se desregistra y borra las cachés de ese slug; la página lee la meta `pwa-sw-kill`, no vuelve a registrar el worker y desregistra el existente. Nunca apaga la entrega. En desarrollo (Vite en caliente) no se registra. Se prueba con dos empresas de slug parecido (`/me/acme` y `/me/acme2`): ninguna atiende ni borra lo de la otra.
*   **Tiempos de espera:** la navegación y `punch/options` esperan como máximo 4 s antes de caer al cascarón o a la cola (antes de que el servidor registre nada). `punch/store` espera hasta 20 s; si se agota, la marca va a la cola, y si el servidor sí la había guardado, la entrega responde «Ya estaba registrada».
*   **Cola:** IndexedDB con las marcas, los boletos, el pase y el contexto; `navigator.storage.persist()` al instalar. Cada marca guarda el `employeeId`: `useOfflineQueue` muestra en `pending` y `kept` solo las de la persona actual, y «Confirmar semana» se apaga solo con su cola. Entrega cuando hay conexión, al abrir y al volver a primer plano, con espera creciente de 5 s a 15 min. Ante 422 o 413 parte el lote y reintenta elemento por elemento.
*   **Estados de una marca y dónde se ven:**

| Estado | Cuándo | Dónde |
|---|---|---|
| «Guardada sin conexión» | Al marcar sin conexión | Marcar, y el contador «n marcas sin enviar» en Marcar y en Inicio |
| «Recibida» | El servidor la aceptó (o ya la tenía: «Ya estaba registrada»); se borra de la cola | Marcar al entregar; después, Mi semana («Sin conexión · por verificar») |
| «Verificada» / «Incorrecta: corregida por Recursos Humanos» | Recursos Humanos la verificó; llega el aviso push | Mi semana (la marca y, si es incorrecta, su corrección) |
| «No aceptada: [motivo]» | Rechazada, con o sin fila; `malformed` | Marcar y Mi semana, hasta tocar «Entendido» o «Pedir corrección». Con `ticket_invalid`, «Entendido» pide confirmar, porque el servidor no guardó nada |

*   **«Listo para marcar sin conexión» o «Aún no está listo»:** revisa cámara, ubicación, bloqueo de pantalla, acuerdo vigente (`context.agreementVersion`), contexto y boletos. El único interruptor que lee el cliente es `context.offline`.
*   **Firma:** ante un `NotAllowedError` rápido, `signWithPasskey` reintenta una vez (solo en la ruta sin conexión; lo mide la compuerta del commit 0) antes de ofrecer el plan B.
*   **Lector de QR:** componente `QrScanner` con la librería `qr`: abre la cámara, espera cada `readFrame` (es asíncrona), acepta una dirección de Marcar con `?q=` de hasta 128 caracteres y cierra la cámara y el bucle al salir. Distingue «Ese código es de otra empresa» (otro slug) de un código de otro centro de la misma empresa, que es válido y lo decide el servidor.
*   **Instalación:** guía en el alta y en Marcar; en el navegador interno de un correo, «Ábralo en Safari o Chrome». En iPhone la instalación es manual; en Android, el aviso del navegador.

### 9. Avisos push

*   **Módulo `Notifications`** (`app/Modules/Notifications`): modelo `PushSubscription` (final, con `tenant_id` y `employee_id`); `PushHosts`, la lista cerrada de hosts de servicios push, con prueba de host desconocido; listeners de `OfflineMarkReviewed` (correcta o incorrecta) y `CorrectionResolved` (aprobada o rechazada), que llaman directamente a la clase emisora.
*   **Baja de suscripciones:** ante 404 o 410 del servicio push; al revocar o reemitir la llave desde Recursos Humanos (evento nuevo `WorkerAccessRevoked`); y desde el cliente con `DELETE push` al tocar «¿No es usted?» o «Salir».
*   **Envío:** un trabajo en la cola `default` con `tries=3` y `backoff=[60,300]`, TTL de 3600 s y espera de `Retry-After` en 429. El aviso no lleva contenido; el service worker muestra un texto fijo («Tiene un aviso en Humetría») que nunca nombra una marca ni una persona, y al tocarlo abre `/me/{slug}/punch`.
*   **Teléfono compartido:** mejor esfuerzo. Una suscripción es de un navegador; si otra persona se suscribe en el mismo teléfono, el aviso llega a la última.
*   **Configuración:** `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` y `VAPID_SUBJECT` (`mailto:` o `https:`) en `config/services.php` y `.env.example`. El cliente pide la llave pública a `GET portal.push.show`. El push hacia la empresa demo se bloquea como el correo.
*   **Permiso:** se pide después de la primera marca exitosa, desde una pantalla con sesión, con una frase antes del cuadro del sistema.

### 10. Norma, Humi, privacidad y operación

*   **Norma** (`docs/normas/jornada/criterios.md`, commit 1, con su prueba): 3.3 se precisa: la hora de una marca en línea la pone el servidor; la de una marca sin conexión se rige por 3.3.1. 3.3.1 «Marca sin conexión»: la regla de tiempo, el tope de 72 h, el QR como cota inferior y la hora declarada como no probada. 2.3, 2.6 y 2.9: horas extra y retardos se calculan con la hora contada aunque la marca esté por verificar (decisión 20). 3.2: pausas sin conexión con la misma regla. 3.4: posición capturada en el aparato. 4.2: el bloqueo fuera de zona no se aplica sin conexión; se señala con `outside_zone`. 5.1: versión nueva del acuerdo. 5.2: el bloqueo por días sin cubrir, su interruptor y cómo se suma a «Día abierto». 5.3: `offline_qr` media y `offline_declared` básica. 5.4: la verificación es una inserción. 6.1: el reporte muestra las marcas sin conexión, las por verificar y quién verificó. 7.1: conservación de la nota y de las entregas. 8.1: la persona ve sus marcas sin conexión y su estado. Historial v0.4. El contenido público de la herramienta (`app/Http/ToolContent/Attendance.php`, que dice que la hora es la del servidor y que el reloj del teléfono no cuenta) se ajusta en el mismo commit.
*   **Acuerdo:** la frase «La hora la pone el servidor, no su teléfono» (`CurrentAgreement`) cambia a «En una marca hecha con conexión, la hora la pone el servidor. En una marca hecha sin conexión, la hora que usted indica se acota con lo que el servidor sí puede comprobar y la revisa Recursos Humanos». El texto vive solo en `CurrentAgreement`. `context.agreementVersion` es la versión vigente si la persona la aceptó y `null` si no (método nuevo de `AgreementStatus`).
*   **Humi:** `AttendanceReader` cuenta las marcas sin conexión por verificar y las que necesitan atención (con bandera o con más de 7 días); solo conteos y estados. Los criterios nuevos entran al buscador. `ModuleCoverageTest` incluye `Notifications` con su razón.
*   **Privacidad y registro:** la nota de la persona es solo para Recursos Humanos y nunca va a logs (`RedactSensitiveData` agrega `justification`, `ticket`, `pass` y `challenge`). Las propiedades de la pestaña nueva tienen su prueba de «sin respuestas individuales». Cada evento nuevo de bitácora tiene su etiqueta y cada clave nueva de metadatos su entrada (`AuditEventLabelsTest`).
*   **Seguridad HTTP:** la cabecera de D8 en `/me/{slug}/*`; la entrega, los boletos, el manifest, `sw.js` y el cascarón en el grupo sin sesión (los boletos con `ResolveDeviceTenant`, que responde 404 si la empresa no tiene `attendance`); la entrega y los boletos fuerzan respuestas JSON; todas las rutas nuevas entran a `RouteSecurityTest` con su razón.
*   **Enmiendas a CLAUDE.md** (en el mismo PR): (1) una línea `Notifications` en «Módulos»: avisos push sin contenido a la persona trabajadora; (2) «No hay módulo de notificaciones…» pasa a decir que cada módulo manda sus correos con su carpeta `Notifications/` y que el módulo `Notifications` solo hace push (ni correo ni WhatsApp; WhatsApp sale solo por `Messaging`, que llegó a main en #147); (3) en «Rutas», que Marcar y Mi semana viven bajo `/me/{slug}`, que la entrega de marcas sin conexión y los boletos son rutas sin sesión cuya credencial es la firma o el boleto, y `notifications-portal.php` en el portal.
*   **Operación y reversa:** la función sin conexión tiene una llave, `attendance.offline.enabled` (decisión 26), que el cliente lee por `context.offline`: apaga la cola y la interfaz, nunca la entrega. Aparte están `PWA_SW_KILL` (el worker) y el interruptor del bloqueo de la semana. Esquema aditivo y solo hacia adelante. `docs/operations/pwa.md` (nuevo) documenta variables, interruptores, reversa, consultas y umbrales de medición y la revisión en teléfonos.
*   **Orden de commits** (un PR por decisión 1; el detalle es del plan de implementación): 0 compuerta de Android · 1 norma · 2 mudanza a `/me` · 3 manifest e íconos · 4 cascarón y service worker · 5 cabecera de cámara y ubicación, y lector de QR · 6 acuerdo · 7 esquema sin conexión · 8 reto v1 y `verifyChallenge` · 9 boletos y token nuevo del QR · 10 llave del dispositivo y pase · 11 entrega · 12 reapertura · 13 semana · 14 verificación de Recursos Humanos · 15 Humi · 16 módulo `Notifications` · 17 cliente de push · 18 guía de instalación · 19 cliente sin conexión · 20 aviso de privacidad · 21 manual. Reversa: el 19 sale solo; 16 a 18 no dependen de lo sin conexión.

### 11. Parámetros

Esta tabla es la fuente única de los parámetros; el plan y `docs/operations/pwa.md` apuntan aquí.

| Parámetro | Llave de configuración | Variable de entorno | Valor |
|---|---|---|---|
| Función sin conexión en el cliente | `attendance.offline.enabled` | `ATTENDANCE_OFFLINE_ENABLED` | encendida |
| Boletos por lote | `attendance.offline.ticket_batch` | — | 8 |
| Boletos guardados en el teléfono | cliente | — | 16 |
| Horizonte del boleto | `attendance.offline.ticket_horizon_days` | — | 35 días |
| Corte de intervalo ancho | `attendance.offline.wide_interval_minutes` | — | 240 min |
| Hora declarada cuenta hasta | `attendance.offline.count_declared_max_hours` | — | 72 h |
| Ventana vieja del QR | `attendance.offline.qr_old_window_minutes` | — | 30 min |
| Marca ya registrada en línea | `attendance.offline.already_recorded_window_minutes` | — | 10 min |
| Marca vieja sin verificar | `attendance.offline.review_age_days` | — | 7 días |
| Marcas por entrega | `attendance.offline.delivery_batch_max` | — | 20 |
| Cuerpo de la entrega | `OfflinePunchRequest` | — | 64 KB |
| Nota de la persona | `OfflinePunchRequest` | — | 500 caracteres |
| Bloqueo por días sin cubrir | `attendance.week.block_uncovered_days` | `ATTENDANCE_WEEK_BLOCK_UNCOVERED` | encendido |
| Llaves del dispositivo activas | `worker_access.device_keys_max` | `WORKER_ACCESS_DEVICE_KEYS_MAX` | 3 |
| Vigencia del pase | `worker_access.pass_hours` | `WORKER_ACCESS_OFFLINE_PASS_HOURS` | 24 h |
| Apagado del service worker | `worker_access.pwa_sw_kill` | `PWA_SW_KILL` | apagado |
| Límite de entrega por IP | limitador `attendance-offline` | — | 600 peticiones por minuto |
| Límite de boletos por IP | limitador `attendance-tickets` | — | 300 por minuto |
| Espacio entre pedidos de boletos | cliente | — | 30 s |
| Límite de la llave del dispositivo | limitador `worker-device-key` | — | 30 por minuto por IP, 20 por minuto por sesión |
| Espera de navegación y `punch/options` | cliente | — | 4 s |
| Espera de `punch/store` | cliente | — | 20 s |
| Espera de la cola entre entregas | cliente | — | de 5 s a 15 min |
| Suscripciones push por persona | `Notifications` | — | 5 |
| TTL del aviso push | `Notifications` | — | 3600 s |
| Llaves VAPID | `services.vapid.*` | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` | sin valor en `.env.example` |

## Alternativas consideradas

Las decisiones de esta sección las tomó el diseño por la delegación del dueño (decisión 28), salvo las que citan otra decisión del brief. Cada una dice su razón técnica. Las letras A a Z siguen el orden del primer borrador; las de dos letras (AA en adelante) se agregaron después y están junto a su tema.

### Tiempo y boleto

#### Alternativa A: Latido cada minuto con tabla de presencia (D1, opción 4)

*   **A favor**: El servidor tiene siempre el último instante en que vio el teléfono.
*   **En contra**: En la reconexión el latido avanza el ancla antes de entregar la marca y el intervalo queda en cero. Un latido con sesión reinicia la sesión de 30 minutos. Escribe una fila por teléfono cada minuto y guarda un registro de presencia de personas.
*   **Por qué se descarta**: El boleto firmado da la misma cota inferior sin tabla, sin carrera y sin registro de presencia.

#### Alternativa B: Tabla de anclas con valores de un solo uso guardados en el servidor (D1, opción 2)

*   **A favor**: Sigue la letra de WebAuthn (el servidor genera y guarda los retos), permite revocar por llave y mostrar «última vez visto».
*   **En contra**: Escribe filas en cada renovación y hay que podarlas; si la poda borra una fila antes de que llegue una marca de un apagón largo, la marca se rechaza. Es presencia de personas.
*   **Por qué se descarta**: El volumen de escritura y el registro de presencia pesan más que revocar por llave; si el boleto no basta se puede pasar a esta opción sin cambiar el reto.

#### Alternativa C: Valor aleatorio elegido por el teléfono (D1, opción 3)

*   **A favor**: Sin piezas nuevas; usa `last_used_at` de la llave como cota.
*   **En contra**: Rompe la regla de WebAuthn de que el reto lo genera el servidor; permite preparar firmas de antemano; la cota solo se mueve cuando la persona firma en línea, así que casi todo intervalo sería ancho.
*   **Por qué se descarta**: Pierde frescura y deja la repetición abierta.

#### Alternativa D: Confiar en la hora del teléfono al reconectar

*   **A favor**: Sin cotas que calcular.
*   **En contra**: Basta cambiar el reloj del sistema; contradice el criterio 3.3.
*   **Por qué se descarta**: No hay hora del teléfono que no se pueda manipular; solo cuenta acotada (decisión 25).

#### Alternativa E: Reloj monótono del navegador (`performance.now`) desde la emisión del boleto

*   **A favor**: No depende del reloj del sistema.
*   **En contra**: Se pausa cuando el teléfono duerme (error 225610 de WebKit): resta tiempo y favorece a quien llega tarde. No sobrevive a un reinicio de la app.
*   **Por qué se descarta**: El error favorece justo el caso que se quiere limitar.

#### Alternativa F: Tres tramos por ancho del intervalo (decisión 14 del brief)

*   **A favor**: Con más de 4 h no contaba nada no probado: el recibo si la marca abría un tramo, la emisión del boleto si lo cerraba.
*   **En contra**: La emisión del boleto queda en o antes de la marca en línea que abrió el día, así que la salida de un día de apagón contaba a la hora de la entrada: el día valía casi cero minutos, retardos y horas extra (criterios 2.6 y 2.9) salían mal y Recursos Humanos tenía que verificar a toda la planta. Su regla «estrictamente entre las vecinas» rechazaba además el caso común (entrada en línea, salida sin conexión entregada horas después).
*   **Por qué se descarta**: La hora declarada acotada cuenta bien el día honesto y lo que una mentira gana queda limitado: hasta 240 min sin bandera, hasta 72 h con `wide_interval` y nada más allá de 72 h, donde cuenta la cota probada como pedía esta alternativa.

#### Alternativa G: Sello del servidor como única cota inferior, sin recibo ni vecinos

*   **A favor**: Una sola marca del servidor; nada que calcular al entregar.
*   **En contra**: Un sello tomado temprano deja declarar cualquier hora posterior, incluso después de la entrega o encima de otras marcas.
*   **Por qué se descarta**: La hora se acota por las dos puntas y por las marcas vecinas (ya rechazada en el brief del 2026-11-14).

#### Alternativa AA: El QR como hora exacta (±30 s) sin verificación (decisión 21 del brief)

*   **A favor**: Una marca con QR contaría al inicio de la ventana y no necesitaría a Recursos Humanos.
*   **En contra**: El QR prueba que el código existía, no cuándo se leyó. En línea se aceptan solo la ventana actual y la anterior (unos 60 s, `QrToken`); sin conexión una foto reenviada del código se puede leer horas o días después y daría hora exacta y evidencia media.
*   **Por qué se descarta**: El QR es cota inferior; con más de 30 min entre la ventana y la hora declarada la marca es `offline_declared` (básica) con `old_window`; toda marca sin conexión queda por verificar. Se quita también la regla de la decisión 21 de mandar a solicitud un QR de más de 72 h: con la ventana en el token y la marca degradada a básica, la edad deja de dar ventaja.

#### Alternativa AK: Token del QR sin cambio y búsqueda acotada de la ventana (48 a 72 h)

*   **A favor**: No cambia el formato del QR del quiosco.
*   **En contra**: Para encontrar la ventana el servidor prueba hasta 8 640 HMAC por marca (72 h de ventanas de 30 s), necesita un tope de edad del QR y una regla para cuando no la encuentra.
*   **Por qué se descarta**: Con `{windowIndex}.{hmac}` se verifica un solo HMAC; no hay QR impresos ni clientes, así que el cambio de formato no rompe nada.

#### Alternativa AF: Cursor del último boleto entregado por persona

*   **A favor**: Cerraría el caso de quien, con conexión, guarda boletos viejos para declarar horas anteriores: se señalaría toda marca cuyo boleto es anterior al último lote entregado.
*   **En contra**: Una tabla más (`attendance_ticket_cursors`) y una escritura por lote entregado, con falsos positivos cuando se pierde la respuesta que traía el lote.
*   **Por qué se descarta**: La exposición ya está acotada sin él: un boleto viejo solo ensancha el intervalo, que con más de 240 min enciende `wide_interval` y con más de 72 h deja contar solo la cota probada. El riesgo que queda (hasta 240 min sin bandera) está en «Riesgos y mitigaciones».

### Almacenamiento, verificación y evidencia

#### Alternativa H: Columnas nuevas en `attendance_marks` (D2)

*   **A favor**: Una sola tabla, sin `JOIN`.
*   **En contra**: Ensancha la tabla de marcas más consultada con columnas que casi ninguna fila usa; la nota y la posición quedan junto a datos que se leen en todo reporte; las entregas rechazadas no tienen marca y necesitarían otra tabla de todos modos.
*   **Por qué se descarta**: La tabla de entregas guarda aceptadas y rechazadas en un solo lugar.

#### Alternativa I: Verificación como edición de la marca (`UPDATE`)

*   **A favor**: Sin tabla nueva.
*   **En contra**: `attendance_marks` es de solo inserción por trigger (criterio 5.4).
*   **Por qué se descarta**: Imposible sin cambiar la norma; la verificación es un registro enlazado (decisión 8).

#### Alternativa J: Verificación como otra marca que suma horas

*   **A favor**: Reutiliza todo el modelo de marcas.
*   **En contra**: Duplicaría horas y confundiría el reporte.
*   **Por qué se descarta**: La verificación es un registro relacionado, no una marca (decisión 8).

#### Alternativa AB: «Correcta» inserta una corrección a la hora declarada

*   **A favor**: Con los tres tramos, era la forma de que la hora declarada contara tras verificar sin editar la marca.
*   **En contra**: La corrección vuelve la marca efectiva `EvidenceLevel::Corrected` y pierde la etiqueta «Sin conexión» y el firmante; `CorrectMark` solo rechaza una hora futura y una marca ya corregida, y no copia credencial, posición ni zona.
*   **Por qué se descarta**: Con la regla de tiempo única la hora no cambia al verificar. Cuando Recursos Humanos necesita otra hora (más de 72 h, o una marca incorrecta) usa el flujo de corrección, y el reporte (`BuildAuthorityReport`) toma la etiqueta y el firmante de la marca original por `corrects_mark_id`.

#### Alternativa K: Plan B con evidencia propia del firmante

*   **A favor**: Distinguiría en la evidencia la llave del dispositivo (sin rostro) de la llave de acceso.
*   **En contra**: La evidencia sale del método (`MarkMethod::evidence()`), que no conoce al firmante; agregar un nivel por firmante cambia el reporte y la norma 5.3 por una diferencia que Recursos Humanos ya ve en la etiqueta del firmante.
*   **Por qué se descarta**: La evidencia sale de la base de tiempo; el firmante se muestra aparte.

#### Alternativa L: Solicitud de corrección como único camino sin conexión (brief del 2026-11-14)

*   **A favor**: Cumple el criterio 3.3 al pie de la letra.
*   **En contra**: La persona no deja constancia propia ni cuenta en horas.
*   **Por qué se descarta**: La marca debe contar, señalada (decisiones 2 y 4 del brief).

#### Alternativa AC: Página nueva de verificación en el panel

*   **A favor**: Espacio para pestañas y un panel de contexto (marcas en línea de otras personas del centro en ese tramo, última vez que se vio la pantalla del centro).
*   **En contra**: Una página y su diseño, consultas y pruebas. El brief había rechazado una pantalla nueva suponiendo que ya existía una lista de marcas; no existe (`routes/attendance.php` no tiene una lista por marca).
*   **Por qué se descarta**: Por tamaño: la pestaña dentro de Correcciones, con verificación de varias a la vez, cubre el trabajo de un apagón. El panel de contexto queda en «Mejoras futuras».

#### Alternativa AD: Solicitud nueva de justificación de falta (decisión 13 y pregunta 15 del brief)

*   **A favor**: La persona inicia y Recursos Humanos aprueba desde una bandeja, sin salir de Asistencia.
*   **En contra**: Dos tablas, cuatro acciones, dos páginas, un aviso y un evento nuevos. `CorrectionRequest` no la puede guardar (`type` no nulo con CHECK de cuatro tipos de marca, `proposed_at` no nulo, aprobar exige `resulting_mark_id`), y `Leave` ya deja a Recursos Humanos registrar el permiso.
*   **Por qué se descarta**: Para esta versión, Recursos Humanos registra la ausencia en `Leave`; el flujo nuevo queda en «Mejoras futuras».

#### Alternativa AL: Declarar la falta dentro de la confirmación de la semana

*   **A favor**: La persona confirma «no trabajé estos días» con su firma, sin depender de Recursos Humanos ni crear permisos en `Leave`.
*   **En contra**: Cambia el sentido de la decisión 13 de «bloquear» a «bloquear hasta declarar», y el digest, el reporte y la norma 5.2 deben llevar las fechas declaradas.
*   **Por qué se descarta**: Para esta versión se queda el bloqueo con su interruptor; queda en «Mejoras futuras».

### Entrega

#### Alternativa M: Entrega con sesión y CSRF desde la página (D3)

*   **A favor**: El mismo modelo de seguridad que el resto de las rutas.
*   **En contra**: Tras un apagón la sesión puede no existir o haber vencido, quien solo usa Marcar nunca la abre, y sin Fetch Metadata (iOS anterior a 16.4) no hay `Sec-Fetch-Site` que deje pasar el CSRF sin token.
*   **Por qué se descarta**: La firma y el boleto de un solo uso son la credencial.

#### Alternativa AG: Exención de CSRF por ruta en `bootstrap/app.php`

*   **A favor**: Una línea (`preventRequestForgery(except: [...])`).
*   **En contra**: Deja correr `StartSession`, que con `SESSION_DRIVER=database` escribe una fila de sesión por entrega sin cookie, y crea un segundo patrón; el repo ya tiene uno probado sin sesión en `routes/complaints-public.php`.
*   **Por qué se descarta**: Se reutiliza el grupo con `withoutMiddleware` y `RejectCrossSiteRequests`.

#### Alternativa N: Entrega desde el service worker (Background Sync)

*   **A favor**: Sale aunque la app esté cerrada.
*   **En contra**: iPhone no tiene sincronización en segundo plano; Android la ofrece de forma desigual.
*   **Por qué se descarta**: No puede ser la vía principal; la entrega sale de la página.

### Identidad y plan B

#### Alternativa O: Sin plan B

*   **A favor**: Menos piezas y menos superficie de seguridad.
*   **En contra**: Una persona con la llave en Google Password Manager en Chrome de iPhone no puede marcar sin conexión, y la app no sabe dónde guardó su llave.
*   **Por qué se descarta**: Nadie debe quedarse sin poder marcar (decisión 15).

#### Alternativa AE: La llave del dispositivo como único firmante sin conexión

*   **A favor**: Quita la ceremonia de llave de acceso sin conexión, la práctica del alta, el aviso por AAGUID y el reintento; la evidencia es la misma con cualquier firmante, y el plan B hace falta igual.
*   **En contra**: La llave del dispositivo no pide rostro ni huella; la llave de acceso sí, y donde firma sin conexión (iCloud: probada) da más garantía de que marcó la persona.
*   **Por qué se descarta**: Por ahora se conservan los dos firmantes (decisiones 5 y 15). En Android lo decide la compuerta del commit 0: si no alcanza 4 de 5 en 2 teléfonos, se adopta esta alternativa allí.

#### Alternativa P: Marca sin conexión sin firma, con la sesión de la persona como única identidad

*   **A favor**: No necesita llave del dispositivo.
*   **En contra**: La sesión vence a los 30 minutos sin uso y a las 8 horas, no se renueva sin conexión y no prueba posesión de nada.
*   **Por qué se descarta**: Vence antes de lo que dura un apagón.

#### Alternativa Q: PRF, `largeBlob` o `devicePubKey` para proteger la llave local

*   **A favor**: Prometían prueba de posesión de un dispositivo sin llaves nuevas.
*   **En contra**: PRF necesita el mismo `get()` que falla; `largeBlob` solo guarda; `devicePubKey` se eliminó de la especificación (PR #2109). `getClientCapabilities` no informa de la red.
*   **Por qué se descarta**: No resuelven el caso que falla.

#### Alternativa R: Forzar que la llave se guarde en iCloud o Google

*   **A favor**: Evitaría el caso de Chrome en iPhone.
*   **En contra**: Un sitio no puede impedir que se guarde en un proveedor de terceros; `hints` es solo una sugerencia.
*   **Por qué se descarta**: No es posible desde un sitio web. El AAGUID y la práctica sin conexión solo avisan, nunca controlan.

#### Alternativa AH: Una clase común de firma HMAC, o reutilizar `TicketSigner`

*   **A favor**: Una sola pieza para firmar y verificar con rotación.
*   **En contra**: El repo firma con `hash_hmac` en línea sobre `app.key` con prefijo de dominio, y solo `PageLink` rota (`[app.key, ...app.previous_keys]`); `TicketSigner` de Complaints está atado a un código de canal y lee `complaints.ticket.*`.
*   **Por qué se descarta**: `OfflineTicket` y `OfflinePasses` son clases propias que siguen el patrón de rotación de `PageLink`.

### Aplicación: service worker, cámara, lector de QR y push

#### Alternativa S: Service worker que guarda las páginas de Marcar

*   **A favor**: Abriría Marcar sin conexión sin un cascarón aparte.
*   **En contra**: La CSP lleva un nonce por petición, así que los scripts de una página guardada quedan bloqueados; sirve datos de una persona a otra en un teléfono compartido; tras un despliegue apunta a archivos que no existen.
*   **Por qué se descarta**: Solo se guardan el cascarón estático y los archivos del build.

#### Alternativa T: Service worker en la raíz del sitio, Workbox o `vite-plugin-pwa`

*   **A favor**: Menos código propio.
*   **En contra**: Una raíz cubriría `/admin` y las páginas anónimas; Workbox y el complemento son dependencias nuevas para unas 100 líneas.
*   **Por qué se descarta**: Un worker por empresa bajo `/me/{slug}`, escrito a mano.

#### Alternativa AI: `camera=(self), geolocation=(self)` en todo `/me/{slug}/*` (D8, adoptada)

*   **A favor**: Una regla en `SecurityHeaders`; sirve igual con enlaces de Inertia o de documento completo; arregla también la ubicación de hoy, que falla al llegar a Marcar con un enlace de Inertia desde Inicio (`home-sections.tsx` usa `<Link>`, y `MarkController` pone la política solo en su propia respuesta).
*   **En contra**: Inicio, Accesos y vacaciones quedan con permiso para pedir cámara. Pero `Permissions-Policy` solo permite que una página la pida: el navegador sigue mostrando su cuadro, y una página que no llama a la cámara nunca lo hace.
*   **Por qué se adopta**: Es más chica y no se rompe sola, a diferencia de la Alternativa AM.

#### Alternativa AM: Política de cámara solo en Marcar, con enlaces de documento completo hacia ella

*   **A favor**: La cámara solo se puede pedir en Marcar y en el cascarón.
*   **En contra**: Cambios en `WorkerSectionLink`, `HomeController`, `home-sections.tsx`, `PersonFrame`, la pantalla de la semana y un tipo de TypeScript, una prueba de navegador y una regla permanente en CLAUDE.md («todo enlace a Marcar es de documento completo») que se rompe en silencio la primera vez que alguien agregue un `<Link>`.
*   **Por qué se descarta**: La política no concede nada por sí sola (Alternativa AI).

#### Alternativa U: Lector de QR con WebAssembly, un worker de blob o `BarcodeDetector` nativo

*   **A favor**: Más rápido o más preciso en algunos teléfonos.
*   **En contra**: WebAssembly y los workers de blob exigirían aflojar la CSP; iPhone no tiene `BarcodeDetector`. Se descartaron `zxing-wasm`, `barcode-detector`, `@yudiel/react-qr-scanner` y `qr-scanner` por eso; `html5-qrcode` y `@zxing/*` por abandono o modo mantenimiento.
*   **Por qué se descarta**: La librería `qr` es JavaScript puro, sin dependencias y compatible con la CSP (decisión 12). Su respaldo es `jsqr`.

#### Alternativa V: Push dentro de `WorkerAccess`, con `laravel-notification-channels/webpush` o un emisor propio (D10)

*   **A favor**: Menos módulos; el paquete trae el ciclo completo.
*   **En contra**: El paquete trae su propio modelo sin `tenant_id` y supone un usuario con cuenta. Un emisor propio cuesta unas 200 líneas y los errores de formato fallan en silencio con Apple. Meter el push en `WorkerAccess` mezcla identidad con mensajería.
*   **Por qué se descarta**: Módulo `Notifications` propio y `minishlink/web-push ^11.0` (decisiones 10 y 12).

#### Alternativa AN: Mandar el push por el módulo `Messaging` (agregada al conciliar con main, 2026-11-24)

*   **A favor**: Ya existe un módulo de transporte (`Messaging`, #147) con consentimiento, pausas, historial y una regla de «un solo canal por persona»; el push sería un canal más y no habría un segundo módulo de envío.
*   **En contra**: `Messaging` es transporte de **WhatsApp y de eventos de correo**: lleva el teléfono (solo su HMAC), el consentimiento de baja por mensaje (STOP/ALTA), plantillas aprobadas por Meta y un medidor que Billing cobra. Un push no tiene teléfono, ni baja por mensaje, ni plantilla, ni cobro; su credencial es una suscripción del navegador ligada a un teléfono concreto y se borra con 404/410. Y el historial por mensaje de `Messaging` guarda la hora exacta de cada envío por persona, justo lo que este diseño evita guardar (el push no deja registro por aviso). Encajarlo obligaría a flexionar los dos contratos.
*   **Por qué se descarta**: Los dos módulos no comparten datos ni reglas; el módulo `Notifications` queda solo para push (ni correo ni WhatsApp) y `Messaging` no se toca. Si en el futuro la empresa elige canales por persona incluyendo push, se unifica el selector en `ChannelPicker`, no el transporte. **Pendiente del dueño**: el nombre `Notifications` junto a `Messaging` puede confundir con las carpetas `Notifications/` de cada módulo; el diseño lo conserva y cambiarlo a `Push` es un renombre mecánico antes del commit 16.

#### Alternativa AJ: Cola propia de avisos push (tabla de pendientes con contenido cifrado)

*   **A favor**: Permitiría textos distintos por aviso o reenviar.
*   **En contra**: El texto es fijo y el destino siempre es Marcar, así que el contenido cifrado, el tipo de aviso y la tabla no se usan; la cola de Laravel ya da reintentos.
*   **Por qué se descarta**: Avisos sin contenido enviados por un trabajo de la cola `default`.

### Semana

#### Alternativa W: Confirmar con la sesión, sin firma

*   **A favor**: Menos pasos para quien confirma varias semanas.
*   **En contra**: Toca los criterios 1.3 y 5.2: la confirmación guarda la llave que firmó y eso sostiene el valor de «acordado».
*   **Por qué se descarta**: Sería un cambio de norma; se conserva la firma al confirmar y objetar.

#### Alternativa X: Dejar que la persona confirme con días vacíos

*   **A favor**: No exige nada nuevo.
*   **En contra**: Es lo que hace el servidor hoy: se confirma sin ninguna marca de un día laborable.
*   **Por qué se descarta**: Una semana no se confirma con un día sin cubrir (decisión 13).

### Alcance

#### Alternativa Y: Relevo de la marca por la pantalla del centro (`screen_relay`)

*   **A favor**: Marca real a través de la pantalla del centro, con hora del servidor.
*   **En contra**: Pide una credencial de escritura de la pantalla, seis dígitos y una copia firmada, y solo sirve donde la pantalla tiene conexión y el teléfono no.
*   **Por qué se descarta**: Su costo no cabe en este PR y el caso que cubre ya lo cubre la marca sin conexión con QR; queda en «Mejoras futuras» (el relevo de dos escaneos ya lo había rechazado el dueño el 2026-11-14).

#### Alternativa Z: Encuestas anónimas dentro de la app con boletas ciegas (RFC 9474)

*   **A favor**: Permitiría contestar sin conexión.
*   **En contra**: Agrega criptografía y una boleta que se pierde si se borran los datos; no elimina la correlación por hora e IP.
*   **Por qué se descarta**: Las encuestas quedan fuera de la app.

## Requisitos no funcionales

*   **Seguridad y privacidad.** La entrega y los boletos son las únicas rutas de escritura nuevas sin sesión ni CSRF; su defensa está en D3, la sección 6 y «Parámetros». Boleto, pase, QR, reto, posición y nota no llegan a logs ni a bitácora. El service worker no guarda páginas ni datos de personas. Cada suscripción push va a un host de una lista cerrada. La llave del dispositivo firma sin rostro ni huella; Recursos Humanos ve qué firmante usó cada marca.
*   **Volumen (estimaciones).** `attendance_offline_marks` gana una fila por marca sin conexión aceptada o rechazada con firma válida: en un centro de 500 personas, un día de apagón completo son unas 2 000 filas (4 marcas por persona); un día normal, casi ninguna. Al volver la conexión, 500 teléfonos mandan una petición cada uno (hasta 20 marcas): 500 peticiones en el primer minuto, debajo de 600 por IP. Los mismos 500 piden boletos al reconectar: arriba de 300 por minuto algunos reciben 429 y reintentan 30 s después, sin efecto porque aún tienen boletos. Un elemento pesa entre 1.5 y 2.5 KB (firma WebAuthn, boleto de 235 caracteres, pase de hasta 512): un lote de 20 cabe en 64 KB.
*   **Rendimiento.** Verificar el QR de una marca es un HMAC (la ventana viene en el token); la firma se verifica una vez por marca. La pestaña del panel se pagina y busca en el servidor y afirma su número de consultas.
*   **Despliegue y reversa.** Esquema aditivo; la función sin conexión del cliente, el worker y el bloqueo de la semana tienen cada uno su interruptor; el commit 19 se revierte solo (sección 10).

## Riesgos y pruebas en teléfonos

### Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Una persona miente en la hora declarada. | Lo que gana está acotado por sus marcas vecinas y el recibo: hasta 240 min sin bandera (un boleto de menos de 4 h, por ejemplo abrir la app a las 08:00 y declarar 08:30 a las 09:30); con `wide_interval`, hasta 72 h contadas hasta que Recursos Humanos la verifique; con más de 72 h, nada. Toda marca sin conexión queda por verificar. El cursor por persona (Alternativa AF) se descartó. |
| Una foto reenviada del QR leída lejos del centro o tarde. | El QR es solo cota inferior; con más de 30 min la marca es básica; `old_window`, `no_position` y `outside_zone` la ponen primero en la lista. |
| Un teléfono robado con la llave ya revocada. | Las marcas declaradas después de la revocación se responden `signature_invalid`; con la llave del dispositivo, además, el pase dura como máximo 24 h. |
| La persona borra los datos de la app antes de entregar. | `persist()`, app instalada, contador visible y texto honesto (decisión 22). Excepción declarada de la meta M4. |
| Marcas sin verificar que se acumulan. | Siguen contando (decisión 19); «Necesita tu atención» y Humi cuentan las de más de 7 días y las con bandera; verificar varias a la vez. |
| Datos personales en el teléfono (posición, nota, boletos, pase y contexto en IndexedDB). | Solo eso; la marca se borra cuando el servidor la tiene o al tocar «Entendido»; nada de páginas ni de Mi semana. |
| Un centro entero detrás de una NAT reconecta a la vez. | 600 entregas y 300 pedidos de boletos por minuto por IP, en cubos propios que no tocan al quiosco; los 429 de boletos no impiden marcar. |
| Un elemento mal formado bloquea la cola. | Ante 422 o 413 el teléfono reintenta elemento por elemento; solo el malo queda «No aceptada» (`malformed`). |
| Una marca en línea cuya respuesta tardó se guarda dos veces. | `punch/store` espera 20 s; si la copia en cola choca con la misma marca en línea (mismo tipo, a menos de 10 min), responde «Ya estaba registrada». |
| Se agotan los boletos en un apagón largo. | Aviso «Pocos boletos» con 2 o menos; con 0, Marcar dice que hace falta abrir la app con internet. Con 4 marcas al día, 16 boletos alcanzan para 4 días. |
| `ticket_invalid` (por ejemplo, `APP_KEY` rotada sin conservar la anterior) deja una marca solo en el teléfono. | La rotación con `APP_PREVIOUS_KEYS` conserva los boletos; el teléfono conserva la marca «No aceptada» y «Entendido» pide confirmar; el log alerta con más de 3 al día. |
| El bloqueo por días sin cubrir frena confirmaciones de toda empresa: horarios desactualizados, personas sin fecha de alta, `Leave` que rechaza `no_working_days` u `overlaps`, o una falta simple que se vuelve permiso. | Interruptor `ATTENDANCE_WEEK_BLOCK_UNCOVERED` (apagado, solo log); «Objetar» siempre disponible; la persona sin horario no se bloquea; declarar la falta en la confirmación queda en «Mejoras futuras». |
| La llave de acceso no firma sin conexión desde la app instalada de Android. | Compuerta del commit 0; el plan B cubre a esa persona; por debajo del criterio, Alternativa AE en Android. |
| Rolldown parte el service worker en trozos y un worker clásico con `import` no se activa. | Compilación aparte con salida fija, decidida con un experimento corto en el commit 4; la prueba de arranque en frío lo detecta. |
| `minishlink/web-push` necesita `ext-gmp` o `bcmath` en la imagen de Dokploy. | NO VERIFICADO; se comprueba al implementar y se anota en `docs/operations/pwa.md`. |
| La llave del dispositivo firma sin rostro ni huella. | Pase de 24 h, revocación por «No fui yo», reemisión y Recursos Humanos; Recursos Humanos ve el firmante. |

### Pruebas en teléfonos reales

Esta es la lista única; el plan de implementación y `docs/operations/pwa.md` apuntan aquí. Las pruebas de navegador simulan la falta de conexión haciendo fallar `fetch` dentro de la página; el arranque real sin conexión solo se prueba en teléfonos.

| # | Dispositivo | Sistema | Acción | Resultado esperado | Umbral |
|---|---|---|---|---|---|
| 1 | 2 Android, uno viejo o con Play services desactualizado (commit 0) | Android con Chrome, app instalada | Firmar sin conexión con la llave de acceso (Google Password Manager), 5 intentos por teléfono, con un reintento ante `NotAllowedError` de menos de 2 s | Firma válida | ≥ 4 de 5 en cada teléfono |
| 2 | Los mismos (commit 0) | Android, app instalada | Pedir `persist()` y cerrar la app con marcas guardadas | `persist()` concedido; el contador sigue al reabrir | 2 de 2 |
| 3 | iPhone reciente y un Android | iOS 17+ y Android, app instalada | Cerrar por completo, modo avión, abrir desde el ícono | Abre en Marcar desde el cascarón | 3 de 3 aperturas por teléfono |
| 4 | iPhone reciente y un Android viejo | Ambos, app instalada | Leer el QR de una pantalla de centro real con moiré y poca luz | Lectura correcta | ≥ 4 de 5, en menos de 3 s cada una |
| 5 | iPhone y Android | Ambos, app instalada | Recibir el aviso push con la app cerrada y tocarlo | Muestra el texto fijo y abre Marcar | 2 de 2 por teléfono |
| 6 | Un iPhone con llave de Google Password Manager en Chrome | iOS 17+ | Alta con práctica de firma sin conexión | Tras 3 intentos fallidos ofrece el plan B; marcar con la llave del dispositivo funciona | 1 de 1 |
| 7 | Un Android | Android, app instalada | Apagón simulado: 4 marcas sin conexión, entrega horas después | Las 4 «Recibida»; cuentan a la hora declarada | 4 de 4 |

## Recursos adicionales

*   `2026-11-20-worker-pwa-offline-brief.md` (misma carpeta): las decisiones del dueño (1 a 28), los resultados de las pruebas en iPhone y Android, y la investigación sobre llaves de acceso sin conexión.
*   `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md` y `docs/superpowers/specs/2026-11-14-worker-access-design.md`: el brief y el diseño de identidad anteriores.
*   `docs/normas/jornada/criterios.md`: criterios 1.3, 2.3, 2.6, 2.9, 3.2 a 3.4, 4.2 a 4.4, 5.1 a 5.4, 6.1, 7.1 y 8.1.
*   `app/Modules/WorkerAccess/README.md` y `CLAUDE.md` del repo.
*   Fuentes técnicas citadas en el brief: código de Chromium para iOS y escritorio, WebAuthn nivel 3, Yubico («Using WebAuthn for Signing»), SimpleWebAuthn (retos propios) y la guía de proveedores de credenciales de Android.

## Mejoras futuras

*   **Marcar desde computadora y el portal de la persona en escritorio:** trabajo aparte, después de integrar este.
*   **Solicitud de justificación de falta** en Asistencia (la persona la pide, Recursos Humanos la aprueba desde una bandeja), si Recursos Humanos la pide. Debe resolver los casos que `Leave` rechaza al registrar el permiso: días festivos o fuera de la semana laboral (`no_working_days`) y solicitudes que se traslapan (`overlaps`).
*   **Declarar la falta dentro de la confirmación de la semana** (Alternativa AL).
*   **Panel de contexto** en la pestaña de verificación: conteo de marcas en línea del centro en ese tramo y última vez que se vio la pantalla del centro.
*   **Relevo de la marca por la pantalla del centro** (`screen_relay`).
*   **Mostrar las llaves del dispositivo en «Accesos»** y que Recursos Humanos las revoque una por una.
*   **Aviso por correo a Recursos Humanos** con las marcas por verificar de más de siete días.
*   **Poda o archivo de `attendance_offline_marks`** según el criterio 7.1, cuando el volumen lo pida.

## Contrato de red

Único para todo el diseño; el plan de implementación lo sigue al pie de la letra.

**Entrega.** `POST /me/{slug}/punch/offline`, ruta `attendance.device.punch.offline`, sin sesión ni CSRF, tenant por `ResolveDeviceTenant`. El cliente manda `Accept: application/json`, `Content-Type: application/json` y `credentials: 'omit'`; la ruta responde siempre JSON. Cuerpo:

```text
{ items: [ {
  seq, type, ticket, center_id,
  qr?,                          // «{windowIndex}.{hmac}», ≤ 128
  declared_at,                  // segundos Unix
  lat_e5?, lng_e5?, accuracy_m?,   // enteros: lo que se firma; el servidor deriva lat = lat_e5 / 1e5
  device_clock_at?,             // segundos Unix, sin firmar, solo pista
  justification?,               // nota ≤ 500, sin firmar
  agreement_version,
  signer: 'passkey' | 'device_key',
  credential?,                  // {id, response:{clientDataJSON, authenticatorData, signature, userHandle?}} si passkey
  device_key_id?, signature?, pass?   // si device_key
} ] }                           // ≤ 20 elementos, ≤ 64 KB; `seq` creciente y local del teléfono
```

| Estado HTTP | Cuándo | Qué hace el teléfono |
|---|---|---|
| 200 | `{ results: [ {seq, status, reason?, stored_status?} ], tickets: [ {ticket, issuedAt} ], context }`; `tickets` y `context` faltan si ninguna firma verificó | Aplica cada resultado |
| 413 | Cuerpo de más de 64 KB | Parte el lote y reintenta elemento por elemento |
| 422 | `{message, errors}`: error de sobre (forma, más de 20, `mixed_people`) | Parte el lote; un elemento rechazado a solas queda «No aceptada» con `malformed` |
| 403 | `Sec-Fetch-Site: cross-site` | Reintenta el lote más tarde |
| 404 | Empresa inexistente o sin el módulo `attendance` | Reintenta el lote más tarde |
| 429, 5xx, error de red | — | Reintenta todo el lote con espera creciente |

`status` ∈ `received | duplicate | rejected | conflict`. Un `duplicate` repite en `stored_status` (`received` | `rejected`) y en `reason` lo guardado; una marca que ya estaba registrada en línea responde `duplicate` con `stored_status: received` y `reason: already_recorded` (sin fila; el teléfono la borra y la pantalla avisa «Ya estaba registrada»). `conflict` trae `reason: ticket_reused`. `reason` guardado ∈ `ticket_expired | person_inactive | agreement_not_accepted | sequence_conflict | wrong_center | pass_expired`; solo de respuesta: `ticket_invalid | signature_invalid | ticket_reused`. `malformed` lo pone el teléfono. El teléfono borra el elemento con `received` o `duplicate` + `stored_status: received`; cualquier otro resultado lo deja «No aceptada».

**Boletos.** `POST /me/{slug}/tickets`, ruta `attendance.device.tickets`, sin sesión ni CSRF, `ResolveDeviceTenant` y `RejectCrossSiteRequests`, limitador `attendance-tickets`; mismas cabeceras que la entrega. Cuerpo: `{ ticket, queue?: {count, oldest_age_minutes} }` (un boleto válido de la persona dentro del horizonte). Respuesta 200: `{ tickets: [ {ticket, issuedAt} ], context: {tenantId, employeeId, centerId, agreementVersion, rpId, offline} }`; boleto inválido o vencido: 422. `agreementVersion` es la versión vigente si la persona la aceptó, si no `null`. `AttachOfflineTickets` agrega el mismo `{tickets, context}` a la raíz de toda respuesta verificada por firma (marcar, leer o confirmar la semana, corregir, aceptar el acuerdo), también en sus 409 y 422 posteriores a la firma, y a la de la entrega.

**Reto canónico v1.** `$expected` = los 32 bytes crudos de SHA-256 de la cadena UTF-8:

```text
humetria-offline-mark-v1|{tenant_id}|{employee_id}|{work_center_id}|{mark_type}|{basis}|{qr_o_vacío}|{declared_at_unix}|{lat_e5}|{lng_e5}|{accuracy_m}|{agreement_version}|{seq}|{pass_id_o_vacío}|{boleto}
```

`lat_e5`, `lng_e5` y `accuracy_m` son los enteros que manda el teléfono; sin posición, los tres campos van vacíos. `basis` es `qr_window` si el elemento trae `qr` y `declared_interval` si no, por la sola presencia del campo; la base que el servidor guarda se decide aparte y no cambia el reto. La llave de acceso firma esos 32 bytes como reto WebAuthn; la llave del dispositivo firma esos 32 bytes con ECDSA P-256 (WebCrypto y `openssl_verify` aplican su propio SHA-256 al mensaje). Los vectores (`tests/Fixtures/attendance/offline-challenge-v1.json`) prueban la cadena y su SHA-256.

**Boleto.** `v1.{payload}.{hmac}` (235 caracteres; validación `max:256`); HMAC con `APP_KEY` y `APP_PREVIOUS_KEYS`.

**Propiedades de las pantallas.**

| Pantalla | Controlador (dueño) | Propiedades nuevas |
|---|---|---|
| Marcar | `MarkController` (Attendance) | `slug`, `deliverUrl` (`attendance.device.punch.offline`), `ticketsUrl` (`attendance.device.tickets`). Sin interruptor ni estado del acuerdo: salen de `context`. |
| Mi semana | `WeekController` (Attendance) | `slug`, `deliverUrl`, además de lo que ya tiene la semana. |
| Inicio | `HomeController` (WorkerAccess) | `slug`, `deviceKey: {optionsUrl, storeUrl, proof}`, con `proof = ProofSteps::for($session, $slug)`. |

**Cliente.** `useOfflineQueue → { pending, kept, notices, deliver }`, filtrados por la persona actual; cada elemento de `kept` («No aceptada») trae su propio `dismiss()`, que usan «Entendido» y «Pedir corrección». La prueba de posesión de la llave del dispositivo usa `signBytes` sobre los 32 bytes del reto. Componente `QrScanner`. VAPID: `GET portal.push.show` → `{ publicKey }`.

**Cascarón.** Vista Blade con `<div id="offline-app" data-company data-home-url data-punch-url data-deliver-url data-tickets-url>`, en el grupo sin sesión, con la cabecera `Permissions-Policy: camera=(self), geolocation=(self)` de todo `/me/{slug}/*`.

**Service worker.** Alcance `/me/{slug}` (sin barra final) con `Service-Worker-Allowed: /me/{slug}`; atiende la ruta exacta del alcance o las que empiezan con alcance + `/`; URL del cascarón `{scope}/offline`; `start_url` e `id` del manifest `/me/{slug}/punch`; caché `humetria-pwa-{slug}-{revision}`, y la limpieza borra solo las viejas de ese slug. Versión: la meta `app-revision`, pasada como `?v=` al registrar. Una sola compilación aparte (Vite con `PWA_WORKER=sw`, script `build:sw`, salida fija `public/build/pwa/sw.js`, worker clásico sin `import`), confirmada con un experimento corto en el commit 4 porque Rolldown puede partirlo. `/me/{slug}/sw.js` (Laravel, sin sesión) sirve ese archivo con `Cache-Control: no-cache`, la CSP y `noindex`; con `PWA_SW_KILL` el controlador responde en su lugar un worker de apagado generado como texto con el slug, y la página, que lee la meta `pwa-sw-kill`, no lo vuelve a registrar. El apagado nunca toca la ruta de entrega. `tsconfig` anidado `resources/js/sw/tsconfig.json` con `exclude: []` y `lib: ["ESNext", "WebWorker"]`; `types:check` = `tsc --noEmit && tsc --noEmit -p resources/js/sw`.

**Eventos públicos.** Attendance: `OfflineMarkAccepted` (se despacha dentro de la transacción; `ReopenOnOfflineMark` reabre con `MarkLock` ya tomado), `OfflineMarkReviewed` (correcta o incorrecta) y `CorrectionResolved(requestId, employeeId, approved)` (en `ResolveCorrection`, al aprobar y al rechazar). WorkerAccess: `WorkerAccessRevoked(employeeId, reason, credentialId?)`. No hay otros; `WorkerAccess/Events` hoy no existe.

**Migraciones.** Fechas posteriores a `2027_01_29_100000` (la última de main al 2026-11-24, `make_company_role_labels_unique`; el prefijo `2027_01_30_*` se reasigna si otro PR de main llega antes), en este orden: ensanche y CHECK de `attendance_marks.method` (`varchar(20)`) → `attendance_offline_marks` (con `review_flags` jsonb, `device_clock_at`, `away_center_id`, índice único simple `(tenant_id, ticket_nonce)` y nombre de índice explícito para `(tenant_id, employee_id, received_at)`, porque el nombre por omisión pasa de 63 caracteres) → `worker_device_keys` → `attendance_mark_reviews` (con `seq`) → `push_subscriptions` (con `employee_id`). Los CHECK se escriben con paréntesis (`CHECK (…)`). Un choque del índice único del boleto dentro de una transacción de Postgres exige un savepoint (`DB::transaction` anidado) o capturarlo tras el `rollback`; se prueba.

## Plan de implementación detallado

Lista única de lo que hay que crear, modificar o borrar, repartida en siete segmentos con dueño distinto. **Cada archivo tiene una sola entrada**, en su segmento dueño; los cambios que un segmento necesita en un archivo ajeno están en su subsección «Necesita de archivos compartidos» (o «de otro dueño»), sin entrada propia. Los archivos compartidos de plataforma son del segmento A6. Los números de entrada **no son consecutivos** (hay huecos de entradas eliminadas): se conservaron para que las referencias entre entradas de un mismo segmento sigan valiendo. Entre segmentos se cita por archivo o clase, no por número. No hay código: solo firmas, esquemas, seudocódigo corto y casos de prueba.

Verificado contra el repo el 2026-11-20 (árbol de `claude/pwa-offline-qr-reader-0077c1`, último commit `9444a821`, sin `vendor/` instalado en el worktree); lo que no se pudo verificar dice «NO VERIFICADO» con la comprobación que falta, y esas comprobaciones se hacen al implementar. Las pruebas locales se hacen por filtro; la verificación completa es el CI, más la regla del dueño de correr la suite completa local antes de empujar (2026-11-18).

| Segmento | Módulo | Contenido |
|---|---|---|
| A1 | Attendance | núcleo: esquema sin conexión, boletos, reto, `RecordOfflineMark`, entrega, mudanza de rutas, QR, acuerdo |
| A2 | Attendance | verificación de RH (pestañas en Correcciones), semana (`WeekCompleteness`, `uncovered_days`), reporte, Humi |
| A3 | WorkerAccess | llave del dispositivo, pase sin conexión, `verifyChallenge`, `WorkerAccessRevoked` |
| A4 | Notifications (nuevo) | push sin cuerpo, suscripciones, listeners |
| A5a | Frontend | service worker, cola IndexedDB, entrega, lector de QR, bundle `offline` |
| A5b | Frontend | pantallas de la persona (Marcar, Mi semana, Inicio, alta), guía de instalación |
| A6 | Plataforma | app instalable (manifest, cascarón, `sw.js`), seguridad HTTP, archivos compartidos, norma, operación, `CLAUDE.md` |

### Archivos por crear, modificar o borrar

#### Segmento A1 · Attendance: núcleo de marcas sin conexión

Verificado contra el repo (rama `claude/pwa-offline-qr-reader-0077c1`, 2026-11-20); lo que no se pudo verificar dice NO VERIFICADO. Rutas relativas a la raíz del repo. Las entradas conservan su número; los huecos (#28, #32, #47, #53, #61) son entradas que pasaron a su dueño (ver «Necesita de archivos compartidos»). Cada entrada cae en el commit natural de su archivo; lo que cae en otro lleva «(commit N)».

Tres hechos del código que fijan el plan:

1. `attendance_marks.method` es `varchar(15)` (`2026_12_09_100000_create_attendance_marks_table.php:26`); `offline_declared` mide 16. La migración ensancha la columna además de cambiar el CHECK.
2. `attendance.device.punch.home` (`a/{slug}`) pasaría a `me/{slug}`, que ya es `worker.home`: ese nombre se borra y sus usos pasan a `attendance.device.punch.show` (`me/{slug}/punch`). Los demás nombres se conservan.
3. Los fixtures viven en `tests/Fixtures/` (F mayúscula, como `tests/Fixtures/attendance/mark-outcomes.json`); Linux en CI distingue mayúsculas.

Qué guarda el servidor: cada marca entregada con boleto y firma válidos deja una fila en `attendance_offline_marks`, aceptada o rechazada. Un boleto inválido, una firma inválida, un boleto reutilizado con otra firma y un elemento mal formado se responden sin fila; el teléfono los conserva «No aceptada».

---

##### Base de datos

###### 1. `database/migrations/2027_01_30_100000_add_offline_methods_to_attendance_marks.php` (Create) · commit 7
*   **Type / Scope**: Migración (Postgres, `DB::unprepared`), mismo precedente que `2027_01_24_300000_add_field_methods_to_attendance_marks.php`. Primera de la serie (fechas posteriores a `2027_01_29_100000_make_company_role_labels_unique.php`, la última de main al 2026-11-24).
*   **Proposed Changes**: `up()`: `ALTER TABLE attendance_marks ALTER COLUMN method TYPE varchar(20), DROP CONSTRAINT attendance_marks_method_known, ADD CONSTRAINT attendance_marks_method_known CHECK (method IN ('qr_and_geofence','geofence','qr','remote','trusted_browser','correction','field','field_unlocated','offline_qr','offline_declared'))`. Ensanchar un `varchar` no reescribe filas y no choca con el trigger de solo inserción (actúa sobre filas, no sobre DDL). `down()`: CHECK de 8 métodos y `varchar(15)`. Docblock: solo crece la lista de métodos; la tabla sigue de solo inserción.

###### 2. `database/migrations/2027_01_30_100100_create_attendance_offline_marks_table.php` (Create) · commit 7
*   **Type / Scope**: Migración. Segunda de la serie (después vienen `worker_device_keys` de A3, `attendance_mark_reviews` de A2 y `push_subscriptions` de A4).
*   **Proposed Changes**: tabla `attendance_offline_marks`:
    *   Columnas:
        *   `id` uuid PK (v4); `tenant_id` FK tenants.
        *   `employee_id` uuid (FK compuesta `(employee_id, tenant_id)` → employees): la persona del boleto, verificada por HMAC y por la firma.
        *   `outcome` varchar(8); `reason` varchar(24) nulo.
        *   `mark_id` uuid nulo **único** (FK compuesta `(mark_id, tenant_id)` → `attendance_marks(id, tenant_id)` sobre `attendance_marks_id_tenant_unique`).
        *   `work_center_id` uuid (FK compuesta → work_centers): el centro que firmó el teléfono, que debe ser el de la persona.
        *   `away_center_id` uuid nulo (FK compuesta → work_centers): el otro centro activo de la empresa para el que verificó el QR, donde estuvo físicamente.
        *   `mark_type` varchar(11); `time_basis` varchar(17) nulo.
        *   `declared_at` timestampTz(0); `counted_at` timestampTz(6) nulo; `window_start` timestampTz(0) nulo.
        *   `ticket_nonce` varchar(32); `ticket_issued_at` timestampTz(0); `received_at` timestampTz(6).
        *   `review_flags` jsonb `not null default '[]'`.
        *   `device_clock_at` timestampTz(0) nulo (sin firmar, solo pista para RH, nunca evidencia).
        *   `signer_type` varchar(10); `signer_id` uuid (la llave de acceso o la llave del dispositivo que verificó).
        *   `seq` integer; `justification` varchar(500) nulo; `agreement_version` integer.
        *   `challenge_hash` char(64); `assertion_hash` char(64); `created_at` timestampTz useCurrent.
    *   Índices:
        *   `unique (tenant_id, ticket_nonce)`: un boleto, una fila.
        *   `attendance_offline_marks_employee_received_index` sobre `(tenant_id, employee_id, received_at)`, con nombre explícito (el nombre por omisión pasa de 63 caracteres).
        *   `(tenant_id, outcome, created_at)`, para la pestaña de A2.
        *   `unique (id, tenant_id)`.
    *   CHECKs, cada uno entre paréntesis:
        *   `CHECK (outcome IN ('accepted','rejected'))`.
        *   `CHECK ((outcome = 'accepted') = (mark_id IS NOT NULL AND reason IS NULL AND counted_at IS NOT NULL AND time_basis IS NOT NULL))`.
        *   `CHECK ((outcome = 'rejected') = (reason IS NOT NULL))`.
        *   `CHECK (reason IN ('ticket_expired','person_inactive','agreement_not_accepted','sequence_conflict','wrong_center','pass_expired'))`.
        *   `CHECK (signer_type IN ('passkey','device_key'))`.
        *   `CHECK (mark_type IN ('start','break_start','break_end','end'))`.
        *   `CHECK (time_basis IN ('qr_window','declared_interval'))`.
        *   `CHECK (time_basis IS DISTINCT FROM 'qr_window' OR window_start IS NOT NULL)`.
        *   `CHECK (counted_at IS NULL OR (counted_at >= ticket_issued_at AND counted_at <= received_at))`.
        *   `CHECK (jsonb_typeof(review_flags) = 'array' AND review_flags <@ '["wide_interval","old_window","no_position","outside_zone","declared_adjusted"]'::jsonb)`.
        *   `CHECK (seq >= 0)`; `CHECK (char_length(justification) <= 500)`.
    *   Trigger de solo inserción `attendance_offline_marks_append_only()` (UPDATE, DELETE, TRUNCATE) **con la excepción de la empresa demo**: mismo cuerpo `DEMO_MAY_DELETE` que `2027_01_19_100000_let_the_demo_company_be_wiped.php`. Sin ella, `DemoWorkspaceRunner::tablesInDeleteOrder()` (`database/seeders/Demo/DemoWorkspaceRunner.php:105`) fallaría al reiniciar la demo.
    *   Retención: se conserva como las marcas (criterio 7.1); sin poda en esta versión. `down()`: drop de la tabla y de la función.

###### 3. `database/factories/Attendance/AttendanceOfflineMarkFactory.php` (Create) · commit 7
*   **Type / Scope**: Factory.
*   **Proposed Changes**: `definition()`: una entrega rechazada coherente (`reason='sequence_conflict'`, boleto emitido 30 min antes del recibo, `declared_at` entre ambos, `signer_type='passkey'` con `signer_id`, nonce aleatorio y hashes de 64 hex). Empresa, centro y persona salen como en `AttendanceMarkFactory`. Estados: `accepted(?AttendanceMark $mark = null)` (crea una marca `offline_declared` si no se le pasa, con `mark_id`, `counted_at = declared_at`, `time_basis='declared_interval'`), `qrWindow()` (`window_start`, método `offline_qr`), `rejected(OfflineRejection $reason)`, `flagged(ReviewFlag ...$flags)` y `deviceKey()`.

---

##### Dominio (Attendance)

###### 4. `app/Modules/Attendance/Enums/MarkMethod.php` (Modify) · commit 7
*   **Type / Scope**: Enum.
*   **Proposed Changes**: `OfflineQr = 'offline_qr'` y `OfflineDeclared = 'offline_declared'`. En `evidence()`, `OfflineQr` va al brazo de `Medium` y `OfflineDeclared` al de `Basic`. Docblock: criterios 3.3.1 y 5.3; el QR sin conexión es una cota inferior («no antes de»), no una hora exacta.

###### 5. `app/Modules/Attendance/Enums/EvidenceLevel.php` (sin cambios, verificado)
*   **Type / Scope**: Enum.
*   **Proposed Changes**: ninguno. La evidencia sale entera de `MarkMethod::evidence()`, es decir, de la base de tiempo: con QR reciente, media; con hora declarada, básica. El firmante no cambia el nivel.

###### 6. `app/Modules/Attendance/Enums/TimeBasis.php` (Create) · commit 7
*   **Type / Scope**: Enum (etiqueta, no rama de cálculo).
*   **Proposed Changes**: `QrWindow = 'qr_window'` (un QR válido y reciente acotó la hora por abajo), `DeclaredInterval = 'declared_interval'`; `method(): MarkMethod` (→ `OfflineQr` / `OfflineDeclared`). La hora sale siempre de la regla de `OfflineTime` (#13).

###### 7. `app/Modules/Attendance/Enums/OfflineRejection.php` (Create) · commit 7
*   **Type / Scope**: Enum (motivos **guardados**; lo lee la pestaña de A2).
*   **Proposed Changes**: `TicketExpired`, `PersonInactive`, `AgreementNotAccepted`, `SequenceConflict`, `WrongCenter`, `PassExpired`. Los motivos que solo van en la respuesta (`ticket_invalid`, `signature_invalid`, `ticket_reused`) son constantes de `OfflineOutcome` (#15), no casos del enum. `malformed` lo asigna el cliente.

###### 8. `app/Modules/Attendance/Enums/SignerType.php` (Create) · commit 7
*   **Type / Scope**: Enum. `Passkey = 'passkey'`, `DeviceKey = 'device_key'`.

###### 9. `app/Modules/Attendance/Support/QrToken.php` (Modify) · commit 9
*   **Type / Scope**: Soporte.
*   **Proposed Changes**: el token pasa de la HMAC hexadecimal sola (`QrToken.php:71`) a `{windowIndex}.{hmac}` (unos 75 caracteres; `MarkRequest` ya acepta `q` hasta 128). El HMAC no cambia: `attendance-qr|{tenant}|{center}|{window}` con `app.key`.
    *   `issue()` devuelve `"{$window}.".self::sign(...)`.
    *   `private static function parse(string $token): ?array{int, string}`: índice entero no negativo, un punto, 64 hex; si no, null.
    *   `verify()` y `expired()` parsean y comparan un solo HMAC. `verify` acepta un índice ∈ [ventana actual − `past_windows`, ventana actual]: en línea se comporta igual que hoy. `expired` acepta índices de las últimas 24 h que ya no verifican. Ya no recorren 2 881 ventanas.
    *   Nuevo `windowStart(string $token, string $tenantId, string $workCenterId, CarbonImmutable $notAfter): ?CarbonImmutable`: el inicio de la ventana del token si su HMAC verifica para ese centro y ese inicio no pasa de `$notAfter`; si no, null. No hace búsqueda ni pone tope de edad: la edad la decide `OfflineTime` (#13).
    *   Un QR con HMAC inválido se trata como si no hubiera QR.
    *   Docblock reescrito: el formato es libre de cambiar, porque no hay clientes ni QR impresos.

###### 10. `app/Modules/Attendance/Support/AgreementStatus.php` (Modify)
*   **Type / Scope**: Soporte.
*   **Proposed Changes**:
    *   (commit 9) `acceptedVersion(string $employeeId): ?int`: la versión vigente publicada (`CurrentAgreement::get()`) si la persona la aceptó; si no, null. Es la fuente de `context.agreementVersion`. Una consulta, como `accepted()` (`AgreementStatus.php:14`), que no cambia.
    *   (commit 11) `acceptedAt(string $employeeId, CarbonImmutable $at): bool`. La versión vigente en `$at` es la de mayor `version` con `created_at <= $at`; si no había ninguna, cualquier versión cuenta. True si hay una `AgreementAcceptance` de la persona para una versión ≥ esa. Una sola consulta (subconsulta sobre `attendance_agreements`).

###### 11. `app/Modules/Attendance/Support/OfflineTicket.php` (Create) · commit 9
*   **Type / Scope**: Soporte (final readonly; recibe `CurrentTenant` y `AgreementStatus`). Clase propia y no un firmante compartido: el repo firma con `hash_hmac` en línea con prefijo de dominio, y `TicketSigner` de Complaints está atado a su canal.
*   **Proposed Changes**: formato `v1.{b64url(json{t,e,i,n})}.{b64url(hmac)}` (235 caracteres):
    *   `t` = tenant, `e` = employee, `n` = 16 bytes aleatorios en hex.
    *   `i` = unix de emisión **redondeado hacia arriba** (`now()->ceilSecond()`): el boleto nunca se fecha antes de la marca en línea que lo emitió, así esa marca es la «anterior» de la regla de tiempo.
    *   HMAC-SHA256 sobre `"attendance-ticket|v1|{payload}"`.
    *   `batch(string $employeeId): list<array{ticket: string, issuedAt: int}>` (`attendance.offline.ticket_batch`, 8).
    *   `verify(string $ticket): ?array{employee_id: string, issued_at: CarbonImmutable, nonce: string}`: prueba cada llave de `keys()` con `hash_equals`. Devuelve null si el formato no sirve, si ninguna llave verifica o si `t` no es la empresa actual. La edad no se mira aquí.
    *   `context(RosterEntry $person): array{tenantId: string, employeeId: string, centerId: string, agreementVersion: int|null, rpId: string, offline: bool}`:
        *   `agreementVersion` = `AgreementStatus::acceptedVersion`;
        *   `rpId` = `config('fortify.passkeys.relying_party_id')`, la misma fuente que `WebAuthnCeremony::rpId()` (`WebAuthnCeremony.php:285-288`);
        *   `offline` = `config('attendance.offline.enabled')`, el único interruptor del cliente.
    *   `keys()` privado: `[config('app.key'), ...config('app.previous_keys')]`, el mismo patrón que `PageLink::keys()` (`app/Modules/Disclosure/Models/PageLink.php:61-63`). Firma con la primera. Una rotación de `APP_KEY` con `APP_PREVIOUS_KEYS` conserva los boletos.

###### 12. `app/Modules/Attendance/Support/OfflineChallenge.php` (Create) · commit 8
*   **Type / Scope**: Soporte puro (final).
*   **Proposed Changes**: `canonical(string $tenantId, string $employeeId, OfflineSubmission $s, ?string $passId): string` arma la cadena UTF-8 `humetria-offline-mark-v1|{tenant_id}|{employee_id}|{work_center_id}|{mark_type}|{basis}|{qr_o_vacío}|{declared_at_unix}|{lat_e5}|{lng_e5}|{accuracy_m}|{agreement_version}|{seq}|{pass_id_o_vacío}|{boleto}`.
    *   `basis` es `qr_window` si el elemento trae `qr`, si no `declared_interval`: lo que el teléfono sabía al firmar. La base guardada puede bajar a `declared_interval` si el QR es viejo (#13).
    *   `lat_e5`, `lng_e5` y `accuracy_m` son los enteros que mandó el teléfono, tal cual; sin posición, los tres van vacíos. No hay redondeo en el servidor.
    *   Lanza `InvalidArgumentException` si algún campo trae `|`.
    *   `hash(string $canonical): string`: los 32 bytes crudos del SHA-256 de la cadena. Es el `$expected` de `verifyChallenge` y de `DeviceKeySignatures::verify`. La llave del dispositivo firma con ECDSA esos 32 bytes como mensaje (WebCrypto y `openssl_verify` aplican su propio SHA-256 encima).

###### 13. `app/Modules/Attendance/Support/OfflineTime.php` (Create) · commit 11
*   **Type / Scope**: Soporte puro (final): la regla de tiempo. Recibe los vecinos que la acción leyó dentro de la transacción.
*   **Proposed Changes**: `static decide(CarbonImmutable $declaredAt, CarbonImmutable $ticketIssuedAt, CarbonImmutable $receivedAt, ?CarbonImmutable $windowStart, ?CarbonImmutable $previous, ?CarbonImmutable $next, OfflinePosition $position): ?OfflineDecision`.
    *   `static reference(CarbonImmutable $declaredAt, CarbonImmutable $ticketIssuedAt, CarbonImmutable $receivedAt): CarbonImmutable` = `min(max(declaredAt, ticketIssuedAt), receivedAt)`. Es el punto alrededor del cual la acción busca la marca efectiva anterior (`marked_at ≤ ref`) y la siguiente (`marked_at > ref`).
    *   `lower = max(ticketIssuedAt, windowStart (si hay QR válido), previous + 1 µs)`; `upper = min(receivedAt, next − 1 µs)`. `lower > upper` ⇒ null (la acción guarda `sequence_conflict`).
    *   Ancho = `receivedAt − ticketIssuedAt`:
        *   ≤ `attendance.offline.count_declared_max_hours` (72 h): `countedAt = clamp(declaredAt, lower, upper)`.
        *   Mayor: `countedAt = lower`, la cota probada. RH la verifica y, si la hora declarada es la buena, la corrige con el flujo existente (`CorrectMark`).
    *   `basis` = `QrWindow` solo si hay ventana y `declaredAt − windowStart ≤ attendance.offline.qr_old_window_minutes` (30); si no, `DeclaredInterval`. `windowStart` sube `lower` en ambos casos.
    *   Banderas (`list<ReviewFlag>`):
        *   `wide_interval`: ancho > `attendance.offline.wide_interval_minutes` (240);
        *   `old_window`: hay ventana y `declaredAt − windowStart` > 30 min;
        *   `no_position` y `outside_zone`: según `OfflinePosition` (#64);
        *   `declared_adjusted`: `countedAt ≠ declaredAt`.
    *   Una mentira sin bandera gana como mucho 240 min; con `wide_interval`, hasta 72 h contadas hasta que RH verifique; más allá cuenta solo la cota probada. Toda marca sin conexión queda «Sin conexión · por verificar» hasta la revisión de RH.
    *   Ejemplos de aceptación:
        *   Entrada en línea 08:00:00, boleto emitido 08:00:00, salida declarada 17:00, entregada 19:00 ⇒ `lower = 08:00:00.000001`, `upper = 19:00`, cuenta 17:00 con `wide_interval`.
        *   Mismo boleto y entrada, salida declarada 07:30 ⇒ `ref = 08:00:00`; la anterior es la entrada; cuenta 08:00:00.000001 con `declared_adjusted`.
        *   Boleto de 80 h ⇒ cuenta `lower` con `wide_interval` y `declared_adjusted`.

###### 14. `app/Modules/Attendance/Data/OfflineSubmission.php` (Create) · commit 11
*   **Type / Scope**: DTO readonly.
*   **Proposed Changes**: los campos del elemento del contrato:
    *   `seq: int`, `type: MarkType`, `ticket: string`, `workCenterId: string`, `qr: ?string`, `declaredAt: CarbonImmutable`;
    *   `latE5: ?int`, `lngE5: ?int`, `accuracyM: ?int`, con `position(): ?Position` que deriva `lat = latE5 / 1e5`;
    *   `deviceClockAt: ?CarbonImmutable`, `justification: ?string`, `agreementVersion: int`, `signerType: SignerType`;
    *   si firmó la llave de acceso: `credential: ?array<string, mixed>`;
    *   si firmó la llave del dispositivo: `deviceKeyId: ?string`, `signature: ?string`, `pass: ?string`.

###### 15. `app/Modules/Attendance/Data/OfflineOutcome.php` (Create) · commit 11
*   **Type / Scope**: DTO readonly.
*   **Proposed Changes**:
    *   Campos: `seq: int`, `status: string` (`received`|`duplicate`|`rejected`|`conflict`), `reason: ?string` y `storedStatus: ?string` (`received`|`rejected`, solo en `duplicate`).
    *   `reason` es un motivo guardado (`OfflineRejection`) o uno de solo respuesta: `TICKET_INVALID = 'ticket_invalid'`, `SIGNATURE_INVALID = 'signature_invalid'`, `TICKET_REUSED = 'ticket_reused'` (va siempre con `conflict`).
    *   `toArray()` → `{seq, status, reason?, stored_status?}`, exactamente como el contrato. Nunca lleva hora, posición ni nada de la marca.

###### 16. `app/Modules/Attendance/Actions/RecordOfflineMark.php` (Create) · commit 11
*   **Type / Scope**: Acción (final readonly).
*   **Proposed Changes**: `handle(OfflineSubmission $s, CarbonImmutable $receivedAt): OfflineOutcome`. Depende de `CurrentTenant`, `Roster`, `WorkCenters`, `Timezones`, `AgreementStatus`, `OfflineTicket`, `WorkerSignatures` y `DeviceKeySignatures` (contratos de WorkerAccess) y `RecordAudit`. Pasos:
    1. La forma ya la validó `OfflinePunchRequest`.
    2. `OfflineTicket::verify` → null ⇒ `rejected/ticket_invalid`, sin fila.
    3. Idempotencia: se busca la fila por `(tenant, nonce)`.
        *   Con el mismo `challenge_hash` y `assertion_hash` ⇒ `duplicate` con `stored_status` (`received` si `accepted`, si no `rejected`) y el `reason` guardado.
        *   Con otros ⇒ `conflict` con `reason: ticket_reused`, sin fila.
    4. Firma (el reto se arma con `OfflineChallenge`):
        *   Llave de acceso: `WorkerSignatures::verifyChallenge(hash, credential, ticketIssuedAt): ?SignedBy`.
        *   Llave del dispositivo: `$passId = DeviceKeySignatures::passId($pass)` (null ⇒ `signature_invalid`); el reto se arma con ese `pass_id`; luego `DeviceKeySignatures::verify(hash, signature, pass, ticketIssuedAt): ?DeviceSigned`.
        *   Ambos contratos aceptan una credencial o llave revocada después de `ticketIssuedAt` y devuelven su `revokedAt`.
        *   ⇒ `signature_invalid`, sin fila y sin gastar el boleto: si la verificación da null, si el firmante es otra persona que la del boleto, o si `revokedAt` no es nulo y `declaredAt > revokedAt` (una marca genuinamente anterior a la revocación sigue valiendo).
    5. Con la firma verificada, en este orden (el primero que falla gana):
        *   Llave del dispositivo: `DeviceSigned->passId !== $passId`, `declaredAt > passExpiresAt` o `passIssuedAt > receivedAt` ⇒ `pass_expired`. Es el único tiempo que existe en este paso.
        *   `receivedAt − ticketIssuedAt` > `attendance.offline.ticket_horizon_days` (35) ⇒ `ticket_expired`.
        *   Persona inexistente o inactiva ⇒ `person_inactive`.
        *   `workCenterId` no es el centro de la persona ⇒ `wrong_center`.
        *   `AgreementStatus::acceptedAt($employee, $ticketIssuedAt)` falso ⇒ `agreement_not_accepted`.
    6. QR:
        *   Primero `QrToken::windowStart($qr, tenant, centro de la persona, $receivedAt)`.
        *   Si no verifica, se prueba en los demás centros activos de la empresa, como `MarkController::centerOf` (`MarkController.php:287-295`): un HMAC por centro.
        *   Si verifica en otro centro, `away_center_id` = ese centro y la zona horaria de la marca es la suya. Si no verifica en ninguno, la marca sigue sin QR.
    7. Posición: con posición, `Geofence::evaluate($zones, $lat, $lng, $accuracyM, CompanyPolicy::current()->max_accuracy_m)` (firma verificada en `Geofence.php:24`) sobre las zonas del centro donde se marcó. El resultado se pasa a `OfflinePosition` (#64). Sin posición, `OfflinePosition::Unknown`. Nunca bloquea ni usa `MarkPolicyCheck`.
    8. `DB::transaction` + `MarkLock::of($employee)` (candado consultivo por persona, `MarkLock.php:17-20`):
        *   Se repite la búsqueda del paso 3: dos entregas del mismo boleto se serializan por el candado.
        *   Con `ref = OfflineTime::reference(...)`, se leen en `EffectiveMarks::of()` la marca efectiva anterior (`marked_at ≤ ref`) y la siguiente (`marked_at > ref`), y se llama a `OfflineTime::decide`.
        *   **Ya registrada:** si choca contra una marca efectiva **en línea** (método distinto de `offline_*`) de la misma persona y del mismo tipo, con `marked_at > ticketIssuedAt` y a ≤ `attendance.offline.already_recorded_window_minutes` (10) de `declaredAt`, responde `duplicate` con `stored_status: received` y `reason: 'already_recorded'`, sin fila, con el log `attendance.offline_mark_already_recorded`. Es el caso de una marca en línea cuya respuesta se perdió y que también quedó en la cola.
        *   Cualquier otro choque es una fila `sequence_conflict`: null de `decide`, un tipo que no esté en `MarkType::allowedAfter(prev)` o un `next` que no se permita después de `type`.
        *   Si no hay choque, se inserta `AttendanceMark` con todas sus columnas:
            *   `tenant_id`, `employee_id`, `work_center_id` (el de la persona), `type`;
            *   `marked_at` = `counted_at`; `method` = `TimeBasis::method()`;
            *   `zone_id`, `latitude`, `longitude`, `accuracy_m` (de `lat_e5`/`lng_e5`/`accuracy_m`) y `distance_m`;
            *   `credential_id` = la llave de acceso, o null si firmó la llave del dispositivo (el firmante queda en `signer_type`/`signer_id` de la fila de entrega);
            *   `timezone` del centro donde se marcó y `away_center_id`.
        *   Junto con la marca va su `AttendanceOfflineMark` `accepted`, con `review_flags`.
        *   El insert de la fila de entrega va en un `DB::transaction` anidado (savepoint de Postgres). Una `UniqueConstraintViolationException` del índice del nonce revierte solo el savepoint: se relee la fila y se responde como en el paso 3. Sin savepoint, Postgres aborta la transacción entera (`25P02`).
        *   `event(new OfflineMarkAccepted(...))` **dentro** de la transacción, con `MarkLock` tomado, como `MarkCorrected` en `CorrectMark.php:57`.
    9. Después del commit:
        *   Bitácora: `RecordAudit->handle('attendance.offline_mark_accepted', $mark, ['employee_id', 'time_basis', 'signer_type', 'interval_minutes', 'review_flags'])`.
        *   Logs `attendance.offline_mark_received` o `attendance.offline_mark_rejected` con `{offline_mark_id, employee_id, reason, basis, interval_minutes, queue_age_minutes, signer_type, review_flags}`. `queue_age_minutes` = `receivedAt − declaredAt`.
        *   Las respuestas sin fila (`ticket_invalid`, `signature_invalid`, `ticket_reused`) dejan `attendance.offline_mark_rejected` sin `offline_mark_id` ni `employee_id`.
        *   Estos logs son la fuente de las medidas: entregadas por día, rechazo por motivo, ancho p50/p95 y edad en cola.
        *   Nunca se registran el boleto, el QR, la posición, la justificación, el pase ni la credencial.

###### 17. `app/Modules/Attendance/Events/OfflineMarkAccepted.php` (Create) · commit 11
*   **Type / Scope**: Evento público.
*   **Proposed Changes**: `final readonly`, `__construct(public string $markId, public string $employeeId, public string $offlineMarkId)`. Solo ids. Se despacha dentro de la transacción (#16 paso 8); `ReopenOnOfflineMark` (A2) reabre con `MarkLock` ya tomado.

###### 18. `app/Modules/Attendance/Models/AttendanceOfflineMark.php` (Create) · commit 7
*   **Type / Scope**: Modelo (final, `BelongsToCurrentTenant`, `HasVersion4Uuids`, `#[UseFactory(AttendanceOfflineMarkFactory::class)]`, `#[Table('attendance_offline_marks')]`, `$timestamps = false`, `#[Fillable([...])]`).
*   **Proposed Changes**: casts para `OfflineRejection`, `TimeBasis`, `SignerType` y `MarkType`; `review_flags` como `AsEnumCollection::of(ReviewFlag::class)` (NO VERIFICADO sobre jsonb con la versión instalada; si no sirve, `array` con un accesor tipado); fechas como `immutable_datetime`. Relación `mark(): BelongsTo<AttendanceMark>`. Docblock `@property` completo (Larastan max). Sin métodos de escritura.

###### 19. `app/Modules/Attendance/Providers/AttendanceServiceProvider.php` (Modify)
*   **Type / Scope**: Proveedor.
*   **Proposed Changes**: en `boot()`, junto a los limitadores existentes (`AttendanceServiceProvider.php:44-78`):
    *   (commit 9) `RateLimiter::for('attendance-tickets', fn (Request $r): Limit => Limit::perMinute(300)->by('attendance-tickets:'.$r->ip()))`: un turno detrás de una NAT; no es el cubo del quiosco.
    *   (commit 11) `RateLimiter::for('attendance-offline', fn (Request $r): Limit => Limit::perMinute(600)->by('attendance-offline:'.$r->ip()))`: cuenta peticiones, no elementos. No hay límite por persona: el boleto de un solo uso y la firma nueva ya frenan el abuso.
    *   (commit 12) La línea de A2 `Event::listen(OfflineMarkAccepted::class, ReopenOnOfflineMark::class)`, junto a la de `MarkCorrected` (línea 40), en el commit que crea el listener.

###### 20. `config/attendance.php` (Modify)
*   **Type / Scope**: Configuración. Un solo espacio de nombres `attendance.offline.*` y una sola llave de interruptor.
*   **Proposed Changes**: bloque `'offline' => [...]`:
    *   (commit 9) `'enabled' => (bool) env('ATTENDANCE_OFFLINE_ENABLED', true)`. Apaga la cola del cliente y la interfaz (el cliente lo lee por `context.offline`); nunca apaga la entrega.
    *   (commit 9) `'ticket_batch' => 8` y `'ticket_horizon_days' => 35` (el horizonte de la corrección).
    *   (commit 11) `'count_declared_max_hours' => 72`, `'wide_interval_minutes' => 240`, `'qr_old_window_minutes' => 30`, `'already_recorded_window_minutes' => 10` y `'delivery_batch_max' => 20`.
    *   Todas son constantes sin `env`, salvo el interruptor: un valor que no se ajusta por entorno no lleva variable. Las llaves de boletos son `app.key` y `app.previous_keys` (#11).

---

##### HTTP y rutas

###### 21. `app/Modules/Attendance/Http/Requests/OfflinePunchRequest.php` (Create) · commit 11
*   **Type / Scope**: FormRequest.
*   **Proposed Changes**: `authorize(): true`, porque la credencial es la firma (como `MarkRequest`). `prepareForValidation()` corta con 413 un cuerpo de más de 64 KB. Un 422 aquí es solo de sobre (`{message, errors}`).
*   Reglas por campo:
    *   `items` required array `min:1|max:` `attendance.offline.delivery_batch_max` (20).
    *   `items.*.seq` integer `min:0` y `distinct`. El orden creciente no se valida: el servidor ordena por `seq`.
    *   `items.*.type` `Rule::enum(MarkType)`.
    *   `items.*.ticket` string `max:256` (un boleto real mide 235).
    *   `items.*.center_id` uuid.
    *   `items.*.qr` nullable string `max:128`.
    *   `items.*.declared_at` required integer.
    *   `items.*.lat_e5`: nullable integer `between:-9000000,9000000`, `required_with` de los otros dos.
    *   `items.*.lng_e5`: nullable integer `between:-18000000,18000000`, `required_with` de los otros dos.
    *   `items.*.accuracy_m`: nullable integer `min:0`, `required_with` de los otros dos.
    *   `items.*.device_clock_at` nullable integer.
    *   `items.*.justification` nullable string `max:500`.
    *   `items.*.agreement_version` integer `min:1`.
    *   `items.*.signer` `in:passkey,device_key`.
    *   `items.*.credential` required_if `signer=passkey`, array con `id` y `response.{clientDataJSON, authenticatorData, signature}` (`userHandle` opcional).
    *   `items.*.device_key_id` uuid, `items.*.signature` string `max:200` y `items.*.pass` string `max:512`, los tres required_if `signer=device_key`.
    *   `not_regex:/\|/` en `ticket`, `qr` y `pass`.
*   Validación de lote, en `after()`: si los boletos válidos nombran a más de una persona, error en `items` con el mensaje `mixed_people`. Un teléfono compartido manda un lote por persona.
*   `submissions(): list<OfflineSubmission>`, ordenadas por `seq`.
*   Atributos en `lang/es/validation.php` (ver «Necesita»).

###### 22. `app/Modules/Attendance/Http/Controllers/OfflineController.php` (Create)
*   **Type / Scope**: Controlador delgado.
*   **Proposed Changes**:
    *   `store(OfflinePunchRequest, RecordOfflineMark)` · commit 11:
        *   `$receivedAt = now()`; llama a `handle` por elemento, en orden de `seq`.
        *   Responde 200 `{results}` con `Cache-Control: no-store, private`.
        *   Si alguna firma verificó: suma uno al limitador de la persona y fija el atributo de `AttachOfflineTickets` (#56), que agrega `{tickets, context}`.
    *   `tickets(OfflineTicketsRequest, OfflineTicket, Roster)` · commit 9:
        *   Boleto que no verifica, de más de `ticket_horizon_days`, o de una persona inexistente o inactiva ⇒ 404. Es el mismo 404 por cualquier causa.
        *   Si llega `queue`, escribe el log `attendance.offline_queue_reported` con `{count, oldest_age_minutes}` (sin persona) para ver colas que no entregan.
        *   Fija el atributo de #56 con la persona del boleto y responde 200 `{}` con `no-store, private`; el middleware agrega `{tickets, context}`.
        *   Un boleto es inerte sin firma: cambiarlo por uno más reciente solo estrecha la cota inferior de la próxima marca.

###### 23. `routes/attendance-device.php` (Modify)
*   **Type / Scope**: Rutas.
*   **Proposed Changes**:
    *   **Mudanza:** el prefijo `a/{tenant:slug}` pasa a `me/{tenant:slug}` en los grupos `attendance-punch`, `attendance-week` y `attendance-correction`, conservando cada nombre, controlador, `where` y limitador. Se **borra** `Route::get('a/{tenant:slug}', ...)->name('attendance.device.punch.home')` (línea 30), porque `me/{slug}` es `worker.home`.
    *   **`AttachOfflineTickets` · commit 9:** esos tres grupos suman `AttachOfflineTickets::class` (#56).
    *   **Grupo sin sesión** al estilo de `routes/complaints-public.php:31-58`, para la entrega (commit 11) y los boletos (commit 9):
        ```
        Route::withoutMiddleware([EncryptCookies, AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession,
                PreventRequestForgery, EndRevokedImpersonation, AssignRequestContext, ForbidWhileImpersonating,
                HandleAppearance, HandleInertiaRequests])
            ->middleware([ResolveDeviceTenant::class, RejectCrossSiteRequests::class, AttachOfflineTickets::class])
            ->group(function (): void {
                Route::post('me/{tenant:slug}/tickets', [OfflineController::class, 'tickets'])
                    ->middleware('throttle:attendance-tickets')->name('attendance.device.tickets');
                Route::post('me/{tenant:slug}/punch/offline', [OfflineController::class, 'store'])
                    ->middleware('throttle:attendance-offline')->name('attendance.device.punch.offline');
            });
        ```
    *   **Por qué sin sesión ni CSRF:**
        *   Tras un apagón la sesión y su cookie pueden no existir o haber vencido.
        *   Los navegadores sin Fetch Metadata (iOS < 16.4) no envían `Sec-Fetch-Site`, así que `RejectCrossSiteRequests` solo frena a los que sí lo envían.
        *   La defensa es la firma por marca, el boleto de un solo uso y los limitadores.
        *   Con `SESSION_DRIVER=database`, cada petición sin cookie abriría una fila en `sessions`.
    *   **Middleware del grupo:**
        *   `RejectCrossSiteRequests` (`abort_if(Sec-Fetch-Site === 'cross-site', 403)`) se importa de `App\Http\Middleware`; el movimiento es de A6.
        *   `ResolveDeviceTenant` da 404 a una empresa desconocida o sin el módulo `attendance` (`ResolveDeviceTenant.php:32-37`).
    *   **Logs:** quitar `AssignRequestContext` no pierde nada en estas rutas. Solo agrega `user_id` e `impersonator_id` (`AssignRequestContext.php:23-35`), que no existen sin sesión. `tenant_id` llega por `TenantContext` al entrar a la empresa. `AssignRequestId` sigue (no está en la lista), así que cada línea lleva `request_id`.
    *   **`bootstrap/app.php`:** no hay exención por ruta.
    *   La pantalla del centro (`kiosk.*`, `qr`) se queda en `a/`. Se actualizan los comentarios.

###### 24. `app/Modules/Attendance/Http/Middleware/ResolveDeviceTenant.php` (Modify)
*   **Type / Scope**: Middleware, solo el docblock (`a/` para la pantalla del centro, `me/` para Marcar, Mi semana, corrección, boletos y entrega). Sin cambio de comportamiento.

###### 25. `app/Modules/Attendance/Http/Controllers/MarkController.php` (Modify)
*   **Type / Scope**: Controlador.
*   **Proposed Changes**:
    *   (commit 5) Se borra la línea 119 (118 antes de #159), que fijaba un `Permissions-Policy` propio con `camera=()`, y se ajusta el docblock de `show()` (líneas 70-73 en main al 2026-11-24). La cabecera de toda página bajo `/me/{slug}/*` sale de la regla de `SecurityHeaders` (A6): `camera=(self), geolocation=(self)`. Si la línea se quedara, taparía esa regla, porque `SecurityHeaders` conserva la cabecera que ya trae la respuesta.
    *   (commit 9) Props nuevas de `show()`: `slug` y `ticketsUrl` (`attendance.device.tickets`).
    *   (commit 11) Prop nueva `deliverUrl` (`attendance.device.punch.offline`).
    *   Sin `offlineEnabled` ni `agreementAccepted`: el interruptor y la versión aceptada viajan en `context`.
    *   (commit 9) `store()`: una línea tras `abort_if($person === null || ! $person->active, 404)` (línea 147): `$request->attributes->set(AttachOfflineTickets::SIGNED, $person->id)`. Así la 200 y la 422 llevan `{tickets, context}`.
    *   Las llamadas a `QrToken::verify`/`expired` (líneas 154, 168, 289) no cambian de firma; el formato cambia dentro de `QrToken` (#9).

###### 26. `app/Modules/Attendance/Http/Controllers/AgreementController.php` (Modify) · commit 9
*   **Type / Scope**: Controlador.
*   **Proposed Changes**: `accept()`: la misma línea del atributo tras verificar la firma y la persona; la 200 `{accepted: true}` y la 409 `agreement_outdated` (línea 53) llevan `{tickets, context}`.

###### 27. `app/Modules/Attendance/Http/Controllers/CorrectionRequestController.php` (Modify) · commit 9
*   **Type / Scope**: Controlador.
*   **Proposed Changes**: `store()`: la misma línea del atributo tras verificar la firma (línea 39) y la persona; la 200 (`PersonWeek::of(...)`, línea 62) y la 422 (línea 56) llevan `{tickets, context}`.

###### 29. `app/Modules/Attendance/Http/Controllers/KioskController.php` (Modify)
*   **Type / Scope**: Controlador.
*   **Proposed Changes**: línea 120 `$url = route('attendance.device.punch.show', $tenant).'?'.http_build_query(['q' => $token]);`. Sin esto, todos los QR quedan muertos. El token sale de `QrToken::issue` (línea 118): desde el commit 9, en forma `{windowIndex}.{hmac}`. La cookie de la línea 88 (`/a/{tenant}`) no cambia.

###### 30. `app/Modules/Attendance/Support/PunchWorkerSection.php` (Modify)
*   **Type / Scope**: Soporte.
*   **Proposed Changes**: línea 28 `route('attendance.device.punch.show', $company->slug)`; docblock `/me/{slug}/punch`. El enlace sigue siendo el de hoy (navegación Inertia): la cabecera de cámara y ubicación de `/me/{slug}/*` ya la tiene Inicio.

###### 33. `tests/Fixtures/attendance/offline-challenge-v1.json` (Create) · commit 8
*   **Type / Scope**: Vectores Pest/Vitest (A5a los lee desde `tests/js`).
*   **Proposed Changes**: `{valid: [{name, input{tenant_id, employee_id, work_center_id, mark_type, basis, qr, declared_at, lat_e5, lng_e5, accuracy_m, agreement_version, seq, pass_id, ticket}, canonical, sha256_hex}], invalid: [...]}`. Solo prueban la cadena canónica y su SHA-256: no hay redondeo que probar. Casos:
    *   con QR (`{windowIndex}.{hmac}`) y posición;
    *   sin QR;
    *   sin posición (tres vacíos);
    *   coordenadas negativas;
    *   llave del dispositivo con `pass_id` (va antes del boleto);
    *   boleto real de 235 caracteres;
    *   `invalid`: un `|` en el QR.

###### 34. `tests/Unit/Attendance/OfflineChallengeTest.php` (Create) · commit 8
*   `mutates(OfflineChallenge::class)`. Cada vector produce su `canonical` y su `sha256_hex`, y `hash()` devuelve 32 bytes. Cada caso de `invalid` lanza. Sin posición, los tres campos van vacíos. Cambiar un solo campo cambia el hash. `pass_id` va antes del boleto.

###### 35. `tests/Unit/Attendance/OfflineTimeTest.php` (Create) · commit 11
*   `mutates(OfflineTime::class)`. Casos:
    *   **Ejemplos de aceptación de #13:** 17:00 con `wide_interval`; 07:30 con `ref = 08:00`, que cuenta 08:00:00.000001 con `declared_adjusted`.
    *   **`reference`:** declarada antes del boleto → el boleto; después del recibo → el recibo; entre ambos → la declarada.
    *   **Cotas:**
        *   declarada antes de `lower` → `lower`; después de `upper` → `upper`;
        *   una ventana de QR sube `lower`;
        *   marca anterior + 1 µs; `next` − 1 µs;
        *   `lower > upper` → null; `lower == upper` → esa hora.
    *   **Límites:**
        *   ancho de 240 min sin bandera, 241 con `wide_interval`;
        *   72 h exactas → la declarada acotada; 72 h y 1 s → `lower`.
    *   **Edad del QR:** 30 min → `qr_window`; 30 min y 1 s → `declared_interval` con `old_window` y `lower` todavía en `window_start`.
    *   **Banderas:** `no_position` y `outside_zone`; ninguna dentro de la zona; `declared_adjusted` solo si la hora contada difiere de la declarada.
    *   **Tipos:** `end` y `break_start` en un intervalo ancho de ≤ 72 h cuentan su hora declarada acotada.

###### 36. `tests/Unit/Attendance/QrTokenTest.php` (Modify) · commit 9
*   La primera prueba (líneas 23-29) espera `"{$window}.".hash_hmac(...)`. Las demás conservan su comportamiento.
*   Casos nuevos:
    *   el token alterado en el índice, en el HMAC o sin punto → `verify` false;
    *   índice no numérico o negativo → false;
    *   `expired` sigue dando true dentro de 24 h y false después;
    *   `windowStart` da el inicio de la ventana firmada;
    *   `windowStart` → null con otro centro, otra empresa, HMAC inválido o ventana posterior a `$notAfter`;
    *   un token de hace 30 días todavía da su ventana (la edad la juzga `OfflineTime`).

###### 37. `tests/Feature/Attendance/OfflineTicketTest.php` (Create) · commit 9
*   `mutates(OfflineTicket::class)`. Casos:
    *   Emite y verifica; un boleto mide 235 caracteres. El lote trae 8 nonces distintos con su `issuedAt`.
    *   `issuedAt` se redondea hacia arriba: a las 08:00:00.400 da 08:00:01, y a las 08:00:00 exactas da 08:00:00.
    *   Boleto de otra empresa → null. Firma alterada, formato roto o `v2.` → null.
    *   Rotación:
        *   un boleto emitido con la llave A verifica con `APP_KEY=B` y `APP_PREVIOUS_KEYS=A`;
        *   deja de verificar con `APP_KEY=B` sola;
        *   un boleto nuevo se firma con la llave actual.
    *   `context` trae `tenantId`, la persona, el centro, `agreementVersion` (null si no aceptó la versión vigente), `rpId` y `offline` (refleja el interruptor).

###### 38. `tests/Feature/Attendance/AgreementStatusTest.php` (Create)
*   (commit 9) `acceptedVersion`: aceptó la vigente → su número; aceptó solo una anterior → null; sin aceptación → null; aceptación de otra persona → null.
*   (commit 11) `acceptedAt`:
    *   aceptó la v1 y la v2 se publicó después de `$at` → true;
    *   la v2 ya estaba vigente en `$at` y solo aceptó la v1 → false;
    *   aceptó la v2 → true;
    *   sin ninguna aceptación → false;
    *   aceptación de otra persona → false.

###### 39. `tests/Feature/Attendance/RecordOfflineMarkTest.php` (Create) · commit 11
*   **Type / Scope**: prueba Feature de la acción (`mutates(RecordOfflineMark::class)`), con `SoftAuthenticator::get(..., challenge: b64url(hash))` y la llave P-256 de la factory de A3.
*   Casos:
    *   **Felices:**
        *   QR reciente → `offline_qr`, evidencia media, hora declarada acotada por la ventana.
        *   Sin QR → `offline_declared`, básica.
        *   Entrada en línea 08:00 + salida sin conexión 17:00 entregada 19:00 → 17:00 con `wide_interval`.
        *   Salida declarada 07:30 con la entrada en línea 08:00 y el boleto de 08:00 → 08:00:00.000001.
        *   Un día entero sin conexión (entrada, pausa, regreso, salida en un lote) → cuatro marcas en orden con sus horas declaradas.
        *   Llave del dispositivo con QR → media; con hora declarada → básica.
        *   QR de otro centro activo → `away_center_id` y su zona horaria.
        *   Sin posición → `no_position`, zona nula.
        *   **Columnas:** la `AttendanceMark` insertada trae `latitude = lat_e5 / 1e5`, `longitude`, `accuracy_m`, `zone_id`, `distance_m`, `method`, `credential_id` (null con la llave del dispositivo), `timezone` y `away_center_id`.
    *   **Bordes:**
        *   QR con HMAC inválido → sin QR (`declared_interval`), sin rechazo.
        *   QR 31 min más viejo que la hora declarada → `offline_declared` con `old_window`.
        *   Boleto de 35 días exactos contra 35 días y 1 s (`ticket_expired`).
        *   Boleto de 63 h (fin de semana) → hora declarada acotada, `wide_interval`.
        *   Boleto de 80 h → `lower`, `wide_interval` y `declared_adjusted`.
        *   Seq 0.
        *   La misma marca dos veces → `duplicate` con `stored_status: received`, sin segunda fila.
        *   Un rechazo guardado y repetido → `duplicate` con `stored_status: rejected` y su `reason`.
        *   Mismo nonce con otra aserción → `conflict` con `ticket_reused`, sin fila.
    *   **Fallos y motivos:**
        *   `ticket_invalid`, sin fila; también con un boleto firmado por una llave que ya no está en `app.key` ni en `app.previous_keys`.
        *   `signature_invalid`, sin fila: el boleto no se gasta y la marca auténtica con ese boleto entra después.
        *   `ticket_expired`, `person_inactive`, `agreement_not_accepted` y `wrong_center` (centro enviado distinto del de la persona), cada uno con su fila.
        *   `pass_expired`: `passId` distinto del leído; `declared_at` después del vencimiento del pase; pase emitido después del recibo.
        *   `sequence_conflict`: entrada con el día abierto; salida sin entrada; `lower > upper`.
        *   Encadenado: si k choca, k+1, que dependía de ella, también choca.
    *   **Revocación:**
        *   Credencial o llave revocada después del boleto y marca declarada antes de la revocación → aceptada.
        *   Marca declarada después de la revocación → `signature_invalid`.
        *   Revocada antes del boleto → `signature_invalid`.
    *   **Ya registrada:**
        *   Una salida en línea a las 17:02 y su copia en cola declarada a las 17:00 → `duplicate` con `stored_status: received`, sin fila, con el log `attendance.offline_mark_already_recorded`.
        *   La misma copia a 11 min → `sequence_conflict`.
        *   Una salida en línea de antes del boleto → `sequence_conflict`.
    *   **Política:** «bloquear fuera de zona» no bloquea; fuera de zona → `outside_zone`.
    *   **Transacción y registro:**
        *   `OfflineMarkAccepted` se despacha dentro de la transacción (un listener comprueba `DB::transactionLevel() > 0`).
        *   Hay entrada de bitácora.
        *   Los logs no traen el boleto, el QR, la posición, la justificación, el pase ni la credencial.
    *   **Concurrencia:** una fila con el mismo nonce insertada antes del savepoint → la entrega responde como en el paso 3 y la transacción externa sigue utilizable.

###### 40. `tests/Feature/Attendance/OfflinePunchTest.php` (Create) · commit 11
*   **Type / Scope**: prueba Feature HTTP de `POST me/{slug}/punch/offline`, con `Accept: application/json` y `Content-Type: application/json`.
*   Casos:
    *   **Sin sesión:** 200 sin cookie de sesión ni token CSRF. La respuesta no pone `Set-Cookie` y no se escribe fila en `sessions`. Trae `results` por `seq` y `{tickets: [{ticket, issuedAt}], context}`.
    *   **Boleto real:** un boleto emitido por `OfflineTicket` pasa la validación.
    *   **Errores de sobre:**
        *   lote de 20 → 200; de 21 → 422 `{message, errors}`;
        *   `items` vacío → 422; `seq` repetido → 422;
        *   cuerpo de más de 64 KB → 413;
        *   `|` en el boleto → 422;
        *   dos personas en el lote → 422 con `mixed_people` en `errors.items`.
    *   **Por elemento:** una firma inválida no tumba el lote; cada elemento trae su resultado.
    *   **Orden:** un lote en desorden se procesa por `seq`.
    *   **Origen:** `Sec-Fetch-Site: cross-site` → 403.
    *   **Empresas:** un boleto de A enviado a `me/b/...` → `ticket_invalid`, sin fila en ninguna. Empresa inexistente o sin asistencia → 404 JSON.
    *   **Limitador:** la petición 601 por IP → 429.
    *   **JSON sin `Accept`:** un 422 o un 404 sale igual en JSON, sin redirección ni sesión.
    *   `Cache-Control: no-store, private`.
    *   **Interruptor:** con `attendance.offline.enabled=false` la entrega sigue respondiendo 200.

###### 41. `tests/Feature/Attendance/OfflineTicketsRouteTest.php` (Create) · commit 9
*   `POST me/{slug}/tickets`, sin sesión ni CSRF. Casos:
    *   **Feliz:** con un boleto válido de la persona → 200 `{tickets: [8], context{tenantId, employeeId, centerId, agreementVersion, rpId, offline}}`. No pone `Set-Cookie` ni escribe fila en `sessions`.
    *   **Antigüedad:** un boleto de 34 días sirve; uno de 35 días y 1 s → 404.
    *   **404 por cualquier causa:** boleto alterado, de otra empresa o de una persona dada de baja; empresa sin el módulo `attendance`.
    *   **Cola:** `queue{count, oldest_age_minutes}` escribe `attendance.offline_queue_reported` sin `employee_id`.
    *   **Origen:** `Sec-Fetch-Site: cross-site` → 403.
    *   **Limitador:** la petición 301 por IP → 429.

###### 42. `tests/Feature/Attendance/OfflineMarksTableTest.php` (Create) · commit 7
*   Base de datos:
    *   El trigger rechaza UPDATE, DELETE y TRUNCATE.
    *   Cada CHECK:
        *   aceptada sin `mark_id` falla; rechazada sin motivo falla;
        *   motivo fuera del conjunto falla, incluido `signature_invalid`;
        *   `qr_window` sin ventana falla;
        *   `counted_at` antes del boleto o después del recibo falla;
        *   `review_flags` con una bandera desconocida o que no sea arreglo falla;
        *   justificación de 501 caracteres falla.
    *   `signer_id` nulo falla (NOT NULL).
    *   `mark_id` único.
    *   Nonce único por empresa; el mismo nonce en dos empresas pasa.

###### 43. `tests/Feature/Attendance/TenantIsolationTest.php` (Modify) · commit 7
*   `it('does not read the offline deliveries of another company with a query')`, con el patrón de la línea 114. Para A2, en este mismo archivo: `it('does not read the reviews of another company with a query')` (`MarkReview`).

###### 44. `tests/Feature/Demo/DemoDeleteExceptionTest.php` (Modify) · commit 7
*   Fila en `insertOnlyTables()` (línea 68): `'attendance_offline_marks' => ['attendance_offline_marks', fn (): string => AttendanceOfflineMarkFactory::new()->createOne()->id, 'attendance_offline_marks is append-only', 'id = id', true]`, junto a la de `attendance_marks` (línea 83). Para A2, aquí también: `'attendance_mark_reviews' => ['attendance_mark_reviews', fn (): string => MarkReviewFactory::new()->createOne()->id, 'attendance_mark_reviews is insert-only', 'id = id', true]`.

###### 45. `tests/Feature/Attendance/LogPrivacyTest.php` (Modify) · commit 11
*   Caso nuevo: una entrega sin conexión (aceptada, rechazada, `ticket_invalid` y `signature_invalid`) y una petición de boletos con `queue` no escriben en ningún log el boleto, el QR, la posición, la justificación, `pass`, `signature` ni `credential`. Sí escriben `queue_age_minutes` e `interval_minutes`.

###### 46. `tests/Feature/Attendance/PunchTest.php` (Modify)
*   Las 18 líneas con `punch.home` (83, 138, 145, 158, 175, 183, 187, 191, 200, 205, 210, 220, 227, 238, 251, 274, 293, 387) pasan a `punch.show`. La línea 171 `/a/acme/punch?q[]=x` pasa a `/me/acme/punch?q[]=x`.
*   Caso nuevo: `it('no longer answers at the old addresses')`. `GET /a/acme`, `/a/acme/punch`, `POST /a/acme/punch`, `/a/acme/punch/options` y `/a/acme/agreement` → 404.
*   (commit 5) La prueba de las líneas 174-179 espera en Marcar `camera=(self)` y `geolocation=(self)` (la regla de `SecurityHeaders`), y en `login` sigue esperando `camera=()`.
*   (commit 9) `it('gives the page its slug and the tickets url')`.
*   (commit 9) `it('brings tickets and context with a mark and with a refusal')`: la 200 y la 422 traen `{tickets, context}`; una firma inválida → 404 sin `tickets`.
*   (commit 9) `it('accepts the QR code in its new format')`: el código de `QrToken::issue` abre Marcar y marca con QR.
*   (commit 11) `it('gives the page the delivery url')`.

###### 48. `tests/Feature/Attendance/KioskTest.php` (Modify)
*   Línea 170: `'url' => route('attendance.device.punch.show', $this->tenant->slug).'?q='.$code`. La línea 94 (cookie `/a/...`) no cambia.
*   Caso nuevo: el QR apunta a `me/{slug}/punch` y esa dirección responde la página de Marcar.
*   (commit 9) Caso nuevo: el token tiene la forma `{windowIndex}.{hmac}`.

###### 49. `tests/Feature/Attendance/CorrectionRequestTest.php` (Modify)
*   Líneas 304 y 307: `punch.home` → `punch.show`. Caso nuevo: 404 en `POST /a/acme/correction`.
*   (commit 9) La 200 y la 422 traen `{tickets, context}`.

###### 50. `tests/Feature/Attendance/AgreementTest.php` (Modify)
*   (commit 6) El texto del acuerdo vigente:
    *   ya no contiene «La hora la pone el servidor, no su teléfono.»;
    *   contiene, exactas, las dos frases nuevas de #57;
    *   crea una versión nueva, y una persona que aceptó la anterior vuelve a `agreement_not_accepted`.
*   (commit 9) Aceptar (200) y la 409 traen `{tickets, context}`.
*   Sin líneas con `punch.home` (verificado con grep).

###### 51. `tests/Feature/Attendance/PunchWorkerSectionTest.php` (Modify)
*   Línea 42: `'href' => route('attendance.device.punch.show', 'acme')`; docblock de la línea 15 (`/me/{slug}/punch`).

###### 52. `tests/Browser/AttendancePunchTest.php` (Modify)
*   Literales `/a/acme/punch?q=` → `/me/acme/punch?q=` (líneas 44, 178, 190 y 292); `/a/acme` → `/me/acme/punch` (líneas 49, 248 y 266).
*   El humo `visitPage('attendance/punch', '/me/acme/punch', ...)` sigue sin errores de JS ni de CSP.
*   Lo que A5b agregue a este archivo (flujos sin conexión de Marcar) se anota aquí.

###### 54. `tests/Unit/Attendance/QrImageTest.php` (Modify, cosmético) · commit 9
*   Línea 14: `QR_IMAGE_URL` a `.../me/acme/punch?q=<índice>.<hmac>`. No cambia el comportamiento; es para que nadie copie la forma vieja.

###### 55. `app/Modules/Attendance/Enums/ReviewFlag.php` (Create) · commit 7
*   **Type / Scope**: Enum (conjunto cerrado de banderas de revisión).
*   **Proposed Changes**: `WideInterval = 'wide_interval'`, `OldWindow = 'old_window'`, `NoPosition = 'no_position'`, `OutsideZone = 'outside_zone'`, `DeclaredAdjusted = 'declared_adjusted'`. Las marcas con banderas salen primero en la pestaña de RH. Toda marca sin conexión, con o sin banderas, sigue «Sin conexión · por verificar» hasta que RH la revisa.

###### 56. `app/Modules/Attendance/Http/Middleware/AttachOfflineTickets.php` (Create) · commit 9
*   **Type / Scope**: Middleware (final readonly; recibe `OfflineTicket` y `Roster`).
*   **Proposed Changes**:
    *   `public const string SIGNED = 'attendance.signed_employee'`.
    *   Tras `$next`, si la petición trae ese atributo (string), la persona existe y está activa, y la respuesta es `JsonResponse`, agrega a la raíz del cuerpo `tickets` (`OfflineTicket::batch($employeeId)`) y `context` (`OfflineTicket::context($person)`).
    *   Llega a toda respuesta posterior a una firma verificada, con cualquier estado (200, 409, 422). Una 404 por firma inválida no fija el atributo y no lleva nada.
    *   Cada controlador firmado solo fija el atributo: #25-#27, `WeekController::verified()` (A2) y `OfflineController` (#22).

###### 57. `app/Modules/Attendance/Support/CurrentAgreement.php` (Modify) · commit 6
*   **Type / Scope**: Soporte (el texto del acuerdo, criterio 5.1). El texto final vive solo aquí.
*   **Proposed Changes**: en el primer párrafo de `text()` (línea 43), la frase «La hora la pone el servidor, no su teléfono.» se reemplaza por «En una marca hecha con conexión, la hora la pone el servidor. En una marca hecha sin conexión, la hora que usted indica se acota con lo que el servidor sí puede comprobar y la revisa Recursos Humanos.». Ningún otro párrafo cambia. El cambio de texto produce una versión nueva (`get()`, líneas 26-37) y cada persona vuelve a aceptar. Lo cubre #50.

###### 58. `tests/Fixtures/attendance/offline-delivery-v1.json` (Create) · commit 11
*   **Type / Scope**: Vectores de la entrega (Pest y Vitest).
*   **Proposed Changes**: `{request: {items: [...]}, response: {results: [...], tickets: [...], context: {...}}, envelope: [...]}`.
    *   `results` trae un ejemplo de cada caso:
        *   `received`;
        *   `duplicate` con `stored_status: received`, incluida una marca ya registrada en línea;
        *   `duplicate` con `stored_status: rejected` y su motivo;
        *   `rejected` con un motivo guardado;
        *   `rejected` con `ticket_invalid` y con `signature_invalid`;
        *   `conflict` con `ticket_reused`.
    *   Los elementos cubren los dos firmantes y las coordenadas en `lat_e5`/`lng_e5`/`accuracy_m`.
    *   `envelope` trae las respuestas 413, 422 `{message, errors}`, 403, 404 y 429.
    *   Pest comprueba que la petición pasa `OfflinePunchRequest` y que `OfflineOutcome::toArray()` produce exactamente esas formas. Vitest (A5a) comprueba, sobre los mismos resultados, la regla de borrado y la de partir el lote.

###### 59. `tests/Feature/Attendance/MarksAppendOnlyTest.php` (Modify) · commit 7
*   El dataset de «grades the evidence of each method» (líneas 59-69) suma `[MarkMethod::OfflineQr, EvidenceLevel::Medium]` y `[MarkMethod::OfflineDeclared, EvidenceLevel::Basic]`.
*   «keeps the marks…» suma `OfflineQr` y `OfflineDeclared`: el CHECK nuevo los acepta y `offline_declared` cabe en la columna (regresión del `varchar(15)`).
*   Caso nuevo: el CHECK rechaza `offline_x`.
*   Lo que A2 agregue a este archivo se anota aquí.

###### 60. `database/factories/Attendance/AttendanceMarkFactory.php` (Modify) · commit 7
*   **Type / Scope**: Factory.
*   **Proposed Changes**: estados `offlineQr()` y `offlineDeclared()` (método y `credential_id` nulo), los que usan #3 y las pruebas de A2.

###### 62. `app/Modules/Attendance/Http/Requests/OfflineTicketsRequest.php` (Create) · commit 9
*   **Type / Scope**: FormRequest.
*   **Proposed Changes**: `authorize(): true` (la credencial es el boleto, que no sirve sin firma). Reglas: `ticket` required string `max:256` `not_regex:/\|/`; `queue` nullable array; `queue.count` integer `min:0`; `queue.oldest_age_minutes` integer `min:0`.

###### 63. `app/Modules/Attendance/Data/OfflineDecision.php` (Create) · commit 11
*   **Type / Scope**: DTO readonly (final).
*   **Proposed Changes**: `__construct(public CarbonImmutable $countedAt, public TimeBasis $basis, /** @var list<ReviewFlag> */ public array $flags)`. El tipo `list<ReviewFlag>` va en el `@param` del constructor (Larastan max), sin `@var` en línea.

###### 64. `app/Modules/Attendance/Enums/OfflinePosition.php` (Create) · commit 11
*   **Type / Scope**: Enum.
*   **Proposed Changes**: `Unknown`, `Inside`, `Outside`, con `static fromReading(?ZoneReading $reading): self`. Sin lectura (no hubo posición) da `Unknown`; si no, `$reading->inside ? Inside : Outside`. `ZoneReading` (`app/Modules/Attendance/Data/ZoneReading.php`: `zoneId`, `distanceM`, `inside`, `accuracyOk`) es lo que devuelve `Geofence::evaluate` (`Geofence.php:24`). `Unknown` enciende `no_position` y `Outside` enciende `outside_zone`.

---

##### Necesita de archivos compartidos

*   **A6** `app/Modules/Complaints/Http/Middleware/RejectCrossSiteRequests.php` → `app/Http/Middleware/RejectCrossSiteRequests.php`, con el import de `routes/complaints-public.php` y `ArchitectureTest`, a más tardar en el commit 9. #23 lo importa del kernel compartido.
*   **A6** `bootstrap/app.php`:
    *   `shouldRenderJsonWhen` (línea 127) suma `|| $request->is('me/*/punch/offline', 'me/*/tickets')`. Así 413, 422, 404 y 429 de esas rutas salen en JSON aunque falte `Accept`, sin redirección ni sesión.
    *   No hay exención de CSRF por ruta.
*   **A6** `app/Http/Middleware/SecurityHeaders.php` (commit 5): `camera=(self), geolocation=(self)` en toda respuesta GET de documento bajo `/me/{slug}/*`. Con eso se borra la línea 119 de `MarkController` (main al 2026-11-24).
*   **A6** `tests/Feature/RouteSecurityTest.php`:
    *   (commit 2) Se quita `attendance.device.punch.home` (línea 61).
    *   (commit 9) Se agrega `attendance.device.tickets`: pública. Un boleto vigente de la persona, inerte sin firma, da un lote nuevo y el contexto. Sin sesión ni cookies, con `RejectCrossSiteRequests` y throttle por IP.
    *   (commit 11) Se agrega `attendance.device.punch.offline`: pública. Boleto de un solo uso y firma por marca, limitador por IP, sin sesión ni cookies, con `RejectCrossSiteRequests`.
*   **A6** `.env.example` y `tests/Unit/EnvExampleTest.php` (commit 9): solo `ATTENDANCE_OFFLINE_ENABLED=true`. Las demás llaves de `attendance.offline.*` son constantes.
*   **A6** `app/Logging/RedactSensitiveData.php` y su prueba: llaves `ticket`, `tickets`, `qr`, `challenge`, `justification`, `pass`, `signature`, `credential`, `lat_e5`, `lng_e5`.
*   **A6** `lang/es/validation.php` → `attributes`:
    *   `items` → «marcas»; `items.*.ticket` → «boleto»;
    *   `items.*.declared_at` → «hora declarada»; `items.*.justification` → «nota»;
    *   `items.*.center_id` → «centro de trabajo»; `items.*.qr` → «código QR»;
    *   `items.*.lat_e5` → «latitud»; `items.*.lng_e5` → «longitud»; `items.*.accuracy_m` → «precisión»;
    *   `items.*.pass` → «pase»; `items.*.signature` → «firma»; `items.*.device_key_id` → «llave del dispositivo»;
    *   `ticket` → «boleto».
*   **A6** `resources/js/lib/audit.ts` y `app/Modules/Tenancy/Support/AuditPages.php` (`KEYS`, línea 120). `tests/Unit/AuditEventLabelsTest.php:56-61` exige una entrada por cada clave de metadatos, salvo `tenant_id`.
    *   Evento nuevo de A1: `'attendance.offline_mark_accepted': 'Marca sin conexión recibida'`.
    *   Claves nuevas de A1: `time_basis`, `signer_type`, `interval_minutes`, `review_flags`. `employee_id` ya existe.
*   **A6** `tests/Feature/FormRequestAuthorizationTest.php`: `OfflinePunchRequest` y `OfflineTicketsRequest` devuelven `authorize(): true` a propósito (la credencial es la firma o el boleto, como `MarkRequest`); si la prueba exige una razón, va ahí.
*   **A6** `docs/normas/jornada/criterios.md`:
    *   3.3.1: el QR sin conexión es cota inferior; la hora declarada se acota hasta 72 h y más allá cuenta la cota probada; banderas de revisión.
    *   5.3: métodos `offline_qr` media y `offline_declared` básica.
*   **A6** `docs/operations/pwa.md`: umbrales de las medidas que salen de los logs de #16 paso 9 y de `attendance.offline_queue_reported`.
*   **A3**: `SignedBy` y `DeviceSigned` con `revokedAt: ?CarbonImmutable`, y las firmas de los contratos:
    *   `WorkerSignatures::verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): ?SignedBy`.
    *   `DeviceKeySignatures::passId(string $pass): ?string`: lee el id del pase verificado por HMAC de la empresa actual e ignora el vencimiento.
    *   `DeviceKeySignatures::verify(string $expected, string $signature, string $pass, CarbonImmutable $ticketIssuedAt): ?DeviceSigned`, con `{deviceKeyId, employeeId, passId, passIssuedAt, passExpiresAt, revokedAt}`.
    *   Una credencial o llave revocada después de `$ticketIssuedAt` verifica y trae su `revokedAt`; una revocada antes, no.
    *   `tests/Feature/WorkerAccess/HomeTest.php:59,64` (`/a/acme` → `/me/acme/punch`) en el commit 2.
*   **A2** `app/Modules/Attendance/Http/Controllers/WeekController.php`:
    *   (commit 2) Línea 52: `'punchUrl' => route('attendance.device.punch.show', $tenant)`.
    *   (commit 9) En `verified()` (líneas 102-113), tras resolver a la persona con `$signed->employeeId` y comprobar que esté activa: `$request->attributes->set(AttachOfflineTickets::SIGNED, $person->id)`.
    *   Las props `slug` y `deliverUrl` las define A2.
*   **A2** `tests/Feature/Attendance/WeekTest.php` (dueño A2):
    *   (commit 2) Líneas 140 y 158: `punch.home` → `punch.show`. Líneas 151-152 → `/me/acme/week/...`. 404 en `/a/acme/week` y en `POST /a/acme/week/options`.
    *   (commit 9) `read`, `confirm` y `dispute` traen `{tickets, context}`, también en la 422 de `confirm`.
*   **A2** `tests/Browser/AttendanceWeekTest.php` (commit 2): líneas 45 y 199 → `/me/acme/week...`. Línea 49: `header a[href$="/me/acme/punch"]`. Líneas 68 y 219 (`navigate('/a/acme')`) → `/me/acme/punch`.
*   **A2** consume:
    *   `AttendanceOfflineMark`, `OfflineRejection`, `TimeBasis`, `ReviewFlag` y `OfflineMarkAccepted` (despachado dentro de la transacción);
    *   los estados de factory de #3 y #60.
    *   No hay filas `signature_invalid` que excluir. El ancho del intervalo es `received_at − ticket_issued_at`.
*   **A5a** consume:
    *   **Vectores:** #33 y #58; el reto con `pass_id` antes del boleto, firmado como 32 bytes crudos.
    *   **Petición de entrega:** coordenadas como enteros `lat_e5`/`lng_e5`/`accuracy_m`; cabeceras `Accept: application/json`, `Content-Type: application/json` y `credentials: 'omit'`.
    *   **Boletos:** `POST /me/{slug}/tickets` con `{ticket, queue?}`. `{tickets, context}` llega en la raíz de toda respuesta firmada y de la ruta de boletos.
    *   **Regla de borrado:** se borra solo con `received` o con `duplicate` + `stored_status: received`. Con `rejected`, `conflict` (`ticket_reused`), `duplicate` + `stored_status: rejected`, `ticket_invalid` o `signature_invalid` se conserva «No aceptada». Con 429, 5xx o falla de red, todo el lote queda pendiente. Con 413 o 422, se parte el lote y se reintenta elemento por elemento.
    *   **Fixtures de `tests/js/lib/attendance/*.test.ts`:** los literales `/a/acme` se cambian en el commit 2, sin cambio de comportamiento.
*   **A5b** consume: props `slug`, `deliverUrl` y `ticketsUrl` de Marcar. La prop `mode` (`'remote'|'onsite'|null`) ya existe en `MarkController::show()` (`work_mode === WorkMode::Remote`, línea 112).

##### Riesgos y pruebas en teléfonos

*   **Hora declarada.** Quien declara puede mentir dentro de las cotas:
    *   nunca antes del boleto, de la marca anterior ni del QR, y nunca después del recibo ni de la marca siguiente;
    *   sin bandera, la mentira gana como mucho 240 min;
    *   con `wide_interval`, hasta 72 h contadas hasta que RH verifique;
    *   más allá de 72 h cuenta solo la cota probada.
    La defensa es la bandera, ese límite y la revisión de RH. No hay cursor de boletos en esta versión.
*   **QR sin conexión = «no antes de».** Prueba que el código existía, no cuándo se leyó; por eso solo sube la cota inferior. Si es más de 30 min más viejo que la hora declarada, la marca baja a `offline_declared` con `old_window`.
*   **Boleto redondeado hacia arriba.** Una entrega sin conexión que llegue en el mismo segundo de su boleto tendría `lower > upper` y saldría `sequence_conflict`. En la práctica no ocurre: la cola se usa porque la petición en línea ya falló.
*   **Ruta de boletos sin sesión.** Cualquiera con un boleto vigente de la persona obtiene boletos nuevos y su contexto (ids, centro, versión del acuerdo). Sin firma no marcan nada, y el contexto no trae nombre, posición ni marcas.
*   **Lote de una sola persona.** Sin esa regla, los boletos de la respuesta no tendrían dueño; un teléfono compartido manda un lote por persona (`mixed_people`).
*   **Nombre `punch.home`.** No se puede conservar: su dirección nueva (`me/{slug}`) es la de `worker.home`. Se borra; su único destino es `punch.show`.
*   NO VERIFICADO: `AsEnumCollection` sobre una columna jsonb con la versión instalada de Laravel (#18); se comprueba al implementar.
*   Pruebas que solo se hacen en teléfonos reales (iPhone y Android):
    *   El QR leído con la cámara abre `me/{slug}/punch?q={windowIndex}.{hmac}`. Sin red, la marca se entrega después con `qr_window`.
    *   Desde Inicio, Marcar abre la cámara y pide la ubicación con la cabecera de `/me/{slug}/*`.
    *   Un fin de semana sin entregar (≈ 63 h) con un lote de varias marcas entra en orden por `seq`, con la hora declarada y `wide_interval`.
    *   Modo avión justo después de enviar en línea: la copia en cola vuelve `duplicate` («Ya estaba registrada») y se borra del teléfono.
    *   La firma de la llave de iCloud sobre los 32 bytes del reto v1 verifica en el servidor. La de Chrome en iPhone falla y cae a la llave del dispositivo.
    *   Rotar `APP_KEY` dejando la anterior en `APP_PREVIOUS_KEYS`, con marcas pendientes en un teléfono: entran.


#### Segmento A2 · Attendance: verificación de RH, semana, reporte y Humi

Segmento: la pestaña «Marcas sin conexión» dentro de Correcciones (verificar una, verificar las seleccionadas, marcar incorrecta con su corrección), `WeekCompleteness` y el bloqueo `uncovered_days` de `ConfirmWeek` con su interruptor, `WeekController`, reapertura de semanas por marca sin conexión, reporte para la autoridad, resumen del panel y lector de Humi. Verificado contra el repo (fecha 2026-11-20); lo no verificado dice NO VERIFICADO. Los números que faltan (2, 5, 7, 9, 10, 11, 18, 27, 30, 33-35, 37-39, 43, 48, 50, 51, 55, 58, 62, 68, 69, 70, 74, 76, 77, 91) son entradas eliminadas; los demás conservan su número, y las entradas nuevas siguen la numeración (79 en adelante), por eso no van en orden.

Cada entrada cae en el commit de su función: **12** reapertura, **13** `WeekCompleteness` y `uncovered_days`, **14** verificación de RH (incluye el reporte y lo que Mi semana muestra de la revisión), **15** Humi y resumen del panel. Lo que va en otro commit lo dice con «(commit N)».

Hechos del código que deciden el diseño de este segmento:

*   `CorrectMark::handle(string $markId, ?CarbonImmutable $newMarkedAt, string $reason, int $userId)` acepta una hora o `null` (anula); rechaza solo una hora futura y una marca ya corregida (`CorrectMark.php:26`). No revisa la secuencia: `ResolveCorrection` lo hace antes con `CorrectedSequence::follows(...)`. La corrección no copia `credential_id`, posición ni `zone_id`.
*   El panel no tiene una lista de marcas: `routes/attendance.php` solo tiene índice, puesta en marcha, centro, correcciones (líneas 42-44), política, horarios, reporte y exportación. La cola de Correcciones (`CorrectionPages`, `CorrectionController::index`, `resources/js/pages/attendance/corrections.tsx` con `CountTabs` y `?status=`) es donde entra la pestaña.
*   Permisos: `view-attendance` (owner, hr, viewer) y `manage-attendance` (owner, hr); el rol `committee` no tiene ninguno (desde #162 hay además roles propios por empresa con permisos sueltos: lo que importa para este diseño es el permiso, no el nombre del rol) (`tests/Feature/Attendance/PermissionsTest.php:21`). `ResolveCorrectionRequest::authorize()` usa `manage-attendance`; `CorrectionController::index` usa `Gate::authorize('view-attendance')`.
*   `ConfirmWeek::blocker(array $marks)` es estático; lo llaman solo `ConfirmWeek::handle` (línea 62) y `PersonWeek::of` (línea 75). Hoy bloquea solo `no_marks` y una última marca que no es Fin (`day_open`). `DisputeWeek` no lo llama: objetar no depende del bloqueo.
*   `ReopenWeeks::handle($markId)` trata una marca sin original (`corrects_mark_id` nulo) leyendo solo sus semanas, y deja objetada una semana objetada. `ConfirmWeek` toma `MarkLock` antes de su fila.
*   Festivos: `Leave\Support\Holidays::between($from, $to)` devuelve los de la LFT (`StatutoryHolidays`) más los de `leave_holidays`, que son **por empresa** (`unique(tenant_id, date)`, sin centro). `Absences` (único implementador `CompanyAbsences`) no los expone hoy.
*   Alta: `Employee` tiene `hired_on` (fecha nula), pero `RosterEntry` **no** la expone. Solo `CompanyRoster::entry()` (línea 138) y `RosterTest` construyen `RosterEntry`. La baja no hace falta: `WeekController` responde 404 a una persona que se fue (docblock de la clase), así que nunca confirma una semana después de irse.
*   `WeekController::verified()` (líneas 102-113) solo verifica la firma; la persona se resuelve después en `view()` (`roster->find(...) ?? abort(404)`, línea 134). `show()` tiene `punchUrl` con `punch.home` en la línea 52. `WeekTest` no afirma conteos de consultas.
*   `CorrectionHistory::of` ya une cada corrección con su original por `corrects_mark_id` (`left join attendance_marks o`) y nunca pone un nombre: dice «por RH». La verificación en el reporte sigue esa regla.
*   El borrado de la empresa demo recorre toda tabla con `tenant_id` (`DemoWorkspaceRunner::tablesInDeleteOrder`): un trigger de solo inserción debe dejar borrar filas de la demo.
*   La fábrica de horarios trabaja lunes a viernes 480 min (`AttendanceScheduleFactory.php:33`); el `beforeEach` de `tests/Browser/AttendanceWeekTest.php` da marcas solo al lunes y al martes.
*   `AuditPages::KEYS` ya tiene `employee_id`, `mark_id` y `decision`; no tiene `corrected`. `tests/Unit/AuditEventLabelsTest.php` exige una etiqueta en `resources/js/lib/audit.ts` por evento de bitácora y una clave en `AuditPages::KEYS` por clave de metadatos.
*   `AttendanceSetup` lo construyen `CompanyAttendanceStanding` y `tests/Feature/Attendance/AttendanceStandingTest.php` (líneas 27 y 44).

---

##### Base de datos

###### 1. `database/migrations/2027_01_30_350000_create_attendance_mark_reviews_table.php` (Create)
*   **Type / Scope**: Migración (commit 14). Su fecha queda entre `worker_device_keys` (`2027_01_30_200000`, commit 10) y `push_subscriptions` (`2027_01_30_900000`, commit 16), en el mismo orden que los commits.
*   **Proposed Changes**: tabla `attendance_mark_reviews`: `id` uuid pk; `seq` `bigserial` (orden de inserción: «la decisión vigente es la última» se lee con `order by seq desc`, determinista aunque dos filas compartan `created_at`); `tenant_id` fk tenants restrict; `mark_id` uuid; `decision` varchar(9); `reviewer_id` foreignId users restrict; `note` text null; `created_at` timestampTz useCurrent (sin `updated_at`). FK compuesta `(mark_id, tenant_id) → attendance_marks(id, tenant_id)` (sobre `attendance_marks_id_tenant_unique`). Índice `(tenant_id, mark_id, seq)`. CHECKs (con paréntesis): `attendance_mark_reviews_decision_known CHECK (decision IN ('verified','incorrect'))`, `attendance_mark_reviews_note_on_incorrect CHECK (decision = 'verified' OR length(btrim(coalesce(note,''))) >= 5)`, `CHECK (char_length(note) <= 500)`. Función `attendance_mark_reviews_insert_only()` con la excepción de la demo (el `IF TG_OP = 'DELETE' … is_demo … RETURN OLD` de `2027_01_19_100000_let_the_demo_company_be_wiped.php`, luego `RAISE EXCEPTION 'attendance_mark_reviews is insert-only'`), triggers `BEFORE UPDATE OR DELETE` por fila y `BEFORE TRUNCATE`.

##### Modelos, enums, factories

###### 3. `app/Modules/Attendance/Models/MarkReview.php` (Create)
*   **Type / Scope**: Modelo Eloquent `final`.
*   **Proposed Changes**: `BelongsToCurrentTenant`, `HasVersion4Uuids`, `HasFactory`, `UPDATED_AT = null`, `#[Fillable(['tenant_id','mark_id','decision','reviewer_id','note'])]`, `#[UseFactory(MarkReviewFactory::class)]`, casts `decision => ReviewDecision::class`, `reviewer_id => integer`, `seq => integer`. Estático `latestByMark(list<string> $markIds): array<string, MarkReview>` (una consulta `distinct on (mark_id) … order by mark_id, seq desc`; arreglo vacío sin consulta si no hay ids). Lo usan la pestaña, `PersonWeek` y `ReviewOfflineMark`.

###### 4. `app/Modules/Attendance/Enums/ReviewDecision.php` (Create)
*   **Type / Scope**: Enum.
*   **Proposed Changes**: `Verified = 'verified'`, `Incorrect = 'incorrect'`.

###### 6. `database/factories/Attendance/MarkReviewFactory.php` (Create)
*   **Type / Scope**: Factory.
*   **Proposed Changes**: por omisión `verified` sobre la marca de una entrega aceptada de la factory de A1 (`AttendanceOfflineMarkFactory::new()->accepted()`), misma empresa, `reviewer_id` de `User::factory()`. Estado `incorrect(string $note = 'El quiosco del centro sí tenía luz.')`.

##### Acciones

###### 8. `app/Modules/Attendance/Actions/ReviewOfflineMark.php` (Create)
*   **Type / Scope**: Acción PHP `final readonly`.
*   **Proposed Changes**: `handle(string $markId, ReviewDecision $decision, int $userId, ?string $note, ?CarbonImmutable $correctedAt = null): MarkReview` y `verifyMany(list<string> $markIds, int $userId): array{verified: int, skipped: int}`, `@throws CorrectionRejected`. Dependencias: `Members`, `CorrectMark`, `RecordAudit`.
    *   Miembro de la empresa (`Members::find`) o `InvalidArgumentException`; `incorrect` sin nota de 5 o más → `InvalidArgumentException`.
    *   `DB::transaction`: `MarkLock::of(empleado)`; la marca debe ser efectiva (`EffectiveMarks::of(...)->whereKey`) o `CorrectionRejected('mark_changed')`; su método `offline_qr|offline_declared` o `CorrectionRejected('not_offline')`. Inserta la fila de revisión.
    *   **`verified`**: solo la fila de revisión. Verificar no cambia la marca: su hora es la que calculó la regla de tiempo de A1 (`OfflineTime::decide`).
    *   **`incorrect`**: solo existe junto con su corrección, en la misma transacción. Con `$correctedAt` (hora que escribe RH) comprueba `CorrectedSequence::follows($emp, $mark, $mark->type, $correctedAt)` (si no, `CorrectionRejected('sequence_invalid')` y no queda la revisión) y llama `CorrectMark::handle($mark->id, $correctedAt, $note, $userId)`; sin hora, `CorrectMark` con `null` anula la marca. Es también el camino cuando la hora contada no es la declarada (bandera `declared_adjusted`, por ejemplo una marca de más de 72 h sin conexión que cuenta en la cota probada) y RH sabe que la declarada es la buena: corrige a esa hora. La persona ve la nota en Mi semana como cualquier corrección. Un día que queda vacío vuelve a ser día sin cubrir para `WeekCompleteness`.
    *   `verifyMany`: lee entre `$markIds` (máximo 25) las marcas sin conexión efectivas de la empresa actual sin revisión (un id de otra empresa o ya revisado no está y suma a `skipped`); por cada una llama `handle($id, Verified, $userId, null)` en su propia transacción; un `CorrectionRejected` suma a `skipped` y sigue. Cada marca escribe su fila, su bitácora y su evento.
    *   Bitácora `attendance.offline_mark_reviewed` con `['mark_id','employee_id','decision','corrected' => bool]`; después del commit `Log::info('attendance.offline_mark_reviewed', mismos datos)` y `event(new OfflineMarkReviewed(...))`, en las dos decisiones. Nunca la nota ni horas en log o bitácora.

###### 12. `app/Modules/Attendance/Actions/ResolveCorrection.php` (Modify)
*   **Type / Scope**: Acción PHP (commit 14).
*   **Proposed Changes**: después del `Log::info('attendance.correction_resolved', …)` de `handle()` (línea 72), `event(new CorrectionResolved($request->id, $request->employee_id, $approve))`, al aprobar y al rechazar. Hoy solo existe `MarkCorrected` y solo al aprobar; A4 lo usa para el aviso push.

###### 13. `app/Modules/Attendance/Actions/ConfirmWeek.php` (Modify)
*   **Type / Scope**: Acción PHP.
*   **Proposed Changes**: inyecta `Absences` (Leave) y `Roster` (Organization). En `handle()`, tras `$marks = EffectiveMarks::forWeek(...)`, arma `WeekCompleteness::of(...)` con las marcas agrupadas por día, `ScheduleTimeline::of([$employeeId])` (la misma lectura que ya hace `PersonWeek`, una consulta), `AbsentDays::of(...)`, `Absences::holidaysBetween($week->start(), $week->end())` y `Roster::find($employeeId)?->hiredOn`. `blocker` cambia de firma: `public static function blocker(array $marks, WeekCompleteness $completeness): ?string` → `no_marks`, `day_open`, luego `uncovered_days` si `$completeness->uncoveredDays() !== []` **y** `config('attendance.week.block_uncovered_days')` es verdadero. Con el interruptor apagado no bloquea, y `handle()` registra `Log::info('attendance.week_uncovered_days_computed', ['employee_id', 'week_start', 'uncovered_days' => count])` cuando hay días sin cubrir (solo en la confirmación, no en cada lectura de Mi semana). El docblock de la clase dice que el bloqueo vale para toda empresa y cómo se apaga.

###### 14. `app/Modules/Attendance/Exceptions/WeekRejected.php` (Modify)
*   **Type / Scope**: Excepción.
*   **Proposed Changes**: solo el docblock: lista `uncovered_days`. Sin campos nuevos: las fechas viajan en la semana que devuelve `WeekController::rejected`.

##### Soporte (lecturas)

###### 15. `app/Modules/Attendance/Support/WeekCompleteness.php` (Create)
*   **Type / Scope**: Clase de soporte `final readonly`, sin consultas (recibe todo; prueba Unit).
*   **Proposed Changes**: `public static function of(WeekPeriod $week, array<string, list<AttendanceMark>> $byDay, ScheduleTimeline $schedules, string $employeeId, array<int, string> $away, list<string> $holidays, ?CarbonImmutable $hiredOn): self`; `public function uncoveredDays(): list<string>` (Y-m-d); `public function day(int $offset): array{required: bool, uncovered: bool}`. Un día está **cubierto** (no se exige marca) cuando:
    *   la persona no tiene horario vigente ese día (`$schedules->on($employeeId, $date) === null`: ninguno, o un día anterior a su primer horario): ese día no se exige. `WorkedDay` trata a quien no tiene horario como 8 h los 7 días; usarlo bloquearía cada domingo. Esa persona sigue con `no_marks` y `day_open`;
    *   es de descanso del horario vigente ese día (`minutes_by_weekday[isoWeekday]` 0 o ausente en el que devuelve `$schedules->on($employeeId, $date)`; con un cambio de horario a mitad de la semana cada día usa el suyo);
    *   es festivo (`$holidays`);
    *   es anterior a `$hiredOn` (nulo: sin límite);
    *   tiene ausencia aprobada (`$away[$offset]`, vacaciones o permiso que Leave cuenta: solo Aprobadas o Tomadas);
    *   todavía no termina: su fecha local es hoy o posterior, en la zona de la semana.
    *   En otro caso es **requerido**, y queda **sin cubrir** si no tiene marcas o `WorkedDay::missingEnd($marks)`. Relación con «Día abierto»: `day_open` sigue mirando solo la última marca de la semana (un turno de noche del domingo que termina el lunes); un día intermedio con entrada y sin salida cae aquí como sin cubrir.
    *   Las marcas que siguen en el teléfono no las ve el servidor: el botón de confirmar se apaga con la cola no vacía de la persona (A5b).

###### 16. `app/Modules/Attendance/Support/PersonWeek.php` (Modify)
*   **Type / Scope**: Soporte (datos de Mi semana).
*   **Proposed Changes**: en `of()` (firma sin cambio): lee `Absences::holidaysBetween` y `Roster::find($employeeId)?->hiredOn` (dos consultas), construye `WeekCompleteness` con `$byDay`, el `$schedules` (`ScheduleTimeline`) y el `$away` que ya tiene (main ya lee el horario por día con `$schedules->on($employeeId, $date)`; no se vuelve a consultar); cada día suma `'required'` y `'uncovered'`; la raíz suma `'uncoveredDays' => list<date>` (también con el interruptor apagado: la persona ve qué días faltan aunque pueda confirmar); `$blocked = ConfirmWeek::blocker($marks, $completeness)`. (commit 14) En `mark()`: suma `'offline' => bool` (método `offline_qr|offline_declared`), `'review' => 'pending'|'verified'|'incorrect'|null` (`pending` para **toda** marca sin conexión sin fila de revisión, con o sin QR) y `'reviewNote' => ?string` (solo con `incorrect`), con una consulta `MarkReview::latestByMark` de las marcas sin conexión de la semana, saltada si no hay ninguna. `'evidence'` no cambia (sale de `MarkMethod::evidence()`, que A1 amplía). `'away'` sigue leyendo `away_center_id`, que una marca sin conexión solo trae cuando el QR verificó para otro centro activo de la empresa. `confirmUrl` sigue saliendo solo con `blocked === null`.

###### 17. `app/Modules/Attendance/Support/OfflineMarkPages.php` (Create)
*   **Type / Scope**: Soporte (props de la pestaña), molde de `CorrectionPages` (commit 14).
*   **Proposed Changes**: `index(string $view, string $search, bool $unflaggedOnly, string $markId = ''): array`, `counts(): array{offline: int, not_accepted: int}` (una consulta cada conteo) y `needsReviewCount(): int` (estático; ver abajo). Las dos vistas mandan su lista en la prop **`offlineMarks`**, las dos paginadas de 25 (`->paginate()->withQueryString()`), con búsqueda por nombre (`Roster::matchingIds`):
    *   `offline`: marcas sin conexión **efectivas sin fila de revisión** (con o sin QR; el `whereNotExists` de `EffectiveMarks::query()`, entrada 88), unidas con su entrega (`attendance_offline_marks.mark_id`, tabla de A1). Orden: con `review_flags` no vacío primero, luego la más vieja. Filtro `?flags=none` («Sin banderas»): solo las de `review_flags = '[]'`, para verificarlas en bloque. Fila: `id`, `person {name, jobTitle}`, `type`, `countedAt` local (la hora contada, `marked_at`), `basis` (`qr_window|declared_interval`), `flags` (de `review_flags`: `wide_interval|old_window|no_position|outside_zone|declared_adjusted`), `ageDays` desde `received_at`. `detail` de la seleccionada (o la primera): fila + `declaredAt` (la hora que escribió la persona), `ticketIssuedAt` (desde cuándo no tenía conexión: la emisión del boleto más nuevo que tenía), `windowStart` (solo con QR: «no antes de»), `receivedAt` (cuándo llegó al servidor), `phoneClockAt` (`device_clock_at`: el reloj del teléfono al marcar, sin firmar, solo pista; nulo si no vino), todas locales; `signer` (`passkey|device_key`); `justification` (la nota opcional de la persona) **solo con `manage-attendance`** (puede ser de salud, como `reason` en `CorrectionPages`); y `proposedAt` para el diálogo de «incorrecta»: la hora declarada si difiere de la contada, si no la contada.
    *   `not_accepted`: entregas con fila rechazada de los últimos 35 días. Solo existen filas con motivos guardados: `ticket_expired`, `person_inactive`, `agreement_not_accepted`, `sequence_conflict`, `wrong_center` (el centro que mandó el teléfono no es el de la persona), `pass_expired`; los que no dejan fila (`ticket_invalid`, `signature_invalid`, `ticket_reused`, `malformed`) no llegan aquí. Fila: persona, `type`, `declaredAt` local, `reason`, `receivedAt` local. Solo lectura: la persona pide una corrección si hace falta.
    *   `needsReviewCount()`: marcas de la vista `offline` con banderas **o** con `received_at` de hace más de `config('attendance.offline.review_age_days')` (7) días. Es lo que cuentan «Necesita tu atención» y Humi (commit 15).
    *   Raíz: `canResolve = Gate::allows('manage-attendance')`, `filters {q, flags}`. Nunca posición, llave, boleto, QR, reto ni dispositivo.

###### 19. `app/Modules/Attendance/Support/AttendancePages.php` (Modify)
*   **Type / Scope**: Soporte (resumen del panel) (commit 15).
*   **Proposed Changes**: en `index()`, junto a `'pendingCorrections'` (línea 98), suma `'offlineNeedsReview' => OfflineMarkPages::needsReviewCount()`. Una consulta. Sin datos de persona. Las no aceptadas no se resumen aquí: la pestaña ya las muestra.

###### 20. `app/Modules/Attendance/Support/CompanyAttendanceStanding.php` (Modify) y 21. `app/Modules/Attendance/Data/AttendanceSetup.php` (Modify)
*   **Type / Scope**: Implementación del contrato público y su DTO (commit 15).
*   **Proposed Changes**: `AttendanceSetup` suma `public int $offlineNeedsReview = 0` (con valor por omisión: las construcciones de `AttendanceStandingTest` siguen valiendo). `current()` lo llena con `OfflineMarkPages::needsReviewCount()`. El docblock pasa de cinco a seis consultas.

##### Reporte para la autoridad

###### 22. `app/Modules/Attendance/Actions/BuildAuthorityReport.php` (Modify)
*   **Type / Scope**: Acción PHP (commit 14).
*   **Proposed Changes**: `EVIDENCE` y `RANK` no cambian: los métodos nuevos caen en `Medium` (`offline_qr`) y `Basic` (`offline_declared`) por `MarkMethod::evidence()` (A1); `evidence()` ya toma la más débil, y una marca corregida sigue «Corregida». Constantes: `OFFLINE = 'Marcó sin conexión'`, `TO_VERIFY = 'Con marcas sin conexión por verificar'`, `VERIFIED = 'Marca sin conexión verificada por RH'`. En `chunk()` una consulta más por bloque: para las marcas efectivas del bloque, la **raíz** de su cadena de correcciones (`with recursive` sobre `corrects_mark_id`, solo `attendance_marks`) unida con `attendance_offline_marks` por `mark_id` (`signer_type`) y con la última fila de `attendance_mark_reviews` (`order by seq desc limit 1`). Así una marca sin conexión que RH corrigió sigue diciendo «Marcó sin conexión» y quién firmó la original. `row()` recibe ese mapa y `flags()` agrega: `OFFLINE` con el firmante («Marcó sin conexión (firmó con su llave de acceso)» o «… (con la llave del dispositivo)») cuando una marca del día es sin conexión o corrige una; `TO_VERIFY` cuando una marca efectiva sin conexión no tiene revisión; `VERIFIED` cuando su última revisión es `verified`. Docblock: «siete consultas» pasa a ocho.

###### 23. `app/Modules/Attendance/Support/ReportPages.php` (Modify)
*   **Type / Scope**: Soporte del reporte (commit 14).
*   **Proposed Changes**: `$totals['toVerify']` (días con `TO_VERIFY`); `OFFLINE` y `VERIFIED` se suman a las banderas informativas que no marcan a la persona (junto a `IN_PROGRESS` y `FIELD` en el `array_diff`; `OFFLINE` se compara por prefijo porque lleva el firmante); `TO_VERIFY` sí la marca. Sale en `summary`.

###### 24. `app/Modules/Attendance/Support/AuthorityCsv.php` (sin cambio)
*   **Type / Scope**: verificado: las banderas salen en la columna «Avisos» (`implode('; ', $row->flags)`). Solo una prueba nueva (ver 67).

###### 25. `app/Modules/Attendance/Support/WorkedDay.php` y `app/Modules/Attendance/Support/HoursCalculator.php` (sin cambio)
*   **Type / Scope**: verificado: cuentan desde `marked_at` de las marcas efectivas; la hora contada de A1 cuenta como cualquier otra. Prueba de regresión en 75.

##### Eventos y listener

###### 26. `app/Modules/Attendance/Events/OfflineMarkReviewed.php` (Create)
*   **Type / Scope**: Evento público (commit 14).
*   **Proposed Changes**: `final readonly`, `__construct(public string $markId, public string $employeeId, public string $decision)` (`verified|incorrect` como texto: los enums son internos). Solo ids. Despachado después del commit, en las dos decisiones. A4 manda el aviso push en ambas, con el mismo texto fijo.

###### 28. `app/Modules/Attendance/Events/CorrectionResolved.php` (Create)
*   **Type / Scope**: Evento público (commit 14).
*   **Proposed Changes**: `final readonly`, `(public string $requestId, public string $employeeId, public bool $approved)`. Despachado después del commit por `ResolveCorrection`. Lo consume A4.

###### 29. `app/Modules/Attendance/Listeners/ReopenOnOfflineMark.php` (Create)
*   **Type / Scope**: Listener síncrono (commit 12).
*   **Proposed Changes**: `handle(OfflineMarkAccepted $event): void` → `$this->reopen->handle($event->markId)`. A1 despacha el evento **dentro** de la transacción de la entrega con `MarkLock` ya tomado, así que el `lockForUpdate` de `ReopenWeeks` corre en esa misma transacción: una confirmación en curso espera y luego ve la marca. Sin transacción propia. No implementa `ShouldQueue`.

##### HTTP del panel

###### 31. `app/Modules/Attendance/Http/Controllers/OfflineMarkController.php` (Create)
*   **Type / Scope**: Controlador delgado (commit 14).
*   **Proposed Changes**: `verify(ReviewOfflineMarkRequest, mixed $tenant, string $mark, ReviewOfflineMark)`, `reject(...)` y `verifyMany(VerifyOfflineMarksRequest, mixed $tenant, ReviewOfflineMark)`: llaman la acción, `Inertia::flash('toast', …)` y `back()`, como `CorrectionController`. Mensajes `REFUSED`: `mark_changed` «La marca ya se corrigió o anuló desde que la abriste.», `sequence_invalid` «Con esa hora el día queda en desorden. Escribe otra hora o deja la marca anulada.», `not_offline` «Esa marca no se hizo sin conexión.». Éxito: «Marca verificada.», «Marca corregida. La persona verá tu nota.», «Verificaste N marcas.» y, con saltadas, «Verificaste N marcas. M cambiaron desde que abriste la lista: revísalas una por una.». La lista la sirve `CorrectionController::index` (entrada 85).

###### 32. `app/Modules/Attendance/Http/Requests/ReviewOfflineMarkRequest.php` (Create)
*   **Type / Scope**: FormRequest (commit 14).
*   **Proposed Changes**: `authorize()` = `manage-attendance` (viewer 403 antes de validar). Reglas: `note` requerida en la ruta `…reject`, opcional en `…verify`, `min:5|max:500`; `corrected_at` `nullable|date_format:Y-m-d H:i` solo en `reject`. `correctedAt(string $timezone): ?CarbonImmutable` (zona de la marca), `note(): ?string`.

###### 92. `app/Modules/Attendance/Http/Requests/VerifyOfflineMarksRequest.php` (Create)
*   **Type / Scope**: FormRequest (commit 14).
*   **Proposed Changes**: `authorize()` = `manage-attendance`. Reglas: `ids` `required|array|min:1|max:25` (una página), `ids.*` `uuid|distinct`. `ids(): list<string>`.

###### 36. `routes/attendance.php` (Modify)
*   **Type / Scope**: Rutas del panel (archivo del módulo) (commit 14).
*   **Proposed Changes**: tras el bloque de correcciones (líneas 42-44): `POST admin/{tenant:slug}/attendance/offline-marks/{mark}/verify` → `tenant.attendance.offline.verify`, `POST …/{mark}/reject` → `tenant.attendance.offline.reject` (con `->whereUuid('mark')`) y `POST …/offline-marks/verify` → `tenant.attendance.offline.verify-many`, las tres con `->where('tenant', $notReserved)` como las vecinas. Sin `GET` nuevo: la lista es `tenant.attendance.corrections.index?status=offline|not_accepted`.
*   **Permisos por ruta (agregado en la conciliación con main, #162 «permisos granulares»)**: desde #162 todo nombre `tenant.*` pide un permiso en `Tenancy/Support/RoutePermissions` (`RequireRoutePermission`) y `RoutePermissionCoverageTest` falla con una ruta sin permiso. Las tres rutas nuevas caen en el patrón general `'tenant.attendance.*' => 'manage-attendance'` (línea 144 de main), que es justo el permiso que sus `FormRequest` piden: **no hace falta tocar `RoutePermissions`**. La pestaña (`tenant.attendance.corrections.index`, ya mapeada a `view-attendance`) la abre quien ve asistencia; la nota de la persona sigue visible solo con `manage-attendance` (`canResolve`). `viewer` y los roles propios con solo `view-attendance` reciben 403 al verificar, y `committee` al abrir, ahora desde el middleware antes del `FormRequest` (el `Gate` queda como segunda barrera). El texto de «Marcas sin conexión» en `docs/normas/permisos/criterios.md` y `PermissionAreas` no cambia: no hay permiso nuevo ni área nueva.

###### 85. `app/Modules/Attendance/Http/Controllers/CorrectionController.php` (Modify)
*   **Type / Scope**: Controlador (commit 14).
*   **Proposed Changes**: `index` pasa `status`, `q`, `flags` y el id seleccionado a `CorrectionPages::index`, que decide la vista. El docblock dice que la cola tiene cuatro pestañas.

###### 86. `app/Modules/Attendance/Support/CorrectionPages.php` (Modify)
*   **Type / Scope**: Soporte de la cola (commit 14).
*   **Proposed Changes**: inyecta `OfflineMarkPages`. `counts` suma `offline` y `not_accepted` (`OfflineMarkPages::counts()`). Con `status` `offline` o `not_accepted` devuelve `[...$offline->index($status, $search, $flags === 'none', $id), 'counts' => …, 'status' => $status, 'filters' => …, 'canResolve' => …]` en lugar de `requests`; con otro valor, lo de hoy (`CorrectionStatus::tryFrom` con caída a `pending`).

###### 88. `app/Modules/Attendance/Support/EffectiveMarks.php` (Modify)
*   **Type / Scope**: Soporte (commit 14).
*   **Proposed Changes**: saca el `whereNotExists` de correcciones (líneas 35 y 103) a `public static function query(): Builder` (marcas efectivas de la empresa) y `of()` lo usa. Lo leen `OfflineMarkPages` y `ReviewOfflineMark::verifyMany`. Sin cambio de comportamiento.

##### Semana (HTTP de la persona)

###### 40. `app/Modules/Attendance/Http/Controllers/WeekController.php` (Modify)
*   **Type / Scope**: Controlador (solo datos). Dueño único del archivo.
*   **Proposed Changes**:
    *   (commit 2) Línea 52: `'punchUrl' => route('attendance.device.punch.show', $tenant)` (mudanza a `/me`).
    *   (commit 9) `verified()` (líneas 102-113) recibe la persona: tras verificar la firma, `$this->roster->find($signed->employeeId) ?? abort(404)` (la misma regla que hoy aplica `view()`) y solo entonces `$request->attributes->set(AttachOfflineTickets::SIGNED, $signed->employeeId)`. Así `read`, `confirm` y `dispute`, también sus 409/422 posteriores a la firma, llevan `{tickets, context}`, y una firma de una persona que se fue no los recibe con su 404.
    *   (commit 13) `rejected()` (línea 124) adjunta `'week' => $this->view(...)` también para `uncovered_days` (hoy solo para `week_changed`): la app ve qué días faltan.
    *   (commit 19) `show()` suma `'slug' => $tenant` y `'deliverUrl' => route('attendance.device.punch.offline', $tenant)` (la ruta existe desde el commit 11), para que Mi semana cuente y entregue la cola de la persona.

##### Leave y Organization (contratos que lee la semana)

###### 79. `app/Modules/Leave/Contracts/Absences.php` (Modify)
*   **Type / Scope**: Contrato público (commit 13).
*   **Proposed Changes**: `public function holidaysBetween(CarbonImmutable $from, CarbonImmutable $to): array` con `@return list<string>` (Y-m-d, en orden, ambos extremos): los de la LFT y los de la empresa, por empresa, no por centro (verificado: `leave_holidays` sin `work_center_id`). El docblock de la interfaz dice que los festivos van aquí porque responden la misma pregunta que las ausencias («quién no trabaja ese día»); un contrato aparte se crea si otro módulo pide solo festivos.

###### 80. `app/Modules/Leave/Support/CompanyAbsences.php` (Modify)
*   **Type / Scope**: Implementación (commit 13).
*   **Proposed Changes**: `holidaysBetween` → `Holidays::between($from, $to)` (una consulta).

###### 81. `tests/Feature/Leave/AbsencesTest.php` (Modify)
*   **Type / Scope**: Prueba Feature (commit 13).
*   **Proposed Changes**: `holidaysBetween` da los de la LFT y los de la empresa, sin los de otra empresa; ambos extremos incluidos; rango que cruza de año.

###### 82. `app/Modules/Organization/Data/RosterEntry.php` (Modify)
*   **Type / Scope**: DTO público (commit 13).
*   **Proposed Changes**: suma al final, después de `public ?string $mobile = null` (que main agregó en #147), `public ?CarbonImmutable $hiredOn = null` (con valor por omisión: `RosterTest` y otros que lo construyen no cambian; quien lo arma en `CompanyRoster` lo pasa por nombre, no por posición).

###### 83. `app/Modules/Organization/Support/CompanyRoster.php` (Modify)
*   **Type / Scope**: Implementación de `Roster` (commit 13).
*   **Proposed Changes**: `entry()` (línea 138) pasa `hiredOn: $employee->hired_on` por nombre, después de `$employee->mobile` (línea 161 en main al 2026-11-24). Sin consulta nueva (misma fila del modelo).

###### 84. `tests/Feature/Organization/RosterTest.php` (Modify)
*   **Type / Scope**: Prueba Feature (commit 13).
*   **Proposed Changes**: `find` trae `hiredOn`; nulo cuando la empresa no la capturó.

##### Humi

###### 41. `app/Modules/Assistant/Support/Readers/AttendanceReader.php` (Modify)
*   **Type / Scope**: Lector de cumplimiento (commit 15).
*   **Proposed Changes**: `status()` suma con `trans_choice`: «Hay N marcas sin conexión por verificar con banderas o con más de 7 días.» (`offlineNeedsReview`; el 7 sale de la configuración). Estado `pending` si hay correcciones o marcas que necesitan revisión; el enlace va a lo más urgente: correcciones, luego marcas sin conexión, luego puesta en marcha. El texto de «Al día» suma «ni marcas sin conexión por verificar». Solo conteos.

###### 42. `app/Modules/Assistant/Support/PanelLinks.php` (Modify)
*   **Type / Scope**: Enlaces de Humi (commit 15).
*   **Proposed Changes**: `attendanceOfflineMarks(): string` → `$this->path('tenant.attendance.corrections.index', ['status' => 'offline'])`, junto a `attendanceCorrections()`.

##### Frontend del panel («tú»)

###### 87. `resources/js/pages/attendance/corrections.tsx` (Modify)
*   **Type / Scope**: Página Inertia delgada (commit 14).
*   **Proposed Changes**: `TABS` suma «Sin conexión» (`offline`) y «No aceptadas» (`not_accepted`) con su conteo. Con esas vistas dibuja `OfflineMarkList` + `OfflineMarkDetail` en la misma rejilla `lg:grid-cols-[380px_minmax(0,1fr)]`; con las otras, lo de hoy. `PageHeader` sin cambio.

###### 44. `resources/js/components/attendance/offline-mark-list.tsx`, 45. `resources/js/components/attendance/offline-mark-list-item.tsx` (Create)
*   **Type / Scope**: Componentes (commit 14).
*   **Proposed Changes**: lista paginada con estado vacío con siguiente paso («No hay marcas sin conexión por verificar. Las que lleguen aparecerán aquí.»; en «No aceptadas»: «Ninguna marca se rechazó en los últimos 35 días.»). En «Sin conexión», con `canResolve`: casilla por renglón, «Seleccionar la página», el filtro «Sin banderas» y el botón «Verificar seleccionadas» (un `POST` a `tenant.attendance.offline.verify-many` con los ids marcados, hasta 25). Cada renglón: nombre, puesto, tipo y hora contada, `OfflineBadge`, las banderas en texto, «hace N días»; en «No aceptadas», el motivo en español.

###### 46. `resources/js/components/attendance/offline-badge.tsx` (Create)
*   **Type / Scope**: Componente (insignia) (commit 14).
*   **Proposed Changes**: `Badge` de `components/ui`: «Sin conexión · por verificar» (sin revisión) o «Sin conexión · no aceptada»; solo tokens.

###### 47. `resources/js/components/attendance/offline-mark-detail.tsx` (Create)
*   **Type / Scope**: Componente (commit 14).
*   **Proposed Changes**: detalle en `card`, con cada hora nombrada (sin «intervalo» sin definir): «Hora declarada 08:02», «Hora contada 08:02» (o la acotada), «Sin conexión desde 07:00 (boleto)», «No antes de 07:15 (QR del centro)» si hay QR, «Llegó 19:00», «Reloj del teléfono 08:05 · no es prueba» si vino; las banderas en texto («Más de 4 h sin conexión», «QR de más de 30 min antes», «Sin posición», «Fuera de la zona del centro», «La hora contada no es la declarada»); «Firmada con su llave de acceso | con la llave del dispositivo»; la nota de la persona (o nada sin permiso, con `ph-mask`). Debajo, `OfflineMarkDecision` con `canResolve`.

###### 49. `resources/js/components/attendance/offline-mark-decision.tsx` (Create)
*   **Type / Scope**: Componente (diálogo), molde de `resources/js/components/attendance/correction-decision.tsx` (commit 14).
*   **Proposed Changes**: botón «Verificar» («Verificar no cambia la marca») y «Es incorrecta», que abre un `Dialog` con nota obligatoria y «Hora correcta» (propuesta con `proposedAt`; vacía «anula la marca»). `useForm` + rutas Wayfinder `@/routes/tenant/attendance/offline`.

###### 52. `resources/js/lib/attendance/offline-marks.ts` (Create)
*   **Type / Scope**: Lógica no visual (commit 14).
*   **Proposed Changes**: `flagText(flag)`, `rejectionText(reason)`, `badgeText(view)`, `selectionPayload(ids)` (sin vacíos ni repetidos, máximo 25). La antigüedad reutiliza `waitingFor` de `resources/js/lib/attendance/corrections.ts` y la consulta `correctionsQuery` (ampliada a `flags`).

###### 53. `resources/js/types/attendance-corrections.ts`, `resources/js/types/attendance.ts`, `resources/js/types/attendance-panel.ts` (Modify)
*   **Type / Scope**: Tipos.
*   **Proposed Changes**: (commit 14) `attendance-corrections.ts`: `CorrectionView = CorrectionStatus | 'offline' | 'not_accepted'`, `CorrectionCounts = Record<CorrectionView, number>`, `OfflineFlag`, `OfflineMarkRow`, `OfflineMarkDetail`, `RejectedOfflineRow`; (commit 14) `attendance.ts`: `ReportSummary` suma `toVerify`; (commit 15) `attendance-panel.ts`: `AttendanceOverviewProps` suma `offlineNeedsReview`.

###### 54. `resources/js/lib/attendance/attention.ts`, `resources/js/components/attendance/attention-list.tsx` (Modify)
*   **Type / Scope**: «Necesita tu atención» (commit 15).
*   **Proposed Changes**: `AttentionInput` suma `offlineNeedsReview`; un renglón tras correcciones: `tone: 'alert'` «N marcas sin conexión por verificar» / «Tienen banderas o llevan más de 7 días. Cuentan en las horas mientras tanto.» / «Verificar marcas» (a la pestaña `offline`). Es el aviso a RH, sin correo. `attention-list.tsx` pasa el prop.

###### 56. `resources/js/lib/attendance/punch-view.ts` (Modify)
*   **Type / Scope**: Línea de evidencia de una marca (`markHow`) (commit 14).
*   **Proposed Changes**: `markHow(level, flag)` acepta `flag === 'offline'` con `OFFLINE_HOW`: `medium` «Sin conexión, no antes del QR del centro», `basic` «Sin conexión, hora declarada». El cambio es aditivo; quien lo pasa en Mi semana es `week-mark.tsx` (A5b).

###### 57. `resources/js/lib/attendance/report-findings.ts`, `resources/js/components/attendance/report-legend.tsx` (Modify)
*   **Type / Scope**: Reporte (commit 14).
*   **Proposed Changes**: hallazgo `to-verify` («N días con marcas sin conexión por verificar» / «Cuentan en las horas; verifícalas antes de una inspección.» / `show: 'flagged'`); la leyenda suma: «Una marca sin conexión se hizo sin conexión y llegó después. Cuenta a la hora que declaró la persona, acotada por lo que el servidor comprueba (su boleto, sus otras marcas y la hora en que llegó); tras más de 72 h sin conexión cuenta en la cota probada hasta que RH la corrige. Con el QR del centro, reciente, se sabe además que no fue antes de ese código (evidencia media); sin QR, la evidencia es básica. RH la verifica.».

##### Pruebas

###### 59. `tests/Feature/Attendance/MarkReviewsTest.php` (Create)
*   **Type / Scope**: Prueba Feature (base de datos) (commit 14).
*   **Proposed Changes**: rechaza `UPDATE`, `DELETE`, `TRUNCATE` con `attendance_mark_reviews is insert-only`; rechaza decisión desconocida; `incorrect` sin nota; `mark_id` de otra empresa (FK compuesta); `latestByMark` da la de mayor `seq` aunque dos compartan `created_at`; la empresa A no lee las revisiones de B por consulta.

###### 60. `tests/Feature/Attendance/ReviewOfflineMarkTest.php` (Create)
*   **Type / Scope**: Prueba Feature (acción), `mutates(ReviewOfflineMark::class)` (commit 14).
*   **Proposed Changes**: verificar `offline_declared` y `offline_qr`: una revisión, ninguna marca nueva, `marked_at` igual. Incorrecta con hora: corrección a esa hora con la nota como motivo, la original se conserva, `MarkCorrected` reabre una semana confirmada y la objetada sigue objetada; incorrecta a la hora declarada de una marca con `declared_adjusted`: la marca efectiva queda a la declarada; sin hora: anula; sin nota o de 4 caracteres: error. Secuencia rota: `sequence_invalid` y **sin** fila de revisión. Marca ya corregida: `mark_changed`. Marca en línea: `not_offline`. Hora futura: error de `CorrectMark`. Quien no es miembro: error. Otra empresa: `ModelNotFound`. `OfflineMarkReviewed` en las dos decisiones, con ids; bitácora sin nota ni hora. Dos revisiones: manda la última. `verifyMany`: 3 ids → 3 filas, 3 bitácoras, 3 eventos; un id ya revisado, uno de otra empresa y uno corregido en medio → `skipped`; lista vacía → `{0, 0}`.

###### 61. `tests/Feature/Attendance/OfflineMarkQueueTest.php` (Create)
*   **Type / Scope**: Prueba Feature (HTTP, props, permisos, tenant) sobre `tenant.attendance.corrections.index?status=offline|not_accepted` y las tres rutas de la entrada 36 (commit 14).
*   **Proposed Changes**: `expectsDatabaseQueryCount` igual con 1 y con 30 marcas, en las dos vistas. Las marcas con banderas salen primero; `?flags=none` deja solo las sin banderas; una revisada sale de la lista; una corregida por la persona (no efectiva) no sale. Las dos vistas paginan `offlineMarks` de 25 y buscan por nombre. Owner y hr ven y resuelven; viewer ve sin la nota de la persona (`canResolve` false) y recibe 403 al verificar, al marcar incorrecta y al verificar seleccionadas; `committee` recibe 403 al abrir (no tiene `view-attendance`). Conteos `offline` y `not_accepted`; rechazos de hace 36 días no salen. Marca de otra empresa por URL 404. **Props sin respuestas individuales**: el JSON no contiene latitud, longitud, `credential_id`, `signer_id`, boleto, QR, `challenge`, ni la nota para viewer. Toasts de éxito y de cada rechazo. Validación: nota de 4 caracteres, `corrected_at` mal formado, `ids` vacío, con 26 o con un id que no es uuid.

###### 63. `tests/Unit/Attendance/WeekCompletenessTest.php` (Create)
*   **Type / Scope**: Prueba Unit de la regla (sin base), `mutates(WeekCompleteness::class)` (commit 13).
*   **Proposed Changes**: día laborable sin marcas → sin cubrir; entrada sin salida → sin cubrir; descanso, festivo, vacaciones, permiso → cubierto; antes del alta → cubierto, el día del alta → requerido; sin horario → ningún día sin cubrir; horario que empieza a mitad de semana (`ScheduleTimeline`, criterio 2.6) → los días anteriores a su inicio no se exigen y los siguientes sí, y un cambio de horario a mitad de semana lee cada día con el suyo (uno que descansa el miércoles y otro que lo trabaja); hoy o futuro → cubierto; turno de noche que termina el lunes → cubierto; semana completa → `uncoveredDays() === []`.

###### 64. `tests/Feature/Attendance/WeeklyConfirmationTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente (commit 13).
*   **Proposed Changes**: casos nuevos, con un horario de lunes a viernes (`AttendanceSchedule::factory()`): `uncovered_days` con un miércoles vacío; se confirma tras una corrección que agrega las marcas; con vacaciones aprobadas ese día; con un festivo ese día; con un permiso que RH registró en Leave para un día pasado y aprobó; persona dada de alta el jueves; horario nuevo (`valid_from`) que empieza el jueves: lunes a miércoles se leen con el anterior y jueves en adelante con el nuevo; objetar sigue posible con días sin cubrir; con `attendance.week.block_uncovered_days` en falso confirma y registra `attendance.week_uncovered_days_computed` con el conteo y sin fechas. Los casos existentes no cambian: su persona no tiene horario (el archivo no crea ninguno; verificado), así que no tiene días sin cubrir. El commit dice que el bloqueo es un **cambio de comportamiento para toda empresa** (decisión del dueño, criterio 5.2).

###### 65. `tests/Feature/Attendance/WeekTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente; dueño único (lo amplían la mudanza y los boletos).
*   **Proposed Changes**:
    *   (commit 2) Líneas 140 y 158: `punch.home` → `punch.show`. Líneas 151-152 → `/me/acme/week/...`. Casos nuevos: 404 en `/a/acme/week` y en `POST /a/acme/week/options`.
    *   (commit 9) `read`, `confirm` y `dispute` traen `tickets` y `context` en la raíz, también en la 422 de `confirm`; una firma válida de una persona que se fue: 404 sin `tickets`.
    *   (commit 13) Los `assertJsonPath('days.N', [...])` exactos (línea 189) suman `required` y `uncovered`. El `beforeEach` crea un horario de lunes a viernes (línea 42) y el miércoles 2026-11-04 no tiene marcas, así que las líneas 197-198 (`blocked` nulo y `confirmUrl`) cambian por el bloqueo: esa prueba pasa a afirmar `blocked: 'uncovered_days'` con `uncoveredDays: ['2026-11-04', …]` y `confirmUrl` nulo, y una copia con el interruptor apagado conserva `blocked` nulo y el `confirmUrl`. El 422 de confirmar con `uncovered_days` trae `week`.
    *   (commit 14) Las marcas suman `offline`, `review`, `reviewNote`; marca sin conexión con `review: pending` (con y sin QR), `verified` e `incorrect` (con nota).
    *   (commit 19) `show` trae `slug` y `deliverUrl`.

###### 66. `tests/Feature/Attendance/ReopenOnOfflineMarkTest.php` (Create)
*   **Type / Scope**: Prueba Feature (listener) (commit 12).
*   **Proposed Changes**: `OfflineMarkAccepted` de una marca de una semana confirmada → pendiente, bitácora `attendance.week_reopened` con `mark_id`; objetada → sigue objetada; semana sin fila → nada; semana de otra persona → nada; un turno de noche del domingo reabre la semana del domingo; despachado dentro de una transacción que luego se revierte → la semana sigue confirmada.

###### 67. `tests/Feature/Attendance/AuthorityReportTest.php`, `tests/Feature/Attendance/AuthorityReportExportTest.php`, `tests/Feature/Attendance/AuthorityReportPageTest.php` (Modify)
*   **Type / Scope**: Pruebas Feature existentes (commit 14).
*   **Proposed Changes**: día con `offline_qr` → evidencia «Media» y «Marcó sin conexión (firmó con su llave de acceso)»; con `offline_declared` sin revisión → «Básica» y «Con marcas sin conexión por verificar»; verificada → «Marca sin conexión verificada por RH»; incorrecta con corrección → evidencia «Corregida» y **sigue** «Marcó sin conexión (… la llave del dispositivo)» por `corrects_mark_id`; cadena de dos correcciones → igual; el CSV trae las banderas en «Avisos»; `summary.toVerify`; `REPORT_PAGE_QUERIES` (hoy 33) sube en 1. Sin nombres de RH en ninguna celda.

###### 71. `tests/Feature/Attendance/ResolveCorrectionTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente (commit 14).
*   **Proposed Changes**: aprobar y rechazar despachan `CorrectionResolved` con ids y `approved`; un rechazo por `sequence_invalid` no lo despacha.

###### 72. `tests/Feature/Attendance/OverviewTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente (commit 15).
*   **Proposed Changes**: prop `offlineNeedsReview`: cuenta una con banderas y una sin banderas de hace 8 días; no cuenta una sin banderas de hace 6 días, una verificada ni las de otra empresa; `OVERVIEW_QUERIES` (hoy 38) sube en 1.

###### 73. `tests/Feature/Assistant/ComplianceOverviewModulesTest.php` (Modify)
*   **Type / Scope**: Prueba del lector de Humi (commit 15).
*   **Proposed Changes**: pendiente con 1 y con N marcas que necesitan revisión (enlace a `?status=offline`); correcciones antes que todo; una marca sin banderas y reciente no lo pone pendiente; datos de otra empresa no cuentan; el texto nunca trae nombre, nota ni motivo.

###### 75. `tests/Unit/Attendance/WorkedDayTest.php` (Modify)
*   **Type / Scope**: Prueba Unit existente (commit 14).
*   **Proposed Changes**: un día con marcas `offline_qr` y `offline_declared` cuenta sus minutos como cualquier otro.

###### 89. `tests/Feature/Attendance/CorrectionQueueTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente (commit 14).
*   **Proposed Changes**: `counts` trae `offline` y `not_accepted`; `QUEUE_QUERIES` (hoy 26) sube en 2 (los dos conteos); un `status` desconocido sigue cayendo a `pending`.

###### 90. `tests/Browser/AttendanceCorrectionsTest.php` (Modify)
*   **Type / Scope**: Prueba Browser existente (mismo dataset de claro, oscuro, escritorio y teléfono) (commit 14).
*   **Proposed Changes**: la pestaña «Sin conexión» muestra la insignia «Sin conexión · por verificar» y las banderas; «Verificar» y el toast; «Es incorrecta» con nota y hora; «Sin banderas», seleccionar dos y «Verificar seleccionadas»; «No aceptadas» con su motivo; viewer ve el aviso de solo lectura sin botones ni casillas.

###### 93. `tests/Feature/Attendance/AttendanceStandingTest.php` (Modify)
*   **Type / Scope**: Prueba Feature existente (commit 15).
*   **Proposed Changes**: `offlineNeedsReview` con una marca con banderas, una sin banderas de hace 8 días y una de otra empresa (cuenta 2); las construcciones de las líneas 27 y 44 suman el valor esperado.

###### 94. `tests/Browser/AttendanceWeekTest.php` (Modify)
*   **Type / Scope**: Prueba Browser existente de Mi semana; dueño único (la página la sirve `WeekController`), con los casos de la mudanza y de la pantalla.
*   **Proposed Changes**:
    *   (commit 2) Líneas 45 y 199 → `/me/acme/week...`; línea 49: `header a[href$="/me/acme/punch"]`; líneas 68 y 219 (`navigate('/a/acme')`) → `/me/acme/punch`.
    *   (commit 13) El `beforeEach` da marcas solo al lunes y al martes con un horario de lunes a viernes, así que las pruebas que confirman (líneas 87 y 151) se rompen con el bloqueo: dejan miércoles a viernes como descanso (`AttendanceSchedule::query()->update(['minutes_by_weekday' => [1 => 480, 2 => 480, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0]])`), sin cambiar las horas que afirman. Caso nuevo: con un miércoles laborable vacío, «Confirmar mi semana» está apagado con «Falta el miércoles…» y el texto de pedir una corrección o el permiso a RH; «No estoy de acuerdo» sigue activo.
    *   (commit 14) Una marca sin conexión se lee «Sin conexión · por verificar» y, verificada, «Verificada».
    *   (commit 19) Con una marca en la cola local de la persona el botón se apaga con el texto de marcas sin enviar. 375 px, claro y oscuro.

###### 78. `tests/js/lib/attendance/offline-marks.test.ts` (Create); `tests/js/lib/attendance/attention.test.ts`, `tests/js/lib/attendance/report-findings.test.ts`, `tests/js/lib/attendance/punch-view.test.ts` (Modify)
*   **Type / Scope**: Vitest.
*   **Proposed Changes**: (commit 14) texto de cada bandera y de cada motivo de rechazo guardado (y uno desconocido); `selectionPayload` sin repetidos y con tope de 25; hallazgo `to-verify`; `markHow(..., 'offline')` para `medium` y `basic` y nulo para otro nivel. (commit 15) renglón nuevo de atención con 0, 1 y N.

---

##### Necesita de archivos compartidos

*   `app/Modules/Attendance/Providers/AttendanceServiceProvider.php` (A1, commit 12): en `boot()`, `Event::listen(OfflineMarkAccepted::class, ReopenOnOfflineMark::class)`.
*   `config/attendance.php` (A1): `'week' => ['block_uncovered_days' => (bool) env('ATTENDANCE_WEEK_BLOCK_UNCOVERED', true)]` (commit 13) y, bajo `offline`, `'review_age_days' => 7` (commit 15).
*   `tests/Feature/Attendance/MarksAppendOnlyTest.php` (A1, commit 14): caso «una revisión no cambia la marca» (sigue igual tras `verified`; el trigger nunca se toca).
*   `tests/Feature/Attendance/TenantIsolationTest.php` (A1, commit 14): `does not read the mark reviews of another company with a query`.
*   `tests/Feature/Attendance/LogPrivacyTest.php` (A1, commit 14): al verificar, al verificar seleccionadas y al marcar incorrecta con nota, ningún log ni bitácora contiene la nota, la hora declarada ni la de RH.
*   `tests/Feature/Demo/DemoDeleteExceptionTest.php` (A1, commit 14): `insertOnlyTables()` suma `'attendance_mark_reviews' => ['attendance_mark_reviews', fn () => MarkReviewFactory::new()->createOne()->id, 'attendance_mark_reviews is insert-only', 'id = id', true]`.
*   `resources/js/components/attendance/week-mark.tsx` (A5b, commit 14): pasa `mark.offline ? 'offline' : null` a `markHow` cuando la marca no está corregida.
*   `tests/js/lib/attendance/week-view.test.ts` (línea 16), `week-text.test.ts` (línea 42) y `week-clock.test.ts` (línea 33) (A5b, commit 13): sus fixtures `WeekDay`/`WeekView` suman `required`, `uncovered` y `uncoveredDays` en el mismo commit que hace obligatorios esos campos en `resources/js/lib/attendance/week.ts`; si no, `types:check` falla (`tests/tsconfig.json` los incluye en modo estricto). Los de marca (`offline`, `review`, `reviewNote`) en el commit 14.
*   `resources/js/lib/audit.ts` y `app/Modules/Tenancy/Support/AuditPages.php` (A6, commit 14): etiqueta `'attendance.offline_mark_reviewed': 'Marca sin conexión verificada'` y clave `'corrected' => 'Corregida'` (las demás claves de ese evento, `employee_id`, `mark_id`, `decision`, ya existen; `attendance.week_reopened` ya tiene etiqueta). Es el único evento de bitácora nuevo de este segmento.
*   `.env.example` y su prueba (A6, commit 13): `ATTENDANCE_WEEK_BLOCK_UNCOVERED=true`.
*   `tests/Feature/FormRequestAuthorizationTest.php` (A6, commit 14): `'verify an offline mark' => ['post', 'tenant.attendance.offline.verify', ['mark' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']]`, `'mark an offline mark incorrect' => ['post', 'tenant.attendance.offline.reject', ['mark' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']]` y `'verify the selected offline marks' => ['post', 'tenant.attendance.offline.verify-many', []]`.
*   `tests/Feature/PaginatedListsTest.php` (A6, commit 14): `'time clock offline marks' => ['attendance/corrections', fn () => route('tenant.attendance.corrections.index', [listsCompany()[0], 'status' => 'offline']), ['offlineMarks']]` y la misma con `'status' => 'not_accepted'`, junto a la de correcciones (línea 110).
*   `lang/es/validation.php` (A6, commit 14) → `attributes`: `'corrected_at' => 'hora correcta'`, `'ids' => 'marcas'` (`note` ya existe).
*   `docs/normas/jornada/criterios.md` (A6, commit 13): 5.2 (confirmar exige días cubiertos: descanso, festivo, antes del alta, ausencia aprobada y días que aún no terminan; cómo convive con «Día abierto»; vale para toda empresa y tiene interruptor; un día sin cubrir se arregla con una corrección o con un permiso que RH registra en Leave), 2.3, 2.6, 2.9, 3.2 y 8.1 (efecto del bloqueo sobre jornada, retardos, horas extra, descanso y conservación); (commit 14) 5.4 (verificar es una inserción; «incorrecta» usa la corrección existente) y 6.1 (banderas del reporte).

##### Dependencias con otros segmentos

*   **A1**: `MarkMethod::OfflineQr` / `OfflineDeclared` con `evidence()` Media y Básica; tabla `attendance_offline_marks` y modelo `AttendanceOfflineMark` con `mark_id`, `outcome`, `reason`, `time_basis`, `signer_type`, `ticket_issued_at`, `window_start`, `declared_at`, `received_at`, `counted_at`, `device_clock_at`, `justification` y `review_flags` (sin columnas de intervalo: el ancho es `received_at − ticket_issued_at`); la regla de tiempo y las banderas (`wide_interval`, `old_window`, `no_position`, `outside_zone`, `declared_adjusted`, con el tope de 72 h y la edad del QR), que este segmento solo lee; los estados de la factory `accepted()`, `qrWindow()`, `rejected(...)`, `flagged(...)`, `deviceKey()`; el evento `OfflineMarkAccepted` despachado **dentro** de la transacción con `MarkLock` tomado; `AttachOfflineTickets::SIGNED` (`'attendance.signed_employee'`), que agrega `{tickets, context}`; la ruta `attendance.device.punch.offline`. A1 quita de sus entradas las líneas de `WeekController.php`, `tests/Feature/Attendance/WeekTest.php` y `tests/Browser/AttendanceWeekTest.php`: están aquí.
*   **A4**: escucha `OfflineMarkReviewed` (las dos decisiones) y `CorrectionResolved` (solo ids) y manda el aviso push.
*   **A5b**: textos de la persona para `blocked = 'uncovered_days'` con `uncoveredDays` («Pida una corrección para agregar la marca que falta, o pida a Recursos Humanos que registre el día como permiso.»); la etiqueta de estado desde `mark.review` y `reviewNote`; el botón de confirmar apagado con la cola no vacía de la persona; usa las props `slug` y `deliverUrl` de Mi semana. Su entrada de `tests/Browser/AttendanceWeekTest.php` pasa a la 94 de este segmento. Este segmento no manda `signer` a la persona.
*   **Documento de diseño (Future Improvements y Alternativas)**: lleva la justificación de faltas como flujo propio y la ausencia declarada dentro de la confirmación (rechazos de Leave anotados: `no_working_days` para festivos o días fuera de la semana laboral, `overlaps`) y el panel de contexto de la verificación; Alternativas lleva «pantalla nueva de verificación» (rechazada por tamaño; la premisa «ya existía una lista de marcas» era falsa).

##### Riesgos y pruebas en teléfonos

| Riesgo | Mitigación |
|---|---|
| El bloqueo `uncovered_days` cambia a toda empresa: hoy una semana con días intermedios vacíos se confirma. | Interruptor `attendance.week.block_uncovered_days` (env `ATTENDANCE_WEEK_BLOCK_UNCOVERED`); apagado, solo registra `attendance.week_uncovered_days_computed` para medir cuántas semanas bloquearía. |
| Horarios desactualizados: un día que ya no se trabaja sigue requerido. | La persona objeta (sigue disponible) o RH corrige el horario; el log con el interruptor apagado deja ver cuántos casos hay. |
| Falta la fecha de alta (`hired_on` nulo): los días antes de entrar cuentan como requeridos en su primera semana. | Sin fecha no hay límite; RH captura la fecha de alta o registra los días como permiso. |
| Leave rechaza el permiso en festivo o fuera de su semana laboral (`no_working_days`) o encimado (`overlaps`). | Los festivos ya están cubiertos; si el horario de Asistencia y la semana laboral de Leave no coinciden, la persona pide una corrección o RH ajusta el horario. |
| Una falta simple sin justificar se registra como permiso aprobado («Permiso especial sin goce») para poder confirmar: ensucia los datos de Leave. | Riesgo aceptado en esta versión; la ausencia declarada dentro de la confirmación queda en Future Improvements. Persona sin horario: sin días sin cubrir. Incapacidades fuera de Leave: RH las registra como permiso. |
| Verificar en bloque esconde una mentira sin bandera. | Solo se seleccionan marcas de una página (25); el filtro «Sin banderas» separa las que no tienen señales; cada marca deja su fila y su bitácora. |
| Una marca sin conexión que RH corrige pasa a evidencia «Corregida». | La bandera «Marcó sin conexión» y el firmante salen de la raíz de la cadena por `corrects_mark_id`. |
| Pruebas existentes que cambian: `WeekTest`, `WeeklyConfirmationTest`, `tests/Browser/AttendanceWeekTest.php` y tres pruebas TS de la semana por el bloqueo; los conteos de `OverviewTest`, `CorrectionQueueTest` y `AuthorityReportPageTest` por funciones nuevas. | Cada una cambia en el commit que cambia el comportamiento y el commit lo dice. |

Pruebas en teléfonos reales (para la lista única de teléfonos del plan):
*   Marca sin QR en modo avión con más de 4 h sin conexión: cuenta a la hora declarada acotada; RH la ve con la bandera, la verifica, y Mi semana muestra «Verificada» (y el push llega, con A4).
*   RH la marca incorrecta con otra hora: el teléfono muestra la nota y la hora corregida (y el push llega).
*   Semana con un miércoles vacío: «Confirmar» apagado con la fecha y el mensaje; RH registra el permiso en Leave y lo aprueba; el teléfono puede confirmar.
*   Semana confirmada, luego se entrega una marca guardada de esos días: al abrir Mi semana está pendiente otra vez.


#### Segmento A3 · WorkerAccess: firma sin caché, llave del dispositivo, rutas de la app instalable y eventos

Verificado contra el repo (fecha 2026-11-20; worktree `pwa-offline-qr-reader-0077c1`). El worktree no tiene `vendor/`: `web-auth/webauthn-lib` 5.3.12 se leyó en el `vendor/` del checkout principal, misma versión que `composer.lock:8321`; quien repita la comprobación corre `composer install` en el worktree. Lo no verificado dice NO VERIFICADO. Las entradas #14, #22, #26 y #27 se eliminaron; los huecos son a propósito porque otras partes citan estos números, y las nuevas van de la #42 en adelante. Cada entrada dice en qué commit del orden global entra («(commit N)»); si un archivo cambia en dos commits, cada subcambio lleva el suyo.

**Términos.** RH = Recursos Humanos. «No fui yo» es el enlace del correo que avisa de una llave de acceso nueva: la persona dice que no la registró ella, y la llave, sus sesiones y sus enlaces caen (`DisownKey`). No es «¿No es usted?» de Marcar. «Llave del dispositivo» es la llave ECDSA que la app guarda en el teléfono (plan B); «llave de acceso» es la passkey.

**Regla de revocación (común a los dos firmantes).** Una marca sin conexión se firma con un boleto que el servidor emitió en `ticketIssuedAt`. Una llave de acceso o del dispositivo **revocada después** de `ticketIssuedAt` todavía verifica, y el resultado trae su `revokedAt`; una revocada **en** ese instante o antes da null. Attendance responde `signature_invalid` (sin fila) si `declared_at > revokedAt`. Así, reemitir o «No fui yo» no pierden las marcas que el teléfono firmó antes de la revocación, y nadie suma marcas declaradas después de ella. «Salir» no revoca la llave del dispositivo; solo la revocan «reemitir» (la revocación de RH) y «No fui yo». WorkerAccess no comprueba si la persona sigue activa: eso es el motivo `person_inactive` de Attendance.

##### Firma de la llave de acceso sin caché ni contador (commit 8)

###### 1. `app/Modules/WorkerAccess/Contracts/WorkerSignatures.php` (Modify) (commit 8)
*   **Type / Scope**: Contrato público.
*   **Proposed Changes**: agregar
    `public function verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): ?SignedBy;`
    PHPDoc: `$expected` son los **32 bytes crudos** del SHA-256 de la cadena canónica v1 en UTF-8 (`hash('sha256', $canonical, true)`, nunca hex); `@param array<mixed> $response` es el PublicKeyCredential del navegador; una llave revocada después de `$ticketIssuedAt` todavía verifica y `SignedBy::$revokedAt` lo dice. Devuelve null ante cualquier fallo (una sola respuesta). No consume caché, no lee ni escribe el contador, no escribe nada en la base (ni `sign_count` ni `last_used_at`), así que verificar dos veces la misma firma da lo mismo (la idempotencia de la entrega depende de esto). La repetición la evita el boleto de Attendance. No comprueba si la persona sigue activa.

###### 2. `app/Modules/WorkerAccess/Support/CeremonySignatures.php` (Modify) (commit 8)
*   **Type / Scope**: Implementación del contrato.
*   **Proposed Changes**: `verifyChallenge()` llama a `$this->ceremony->verifyChallenge($expected, $response, $ticketIssuedAt)` y regresa null ante `InvalidCredential` (igual que `verify()`, verificado en el archivo).

###### 3. `app/Modules/WorkerAccess/Support/WebAuthnCeremony.php` (Modify) (commit 8)
*   **Type / Scope**: Ceremonia WebAuthn.
*   **Proposed Changes**:
    1.  Método nuevo `public function verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): SignedBy` (`@throws InvalidCredential`). Pasos:
        1.  `credential($response)`; la respuesta debe ser `AuthenticatorAssertionResponse`.
        2.  Busca `WorkerCredential` por `credential_id` con `where(fn ($q) => $q->whereNull('revoked_at')->orWhere('revoked_at', '>', $ticketIssuedAt))`, **sin** `lockForUpdate`, **sin** `consume()` y sin `Roster`.
        3.  `self::verified(fn () => AuthenticatorAssertionResponseValidator::create($this->steps(countless: true)->requestCeremony())->check($this->record($device), $assertion, $this->requestOptions($expected), $this->rpId(), null))`.
        4.  `new SignedBy($device->id, $device->employee_id, $device->revoked_at)`. Sin `update()` ni transacción.
    2.  Por qué así: `CeremonyStepManagerFactory::requestCeremony()` (vendor `src/CeremonyStep/CeremonyStepManagerFactory.php:118`) arma 14 pasos, entre ellos `CheckChallenge`, `CheckAllowedOrigins` (por `setAllowedOrigins`, que ya usa `steps()`), `CheckRelyingPartyIdIdHash`, `CheckUserVerification`, `CheckSignature`, `CheckBackupEligibilityIsUnchanged` y `CheckCounter` (línea 141, con `$this->counterChecker`). **El único paso que hay que quitar es `CheckCounter`.** No se arma una lista a mano (se rompería en silencio con una versión nueva): se le da a la fábrica un verificador de contador que no hace nada con `setCounterChecker` (método público, línea 59). Así el origen (`fortify.passkeys.allowed_origins`), el RP, la verificación de la persona (`requestOptions` pide `required`), `CheckChallenge` contra `$expected` y el indicador de respaldo conocido (`record()` pasa `backupEligible`/`backupStatus`) se comprueban igual que en `verifyAssertion`. `CheckCounter` también asigna `$credentialRecord->counter`, pero ese registro solo vive en memoria.
    3.  `steps()` cambia a `private function steps(bool $countless = false): CeremonyStepManagerFactory`; con `true` llama a `setCounterChecker(new IgnoreCounter)`.
    4.  El PHPDoc de la clase suma: «`verifyChallenge` verifica un reto que armó quien llama (la marca sin conexión): ni caché ni contador, nada se escribe; una llave revocada después del boleto todavía verifica y se devuelve su fecha de revocación».

###### 4. `app/Modules/WorkerAccess/Support/IgnoreCounter.php` (Create) (commit 8)
*   **Type / Scope**: Soporte (final, readonly).
*   **Proposed Changes**: `final readonly class IgnoreCounter implements \Webauthn\Counter\CounterChecker { public function check(CredentialRecord $credentialRecord, int $currentCounter): void {} }` (firma de la interfaz verificada en vendor `src/Counter/CounterChecker.php`). PHPDoc: las llaves sincronizadas reportan 0, y una entrega fuera de orden rompería el contador de las atadas al dispositivo; la verificación en línea (`verify`) sigue usando `ThrowExceptionIfInvalid` (el valor por omisión de la fábrica, línea 51).

###### 42. `app/Modules/WorkerAccess/Data/SignedBy.php` (Modify) (commit 8)
*   **Type / Scope**: DTO público (hoy `credentialId` y `employeeId`, verificado).
*   **Proposed Changes**: parámetro final nuevo `public ?CarbonImmutable $revokedAt = null`. PHPDoc: «cuándo se revocó la llave, si se revocó después del boleto (solo `verifyChallenge`); `verify()` en línea nunca lo llena». Los usos actuales (`new SignedBy($device->id, $device->employee_id)` en `verifyAssertion`) no cambian.

##### Llave del dispositivo y pase sin conexión (commit 10)

###### 5. `database/migrations/2027_01_30_200000_create_worker_device_keys_table.php` (Create) (commit 10)
*   **Type / Scope**: Migración (aditiva, con `down()` como las del módulo).
*   **Proposed Changes**: `worker_device_keys`: `id` uuid pk; `tenant_id` foreignUuid → tenants `restrictOnDelete`; `employee_id` uuid; FK compuesta `(employee_id, tenant_id)` → `employees(id, tenant_id)` `restrictOnDelete` (como `worker_access_states`, verificado en `2027_01_21_110000_create_worker_access_states_table.php`); `public_key` text (SPKI DER en base64url) con `CHECK (char_length(public_key) <= 200)`; `label` string(60); `last_used_at` timestampTz(6) nulo; `revoked_at` timestampTz(6) nulo; `revoked_reason` string(20) nulo con `CHECK (revoked_reason IN ('reissued', 'not_me', 'replaced', 'left_company'))` y `CHECK ((revoked_at IS NULL) = (revoked_reason IS NULL))`; `timestampsTz`. Índice parcial `(tenant_id, employee_id) WHERE revoked_at IS NULL` (sentencia cruda). La fecha (`2027_01_30_200000`) va después de `2027_01_29_100000_make_company_role_labels_unique.php` (la última de main, verificado el 2026-11-24), después de `attendance_offline_marks` y antes de `attendance_mark_reviews` y `push_subscriptions`. Que `attendance_mark_reviews` entre en un commit anterior con fecha posterior no importa: Laravel corre toda migración pendiente aunque su fecha sea anterior a una ya corrida, y en una base nueva corren por fecha; ninguna de las dos depende de la otra.

###### 6. `app/Modules/WorkerAccess/Models/WorkerDeviceKey.php` (Create) (commit 10)
*   **Type / Scope**: Modelo Eloquent (final).
*   **Proposed Changes**: `use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids`; `#[Fillable([...])]`, `#[Hidden(['public_key'])]`, `#[UseFactory(WorkerDeviceKeyFactory::class)]`; `@property` de cada columna; casts `immutable_datetime` (como `WorkerCredential`). Método estático `revokeAllOf(string $employeeId, string $reason): int`: UPDATE condicional `whereNull('revoked_at')` que pone `revoked_at = now` y el motivo (como hace `ReissueAccess` con las llaves de acceso). `TenantScopedModelsTest` lo descubre solo: recorre los modelos cuya tabla tiene `tenant_id` (verificado, líneas 94-98), sin entrada nueva.

###### 7. `database/factories/WorkerAccess/WorkerDeviceKeyFactory.php` (Create) (commit 10)
*   **Type / Scope**: Factory.
*   **Proposed Changes**: llave P-256 real (`openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC])`, la pública exportada como DER en base64url), `label` «Este teléfono»; estado `revoked(string $reason = 'reissued', ?CarbonImmutable $at = null)`. Las pruebas que firman usan el ayudante #30, no la factory.

###### 8. `app/Modules/WorkerAccess/Support/DeviceKeyCrypto.php` (Create) (commit 10)
*   **Type / Scope**: Soporte puro (final, métodos estáticos), sin base de datos.
*   **Proposed Changes**:
    *   `publicKeyFrom(string $spkiBase64Url): ?OpenSSLAsymmetricKey`: decodifica el base64url, lo envuelve en PEM (`-----BEGIN PUBLIC KEY-----`) y llama a `openssl_pkey_get_public`; si `openssl_pkey_get_details` no dice `type === OPENSSL_KEYTYPE_EC` con `ec.curve_name === 'prime256v1'`, regresa null.
    *   `derFromRaw(string $rs): ?string`: si no son exactamente 64 bytes regresa null; si no, separa r y s (32 bytes cada uno), les quita los ceros a la izquierda, agrega un `0x00` cuando el bit alto está prendido y arma `SEQUENCE { INTEGER r, INTEGER s }`.
    *   `verifies(OpenSSLAsymmetricKey $key, string $message, string $rsBase64Url): bool`: `openssl_verify($message, $der, $key, OPENSSL_ALGO_SHA256) === 1`. **Mensaje firmado:** `$message` son los 32 bytes crudos (el reto v1 de una marca, o el reto de posesión del alta). WebCrypto `sign({name:'ECDSA', hash:'SHA-256'}, key, bytes)` y `openssl_verify` aplican cada uno su SHA-256 sobre esos mismos bytes, así que coinciden.

###### 9. `app/Modules/WorkerAccess/Support/OfflinePasses.php` (Create) (commit 10)
*   **Type / Scope**: Soporte (final readonly), sin tabla. Clase propia, sin firmante compartido con los boletos: el repo firma con `hash_hmac` en línea y prefijo de dominio en cada módulo.
*   **Proposed Changes**: el pase es `v1.{payload}.{hmac}`. `payload` es el base64url de un JSON `{"t":tenant_id,"e":employee_id,"k":device_key_id,"i":pass_id (uuid v4 aleatorio),"n":issued_unix,"x":expires_unix}`. `hmac` es el base64url de `hash_hmac('sha256', "worker-access-offline-pass|v1.{payload}", $key, true)`. Mide unos 318 caracteres (cuenta con uuid de 36 y fechas de 10 dígitos).
    *   `issue(WorkerDeviceKey $key): array{pass: string, passId: string, expiresAt: CarbonImmutable}`, con `x = n + worker_access.pass_hours·3600`.
    *   `read(string $pass): ?array{tenant: string, employee: string, key: string, id: string, issuedAt: CarbonImmutable, expiresAt: CarbonImmutable}`: largo ≤ 512; compara con `hash_equals` contra cada llave. Null si el formato no sirve, si es otra versión o si el JSON no trae los seis campos con su tipo. **No** compara con «ahora»: la vigencia la decide Attendance.
    *   Llaves: `config('app.key')` y después cada una de `config('app.previous_keys')` (verificado en `config/app.php:142`), el mismo patrón que `app/Modules/Disclosure/Models/PageLink.php:61-63` y que los boletos de Attendance. Rotar `APP_KEY` dejando la vieja en `APP_PREVIOUS_KEYS` conserva los pases pendientes; sin variable de entorno propia.

###### 10. `app/Modules/WorkerAccess/Contracts/DeviceKeySignatures.php` (Create) (commit 10)
*   **Type / Scope**: Contrato público (lo usa Attendance).
*   **Proposed Changes**:
    ```
    interface DeviceKeySignatures
    {
        /** The id of `$pass` when its HMAC verifies and it is of the current company; null otherwise. Ignores expiry and
         *  the key's revocation: Attendance needs it to rebuild the challenge before verifying. */
        public function passId(string $pass): ?string;

        /** Whose device key signed `$expected` (the 32 raw bytes of the challenge) holding `$pass`; a key revoked after
         *  `$ticketIssuedAt` still verifies and carries its `revokedAt`. Null for any answer that does not verify (one
         *  answer for every cause). Reads only: no cache, nothing written. */
        public function verify(string $expected, string $signature, string $pass, CarbonImmutable $ticketIssuedAt): ?DeviceSigned;
    }
    ```
    `$signature`: r‖s de 64 bytes en base64url, como lo da WebCrypto.

###### 11. `app/Modules/WorkerAccess/Data/DeviceSigned.php` (Create) (commit 10)
*   **Type / Scope**: DTO readonly público.
*   **Proposed Changes**: `__construct(public string $deviceKeyId, public string $employeeId, public string $passId, public CarbonImmutable $passIssuedAt, public CarbonImmutable $passExpiresAt, public ?CarbonImmutable $revokedAt = null)`. Attendance comprueba, con `declared_at` (el único tiempo que existe en ese paso): `passId` igual al `pass_id` del reto, `declared_at ≤ passExpiresAt` y `passIssuedAt ≤ received_at` (si no, `pass_expired`); y `declared_at ≤ revokedAt` cuando no es null (si no, `signature_invalid`).

###### 12. `app/Modules/WorkerAccess/Support/CompanyDeviceKeySignatures.php` (Create) (commit 10)
*   **Type / Scope**: Implementación (final readonly). Depende de `CurrentTenant`.
*   **Proposed Changes**:
    *   `passId($pass)`: `OfflinePasses::read($pass)`; si es null o su `tenant` no es la empresa actual, null; si no, su `id`.
    *   `verify(...)`: `OfflinePasses::read($pass)` y comprueba que `tenant` sea la empresa actual. Busca `WorkerDeviceKey::query()->whereKey($p['key'])->where(fn ($q) => $q->whereNull('revoked_at')->orWhere('revoked_at', '>', $ticketIssuedAt))->first()` (con el alcance de la empresa) y exige `employee_id === $p['employee']`. Después `DeviceKeyCrypto::publicKeyFrom` y `verifies($key, $expected, $signature)`. Si todo pasa, `DeviceSigned` con los datos del pase y el `revoked_at` de la fila; si algo falla, null. No escribe nada, ni logs (igual que la ceremonia).

###### 13. `app/Modules/WorkerAccess/Actions/RegisterDeviceKey.php` (Create) (commit 10)
*   **Type / Scope**: Acción.
*   **Proposed Changes**: `handle(string $employeeId, string $publicKey, string $label): WorkerDeviceKey`. Corre en `DB::transaction` con `pg_advisory_xact_lock(hashtext('worker_device_keys:{id}'))`, como `RegisterKey` (verificado: `worker_credentials:{$employeeId}`, línea 43). Si la persona ya tiene `worker_access.device_keys_max` llaves activas, revoca con `replaced` la más vieja: la de `coalesce(last_used_at, created_at)` menor. Después inserta la nueva con `last_used_at = now`. Nunca responde 409. La prueba de posesión y la llave pública inválida se rechazan antes, en el controlador. Escribe la bitácora `worker_access.device_key_registered` (con el modelo, `employee_id` y, si reemplazó una, `replaced_id`) y el log con el mismo nombre (`device_key_id`, `employee_id`, `replaced_id`), nunca la llave pública.

###### 15. `app/Modules/WorkerAccess/Http/Controllers/DeviceKeyController.php` (Create) (commit 10)
*   **Type / Scope**: Controlador (final readonly, delgado). Solo JSON.
*   **Proposed Changes**:
    *   `options(Request)`: crea un reto aleatorio de 32 bytes y lo guarda en caché en `worker-access-device:{tenant}:{sha256(session id)}:{sha256(reto)}` por `worker_access.webauthn.challenge_seconds`. Responde 200 `{challenge}` en base64url con `Cache-Control: no-store, private`.
    *   `store(RegisterDeviceKeyRequest)`: toma el reto con `Cache::pull` (un solo uso). La firma de posesión es sobre los **32 bytes crudos** del reto decodificado (en la app, `signBytes`). Si el reto no estaba, si `publicKeyFrom` regresa null o si la firma no verifica, responde 422 «No pudimos preparar este teléfono. Intente de nuevo.» y deja el log `worker_access.device_key_refused` (sin datos de la llave). Si pasa, corre `RegisterDeviceKey`, emite el pase y responde **201** `{deviceKeyId, pass, passId, passExpiresAt}` (`passExpiresAt` en ISO-8601 UTC).
    *   `pass(RenewPassRequest)`: busca la llave `device_key_id` activa de la persona de la sesión. Si no hay, responde 404 y la app borra su llave local. Si hay, actualiza `last_used_at` y responde 200 con la **misma forma** `{deviceKeyId, pass, passId, passExpiresAt}`. Sin prueba reciente (no la pide) y sin log (solo cambia `last_used_at`).
    *   Sin prueba reciente, `options` y `store` responden 403 `{message, proofRequired: true}`: lo hace `RequireRecentProof` con todo cliente que pide JSON (verificado en el middleware). Sin sesión, las tres dan 401 (`RequireWorkerSession.php:32`). No hay flujo con Inertia ni redirección.
    *   La persona sale siempre de `WorkerSession::current()`, nunca de lo que mande el cliente.

###### 16. `app/Modules/WorkerAccess/Http/Requests/RegisterDeviceKeyRequest.php` (Create) (commit 10)
*   **Type / Scope**: FormRequest.
*   **Proposed Changes**: `authorize()`: hay `WorkerSession` (sin sesión, 403 antes de validar, como pide `FormRequestAuthorizationTest`; en la ruta real el 401 de `RequireWorkerSession` llega antes). Reglas: `public_key` required, string, max:200, regex base64url; `challenge` required, string, max:64, base64url; `signature` required, string, max:100, base64url; `label` required, string, max:60, con la misma regex que `RegisterKeyRequest`.

###### 17. `app/Modules/WorkerAccess/Http/Requests/RenewPassRequest.php` (Create) (commit 10)
*   **Type / Scope**: FormRequest.
*   **Proposed Changes**: `authorize()`: hay sesión. Reglas: `device_key_id` required, uuid.

###### 18. `routes/worker-access.php` (Modify)
*   **Type / Scope**: Rutas. Dueño también de las rutas de la app instalable (controlador `InstallableAppController`, de A6).
*   **Proposed Changes**:
    1.  **App instalable sin sesión.** Un grupo nuevo, al final del archivo, con el mismo `Route::withoutMiddleware([...])` que `routes/complaints-public.php:31-42` (`EncryptCookies`, `AddQueuedCookiesToResponse`, `StartSession`, `ShareErrorsFromSession`, `PreventRequestForgery`, `EndRevokedImpersonation`, `AssignRequestContext`, `ForbidWhileImpersonating`, `HandleAppearance`, `HandleInertiaRequests`, verificados ahí) y `->middleware(['throttle:worker-enter', ResolveWorkerTenant::class])` (300 por minuto por IP: holgado para la revisión del worker en cada navegación). Comentario de una línea: «La app instalable: manifest, service worker y cascarón; sin sesión ni cookie (con `SESSION_DRIVER=database` cada petición sin cookie abriría una fila en `sessions`, y los teléfonos instalados revisan el worker en cada navegación); nada de nadie». Rutas, todas GET:
        *   `me/{tenant:slug}/manifest.webmanifest` → `[InstallableAppController::class, 'manifest']`, `worker.app.manifest` **(commit 3)**.
        *   `me/{tenant:slug}/sw.js` → `'serviceWorker'`, `worker.app.sw` **(commit 4)**.
        *   `me/{tenant:slug}/offline` → `'offline'`, `worker.app.offline` **(commit 4)**.
        `ResolveWorkerTenant` no usa la sesión (verificado: solo `Companies::enterBySlug`).
    2.  **Llave del dispositivo (commit 10).** Grupo después del de `proof`, con `['throttle:worker-device-key', ResolveWorkerTenant::class, RequireWorkerSession::class]` y prefijo `me/{tenant:slug}/device-key`:
        *   `POST options` → `[DeviceKeyController::class, 'options']` con `->middleware('worker.proof')`, `worker.device-key.options`.
        *   `POST /` → `store` con `->middleware('worker.proof')`, `worker.device-key.store`.
        *   `POST pass` → `pass`, sin prueba reciente, `worker.device-key.pass`.
        Con un comentario que lo explique. Las tres llevan sesión y CSRF normal (`X-XSRF-TOKEN`), cumplen las dos reglas de `RouteSecurityTest` sobre rutas con sesión (`me/{tenant}/…` con `ResolveWorkerTenant` antes de `RequireWorkerSession`; `RequireRecentProof` después de la sesión) y no van en la lista pública.

###### 19. `app/Modules/WorkerAccess/Providers/WorkerAccessServiceProvider.php` (Modify) (commit 10)
*   **Type / Scope**: Proveedor.
*   **Proposed Changes**: en `register()`, `bind(DeviceKeySignatures::class, CompanyDeviceKeySignatures::class)`. En `boot()`, el limitador `worker-device-key`: 30 por minuto por IP y 20 por minuto por sesión (el hash del id de sesión nombra el contador, como `worker-proof`). La renovación del pase se llama al abrir `/me` con sesión; el límite sobra para eso.

###### 20. `config/worker_access.php` (Modify)
*   **Type / Scope**: Configuración.
*   **Proposed Changes**: llaves nuevas, comentadas como las demás, junto a `max_keys` (que no se toca):
    ```
    'device_keys_max' => (int) env('WORKER_ACCESS_DEVICE_KEYS_MAX', 3),        // (commit 10)
    'pass_hours' => (int) env('WORKER_ACCESS_OFFLINE_PASS_HOURS', 24),          // (commit 10)
    'pwa_sw_kill' => (bool) env('PWA_SW_KILL', false),                          // (commit 4)
    ```
    `pwa_sw_kill`: con `true`, `InstallableAppController` sirve el worker de apagado y la página pone el meta `pwa-sw-kill`. Lo lee solo la app instalable; no hay `config/pwa.php`.

##### Eventos públicos y dónde se disparan (commit 16)

`WorkerAccess/Events` hoy no existe (verificado). `WorkerAccessRevoked` es su único evento; entra con el módulo `Notifications`, que es quien lo escucha.

###### 21. `app/Modules/WorkerAccess/Events/WorkerAccessRevoked.php` (Create) (commit 16)
*   **Type / Scope**: Evento público (final readonly).
*   **Proposed Changes**: `__construct(public string $employeeId, public string $reason, public ?string $credentialId = null)`. `reason` ∈ `reissued`, `revoked_by_hr`, `not_me`; `credentialId` es null cuando cae todo el acceso de la persona. Solo ids. Se dispara **después** del commit de la transacción y solo si algo cambió. Lo escucha Notifications para borrar las suscripciones push de la persona. «Salir» no dispara ningún evento: el cliente da de baja su suscripción con `DELETE push`.

###### 23. `app/Modules/WorkerAccess/Actions/RevokeCredential.php` (Modify) (commit 16)
*   **Proposed Changes**: dentro de `if ($revoked)`, después del log: `event(new WorkerAccessRevoked($credential->employee_id, 'revoked_by_hr', $credential->id))`. No toca las llaves del dispositivo: no hay forma de ligarlas a una llave de acceso; RH las revoca con «reemitir».

###### 24. `app/Modules/WorkerAccess/Actions/ReissueAccess.php` (Modify)
*   **Proposed Changes**:
    *   **(commit 10)** Dentro del closure de la transacción, `WorkerDeviceKey::revokeAllOf($person->id, 'reissued')`; su número entra a la bitácora y al log como `device_keys_revoked`. Si la emisión del enlace falla, la transacción lo deshace todo.
    *   **(commit 16)** Después del log, `event(new WorkerAccessRevoked($person->id, 'reissued'))`, siempre que la transacción haya terminado, porque aunque no haya llaves las sesiones sí terminan (`moveValidAfter`).

###### 25. `app/Modules/WorkerAccess/Actions/DisownKey.php` (Modify)
*   **Proposed Changes**:
    *   **(commit 10)** Dentro de la transacción, después de `revokePending`, `WorkerDeviceKey::revokeAllOf($credential->employee_id, 'not_me')`: un teléfono preparado en la sesión del intruso deja de firmar marcas nuevas.
    *   **(commit 16)** Después del log, `event(new WorkerAccessRevoked($credential->employee_id, 'not_me', $credential->id))`.
    *   Los dos quedan detrás de `if (! $disowned) return;`.

###### 25b. `app/Modules/WorkerAccess/Listeners/RevokeOnDeactivation.php` (Modify) — agregado en la conciliación con main (2026-11-24)
*   **Contexto**: en main (PR #159 y anteriores) esta clase, que ya existe, revoca las llaves de acceso de quien deja la empresa con el motivo `left_company`, revoca sus enlaces pendientes y mueve `valid_after` (la sesión termina). Se ejecuta dentro de la transacción de la baja (`EmployeeDeactivated`) y su log sale con `DB::afterCommit`.
*   **Proposed Changes**:
    *   **(commit 10)** Dentro de esa misma transacción, `WorkerDeviceKey::revokeAllOf($event->employeeId, 'left_company')`: quien dejó la empresa no puede firmar marcas nuevas con la llave de su teléfono. Su número entra a la bitácora y al log existentes `worker_access.access_revoked_on_leaving` como `device_keys_revoked`. La salida temprana «nada de suyo que revocar» (`$revoked === 0 && ! $links`) también cuenta las llaves del dispositivo, para no saltarse la bitácora de quien solo tenía una.
    *   **(commit 16)** Después de confirmar (dentro del mismo `DB::afterCommit`), `event(new WorkerAccessRevoked($event->employeeId, 'left_company'))`, para que el módulo `Notifications` borre sus suscripciones push. Se despacha aunque no tuviera llaves: las sesiones sí terminan.
    *   Una marca sin conexión que la persona firmó antes de irse y que llega después se rechaza con `person_inactive` (Attendance, entrada 1 de este segmento): queda a la vista de RH como «No aceptada» y la persona ya no tiene sesión para pedir una corrección. RH la ve y puede registrarla con su corrección directa (criterio 5.4). Es el costo aceptado de no filtrar por persona activa en WorkerAccess.

##### Inicio de la persona

###### 28. `app/Modules/WorkerAccess/Http/Controllers/HomeController.php` (Modify) (commit 10)
*   **Proposed Changes**: el constructor recibe además `ProofSteps` (Support del módulo; `for(OpenSession $session, string $slug)` verificado en `ProofSteps.php:21`). En la pantalla con sesión (`worker-access/home`, línea 38 y siguientes) se agregan dos props:
    *   `slug` = `$tenant`.
    *   `deviceKey` = `{optionsUrl: route('worker.device-key.options', $tenant), storeUrl: route('worker.device-key.store', $tenant), proof: $steps->for($session, $tenant)}` si `Companies::current()->allows('attendance')` (método verificado en `Tenancy\Data\CompanySummary`), si no `null`: la llave del dispositivo solo sirve para marcar.
    La pantalla de entrada sin sesión no cambia. Las secciones (`array_map` de la línea 41) tampoco.

##### Documentación

###### 29. `app/Modules/WorkerAccess/README.md` (Modify)
*   **Proposed Changes**:
    *   **(commit 4)** Sección nueva «App instalable»: manifest, service worker (`Service-Worker-Allowed: /me/{slug}`) y cascarón bajo `/me/{slug}`, sin sesión ni cookie y por qué; `PWA_SW_KILL` (`worker_access.pwa_sw_kill`) sirve un worker que se desregistra y borra sus cachés. **(commit 3)** el párrafo del manifest entra primero.
    *   **(commit 10)** «Tablas»: `worker_device_keys` y su `revoked_reason` (`reissued`, `not_me`, `replaced`, `left_company`). Sección «Llave del dispositivo y pase sin conexión»: registro solo JSON con sesión y prueba reciente, prueba de posesión sobre los bytes del reto, máximo 3 con reemplazo de la menos usada, pase HMAC de 24 h ligado a la llave y la persona (rota con `APP_KEY`/`APP_PREVIOUS_KEYS`), renovación con sesión al abrir `/me` (quien usa el plan B entra a `/me` con conexión al menos una vez al día); «reemitir», «No fui yo» y la baja de la persona (`left_company`) la revocan, «Salir» no; la regla de revocación contra el boleto. «Panel»: la fila de reemisión cuenta también las llaves del dispositivo. «Reglas»: ni la llave pública, ni el pase, ni la firma llegan a logs, bitácora o props.
    *   **(commit 8)** «Contratos»: `verifyChallenge` y `SignedBy::$revokedAt`. **(commit 10)** `DeviceKeySignatures` (`passId`, `verify`). **(commit 16)** el evento `WorkerAccessRevoked`.

##### Pruebas

###### 30. `tests/Support/SoftDeviceKey.php` (Create) (commit 10)
*   **Type / Scope**: Ayudante de prueba (como `SoftAuthenticator`, que ya existe en `tests/Support`).
*   **Proposed Changes**: `final class SoftDeviceKey` con una llave EC P-256 en memoria. `publicKey(): string` da el SPKI en base64url. `sign(string $bytes): string` firma con `openssl_sign(..., OPENSSL_ALGO_SHA256)`, convierte el DER a r‖s de 64 bytes y lo devuelve en base64url, para imitar a WebCrypto.

###### 31. `tests/Fixtures/worker-access/device-key-vectors.json` (Create) (commit 10)
*   **Type / Scope**: Vectores compartidos PHP/JS (la carpeta `tests/Fixtures/worker-access/` es nueva; hoy existe `tests/Fixtures/attendance/`).
*   **Proposed Changes**: 3 casos fijos con `spki` (base64url), `message` (hex de 32 bytes), `signature` (r‖s base64url) y `valid`: un r con el bit alto prendido, un s con ceros a la izquierda y una firma alterada. La app los lee en Vitest (`crypto.subtle.verify`); PHP, en #32.

###### 32. `tests/Unit/WorkerAccess/DeviceKeyCryptoTest.php` (Create) (commit 10)
*   **Casos**: las firmas válidas de los vectores verifican y la alterada no; `derFromRaw` con 63 y 65 bytes regresa null y con r de bit alto agrega `0x00` (se compara el DER esperado); una llave RSA y una EC P-384 no pasan `publicKeyFrom`; un SPKI basura tampoco; un mensaje distinto no verifica; ida y vuelta con `SoftDeviceKey`. `mutates(DeviceKeyCrypto::class)`. (La carpeta `tests/Unit/WorkerAccess` es nueva.)

###### 33. `tests/Unit/WorkerAccess/OfflinePassesTest.php` (Create) (commit 10)
*   **Casos**: emitir y leer devuelve los seis campos y el `passId` emitido; `expiresAt` es exactamente `issuedAt` + 24 h y respeta `worker_access.pass_hours`; un vector fijo (llave, payload y HMAC conocidos) asegura el formato; el byte cambiado en el payload o en el HMAC, el prefijo `v2`, menos de 3 partes, un JSON sin un campo o un pase de 513 caracteres dan null; un pase firmado con la `APP_KEY` vieja se lee tras rotar si está en `app.previous_keys`, y no si ya no está. `mutates(OfflinePasses::class)`.

###### 34. `tests/Feature/WorkerAccess/WorkerSignaturesTest.php` (Modify) (commit 8)
*   **Casos nuevos de `verifyChallenge`**:
    *   Verifica un reto propio (`SoftAuthenticator::get` con `challenge:` igual a los bytes) y dice qué llave y qué persona firmaron, con `revokedAt` null.
    *   La misma firma verifica dos veces (sin caché).
    *   Un reto distinto da null; una llave de otra empresa da null.
    *   **Revocación contra el boleto**: revocada 1 s después de `ticketIssuedAt` verifica y trae ese `revokedAt`; revocada 1 s antes da null; revocada exactamente en `ticketIssuedAt` da null (límite exacto).
    *   Una persona dada de baja **sí** verifica (`person_inactive` es de Attendance).
    *   No escribe nada: `sign_count`, `backup_state` y `last_used_at` quedan igual.
    *   `verify()` en línea sigue devolviendo `revokedAt` null.

###### 35. `tests/Feature/WorkerAccess/WebAuthnCeremonyTest.php` (Modify) (commit 8)
*   **Casos nuevos en `describe('verifyChallenge')`** (junto a `describe('registration')` y `describe('assertion')`, verificados):
    *   Con una llave que cuenta, firmas con contador 5 y luego 3 (fuera de orden) verifican las dos y el contador guardado no se mueve.
    *   Una llave sincronizada (contador 0) verifica.
    *   El origen, el RP o la falta de verificación de la persona se rechazan (mismo dataset que `assertion`).
    *   Una llave que cambia `backupEligible` se rechaza.
    *   Una firma alterada se rechaza; algo que no es una firma da la misma respuesta.
    *   `verify()` en línea sigue rechazando un contador menor después de un `verifyChallenge`.
    *   El caso que ya existe, «writes nothing to the log» (línea 285), se amplía con `verifyChallenge`.

###### 36. `tests/Feature/WorkerAccess/DeviceKeyTest.php` (Create) (commit 10)
*   **Casos**:
    *   **Registro feliz**: entrar con llave (cuenta como prueba), pedir `options`, firmar los 32 bytes del reto con `SoftDeviceKey` y guardar. Responde 201 `{deviceKeyId, pass, passId, passExpiresAt}`; la fila queda activa con `last_used_at`, y `OfflinePasses::read` del pase da ese `passId`.
    *   **Sin sesión**: 401 en las tres rutas. **Sin prueba reciente** (sesión abierta con enlace): `options` y `store` dan 403 con `proofRequired: true` en JSON; `pass` sí responde.
    *   **Reto**: uno usado dos veces da 422 la segunda; uno de otra sesión, 422; uno vencido (60 s y 1 s con `travelTo`), 422; la firma del texto base64url del reto (no de sus bytes) da 422.
    *   **Posesión**: una firma con otra llave da 422; una llave pública que no es P-256 da 422; el formato inválido da 422 por validación; una etiqueta de 61 caracteres da 422.
    *   **Límite**: con 3 activas, la 4.ª responde 201 (nunca 409), revoca con `replaced` la de `coalesce(last_used_at, created_at)` menor y quedan 3 activas; con 2 activas la 3.ª no reemplaza ninguna (límite exacto); dos registros simultáneos no dejan 4 (candado).
    *   **Pase**: la renovación con sesión responde 200 con la misma forma que el registro, un `passId` nuevo, y mueve `last_used_at`; con una llave revocada, de otra persona o de otra empresa da 404.
    *   **Bitácora y log**: el registro escribe `worker_access.device_key_registered` en los dos, con `replaced_id` cuando reemplazó.

###### 37. `tests/Feature/WorkerAccess/DeviceKeySignaturesTest.php` (Create) (commit 10)
*   **Casos**:
    *   **Feliz**: `verify` regresa `DeviceSigned` con `deviceKeyId`, `employeeId`, `passId`, `passIssuedAt` y `passExpiresAt` del pase, y `revokedAt` null.
    *   **`passId`**: da el id de un pase propio, también vencido o de una llave revocada; null para un pase alterado, con un formato inválido o de la empresa B leído en A.
    *   **Revocación contra el boleto**: una llave revocada (cada motivo) 1 s después de `ticketIssuedAt` verifica y trae su `revokedAt`; 1 s antes o en el mismo instante da null.
    *   **Una sola respuesta (null)**: la firma de otro mensaje; la firma de otra llave; un pase de otra llave de la misma persona; un pase de otra persona; un pase alterado; un pase de la empresa B leído en A; una firma con formato inválido.
    *   **Vencido**: un pase vencido **sí** verifica y trae su `passExpiresAt` (la vigencia la decide Attendance). Una persona dada de baja también verifica.
    *   **Solo lectura**: no escribe nada (ni `last_used_at` ni logs).

###### 38. `tests/Feature/WorkerAccess/RevokeCredentialTest.php`, `ReissueTest.php`, `NotMeTest.php`, `SessionTest.php` (Modify)
*   **Casos**:
    *   **(commit 10)** `ReissueTest`: las llaves del dispositivo de la persona quedan revocadas con `reissued` y las de otra persona no; la bitácora y el log traen `device_keys_revoked`; si falla la emisión del enlace, siguen activas. `NotMeTest`: quedan revocadas con `not_me`. `RevokeCredentialTest`: las llaves del dispositivo siguen activas. `SessionTest`: «Salir» no revoca la llave del dispositivo (la fila sigue activa). `RevokeOnDeactivationTest` (existente en `tests/Feature/WorkerAccess/`, ampliada): al dar de baja a la persona sus llaves del dispositivo quedan revocadas con `left_company` y las de otra persona no; quien solo tenía una llave del dispositivo deja bitácora; si la baja se deshace (transacción revertida) siguen activas.
    *   **(commit 16)** Con `Event::fake([WorkerAccessRevoked::class])`: `RevokeCredentialTest`, una vez con `revoked_by_hr` y el id, y nada al revocar de nuevo; `ReissueTest`, con `reissued` y `credentialId` null, y nada si falla la emisión del enlace; `NotMeTest`, con `not_me` y el id, y nada al confirmar otra vez; `SessionTest`, «Salir» no despacha ningún evento de WorkerAccess; `RevokeOnDeactivationTest`, una vez con `left_company` y `credentialId` nulo después de confirmar la baja, y nada si la baja se revierte.

###### 39. `tests/Feature/WorkerAccess/HomeTest.php` (Modify)
*   **Casos**:
    *   **(commit 2)** La sección de prueba de asistencia de las líneas 59 y 64 cambia su `href` de `/a/acme` a `/me/acme/punch` (dato de prueba; el caso sigue igual).
    *   **(commit 10)** Con sesión, las props traen `slug: 'acme'` y `deviceKey` con `optionsUrl`, `storeUrl` y el `proof` de `ProofSteps` (modo `key` para quien tiene llave, `code` para quien no); con la empresa sin el módulo `attendance`, `deviceKey` es null; la pantalla de entrada sin sesión no trae `deviceKey`.

###### 40. `tests/Feature/WorkerAccess/TenantIsolationTest.php` (Modify) (commit 10)
*   **Casos**: la empresa A no lee ni revoca las llaves del dispositivo de B por consulta (`WorkerDeviceKey::query()` y `revokeAllOf`). Por URL: renovar el pase con el id de una llave de B, en sesión de A, da 404. Un pase de B no verifica ni da `passId` en A (también en #37).

###### 41. `tests/Feature/WorkerAccess/PrivacyTest.php` (Modify) (commit 10)
*   **Caso nuevo**: registrar, renovar y reemitir. Ni la llave pública, ni el pase, ni su HMAC, ni la firma, ni el reto de posesión aparecen en el log, la bitácora o las props de `worker.home` y `worker.key.show`; los logs traen `worker_access.device_key_registered` y `device_keys_revoked` en `worker_access.access_reissued`.

###### 43. `tests/Feature/WorkerAccess/InstallableAppRoutesTest.php` (Create) (commit 3, ampliado en 4)
*   **Type / Scope**: Prueba Feature de las rutas (el comportamiento de cada respuesta lo prueba `InstallableAppTest`, de A6).
*   **Casos**: **(commit 3)** `GET worker.app.manifest` sin cookie no devuelve `Set-Cookie` y no crea fila en `sessions`; una empresa desconocida o bloqueada da 404. **(commit 4)** lo mismo para `worker.app.sw` y `worker.app.offline`; las tres siguen respondiendo con una cookie de sesión vencida.

##### Necesita de archivos compartidos

*   `.env.example` y `tests/Unit/EnvExampleTest.php` (A6): debajo de `WORKER_ACCESS_MAX_KEYS=3` (línea 153, verificado), `PWA_SW_KILL=false` **(commit 4)**, `WORKER_ACCESS_DEVICE_KEYS_MAX=3` y `WORKER_ACCESS_OFFLINE_PASS_HOURS=24` **(commit 10)**.
*   `app/Logging/RedactSensitiveData.php` y su prueba (A6) **(commit 10)**: agregar `pass`, `signature` y `challenge` si no los agrega ya otra parte (verificado: hoy están `ticket`, línea 107, `public_key`, línea 121, y `credential`; no `pass`, `signature` ni `challenge`).
*   `resources/js/lib/audit.ts` (A6) **(commit 10)**: etiqueta de `worker_access.device_key_registered` («Llave del teléfono registrada»). Es el único evento de bitácora nuevo de WorkerAccess (verificado que hoy no existe; `tests/Unit/AuditEventLabelsTest.php:29-40` lo exige).
*   `app/Modules/Tenancy/Support/AuditPages.php` `KEYS` (A6) **(commit 10)**: `replaced_id` («Llave reemplazada») y `device_keys_revoked` («Llaves del teléfono revocadas»); `employee_id` ya existe (línea 153) y `keys_revoked` también (línea 160). Lo exige `AuditEventLabelsTest.php:95-104`.
*   `tests/Feature/RouteSecurityTest.php` (A6): `worker.app.manifest` **(commit 3)**, `worker.app.sw` y `worker.app.offline` **(commit 4)** en la lista pública y en la de rutas sin sesión, con su razón. Las tres de `device-key` no cambian nada (cumplen sus reglas; no son públicas).
*   `docs/operations/pwa.md` (A6): pases y boletos se firman con `APP_KEY` y se leen con `APP_PREVIOUS_KEYS`; rotar `APP_KEY` sin poner la vieja en `APP_PREVIOUS_KEYS` invalida ambos y deja marcas «No aceptada» en los teléfonos.
*   Sin cambios (verificado): `TenantScopedModelsTest` descubre el modelo por su columna `tenant_id`.

##### Dependencias con otros segmentos

*   **Attendance (A1)**:
    *   Antes de verificar, `DeviceKeySignatures::passId($pass)` para armar el reto (`{pass_id_o_vacío}`); después, `verifyChallenge($expected, $credential, $ticketIssuedAt)` o `verify($expected, $signature, $pass, $ticketIssuedAt)`, con `$expected` = los 32 bytes crudos del SHA-256 de la cadena canónica v1.
    *   Con `declared_at`: `passId` igual al del reto, `declared_at ≤ passExpiresAt` y `passIssuedAt ≤ received_at` (si no, `pass_expired`); `declared_at ≤ revokedAt` cuando no es null, en `SignedBy` y en `DeviceSigned` (si no, `signature_invalid`, sin fila); el firmante es la persona del boleto; la persona sigue activa (`person_inactive`), porque los contratos de WorkerAccess no lo filtran.
    *   Valida `pass` con `max:512` y `device_key_id` como uuid en la entrega.
    *   Guarda `signer_type` (`passkey`/`device_key`) con `signer_id` igual a `SignedBy::credentialId` o a `DeviceSigned::deviceKeyId`.
    *   Los boletos y su `context` no dependen de WorkerAccess (ruta sin sesión); solo la renovación del pase pide sesión.
*   **Notifications (A4)**: escucha solo `WorkerAccessRevoked(employeeId, reason, credentialId?)`, que entra en el commit 16.
*   **Plataforma (A6)**: `InstallableAppController` lee `config('worker_access.pwa_sw_kill')`; sus rutas las declara #18.
*   **Runtime de la app (A5a)**:
    *   Guarda en IndexedDB la llave no extraíble, `deviceKeyId`, el pase, `passId` y `passExpiresAt`; firma con `ECDSA/SHA-256` los 32 bytes crudos (`signBytes`) y lee los vectores #31.
    *   `usePassRenewal` llama a `worker.device-key.pass` al abrir `/me` con sesión. La URL sale de Wayfinder con la prop `slug` (el plugin está en `vite.config.ts:2,30`), no de una prop. Ante 404 borra la llave local; ante 401 no hace nada (sin sesión no hay renovación). La renovación no pide prueba reciente: el 403 `proofRequired` solo llega en el alta.
    *   Al «Salir», borra su llave y su pase locales, **no** la cola (las marcas ya firmadas se entregan y verifican); no manda `device_key_id`.
*   **Pantallas (A5b)**:
    *   El alta usa `deviceKey.optionsUrl` y `deviceKey.storeUrl` con `fetch` JSON (sin `router.post`), firma los bytes del reto con `signBytes`, y ante 403 `proofRequired` abre el diálogo de prueba reciente con `deviceKey.proof` y reintenta. Con `deviceKey` null no muestra la tarjeta.
    *   El aviso por AAGUID se lee del lado del cliente en `authenticatorData` al crear la llave de acceso; el servidor no lo guarda (hoy `record()` usa un AAGUID en ceros, verificado).

##### Riesgos y pruebas en teléfonos

*   **Ventana tras revocar.** Después de reemitir o «No fui yo», un teléfono con un boleto anterior entrega las marcas que firmó **antes** de la revocación; una marca declarada después de `revokedAt` se rechaza. Con llave del dispositivo, además, `declared_at ≤ passExpiresAt` (pase de ≤ 24 h). Toda marca sin conexión queda «por verificar».
*   **Renovación diaria.** El pase vale 24 h y solo se renueva con sesión al abrir `/me`: quien usa la llave del dispositivo debe entrar a `/me` con conexión al menos una vez al día; si no, sus marcas sin conexión salen `pass_expired`.
*   **Reemplazo de la llave menos usada** en lugar de 409: quien borra los datos o reinstala no queda bloqueado; la llave huérfana se revoca con `replaced` al registrar la cuarta.
*   **«Salir» deja la fila activa** con la llave privada ya borrada en el teléfono: no firma nada y se reemplaza con el tiempo.
*   RH no ve las llaves del dispositivo en «Accesos»; las revoca con «reemitir». Mostrarlas es trabajo aparte.
*   `revokeAllOf` en `DisownKey` revoca **todas** las llaves del dispositivo de la persona, también las legítimas, igual que con las sesiones.
*   No se envía correo al registrar una llave del dispositivo: la prueba reciente ya cuenta como confirmación.
*   **Pruebas en teléfonos reales** (van a la lista consolidada de A6):
    *   iPhone con la llave de acceso en Chrome / Google: el alta registra la llave del dispositivo y marca sin conexión con ella.
    *   La llave del dispositivo sobrevive al cierre de la app y al reinicio, en iOS (app en la pantalla de inicio) y en Android (`persist()`).
    *   Después de «Salir», el teléfono ya no firma sin conexión (la llave local se borró) y las marcas que tenía en cola se entregan y cuentan.
    *   Una firma WebCrypto r‖s de Safari y una de Chrome para Android verifican en el servidor (dos motores distintos).
    *   Una llave de acceso de iCloud (contador 0) y una ligada al dispositivo con contador, entregadas fuera de orden, verifican con `verifyChallenge`.
    *   Reemitir con marcas en cola: las firmadas antes se aceptan; las nuevas ya no se pueden firmar.
    *   App instalada que se abre tras días sin uso: el pase se renueva al abrir `/me` con conexión.


#### Segmento A4 · Notifications (módulo nuevo)

Avisos push **sin cuerpo** a la persona trabajadora: el teléfono recibe una señal vacía y el service worker muestra siempre el mismo texto («Tiene un aviso en Humetría»). Dos eventos disparan el aviso: marca sin conexión revisada (`OfflineMarkReviewed`, tanto si Recursos Humanos la verifica como si la marca incorrecta) y solicitud de corrección resuelta (`CorrectionResolved`, al aprobar y al rechazar). No hay tipos de aviso, carga cifrada ni datos de la persona en la petición. No hay contrato público: los listeners del módulo llaman directo a su clase emisora (`Support/PushNotifier`); un contrato se agrega cuando otro módulo necesite avisar.

Todo el segmento entra en el commit 16 (módulo `Notifications`); sus pruebas de extremo a extremo usan eventos que ya existen para entonces (`OfflineMarkReviewed` y `CorrectionResolved` de Attendance, `WorkerAccessRevoked` de WorkerAccess). El cliente de push (permiso, manejador `push` del service worker, baja al salir) es del commit 17. Las rutas de push siguen con la sesión del portal; no usan boletos ni `AttachOfflineTickets`, así que la entrega y los boletos sin sesión no les cambian nada.

Verificado contra el repo (fecha 2026-11-20); lo no verificado dice NO VERIFICADO. No existen hoy: `app/Modules/Notifications`, `database/factories/Notifications`, `tests/Feature/Notifications`. `app/Modules/WorkerAccess/Events` no existe; `app/Modules/Attendance/Events` tiene solo `DayClosed`, `MarkCorrected`, `WeekConfirmed`, `WeekDisputed`. La última migración de main es `2027_01_29_100000_make_company_role_labels_unique.php` (2026-11-24).

Librería: `minishlink/web-push` v11.0.0 (referencia `f8410af`). El worktree no tiene `vendor/`; lo que sigue se leyó en la fuente de esa versión en la caché local de Composer y **no es reproducible desde el repo: NO VERIFICADO** hasta instalarla. Cada punto tiene su prueba en este segmento, que lo confirma o lo tumba en el commit 16:
*   `WebPush::prepare` (`src/WebPush.php:294-323`): con cuerpo vacío no cifra, no lee `p256dh` ni `auth` y manda `Content-Length: 0` sin `Content-Encoding`.
*   El encabezado VAPID (`src/WebPush.php:336-343`) exige que la suscripción tenga `contentEncoding`; con `aes128gcm` es `Authorization: vapid t={jwt}, k={llave pública}` (`src/VAPID.php:176`). `Subscription::create(['endpoint' => …, 'contentEncoding' => 'aes128gcm'])` es válido sin llaves (`src/Subscription.php:54-78`).
*   El constructor (`src/WebPush.php:74-106`) llama `Utils::checkRequirement($logger)`; sin `gmp` ni `bcmath` emite `trigger_error(E_USER_NOTICE)` si no recibe logger, y Laravel convierte todo error que entra en `error_reporting()` en `ErrorException` (`Illuminate\Foundation\Bootstrap\HandleExceptions::handleError`). Por eso se le pasa un `NullLogger` (entrada 9). `gmp` y `bcmath` son solo `suggest`; en el PHP local están las dos; en la imagen de Dokploy NO VERIFICADO (con el `NullLogger` no importa).
*   Que v11 exija `php-http/discovery` en tiempo de ejecución: NO VERIFICADO (hoy el lock solo lo menciona en `require-dev`/`suggest` de `laravel/framework`, no instalado). El diseño no depende de ello: el cliente PSR-18 y las fábricas PSR-17 se pasan explícitos.
*   `flush()` atrapa `ClientExceptionInterface` y devuelve un reporte rechazado; `MessageSentReport::isSubscriptionExpired()` es 404 o 410.

Los números 1, 2 y 11 no se usan (eran el tipo de aviso, el contrato público y la carga cifrada); los demás se conservan porque otras partes los citan.

##### Archivos: dominio y datos

###### 3. `app/Modules/Notifications/Models/PushSubscription.php` (Create)
*   **Type / Scope**: modelo Eloquent `final`.
*   **Proposed Changes**: `use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids` (como `WorkerAccess/Models/WorkerCredential.php:45`). Columnas: `id`, `tenant_id`, `employee_id` (la persona a quien se avisa; obligatorio), `endpoint`, `p256dh`, `auth`, `last_success_at`, `created_at`, `updated_at`. Con `#[Fillable(['tenant_id','employee_id','endpoint','p256dh','auth','last_success_at'])]`, `#[Hidden(['endpoint','p256dh','auth'])]` y `#[UseFactory(PushSubscriptionFactory::class)]`. Casts: `last_success_at` como `immutable_datetime`. PHPDoc `@property` igual que en `WorkerCredential`. `tenant_id` se asigna de forma explícita al crear, porque el trait solo filtra y no rellena.

###### 4. `database/migrations/2027_01_30_900000_create_push_subscriptions_table.php` (Create)
*   **Type / Scope**: migración aditiva; la **última** del plan (después de marcas, marcas sin conexión, llaves del dispositivo y revisiones). Su prefijo solo necesita ser posterior a `2027_01_29_100000` y a las otras migraciones del plan; no depende de sus tablas.
*   **Proposed Changes**: tabla `push_subscriptions`:
    *   `uuid id` es la llave primaria.
    *   `foreignUuid tenant_id` usa `constrained()->restrictOnDelete()`.
    *   `uuid employee_id` **not null** (la persona a quien se avisa; `notify($employeeId)` filtra por ella) con la llave foránea compuesta `(employee_id, tenant_id)` hacia `employees(id, tenant_id)`, como `2027_01_21_110000_create_worker_access_states_table.php:23` (que usa `restrictOnDelete`); aquí `cascadeOnDelete`, porque la fila es desechable.
    *   Columnas `text endpoint`, `string p256dh(100)` y `string auth(32)`, más `timestampTz last_success_at` nula y `timestampsTz`. `p256dh` y `auth` se guardan aunque el envío sin cuerpo no las lee (ver arriba): el navegador las entrega en cada alta y un aviso con cuerpo en el futuro no exigiría volver a suscribir a nadie.
    *   `unique(tenant_id, endpoint)` e `index(tenant_id, employee_id)`.
    *   `CHECK (endpoint LIKE 'https://%')` y `CHECK (char_length(endpoint) <= 2048)`.
    *   No hace falta lista en el reinicio de la demo: `DemoWorkspaceRunner.php:107` lee de `information_schema` toda tabla con `tenant_id`.

###### 5. `database/factories/Notifications/PushSubscriptionFactory.php` (Create)
*   **Type / Scope**: factory.
*   **Proposed Changes**: `tenant_id` y `employee_id` salen de `Employee::factory()`, como en las factories de WorkerAccess. `endpoint` es `https://fcm.googleapis.com/fcm/send/{uuid}`. `p256dh` y `auth` son cadenas base64url aleatorias de 87 y 22 caracteres (no se cifra nada, así que no hacen falta vectores de RFC 8291). Estados: `mozilla()` (`https://updates.push.services.mozilla.com/wpush/v2/{uuid}`), `apple()` (`https://web.push.apple.com/{uuid}`) y `succeeded()`.

##### Archivos: envío

###### 6. `app/Modules/Notifications/Support/VapidConfig.php` (Create)
*   **Type / Scope**: lectura de configuración (`final readonly`).
*   **Proposed Changes**: `public static function fromConfig(): ?self`, más `public function publicKey(): string` y `public function auth(): array{VAPID: array{subject:string, publicKey:string, privateKey:string}}`. Lee `config('services.vapid.*')`.
    *   Devuelve `null`, y deja log `push.unconfigured` con `['reason' => 'missing_keys'|'subject_invalid']`, cuando falta una llave o cuando `subject` no empieza por `mailto:` o `https://` (sensible a mayúsculas).
    *   No valida en el arranque: una mala configuración nunca tumba la app, solo apaga el push.

###### 7. `app/Modules/Notifications/Support/PushHosts.php` (Create)
*   **Type / Scope**: lista cerrada contra SSRF.
*   **Proposed Changes**: `public static function allows(string $endpoint): bool`. Exige `https`, sin usuario ni puerto, y un host exacto o subdominio (separado por punto) de: `fcm.googleapis.com`, `push.services.mozilla.com` (incluye `updates.push.services.mozilla.com`), `push.apple.com` (incluye `web.push.apple.com`) y `notify.windows.com`. Sin esta lista, cualquier persona con sesión haría que el servidor mandara un POST a la dirección que ella escriba.

###### 8. `app/Modules/Notifications/Support/LaravelHttpClient.php` (Create)
*   **Type / Scope**: adaptador PSR-18 (`final readonly`, `implements Psr\Http\Client\ClientInterface`).
*   **Proposed Changes**: `sendRequest(RequestInterface $request): ResponseInterface`.
    *   Envía por `Http::withHeaders($request->getHeaders())->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/octet-stream')->withoutRedirecting()->timeout(10)->send($request->getMethod(), (string) $request->getUri())` y regresa `->toPsrResponse()` (los dos métodos existen en `Illuminate\Http\Client`).
    *   Un `ConnectionException` se convierte en `PushNetworkFailed` (entrada 10).
    *   Así lo intercepta `Http::fake()`, y `Http::preventStrayRequests()` (`tests/TestCase.php:22`) lo cubre.

###### 9. `app/Modules/Notifications/Support/WebPushFactory.php` (Create)
*   **Type / Scope**: arma la instancia de la librería.
*   **Proposed Changes**: `public function make(VapidConfig $vapid): WebPush` arma `new WebPush($vapid->auth(), ['TTL' => SendPush::TTL, 'urgency' => 'normal'], new LaravelHttpClient, new HttpFactory, new HttpFactory, null, new NullLogger)`.
    *   `GuzzleHttp\Psr7\HttpFactory` (`guzzlehttp/psr7` 3.2.0, ya en el lock) es la fábrica PSR-17 de peticiones y de streams; así nada usa `Psr18ClientDiscovery` ni `Psr17FactoryDiscovery`.
    *   `Psr\Log\NullLogger` (`psr/log` 3.0.2, ya en el lock) evita que el aviso de `Utils::checkRequirement` se vuelva excepción.
    *   El constructor sigue buscando un cliente asíncrono con `HttpAsyncClientDiscovery::find()` dentro de un `try/catch`; sin adaptador da `null`, y no se usa porque no llamamos `flushPooled`.
    *   `setReuseVAPIDHeaders(true)`.

###### 10. `app/Modules/Notifications/Exceptions/PushNetworkFailed.php`, `PushRateLimited.php`, `PushUnavailable.php` (Create)
*   **Type / Scope**: excepciones `final`.
*   **Proposed Changes**:
    *   `PushNetworkFailed extends RuntimeException implements Psr\Http\Client\NetworkExceptionInterface` con `getRequest()`. Así `flush()` la atrapa y devuelve un reporte sin respuesta.
    *   `PushRateLimited(public readonly int $retryAfter)` cubre el 429.
    *   `PushUnavailable` cubre un 5xx o la falta de respuesta: es lo que se reintenta con `backoff`.

###### 12. `app/Modules/Notifications/Actions/SendPush.php` (Create)
*   **Type / Scope**: acción (`handle()`).
*   **Proposed Changes**: `public const int TTL = 3600;` y `handle(string $subscriptionId): void` (en la empresa actual).
    *   Busca la suscripción. Si ya no existe, termina sin hacer petición.
    *   `VapidConfig::fromConfig()` nulo: termina.
    *   Arma `Subscription::create(['endpoint' => $s->endpoint, 'contentEncoding' => 'aes128gcm'])` y llama `sendOneNotification($subscription)` **sin cuerpo**.
    *   Con éxito (2xx): `last_success_at = now()` y log `push.sent`.
    *   `isSubscriptionExpired()` (404 o 410): borra la fila y deja log `push.expired`.
    *   429: lee `Retry-After`, en segundos o como fecha HTTP. Sin el encabezado usa 60; el valor queda acotado entre 1 y 3600. Lanza `PushRateLimited`.
    *   Sin respuesta o 5xx: lanza `PushUnavailable`.
    *   Otro 4xx (400, 403, 413): log `push.failed` con el `status`, sin reintento y sin borrar. Un 403 suele ser una llave VAPID equivocada, y borrar ahí vaciaría a todos ante un solo error de configuración.
    *   Logs: solo `subscription_id`, `status` y `reason`. Nunca `endpoint`, llaves ni `employee_id`.

###### 13. `app/Modules/Notifications/Jobs/DeliverPush.php` (Create)
*   **Type / Scope**: trabajo en cola.
*   **Proposed Changes**: `final class DeliverPush implements ShouldQueue, ShouldQueueAfterCommit` con `Queueable`.
    *   `public int $tries = 3; public array $backoff = [60, 300];` (como `Attendance/Jobs/CloseCenterDays.php:20-23`) y cola `default`; no se toca `config/horizon.php` (la `timeout` de 60 s del supervisor cubre los 10 s del adaptador).
    *   Constructor `(string $tenantId, string $subscriptionId)`: solo ids viajan en la cola.
    *   `handle(Companies $companies, SendPush $send)`:
        *   `$company = $companies->enter($this->tenantId)` pone la empresa actual y el contexto de logs.
        *   Si `$company->blocked`, termina.
        *   Llama `$send->handle($this->subscriptionId)`. Atrapa solo `PushRateLimited` y hace `$this->release($e->retryAfter)`. `PushUnavailable` se propaga para usar el `backoff`.
    *   `failed(Throwable)`: log `push.failed` con `reason = 'exhausted'`.
    *   Un trabajo por suscripción: un 429 de un teléfono no repite los demás.

###### 14. `app/Modules/Notifications/Support/PushNotifier.php` (Create)
*   **Type / Scope**: clase emisora `final` (interna del módulo; sin interfaz).
*   **Proposed Changes**: `public function notify(string $employeeId): void`. Sin tipo ni carga. Trabaja en la empresa actual (`CurrentTenant`) y lanza `NoCurrentTenant` fuera de una empresa. Sigue este orden:
    1.  `VapidConfig::fromConfig()` es nulo: termina (ya quedó `push.unconfigured`).
    2.  `DemoCompanies::isDemo($tenantId)` (`Tenancy/Contracts/DemoCompanies.php:10`): log `push.demo_blocked` en `debug`, como `app/Listeners/BlockDemoMail.php`, y termina.
    3.  Despacha `DeliverPush` por cada `PushSubscription` de la persona (hasta 5: `StoreSubscription` mantiene ese tope).

    Si la transacción que emitió el evento se deshace, no sale nada, gracias a `ShouldQueueAfterCommit`.

###### 15. `app/Modules/Notifications/Actions/StoreSubscription.php` (Create)
*   **Type / Scope**: acción.
*   **Proposed Changes**: `handle(string $employeeId, string $endpoint, string $p256dh, string $auth): PushSubscription`.
    *   Usa `DB::transaction` y `upsert` por `(tenant_id, endpoint)`. Teléfono compartido, **mejor esfuerzo**: la misma dirección se reasigna a quien se suscribió al último, y esa persona gana el aviso; la anterior deja de recibirlo en ese teléfono hasta que vuelva a abrir sesión con permiso concedido.
    *   Tope de 5 por persona (`MAX_PER_PERSON`): borra la más vieja.
    *   Log `push.subscribed` con `subscription_id` y `replaced` (bool).

###### 16. `app/Modules/Notifications/Actions/ForgetSubscriptions.php` (Create)
*   **Type / Scope**: acción.
*   **Proposed Changes**: `handle(string $employeeId, ?string $endpoint, string $reason): int`.
    *   Con `endpoint`, borra solo esa suscripción de la persona; con `null`, todas las de la persona.
    *   `$reason` es `unsubscribed` (la persona, por `DELETE …/push`) o `revoked` (`WorkerAccessRevoked`).
    *   Log `push.subscriptions_forgotten` con `count` y `reason`, aun con 0.
    *   Devuelve cuántas borró.

##### Archivos: listeners y proveedor

###### 17. `app/Modules/Notifications/Listeners/NotifyMarkReviewed.php` (Create)
*   **Type / Scope**: listener síncrono.
*   **Proposed Changes**: recibe `PushNotifier` (entrada 14) por inyección. `handle(OfflineMarkReviewed $event)` llama `notify($event->employeeId)` en las dos decisiones (verificada, o incorrecta junto con su corrección), con el mismo texto fijo.

###### 18. `app/Modules/Notifications/Listeners/NotifyCorrectionResolved.php` (Create)
*   **Type / Scope**: listener síncrono.
*   **Proposed Changes**: recibe `PushNotifier` por inyección. `handle(CorrectionResolved $event)` llama `notify($event->employeeId)`, al aprobar y al rechazar.
    *   **No se usa `MarkCorrected`.** Hoy se emite en `CorrectMark.php:57` (también cuando Recursos Humanos corrige por su cuenta) y en `ResolveCorrection.php:111`, solo al aprobar. Con `MarkCorrected` la persona no se enteraría de un rechazo y recibiría avisos de correcciones que no pidió.

###### 19. `app/Modules/Notifications/Listeners/ForgetOnAccessRevoked.php` (Create)
*   **Type / Scope**: listener síncrono.
*   **Proposed Changes**: `handle(WorkerAccessRevoked $event)` llama `ForgetSubscriptions::handle($event->employeeId, null, 'revoked')`. Cubre el caso en que Recursos Humanos reemite o revoca sin tener el teléfono en la mano.
    *   «Salir» y «¿No es usted?» no pasan por aquí: el cliente llama `DELETE /me/{slug}/push` con su `endpoint` antes de cerrar la sesión (pantallas de `leave-button.tsx` y `not-you.tsx`). Si ese `DELETE` falla (sin red), la suscripción queda y el siguiente aviso llega a ese teléfono con el texto fijo, sin datos; se acepta.

###### 20. `app/Modules/Notifications/Providers/NotificationsServiceProvider.php` (Create)
*   **Type / Scope**: proveedor.
*   **Proposed Changes**:
    *   `boot()`: `Event::listen(OfflineMarkReviewed::class, NotifyMarkReviewed::class)`, `Event::listen(CorrectionResolved::class, NotifyCorrectionResolved::class)` y `Event::listen(WorkerAccessRevoked::class, ForgetOnAccessRevoked::class)`.
    *   Si `runningInConsole()`: `$this->commands([GenerateVapidKeys::class])`, como `AssistantServiceProvider.php:103`.

###### 21. `app/Modules/Notifications/Console/GenerateVapidKeys.php` (Create)
*   **Type / Scope**: comando Artisan.
*   **Proposed Changes**: firma `notifications:vapid-keys`. Imprime `VAPID_PUBLIC_KEY=…` y `VAPID_PRIVATE_KEY=…` con `Minishlink\WebPush\VAPID::createVapidKeys()`. No escribe en `.env`. Advierte que rotarlas invalida toda suscripción y que la app se vuelve a suscribir al abrir. Log `push.vapid_keys_generated`, sin las llaves.

##### Archivos: HTTP

###### 22. `routes/notifications-portal.php` (Create)
*   **Type / Scope**: rutas del módulo en el portal de la persona (`*-portal.php`, como `routes/leave-portal.php`).
*   **Proposed Changes**: `Route::middleware([ResolveWorkerTenant::class, RequireWorkerSession::class])->prefix('me/{tenant:slug}/push')` con:
    *   `GET /` → `[PushSubscriptionController::class, 'show']`, `throttle:portal-views`, nombre `portal.push.show`.
    *   `POST /` → `'store'`, `throttle:portal-actions`, nombre `portal.push.store`.
    *   `DELETE /` → `'destroy'`, `throttle:portal-actions`, nombre `portal.push.destroy`.

    Notas:
    *   Los limitadores `portal-views` y `portal-actions` existen en `WorkerAccessServiceProvider.php:99-105` y se usan por nombre, como en `routes/leave-portal.php`.
    *   Sin `worker.proof`: suscribirse no expone nada.
    *   Con `RequireWorkerSession` la ruta cuenta como autenticada en `RouteSecurityTest` (`requiresAuthentication`, línea 121) y va bajo `me/{tenant}/…` con `ResolveWorkerTenant` primero, como exige esa prueba; no entra a la lista pública. Ningún parámetro choca con `CREDENTIAL_PARAMETER`.
    *   CSRF normal (`X-XSRF-TOKEN`): sale de la página con sesión.

###### 23. `app/Modules/Notifications/Http/Controllers/PushSubscriptionController.php` (Create)
*   **Type / Scope**: controlador delgado (`final readonly`).
*   **Proposed Changes**: recibe `WorkerSession` (contrato público; `current(): ?WorkerIdentity`).
    *   `show()`: `JsonResponse { publicKey: string | null }`. Con `null` la app no ofrece el permiso.
    *   `store(StorePushSubscriptionRequest)`: `StoreSubscription` con `employeeId` de la sesión; 204.
    *   `destroy(ForgetPushSubscriptionRequest)`: `ForgetSubscriptions(employeeId, endpoint, 'unsubscribed')` y 204 aunque no exista. No es oráculo: solo toca filas de la persona de la sesión.
    *   Una sesión nula no llega aquí (`RequireWorkerSession.php:32` responde 401 en JSON). Si llegara, `abort(401)`.

###### 24. `app/Modules/Notifications/Http/Requests/StorePushSubscriptionRequest.php` y `ForgetPushSubscriptionRequest.php` (Create)
*   **Type / Scope**: FormRequests.
*   **Proposed Changes**: `authorize(): true`, porque la sesión es la credencial (igual que `WorkerAccess/Http/Requests/RegisterKeyRequest.php:15-17`).
    *   Reglas de `store` (el cuerpo es `subscription.toJSON()`; otras llaves, como `contentEncoding`, se ignoran):
        *   `endpoint`: `required|string|max:2048|url:https` y un cierre que use `PushHosts::allows`.
        *   `keys.p256dh`: `required|string|regex:/^[A-Za-z0-9_-]{87}$/` (65 bytes en base64url).
        *   `keys.auth`: `required|string|regex:/^[A-Za-z0-9_-]{22}$/` (16 bytes).
    *   Reglas de `forget`: solo `endpoint` (`required|string|max:2048`).

##### Archivos: documentación del módulo

###### 25. `app/Modules/Notifications/README.md` (Create)
*   **Type / Scope**: documentación.
*   **Proposed Changes**:
    *   Qué avisa (marca revisada, corrección resuelta), que el aviso no lleva cuerpo y que el texto es fijo.
    *   La tabla y su baja (404/410, revocación, `DELETE` del cliente al salir o con «¿No es usted?», tope de 5) y el teléfono compartido como mejor esfuerzo.
    *   Configuración VAPID, el comando y que rotar llaves exige volver a suscribirse.
    *   Bloqueo de la demo, la lista de hosts y los logs `push.*`.

##### Archivos: pruebas

###### 26. `tests/Feature/Notifications/PushSubscriptionRoutesTest.php` (Create)
*   **Type / Scope**: prueba Feature (HTTP).
*   **Casos**:
    *   **Alta:**
        *   Con sesión se guarda con `tenant_id` y `employee_id` de la sesión (un `tenant_id` o `employee_id` enviados en el cuerpo se ignoran).
        *   La misma dirección dos veces deja una fila.
        *   Si otra persona de la misma empresa usa la misma dirección, la fila pasa a ella (teléfono compartido: gana la última).
        *   Con la sexta suscripción se borra la más vieja; con 5 exactas no se borra nada.
    *   **Validación (422):**
        *   `http://` y hosts fuera de la lista (`https://169.254.169.254/x`, `https://evil.example/fcm.googleapis.com`).
        *   2048 caracteres se aceptan y 2049 no.
        *   `p256dh` de 86 y 88 caracteres, `auth` de 21 y 23, y caracteres fuera de base64url.
    *   **`GET`:** con llaves devuelve `{ publicKey }`; sin llaves o con `subject` inválido devuelve `publicKey: null`.
    *   **Baja:** la propia se borra; una dirección desconocida da 204 sin cambios; la de otra persona de la misma empresa da 204 y la fila sigue.
    *   **Acceso:**
        *   Sin sesión, en JSON, da 401 y no escribe nada.
        *   Un slug desconocido o una empresa bloqueada dan 404.
        *   Los logs `push.subscribed` y `push.subscriptions_forgotten` no contienen `endpoint`, `p256dh` ni `auth`.

###### 27. `tests/Feature/Notifications/TenantIsolationTest.php` (Create)
*   **Type / Scope**: prueba Feature de aislamiento A/B.
*   **Casos**:
    *   **Por consulta:** en la empresa B, `PushSubscription::query()` no ve las filas de A, y `ForgetSubscriptions` con un `employee_id` de A borra 0.
    *   **Por URL:**
        *   Con la sesión de A, `DELETE me/{slugB}/push` con la dirección de A no borra nada: la sesión de A no vale en B (401).
        *   Con la sesión de B y la dirección de A, devuelve 204 y la fila de A sigue.
        *   Un slug inexistente da 404.
    *   **Por evento:** `WorkerAccessRevoked` en B no toca las filas de A.
    *   **Por notificador:** `notify()` en B con un `employee_id` de A no encola nada.

###### 28. `tests/Feature/Notifications/SendPushTest.php` (Create)
*   **Type / Scope**: prueba Feature del envío con `Http::fake()` y VAPID real generado en la prueba con `VAPID::createVapidKeys()`.
*   **Casos**:
    *   **201:**
        *   Una sola petición `POST` al `endpoint`, con cuerpo vacío, `Content-Length: 0`, sin `Content-Encoding`, `TTL: 3600`, `Urgency: normal` y `Authorization` que empieza por `vapid t=` y contiene `k=` igual a la llave pública.
        *   Actualiza `last_success_at` y deja log `push.sent`.
    *   **404 y 410:** borran la fila y dejan `push.expired`.
    *   **429** (con `withFakeQueueInteractions()` y `assertReleased`, como `tests/Feature/Engagement/InvitationsTest.php:202-207`):
        *   `Retry-After: 120` libera el trabajo con 120.
        *   Una fecha HTTP 90 s adelante libera con unos 90, con `Carbon::setTestNow`.
        *   Sin encabezado libera con 60; con `99999` queda en 3600; con `0` o negativo, en 1.
    *   **500 y error de red:** `ConnectionException` lanza `PushUnavailable` y el trabajo falla para reintentar.
    *   **400 y 403:** `push.failed` con `status`, sin excepción y con la fila intacta.
    *   **Sin petición alguna:** suscripción borrada entre el encolado y el envío; empresa bloqueada; sin llaves.
    *   **Sin `gmp` ni `bcmath` no truena:** `WebPushFactory::make` no lanza aunque `Utils::checkRequirement` avise (se comprueba que recibe un logger; NO VERIFICADO cómo simular la extensión ausente en la prueba: si no se puede, basta afirmar que el sexto argumento no es nulo).
    *   **Propiedades:** `DeliverPush` tiene `tries=3` y `backoff=[60,300]`; `failed()` deja `push.failed` con `reason=exhausted`.

###### 29. `tests/Feature/Notifications/PushNotifierTest.php` (Create)
*   **Type / Scope**: prueba Feature con `Queue::fake()`.
*   **Casos**:
    *   Despacha un `DeliverPush` por cada una de las 2 suscripciones de la persona y ninguno para otra persona.
    *   Una persona sin suscripciones no despacha nada.
    *   Sin llaves deja `push.unconfigured` con `missing_keys` y no despacha.
    *   Un `subject` de `http://x`, `x@y` o vacío deja `subject_invalid`; uno de `mailto:a@b` o `https://humetria.com` sí despacha.
    *   La empresa demo (de `DemoCompanies`) no despacha y deja `push.demo_blocked`.
    *   Dentro de un `DB::transaction` que se deshace, no se despacha nada.
    *   Fuera de una empresa lanza `NoCurrentTenant`.

###### 30. `tests/Feature/Notifications/ListenersTest.php` (Create)
*   **Type / Scope**: prueba Feature por evento, con `Queue::fake()`.
*   **Casos**:
    *   `OfflineMarkReviewed` («correcta» e «incorrecta») despacha un `DeliverPush` por suscripción de la persona.
    *   `CorrectionResolved` aprobada y rechazada despachan.
    *   `MarkCorrected` **no** despacha.
    *   `WorkerAccessRevoked` borra todas las suscripciones de la persona, deja las de otra persona, y repetirlo no falla.
    *   **Extremo a extremo, una prueba por evento:** se llama la acción real (`ResolveCorrection` al rechazar, la revisión de una marca sin conexión, `RevokeCredential`) y se comprueba el efecto, para detectar si la acción deja de emitir el evento.

###### 31. `tests/Feature/Notifications/PushPrivacyTest.php` (Create)
*   **Type / Scope**: prueba Feature de privacidad.
*   **Casos**:
    *   La petición a la dirección push lleva cuerpo vacío para los dos eventos (ningún nombre, hora, empresa ni id sale del servidor).
    *   `PushSubscription::toArray()` oculta `endpoint`, `p256dh` y `auth`.
    *   En un ciclo completo (alta, envío y 410), ningún registro de log contiene `endpoint`, llaves, nombre ni `employee_id`.

###### 32. `tests/Feature/Notifications/LaravelHttpClientTest.php` (Create)
*   **Type / Scope**: prueba del adaptador con `Http::fake()`. Va en `tests/Feature`, porque `tests/Pest.php:54` liga `TestCase` (con la app) solo a `Feature`, `Browser` y `Ssr`.
*   **Casos**:
    *   Pasa el método, la URL, los encabezados y el cuerpo sin cambios.
    *   Devuelve un PSR-7 con estado, encabezados (`Retry-After`) y cuerpo.
    *   No sigue redirecciones: un 301 regresa como 301.
    *   Un `ConnectionException` se convierte en `NetworkExceptionInterface` con su petición.
    *   Una petición no fingida falla por `preventStrayRequests`.

###### 33. `tests/Unit/Notifications/PushHostsTest.php` y `tests/Feature/Notifications/VapidConfigTest.php` (Create)
*   **Type / Scope**: `PushHosts` es lógica pura (Unit); `VapidConfig` lee `config()` y deja logs (Feature).
*   **Casos**:
    *   **Hosts aceptados:** `https://fcm.googleapis.com/…`, `https://web.push.apple.com/…`, `https://updates.push.services.mozilla.com/…`.
    *   **Hosts desconocidos rechazados:** `http://fcm…`, `https://fcm.googleapis.com.evil.com`, `https://evilfcm.googleapis.com` (sin punto), `https://evil.example/fcm.googleapis.com`, una IP, `localhost`, un `user@host` y un puerto explícito.
    *   **Subject:** límites de `mailto:` y `https://`, mayúsculas, espacios alrededor y vacío.

###### 34. `tests/Feature/Notifications/GenerateVapidKeysTest.php` (Create)
*   **Type / Scope**: prueba del comando.
*   **Casos**: la salida tiene dos líneas con base64url de 87 y 43 caracteres; dos ejecuciones dan llaves distintas; no cambia `.env` y no hay petición HTTP.

##### Necesita de archivos compartidos

Todos son de A6.

*   `composer.json` → `require`: `"minishlink/web-push": "^11.0"` y el cambio de `config.allow-plugins."php-http/discovery"` (línea 172), en el commit 16 y con el texto de A6. Que v11 traiga `php-http/discovery` es NO VERIFICADO (lo mismo dice A6); este segmento no lo necesita porque pasa las fábricas explícitas (entrada 9). Se actualiza `composer.lock`.
*   `bootstrap/providers.php`: `NotificationsServiceProvider::class` después de `AttendanceServiceProvider::class` (línea 47; `WorkerAccessServiceProvider` está en la 46), porque escucha eventos de los dos.
*   `routes/web.php`: `require __DIR__.'/notifications-portal.php';` junto a los demás `*-portal.php` (líneas 52-61).
*   `config/services.php`: `'vapid' => ['public_key' => env('VAPID_PUBLIC_KEY'), 'private_key' => env('VAPID_PRIVATE_KEY'), 'subject' => env('VAPID_SUBJECT')],`.
*   `.env.example`: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` y `VAPID_SUBJECT`, con el valor y el comentario que fija A6 (vacías = sin push, `push.unconfigured`; el comando `sail artisan notifications:vapid-keys`). La prueba que lo exige es `tests/Unit/EnvExampleTest.php`.
*   `tests/Feature/Assistant/ModuleCoverageTest.php`, en `humiExemptCodeModules()` (línea 23): la exención de `Notifications` con la razón que fija A6 (única fuente). No va en `humiExemptCompanyModules()`: no es un módulo del catálogo `Module`.
*   `tests/Feature/TenantScopedModelsTest.php`: lo que haga falta lo decide A6; `PushSubscription` usa `BelongsToCurrentTenant` y no pide excepción.
*   `tests/Unit/ArchitectureTest.php`: las reglas por módulo ya recorren `app/Modules/*`; se revisa que ninguna regla `arch()` sobre carpetas `Notifications/` de correo choque con el módulo, y se agrega la regla de un destino «solo `Notifications` usa `Minishlink\WebPush`».
*   `lang/es/validation.php` → `attributes`: `'endpoint' => 'dirección de avisos'`, `'keys.p256dh' => 'llave de avisos'`, `'keys.auth' => 'llave de avisos'`.
*   `app/Logging/RedactSensitiveData.php`: agregar `endpoint`, `p256dh` y `auth` (hoy está `authorization`, no `auth`), con su caso en `tests/Feature/Logging/StructuredLogTest.php`. Defensa por si un 422 o una excepción loguea la entrada.
*   `CLAUDE.md`: la línea del módulo `Notifications`, la frase «No hay módulo de notificaciones» y la mención de `routes/notifications-portal.php`, con el texto de A6 (única fuente; este segmento no propone otra redacción).
*   `docs/operations/pwa.md`: variables VAPID, el comando, la rotación, la lista de hosts y los logs `push.*`. El aviso de privacidad cubre que el aviso no lleva contenido.

##### Riesgos y pruebas en teléfonos

**Lo que este módulo espera de otros (por clase, no por número):**
*   Attendance: `OfflineMarkReviewed` y `CorrectionResolved(string $requestId, string $employeeId, bool $approved)` con `public string $employeeId`; `CorrectionResolved` lo emite `ResolveCorrection::handle` al aprobar y al rechazar (hoy el rechazo no emite nada). Se emiten con la empresa actual puesta; si van dentro de una transacción, el envío espera al commit.
*   WorkerAccess: `WorkerAccessRevoked(string $employeeId, string $reason, ?string $credentialId)` al revocar, reemitir, «No fui yo» y cuando la persona deja la empresa (`left_company`), con la empresa actual puesta. No hay `WorkerSignedOut`.
*   Service worker (commit 17): maneja `push` con `showNotification('Humetría', { body: 'Tiene un aviso en Humetría' })` siempre, sin leer `event.data` (iOS revoca la suscripción si un push no muestra aviso); `notificationclick` abre o enfoca `/me/{slug}/punch` (el `start_url` de la app). Guarda la `publicKey` con la que se suscribió y vuelve a suscribirse si cambia.
*   Pantallas (commit 17): piden el permiso después de la primera marca exitosa (`GET portal.push.show` → `{ publicKey }`, `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey })`, `POST portal.push.store` con `subscription.toJSON()`); antes de «Salir» y de «¿No es usted?» llaman `DELETE portal.push.destroy`; al abrir sesión con permiso concedido vuelven a hacer `POST`.
*   Existentes: `CurrentTenant`, `Companies::enter` (`Tenancy/Contracts/Companies.php:23`), `DemoCompanies::isDemo`, `WorkerSession::current()`, `ResolveWorkerTenant`, `RequireWorkerSession`, limitadores `portal-views` y `portal-actions`.

**Riesgos:**
*   **SSRF**: `PushHosts` limita a qué host se envía. Si un navegador usa otro servicio push, se agrega su host con su prueba. NO VERIFICADO: los hosts de Edge/Windows (se asume `notify.windows.com`) y que Samsung Internet use FCM.
*   **Unicidad**: `unique(tenant_id, endpoint)`, no global: el upsert nunca cruza empresas, y cada empresa tiene su propio service worker, así que su dirección es distinta.
*   **`php-http/discovery`**: NO VERIFICADO que v11 lo exija. Si lo trae, el cliente PSR-18 y las fábricas PSR-17 ya se pasan explícitos y solo queda la búsqueda del cliente asíncrono dentro de un `try/catch` (inofensiva).
*   **Teléfono compartido**: mejor esfuerzo; la última persona suscrita en ese teléfono gana el aviso.
*   **Baja que no llega**: si el `DELETE` del cliente al salir falla sin red, la suscripción queda hasta el siguiente 404/410 o hasta `WorkerAccessRevoked`. El aviso no lleva datos; se acepta.
*   **Rotación de VAPID**: las suscripciones viejas responden 400 o 403. No se borran (sería un borrado masivo por un error de configuración); quedan con `push.failed` hasta que la app se vuelve a suscribir.
*   **Persona que se fue**: no se revisa que siga activa al enviar; el aviso no tiene contenido. Su baja emite `WorkerAccessRevoked` con `left_company` (`RevokeOnDeactivation`, entrada 25b de WorkerAccess), así que sus suscripciones se borran.
*   **TTL de 3600 s**: un teléfono sin red más de una hora no recibe el aviso. Aceptable: la app muestra el estado al abrir.

**Pruebas que solo se hacen en teléfonos reales:**
*   iPhone (iOS 16.4 o más, app en la pantalla de inicio) y Android (Chrome): permiso después de la primera marca; el aviso con la pantalla bloqueada muestra solo «Tiene un aviso en Humetría»; tocarlo abre `/me/{slug}/punch`.
*   iOS: un push **sin cuerpo** muestra la notificación y la suscripción sigue viva tras varios envíos.
*   La baja: tras «Salir» o «¿No es usted?» ya no llega nada; si se quita el permiso en el sistema o se borran los datos de la app, el siguiente envío recibe 404/410 y la fila se borra.
*   Teléfono compartido (mejor esfuerzo): la segunda persona se suscribe y la primera deja de recibir en ese teléfono.
*   Con las llaves VAPID de producción en Dokploy, un envío real a FCM, Apple y Mozilla responde 201 con cuerpo vacío. Con un `subject` mal escrito no se envía y queda `push.unconfigured`.


#### Segmento A5a · Frontend: tiempo de ejecución de la PWA

Segmento: lógica pura de la app sin conexión (`resources/js/lib/pwa/*`, con Vitest), `signed-request.ts`, adaptador de IndexedDB y hooks (cola, boletos, renovación del pase, marca sin conexión, lector de QR), el componente `QrScanner`, el bundle de entrada `offline` con `offline-app.tsx`, la fuente del service worker, la regla de lint del worker y las pruebas Vitest y de navegador de este tiempo de ejecución. Las pantallas y los textos de la persona son de A5b; aquí se entrega lo que ellas consumen. `tsconfig.json`, `resources/js/sw/tsconfig.json`, `vite.config.ts` (incluida la compilación aparte del worker) y `package.json` son de A6; el worker de apagado lo genera el controlador de A6; los vectores de `tests/Fixtures/attendance/*` son de A1 y los de la llave del dispositivo, de A3: aquí solo se leen.

La numeración tiene huecos a propósito (23, 24, 25, 26 y 28 ya no son de este segmento o no existen); los números que quedan no cambian porque otras partes los citan. Cada entrada dice su commit del orden global; un subcambio que llega en otro commit lo dice entre paréntesis.

Verificado contra el repo (2026-11-20); lo no verificado dice NO VERIFICADO:

*   `resources/js/lib/pwa/`, `resources/js/sw/`, `resources/js/offline.tsx` y `public/build/pwa/` **no existen**. `/public/build` está en `.gitignore` (línea 4): el worker compilado lo produce la build (`Dockerfile:44` corre `npm run build:ssr`).
*   `lib/attendance/signed-request.ts` (71 líneas) exporta `SignDeps` (`{fetcher, getKey, xsrf}`), `post`, `SignedRequest` y `sendSigned`; `sign()` **no** se exporta y ya convierte `NotAllowedError` en `'cancelled'` y cualquier otra cosa en `'failed'`. `post()` manda `Accept` y `Content-Type: application/json`; ningún `fetch` lleva tiempo de espera.
*   `lib/attendance/week.ts` mide 299 líneas y `lib/attendance/punch.ts` 259 (tope 300): este segmento no los toca.
*   `hooks/use-punch.ts:16` exporta `getKey(options)` (sin `as`); `use-punch.ts:111` y `use-week.ts:56` arman `fetcher: fetch.bind(window)` **al momento de marcar**, así que reemplazar `window.fetch` en la página alcanza a esas llamadas. `xsrfToken` vive en `lib/assistant/xsrf.ts`.
*   `lib/webauthn.ts`: `assertionPayload` da `{id, rawId, type, response:{clientDataJSON, authenticatorData, signature, userHandle?}, clientExtensionResults}` (contiene la forma `credential` del contrato); `creationOptions` exige y conserva `rp.id` (`relyingParty`, líneas 65-71). El servidor arma ese `rp.id` con `fortify.passkeys.relying_party_id` (`WorkerAccess/Support/WebAuthnCeremony.php:157` y `:285-287`; `config/fortify.php:148` = host de `app.url`), el mismo valor que `context.rpId`.
*   `vite.config.ts:15-16`: `input: ['resources/css/app.css', 'resources/js/app.tsx']`, `ssr: 'resources/js/app.tsx'`, `chunkFileNames` con hash; Vitest corre en `node` y la cobertura solo exige `resources/js/lib/**` al 90 %.
*   `tsconfig.json` incluye `resources/js/**/*.ts` sin `exclude` y sin `lib` (DOM por omisión); `package.json:12` es `"types:check": "tsc --noEmit"`. TypeScript 5.9.3 hereda `exclude` de un `tsconfig` extendido.
*   `resources/views/app.blade.php:6` y `:8` exponen `<meta property="csp-nonce">` y `<meta name="app-revision">`; `config/app.php:69`: `env('APP_REVISION') ?: 'unknown'`, y `Dockerfile:76` lo llena con `GIT_SHA` (hexadecimal, sin guiones: el nombre de la caché se parte por el último guion).
*   `app.tsx`: `globalThis.__webpack_nonce__ = cspNonce` (línea 32), bloques `if (typeof document !== 'undefined')` (40 y 116), `router.on('navigate', …)` (51) e `initializeTheme()` (85). `AppErrorBoundary` existe (`components/app-error-boundary.tsx`).
*   `frontend-architecture.ts` (116 líneas) define `overrides` con `no-restricted-imports` por carpeta (líneas 85-110).
*   Fixtures en **`tests/Fixtures/`** con mayúscula (`tests/Fixtures/attendance/mark-outcomes.json`). Vitest ya tiene `tests/js/support/`.
*   `qr` 0.7.2 no está instalado en el worktree; su API se leyó del paquete publicado: `exports` `./dom.js` y `./decode.js`; `QRCamera.readFrame(canvas): Promise<QRCanvasResult | undefined>`, `QRCanvasResult = string | DecodeResult[]`, `DecodeResult = string | Error`; `QRCanvas` sin worker por omisión. Se vuelve a comprobar al instalarlo (commit 5).
*   `WorkMode::Remote` existe en Attendance (`app/Modules/Attendance/Enums/WorkMode.php`); este segmento no lo usa.
*   Pruebas de navegador: `visitPage()` (`tests/Pest.php:502`) exige un componente de Inertia; `tests/Browser/attendance-helpers.php` (cargado en `tests/Pest.php:951`) da `contextGuid()`, `withFingerprintReader()` y `standingAt()`. `QrImage::dataUri()` existe (`app/Modules/Attendance/Support/QrImage.php:32`). `vendor/` no está instalado en el worktree: la API interna de Pest Browser 5.1.2 es NO VERIFICADO.

---

##### Lógica pura (`resources/js/lib/pwa`, cubierta por Vitest)

###### 1. `resources/js/lib/pwa/challenge.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura (reto canónico v1).
*   **Proposed Changes**:
    *   `type ChallengeFields = { tenantId; employeeId; centerId; type; qr: string | null; declaredAt: number /* unix s */; position: { latE5: number; lngE5: number; accuracyM: number } | null; agreementVersion: number; seq: number; passId: string | null; ticket: string }`.
    *   `canonicalText(fields): string | null` → `humetria-offline-mark-v1|{tenant_id}|{employee_id}|{work_center_id}|{mark_type}|{basis}|{qr_o_vacío}|{declared_at_unix}|{lat_e5}|{lng_e5}|{accuracy_m}|{agreement_version}|{seq}|{pass_id_o_vacío}|{boleto}`; `basis` = `qr_window` si hay `qr`, si no `declared_interval` (regla del contrato). `null` si algún campo contiene `|`, si un número no es entero o si falta el boleto. Sin posición, los tres campos vacíos.
    *   `positionOf(coords)`: `{ latE5: Math.round(latitude * 1e5), lngE5: …, accuracyM: Math.round(accuracy) }`. El teléfono **manda estos enteros** (lo mismo que firma) y el servidor deriva `lat = lat_e5 / 1e5`: no hay contrato de redondeo entre PHP y JS.
    *   `challengeHash(text): Promise<Uint8Array<ArrayBuffer>>` = `crypto.subtle.digest('SHA-256', new TextEncoder().encode(text))`: los 32 bytes crudos que firman la llave de acceso (como `challenge`) y la llave del dispositivo (como mensaje).

###### 2. `resources/js/lib/pwa/sw-rules.ts` (Create, commit 4)
*   **Type / Scope**: Lógica pura del service worker. Sin tipos DOM ni WebWorker (solo `URL` y cadenas) para que compile en los dos `tsconfig`; no importa nada. Es lo único que el worker importa.
*   **Proposed Changes**:
    *   `NETWORK_TIMEOUT_MS = 4_000`: un solo valor para la navegación del worker y para `punch/options` (archivo 13).
    *   `workerSlug(pathname): string | null`: el `{slug}` de `/me/{slug}` y de lo que cuelga de ahí (`/me/` solo, `/a/acme` y `/admin` → `null`). Vive aquí porque lo usan el worker y `register.ts`.
    *   `inScope(pathname, scopePath)`: `pathname === scopePath || pathname.startsWith(scopePath + '/')`. El alcance es `/me/{slug}` sin barra final: `/me/acme` queda dentro y `/me/acme-industrial` fuera.
    *   `routeOf({ method, url, mode, origin, scopePath }): 'asset' | 'navigation' | 'pass'`: no-GET → `pass`; otro origen → `pass`; `/build/assets/*` → `asset` (primero caché; se guarda al bajarlo); `mode === 'navigate'` e `inScope` → `navigation`; todo lo demás (XHR de Inertia, `sw.js`, manifest, entrega, boletos, otra empresa, `/admin`) → `pass`.
    *   `fallsBackToShell(outcome: 'network-error' | 'timeout' | number): boolean`: solo sin red o pasados 4 s; nunca ante un 4xx/5xx.
    *   `shellUrl(scope)` → `{scope}/offline`; `punchUrl(scope)` → `{scope}/punch` (el `start_url` y el destino del toque en un aviso).
    *   `shellAssets(html, origin): string[]`: rutas `/build/assets/*` de `<script type="module" src>` y `<link rel="stylesheet|modulepreload" href>` del cascarón.
    *   `cacheName(slug, revision)` → `humetria-pwa-{slug}-{revision}`; `staleCaches(keys, slug, revision)`: las que empiezan con `humetria-pwa-{slug}-`, cuyo resto no tiene guion (así `acme` no toca las de `acme-industrial`) y que no son la actual.
    *   `NOTICE = { title: 'Humetría', body: 'Tiene un aviso en Humetría' }` (commit 17): el push llega sin cuerpo y el texto es fijo.

###### 3. `resources/js/lib/pwa/queue.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura del registro de la cola y sus estados.
*   **Proposed Changes**:
    *   `QUEUE_SCHEMA = 1`; `type QueuedMark = { schema: 1; seq; employeeId; createdAt /* reloj del teléfono, solo para mostrar y para la edad de la cola */; item: DeliveryItem /* el elemento del contrato, ya firmado */; state: 'queued' | 'kept'; reason: string | null }`. No se guarda un estado «enviando»: la entrega en curso es una bandera en memoria; si la app se cierra a media entrega, la marca sigue `queued` y el servidor responde `duplicate`.
    *   Esquema (leer-viejo-escribir-nuevo): `readMark(value: unknown): QueuedMark | 'newer' | null` acepta `schema ≤ QUEUE_SCHEMA` (convierte en memoria lo viejo), devuelve `'newer'` para un esquema mayor y `null` para algo dañado; lo que se escribe siempre lleva `QUEUE_SCHEMA`. Un registro `'newer'` (pestaña con un bundle viejo) no se entrega, no se borra y no se reescribe.
    *   `forPerson(marks, employeeId)`: la pantalla solo cuenta y lista las marcas de la persona actual; la entrega sí lleva las de todas.
    *   `pending(marks)` (las `queued`, por `seq`) y `kept(marks)` (las «No aceptada»).
    *   `apply(marks, results)`: `received`, o `duplicate` con `stored_status: 'received'` (con `reason: 'already_recorded'` cuando una marca en línea de la misma persona y tipo cerca de la hora declarada ya existía: además de salir de la cola, `apply` devuelve un aviso `{seq, kind: 'already_recorded'}` que el hook expone en `notices`, y la pantalla lo muestra una vez) → sale de la cola; `rejected` (motivos guardados), `conflict` (`ticket_reused`), `duplicate` con `stored_status: 'rejected'` y los motivos solo de respuesta (`ticket_invalid`, `signature_invalid`) → `kept` con su `reason`; una marca del lote sin resultado sigue `queued`. Nada sale por un error reintentable. Una `kept` solo sale con `discard(seq)` interno, que la pantalla alcanza por `kept[i].dismiss()` (archivo 15).

###### 4. `resources/js/lib/pwa/tickets.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura de boletos, contexto y pase.
*   **Proposed Changes**:
    *   `ticketsIn(json: unknown): Ticket[]` con `Ticket = { ticket: string; issuedAt: number }` y `contextIn(json)` → `{ tenantId, employeeId, centerId, agreementVersion: number | null, rpId, offline: boolean }` o `null`: leen `tickets` y `context` de la raíz de **cualquier** respuesta firmada (marcar, semana, corregir, acuerdo), de la entrega y de `POST /me/{slug}/tickets`. `agreementVersion: null` = la persona no ha aceptado el acuerdo vigente.
    *   `passIn(json)`: `{ deviceKeyId, pass, passId, passExpiresAt }` (201 del registro y 200 de la renovación, misma forma) o `null`.
    *   `merge(stored, incoming, max = 16)`: sin duplicados por `ticket`, más nuevos primero, tope `max` (dos lotes de 8).
    *   `newest(tickets)` y `without(tickets, used)`: cada marca gasta el boleto **más nuevo** que quede (la cota más estrecha) y ese boleto se descarta. `stock(tickets)`: `'none'` (0: «Aún no está listo: ábrala con internet»), `'low'` (≤ 2: «Pocos boletos») u `'ok'`. El teléfono no poda por edad: su reloj no es evidencia y el servidor decide `ticket_expired`.
    *   `renewalDue(lastAt, now)`: `true` si pasaron ≥ 30 s desde la última petición de boletos.
    *   `passUsable(pass, nowPhone)`: `passExpiresAt` en el futuro según el teléfono (solo para avisar; el servidor compara contra `declared_at`).

###### 5. `resources/js/lib/pwa/delivery.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura del planificador de entrega.
*   **Proposed Changes**:
    *   `type DeliveryItem` = elemento del contrato: `{ seq, type, ticket, center_id, qr?, declared_at, lat_e5?, lng_e5?, accuracy_m?, device_clock_at?, justification?, agreement_version, signer: 'passkey' | 'device_key', credential?, device_key_id?, signature?, pass? }`.
    *   `JSON_HEADERS = { Accept: 'application/json', 'Content-Type': 'application/json' }`: las peticiones de entrega y de boletos los llevan siempre, con `credentials: 'omit'` y sin CSRF.
    *   `nextBatch(marks, schedule, now): QueuedMark[]`: solo si `now ≥ schedule.nextAttemptAt`; las primeras `pending` por `seq` **de una sola persona** (la de la marca más vieja: tras «¿No es usted?» la cola puede guardar marcas de dos personas y el servidor rechaza el sobre mezclado), hasta 20, o **de una en una** mientras `schedule.isolating > 0`.
    *   `body(batch)` → `{ items: batch.map((m) => m.item) }`. `seq` es local del teléfono: no da orden en el servidor.
    *   `outcomeOf(status, json, retryAfter, batchSize)`, por estado:
        1.  200 → `{ results, tickets, context }`.
        2.  422 o 413 con lote de más de uno → `split`: `isolating = batchSize` y el siguiente intento manda esas marcas una por una.
        3.  422 o 413 con una sola marca → esa marca queda `kept` con motivo `malformed` (lo asigna el cliente); la cola sigue.
        4.  429 → reintento con `Retry-After`.
        5.  403, 404, 5xx o error de red → reintento del lote entero, sin borrar nada.
    *   `delayFor(attempts, retryAfterSeconds)`: 5 s, 10 s, 20 s… tope 15 min; `Retry-After` manda si es mayor. Un 200 reinicia `attempts`.
    *   `isNetworkFailure(error: unknown)`: `TypeError` de `fetch` o `DOMException` `TimeoutError`/`AbortError`. La usa también `punch.ts` (A5b) para pasar a la cola.

###### 6. `resources/js/lib/pwa/platform.ts` (Create, commit 18)
*   **Type / Scope**: Lógica pura de capacidades de plataforma.
*   **Proposed Changes**:
    *   `isInstalled({ standaloneMedia: boolean, navigator: object })`: `display-mode: standalone` o `'standalone' in navigator && navigator.standalone === true` (iOS; con `in`, sin `as`).
    *   `inAppBrowser(userAgent): boolean`: `FBAN|FBAV|Instagram|Line/|GSA/|Outlook|MicroMessenger|; wv\)` (lista corta; un navegador interno no listado cae al camino normal).
    *   `phoneOf(userAgent, maxTouchPoints): 'iphone' | 'android' | 'other'`: reutiliza `deviceLabel()` de `lib/webauthn.ts` (iPadOS con agente de Mac y toque → `iphone`).
    *   `shouldAskPersistence(installed, persisted)` (la usa el archivo 14 en el commit 19).

###### 7. `resources/js/lib/pwa/sign-offline.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura de la ceremonia de firma local con la llave de acceso.
*   **Proposed Changes**:
    *   `offlineOptions(hash, rpId): PublicKeyCredentialRequestOptions` = `{ challenge: hash, rpId, userVerification: 'required', timeout: 60_000 }`; no pide `punch/options` al servidor. `rpId` sale de `context.rpId` al marcar; en la práctica del alta, del `rp.id` de las opciones de creación que el servidor acaba de dar (mismo valor, ver hechos verificados): nunca de `location.hostname`.
    *   `signWithPasskey(options, deps: { getKey; now: () => number }): Promise<SignedKey | 'cancelled' | 'failed'>`: usa `sign()` de `signed-request.ts` (archivo 13). Si devuelve `'cancelled'` en **menos de 2 000 ms**, reintenta **una sola vez**; un segundo fallo, o uno lento, devuelve `'cancelled'` y la pantalla ofrece el plan B. Es el **único** reintento del sistema y solo lo usa la ruta sin conexión. El reintento es una hipótesis (en Android instalado se vieron dos fallos de 1.9 s y 1.3 s) que se mide en la puerta de Android del commit 0.

###### 8. `resources/js/lib/pwa/device-key.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura del plan B (WebCrypto).
*   **Proposed Changes**:
    *   `createDeviceKey(subtle)`: `generateKey({ name: 'ECDSA', namedCurve: 'P-256' }, false, ['sign', 'verify'])` (la sobrecarga ya da `CryptoKeyPair`, sin `as`) y `publicSpki` (base64url de `exportKey('spki', publicKey)`) para el registro de A3.
    *   `signBytes(subtle, privateKey, bytes): Promise<string>`: `subtle.sign({ name: 'ECDSA', hash: 'SHA-256' }, key, bytes)` → base64url de r‖s (64 bytes). El mensaje firmado son **32 bytes crudos**: al marcar, `challengeHash` (lo que recibe `DeviceKeySignatures::verify($expected, …)`); en el registro, los bytes decodificados del reto de `device-key/options` (prueba de posesión). WebCrypto y `openssl_verify(…, OPENSSL_ALGO_SHA256)` aplican su propio SHA-256 sobre ese mensaje. No hay firma sobre texto.
    *   `isCryptoKey(value: unknown)` para leerla de IndexedDB.

###### 9. `resources/js/lib/pwa/offline-mark.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura que compone una marca sin conexión (con dependencias inyectadas).
*   **Proposed Changes**:
    *   `prepareMark(input: { type; qr; position; declaredAt; justification; useDeviceKey }, deps: { store: OfflineStore; getKey; subtle; now }): Promise<PreparedMark>`.
    *   Pasos:
        1.  Sin contexto, con `offline: false`, con `agreementVersion: null` o sin boletos → `{ kind: 'not-ready', why }`.
        2.  Toma el boleto más nuevo y el siguiente `seq`; arma texto y hash (`positionOf` para las coordenadas).
        3.  Firma con la llave de acceso (`signWithPasskey`, `rpId` del contexto) o, si `useDeviceKey`, con la llave del dispositivo (`signBytes`) y el pase (`passId` en el reto; `device_key_id`, `signature` y `pass` en el elemento). Pase vencido → `{ kind: 'pass-expired' }`; llave de acceso cancelada → `{ kind: 'cancelled', deviceKey: boolean }`.
        4.  Éxito → en **una sola transacción** del almacén borra el boleto usado, guarda la marca `queued` (con `employeeId` del contexto y `device_clock_at` = reloj del teléfono, sin firmar) y sube `seq` → `{ kind: 'saved', mark }`.

###### 10. `resources/js/lib/pwa/offline-store.ts` (Create, commit 19)
*   **Type / Scope**: Lógica pura: repositorio tipado sobre un almacén clave-valor.
*   **Proposed Changes**:
    *   `type KeyValueStore = { get(key): Promise<unknown>; entries(prefix): Promise<[string, unknown][]>; transact(puts: [string, unknown][], deletes: string[]): Promise<void> }`.
    *   `offlineStore(kv)`: `marks()`, `saveMarks()`, `discard(seq)`, `tickets()`, `saveTickets()`, `context()`, `pass()`, `savePass()`, `deviceKey()`, `practice()`, `savePractice()`, `nextSeq()`, `schedule()`; llaves `mark/{seq}`, `tickets`, `context`, `pass`, `device-key`, `practice`, `seq`, `schedule` (con `lastTicketsAt`). Todo lo leído pasa por guardas (`readMark` para las marcas); un valor dañado se ignora, nunca se fuerza.
    *   `practice`: `{ result: 'passkey' | 'device_key' | 'failed'; at }` o nada; lo escribe la práctica del alta de A5b y lo lee «Listo para marcar». Es consultivo: no bloquea marcar.
    *   `forgetPerson()` («¿No es usted?» y «Salir»): borra en el teléfono boletos, pase, llave del dispositivo, práctica y contexto; **conserva** las marcas sin enviar (ya están firmadas y no necesitan sesión). No revoca nada en el servidor.
    *   Una base por empresa: `humetria-me-{slug}`.

###### 11. `resources/js/lib/pwa/qr-scan.ts` (Create, commit 5)
*   **Type / Scope**: Lógica pura del lector de QR.
*   **Proposed Changes**:
    *   `firstText(read: QRCanvasResult | undefined): string | undefined` (`import type` de `qr/dom.js`; descarta los `Error` de la lista).
    *   `punchCode(text, { origin, slug }): { code: string } | 'other-company' | null`: `new URL` válido del mismo `origin` con `pathname === /me/{slug}/punch`, `q` presente, ≤ 128 caracteres y solo `[A-Za-z0-9._-]` → `{ code }` (sirve para el token hexadecimal de hoy y para `{windowIndex}.{hmac}` desde el commit 9; tampoco trae `|` al reto). Un `/me/{otro}/punch` → `'other-company'`. Lo demás, incluida la dirección vieja `/a/{slug}/punch`, → `null`. Un QR de **otro centro** de la misma empresa pasa: lo decide el servidor.
    *   `cameraFailure(error: unknown): 'denied' | 'unavailable' | 'failed'` (`NotAllowedError`; `NotFoundError`/`NotReadableError`/sin `mediaDevices`).

###### 12. `resources/js/lib/pwa/register.ts` (Create, commit 4)
*   **Type / Scope**: Lógica pura del registro del service worker (con el contenedor inyectado).
*   **Proposed Changes**:
    *   `registerWorkerApp({ pathname, revision, killed, container, dev }): Promise<void>`: si `dev` (Vite en caliente), sin `container` o `workerSlug(pathname)` (de `sw-rules.ts`) es `null` → nada. Si `killed` (meta `pwa-sw-kill` presente) → no registra y desregistra el existente (`container.getRegistration('/me/{slug}')` → `unregister()`); así una recarga no vuelve a instalar el worker. Si no, `container.register('/me/{slug}/sw.js?v={revision}', { scope: '/me/{slug}' })` (sin barra final: necesita `Service-Worker-Allowed: /me/{slug}` de A6). La versión sale **solo** del meta `app-revision`; un `?v=` nuevo es un script nuevo. Un fallo se traga (la app sigue en línea).
    *   `revisionOf(document)` y `killedIn(document)`: leen `meta[name="app-revision"]` y `meta[name="pwa-sw-kill"]`.

###### 13. `resources/js/lib/attendance/signed-request.ts` (Modify, commit 19)
*   **Type / Scope**: Lógica pura existente (dueño: este segmento; único lugar de los tiempos de espera del cliente).
*   **Proposed Changes**:
    *   `async function sign(...)` pasa a `export async function sign(options, { getKey }: Pick<SignDeps, 'getKey'>)`: se exporta **una vez**, sin cambiar su comportamiento; la reutiliza `signWithPasskey` (archivo 7). Aquí no hay reintento.
    *   `SignDeps` gana `timeouts?: { options: number; request: number }`; `sendSigned` pone `AbortSignal.timeout(options)` en la petición de opciones y `AbortSignal.timeout(request)` en la del cambio. `export const PUNCH_TIMEOUTS = { options: NETWORK_TIMEOUT_MS, request: 20_000 }`: 4 s solo antes de que el servidor registre nada (opciones), 20 s para `punch/store`; si se agota, la marca va a la cola. `use-punch.ts` (A5b) lo pasa en sus `deps`; las demás acciones firmadas no cambian. Queda en ~80 líneas.

##### Adaptadores, hooks y entrada `offline`

###### 14. `resources/js/hooks/use-offline-store.ts` (Create, commit 19)
*   **Type / Scope**: Hook + adaptador de IndexedDB (fuera de la cobertura de Vitest a propósito; lo cubren las pruebas de navegador).
*   **Proposed Changes**: `openIdbStore(name): Promise<KeyValueStore>` (base en versión 1 con un solo almacén `kv`; el esquema evoluciona en el campo `schema` de cada registro; `transact` en una sola transacción `readwrite`; `request.result` se asigna a `unknown` y se valida en `offline-store.ts`; guarda un `CryptoKey` no extraíble por clonación estructurada). `useOfflineStore(slug)` devuelve `OfflineStore | null` y pide `navigator.storage.persist()` una vez si `shouldAskPersistence`.

###### 15. `resources/js/hooks/use-offline-queue.ts` (Create, commit 19)
*   **Type / Scope**: Hook (contrato con A5b).
*   **Proposed Changes**: `useOfflineQueue({ slug, deliverUrl })` → **`{ pending, kept, notices, deliver }`**, filtrados a la persona del contexto guardado (sin contexto: `0` y `[]`, pero la entrega sigue):
    *   `pending: number` (sus marcas `queued`; A5b apaga «Confirmar mi semana» si es > 0).
    *   `kept: KeptMark[]` con `KeptMark = { seq, type, declaredAt, reason, dismiss: () => Promise<void> }`: las «No aceptada» siguen en el teléfono hasta que la persona toque «Entendido» o envíe «Pedir corrección», y ahí A5b llama `kept[i].dismiss()`. No hay `dismiss(seq)` en la API.
    *   `deliver: () => Promise<void>`.
    *   Entrega al montar, en `online`, en `visibilitychange` visible y con un temporizador a `nextAttemptAt`; `fetch` con `JSON_HEADERS`, `credentials: 'omit'`, sin CSRF y `AbortSignal.timeout(10_000)`; aplica `outcomeOf` y `apply` y guarda `tickets` y `context`. Una sola entrega a la vez por pestaña (bandera en `ref`); entre pestañas lo cubre la idempotencia del servidor.

###### 16. `resources/js/hooks/use-tickets.ts` (Create, commit 19)
*   **Type / Scope**: Hook.
*   **Proposed Changes**: `useTickets({ slug, ticketsUrl })` → `{ stock, refresh }`. `POST /me/{slug}/tickets` **sin sesión**: `JSON_HEADERS`, `credentials: 'omit'`, cuerpo `{ ticket: newest(tickets).ticket, queue: { count, oldest_age_minutes } }` (conteo y edad de las marcas `queued` de esa persona según el reloj del teléfono; sin `queue` si no hay). Corre al abrir, al volver a primer plano y en `online`, nunca antes de 30 s desde la anterior (`renewalDue`). Sin boletos guardados no pide nada: los primeros llegan con una acción firmada en línea (`storeTicketsFrom`). 200 → guarda `tickets` (`merge`) y `context`; 403, 404, 429 o sin red → no borra nada. `storeTicketsFrom(json)` guarda `{tickets, context}` de cualquier respuesta firmada.

###### 17. `resources/js/hooks/use-offline-mark.ts` (Create, commit 19)
*   **Type / Scope**: Hook.
*   **Proposed Changes**: `useOfflineMark(slug)` → `{ mark(input), phase: 'signing' | null, result: PreparedMark | null, busy }`; llama `prepareMark` con `getKey` de `hooks/use-punch.ts`, `crypto.subtle` y `Date.now`; el botón queda apagado mientras `busy`.

###### 18. `resources/js/hooks/use-qr-scanner.ts` (Create, commit 5)
*   **Type / Scope**: Hook.
*   **Proposed Changes**: `useQrScanner({ slug, onCode })` → `{ videoRef, start, stop, status: 'idle' | 'opening' | 'scanning' | 'other-company' | 'denied' | 'unavailable' }`. `start`: `rearCamera(video)` y `new QRCanvas()` (sin `overlay` ni `resultQR`) de `qr/dom.js`; `frameLoop(cb, video)` con bandera `busy`: `const read = await camera.readFrame(canvas)`, `firstText`, `punchCode`; un QR de otra empresa pone `other-company` y sigue leyendo; uno válido apaga bucle y cámara y llama `onCode(code)`. `stop` (y la limpieza del efecto, `pagehide` y `visibilitychange` oculto) cancelan el bucle y llaman `camera.stop()`. Importación estática de `qr/dom.js` (una dinámica dejaría un trozo sin precachear para el cascarón).

###### 19. `resources/js/components/attendance/qr-scanner.tsx` (Create, commit 5)
*   **Type / Scope**: Componente React (módulo `attendance`).
*   **Proposed Changes**: `QrScanner({ slug, onCode, onClose })`: `<video playsInline muted aria-label="Vista de la cámara">`, botón «Cerrar cámara», textos por estado en «usted» (`opening`: «Abriendo la cámara…»; `scanning`: «Apunte al código de la pantalla de su centro.»; `other-company`: «Ese código es de otra empresa.»; `denied`: «Permita el uso de la cámara para leer el código.»; `unavailable`: «Este teléfono no pudo abrir la cámara.»). Solo tokens del design system; < 100 líneas. A5b lo coloca en Marcar (en línea y sin conexión).

###### 20. `resources/js/offline.tsx` (Create, commit 4; atributos de cola en el commit 19)
*   **Type / Scope**: Entrada de bundle (sin Inertia, sin datos del servidor).
*   **Proposed Changes**: lee `#offline-app` (guarda `instanceof HTMLElement`, sin `!`) y sus `data-company` (nombre comercial: dato de la empresa, no de personas) y `data-home-url`; desde el commit 19 también `data-punch-url`, `data-deliver-url` y `data-tickets-url`. Un atributo que falta → la pantalla genérica de `AppErrorBoundary`. `slug` = `workerSlug(location.pathname)`. `globalThis.__webpack_nonce__ = cspNonce` como `app.tsx`; `initializeTheme()`; `registerWorkerApp(...)`; `createRoot(root).render(<AppErrorBoundary><OfflineApp … /></AppErrorBoundary>)`. Sin importaciones dinámicas.

###### 21. `resources/js/app.tsx` (Modify, commit 4)
*   **Type / Scope**: Entrada existente.
*   **Proposed Changes**: dentro del bloque `if (typeof document !== 'undefined')` de la línea 40 (Node renderiza este archivo para SSR): `void registerWorkerApp({ pathname: location.pathname, revision: revisionOf(document), killed: killedIn(document), container: navigator.serviceWorker, dev: import.meta.env.DEV })`, y lo mismo en el `router.on('navigate', …)` de la línea 51. Así se registra en cualquier página `/me/{slug}` (incluido el `start_url` `/me/{slug}/punch`) sin tocar cada página; el registro es idempotente.

##### Service worker

###### 22. `resources/js/sw/sw.ts` (Create, commit 4; manejadores de push en el commit 17)
*   **Type / Scope**: Fuente del service worker (TypeScript, < 120 líneas). Una sola compilación (`PWA_WORKER=sw`, de A6) a `public/build/pwa/sw.js`; no hay worker de apagado compilado (lo genera el controlador de A6).
*   **Proposed Changes**: `declare const self: ServiceWorkerGlobalScope;` (archivo módulo; sin `as`). Solo importa `@/lib/pwa/sw-rules` (archivo 27).
    1.  Arranque: `revision` = `new URL(self.location.href).searchParams.get('v') ?? 'unknown'`; `scope` = `self.registration.scope` (`https://…/me/{slug}`, sin barra); `slug` = `workerSlug(new URL(scope).pathname)`.
    2.  `install`: `fetch(shellUrl(scope), { cache: 'no-store' })`, guarda el cascarón y `shellAssets(html)` en `cacheName(slug, revision)`; `skipWaiting()`.
    3.  `activate`: borra `staleCaches(keys, slug, revision)` (solo las de **su** empresa); `clients.claim()`.
    4.  `fetch`: según `routeOf`: `asset` → caché o red (y guarda); `navigation` → red con `AbortSignal.timeout(NETWORK_TIMEOUT_MS)`, y **solo** si la red rechaza o pasan 4 s, el cascarón guardado (nunca ante 4xx/5xx); `pass` → no llama `respondWith`. Nada de HTML de Inertia ni peticiones que no sean GET en caché.
    5.  `push` (commit 17): el aviso llega sin cuerpo; `event.waitUntil(self.registration.showNotification(NOTICE.title, { body: NOTICE.body }))` (cada push muestra aviso: iOS revoca la suscripción si no).
    6.  `notificationclick` (commit 17): enfoca una ventana dentro del alcance o abre `punchUrl(scope)`.

###### 27. `frontend-architecture.ts` (Modify, commit 4)
*   **Type / Scope**: Reglas de lint del frontend.
*   **Proposed Changes**: un `override` para `resources/js/sw/**` (junto a los de las líneas 85-110): `no-restricted-imports` con `group: ['@/**', '!@/lib/pwa/sw-rules', 'qr', 'qr/**', 'react', 'react/**']`, mensaje «El service worker solo importa lib/pwa/sw-rules: su compilación debe quedar en un archivo sin import.». La prueba de la regla va en `FrontendArchitectureTest` (A6).

###### 46. `resources/js/components/attendance/offline-app.tsx` (Create, commit 4; Marcar sin conexión en el commit 19)
*   **Type / Scope**: Componente React (módulo `attendance`), raíz del cascarón.
*   **Proposed Changes**:
    *   Commit 4: `OfflineApp({ company, homeUrl })` muestra un inicio sin conexión con el nombre de la empresa, «No hay internet» y un `<a href={homeUrl}>` para reintentar. No importa nada de A5b.
    *   Commit 19: gana `slug`, `punchUrl`, `deliverUrl` y `ticketsUrl`; con `location.pathname === {scope}/punch` (el `start_url`) muestra Marcar sin conexión (`punch-offline-screen.tsx` de A5b, con los hooks de este segmento); con otra ruta del alcance, el inicio con el conteo de `useOfflineQueue` y un `<a href={punchUrl}>` a Marcar. Llama `useOfflineQueue` para entregar en cuanto vuelva la red.
    *   < 100 líneas; sin datos de personas en el HTML (todo sale de IndexedDB).

##### Pruebas

###### 29. `tests/js/support/memory-store.ts` (Create, commit 19)
*   **Type / Scope**: Ayudante de Vitest.
*   **Proposed Changes**: `memoryStore(): KeyValueStore` con `Map`; `transact` aplica todo o nada (una opción `failNext` para probar que no queda medio escrito). Vive en pruebas, no en `lib`.

###### 30. `tests/js/lib/pwa/challenge.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: cada vector de `tests/Fixtures/attendance/offline-challenge-v1.json` (A1) da la misma cadena canónica y el mismo SHA-256 que PHP; el `pass_id` va antes del boleto; `|` en cualquier campo → `null`; números no enteros → `null`; sin boleto → `null`; sin posición (tres vacíos); con y sin QR (cambia `basis`); `pass_id` vacío y presente; `positionOf` da enteros.

###### 31. `tests/js/lib/pwa/sw-rules.test.ts` (Create, commit 4)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: `workerSlug` (`/me/acme`, `/me/acme/punch`, `/me/`, `/a/acme`, `/admin`); `routeOf`: POST a `/me/acme/punch/offline` → `pass`; GET de otro origen → `pass`; `/build/assets/app-x.js` → `asset`; navegación a `/me/acme` (sin barra) y `/me/acme/punch` → `navigation`; navegación a `/me/acme-industrial/punch` y `/me/otra/punch` → `pass`; `/me/acme/sw.js`, manifest, `/me/acme/tickets` y XHR de Inertia → `pass`; `/admin` → `pass`. `fallsBackToShell`: 404, 419, 500, 503 → `false`; error de red y `timeout` → `true`. `shellUrl` y `punchUrl` de `https://h/me/acme`. `shellAssets` (script, modulepreload, stylesheet; ignora otro origen y `/favicon`). `staleCaches` con cachés de `acme` (vieja y actual), de `acme-industrial` y ajenas: solo borra la vieja de `acme`. `NOTICE` con el texto fijo (commit 17).

###### 32. `tests/js/lib/pwa/queue.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: `apply` con `received` (sale), `duplicate`+`stored_status: received` (sale), `duplicate`+`stored_status: rejected`, `rejected`, `conflict`/`ticket_reused`, `ticket_invalid` y `signature_invalid` (todas `kept` con su motivo), una marca sin resultado (sigue `queued`); `forPerson` con marcas de dos personas; `pending` ordena por `seq` y no cuenta `kept`; `readMark`: esquema 1 → marca, esquema 2 → `'newer'`, sin `schema` u objeto dañado → `null`; lo escrito lleva `schema: 1`.

###### 33. `tests/js/lib/pwa/tickets.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: `ticketsIn`/`contextIn` con respuesta sin `tickets`/`context`, con entradas dañadas y válidas, y `agreementVersion: null`; `merge` sin duplicados, más nuevo primero, tope 16 (17 entran → 16); `newest` y `without`; `stock` con 0, 2, 3 boletos; `renewalDue` a 29 s y a 30 s; `passUsable` antes, en y después de `passExpiresAt`; `passIn` con la forma `{deviceKeyId, pass, passId, passExpiresAt}` y con datos parciales → `null`.

###### 34. `tests/js/lib/pwa/delivery.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: 20 y 21 pendientes → lote de 20; marcas de dos personas → el lote solo lleva las de la marca más vieja; antes de `nextAttemptAt` → lote vacío; orden por `seq` aunque se guardaron desordenadas; `body` coincide con los elementos de `tests/Fixtures/attendance/offline-delivery-v1.json` (A1) y su respuesta se lee con `outcomeOf`; 200 con resultados mezclados; **lote venenoso**: 422 con 5 marcas → `split`, los 5 intentos siguientes van de una en una, la mala queda `kept` con `malformed` y las otras 4 se entregan; 413 igual; 403, 404, 500, 503 y error de red → nada se borra y la espera crece 5/10/20 s hasta 15 min; `Retry-After: 120` en 429 manda; un 200 reinicia la espera; `isNetworkFailure` con `TypeError`, `TimeoutError`, `AbortError` y otros errores.

###### 35. `tests/js/lib/pwa/platform.test.ts` (Create, commit 18)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: instalada por `display-mode` y por `navigator.standalone`; no instalada; agentes de Gmail iOS (`GSA/`), Outlook, Facebook, Instagram y WebView de Android (`; wv)`) → navegador interno; Safari de iPhone y Chrome de Android → no; iPadOS con agente de Mac y toque → `iphone`; `shouldAskPersistence`.

###### 36. `tests/js/lib/pwa/sign-offline.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: `offlineOptions` (reto = bytes del hash, `userVerification: 'required'`, `rpId` dado); éxito a la primera; `NotAllowedError` a 600 ms y a 1 999 ms → reintenta y firma; a 2 000 ms exactos y a 4 600 ms → `cancelled` sin reintento; dos fallos rápidos → un solo reintento y `cancelled`; otro error → `failed` sin reintento; nunca llama a `fetch`.

###### 37. `tests/js/lib/pwa/device-key.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: la llave privada no se exporta (`exportKey` rechaza); SPKI en base64url; la firma de 32 bytes mide 64 bytes y verifica con la pública; los vectores de `tests/Fixtures/worker-access/device-key-vectors.json` (A3) verifican con `subtle.verify` y el alterado no; `isCryptoKey` con valores dañados.

###### 38. `tests/js/lib/pwa/offline-mark.test.ts` (Create, commit 19)
*   **Type / Scope**: Prueba Vitest (con `memoryStore`).
*   **Proposed Changes**: sin contexto, con `offline: false`, con `agreementVersion: null` y sin boletos → `not-ready`; se gasta el boleto más nuevo y no se reutiliza en la siguiente marca; `seq` sube 1, 2, 3; falla de escritura → no se gasta el boleto ni sube `seq`; el elemento guardado tiene la forma del contrato (`signer: 'passkey'` con `credential`, `lat_e5`/`lng_e5`/`accuracy_m` enteros, `employeeId` del contexto); llave cancelada con llave del dispositivo → `cancelled` con `deviceKey: true`; plan B con pase vigente → `signer: 'device_key'`, `device_key_id`, `signature`, `pass` y `pass_id` en el reto; pase vencido → `pass-expired`; `forgetPerson` borra boletos, pase, llave, práctica y contexto y conserva la cola.

###### 39. `tests/js/lib/pwa/qr-scan.test.ts` (Create, commit 5)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: `firstText` (`undefined`, cadena, `[Error, 'x']`, solo `[Error]`); `punchCode`: válido con un token hexadecimal y con `q=123.ab12`; otro origen → `null`; `/me/otra/punch` → `'other-company'`; `/a/acme/punch` → `null`; sin `q`; `q` de 128 → sí y de 129 → no; `q` con `|` o espacio; texto que no es URL; `cameraFailure` por nombre de error.

###### 40. `tests/js/lib/pwa/register.test.ts` (Create, commit 4)
*   **Type / Scope**: Prueba Vitest.
*   **Proposed Changes**: registra `/me/acme/sw.js?v=abc` con `scope: '/me/acme'` (sin barra) desde `/me/acme/punch`; no registra en `dev`, sin contenedor ni fuera de `/me`; con `killed` no registra y desregistra el existente; un `register` que rechaza no lanza; `revisionOf` y `killedIn` con y sin `meta`.

###### 41. `tests/js/lib/attendance/punch-signed.test.ts` (Modify, commit 19)
*   **Type / Scope**: Prueba Vitest existente.
*   **Proposed Changes**: `sign` exportada: `NotAllowedError` → `cancelled` (sin reintento), otro error → `failed`, `null` → `failed`; `sendSigned` con `timeouts` pone `signal` en las dos peticiones y sin ellos no; `PUNCH_TIMEOUTS` = 4 000 y 20 000.

###### 42. `tests/Browser/pwa-helpers.php` (Create, commit 4; ayudantes de red y cámara en los commits 19 y 5)
*   **Type / Scope**: Ayudantes de pruebas de navegador.
*   **Proposed Changes**: la falta de red se simula **dentro de la página** haciendo fallar `fetch` (no se usa `setOffline` ni se exige WebAuthn virtual sin red; el autenticador virtual firma localmente igual):
    *   `visitShell(string $url, bool $dark = false, bool $mobile = false)` (commit 4): abre el cascarón `/me/acme/offline` con red, afirma `#offline-app` y corre `pageIsHealthy`.
    *   `workerReady(): string` (commit 4): script `eventually('() => navigator.serviceWorker.controller !== null')`.
    *   `withCameraShowing(AwaitableWebpage $page, string $text)` (commit 5): `$page->script()` que reemplaza `navigator.mediaDevices.getUserMedia` por un `canvas.captureStream(15)` que dibuja la imagen de `QrImage::dataUri($text)` (`img-src` admite `data:`) y guarda el `MediaStream` en `window.__camera` para afirmar que sus pistas quedaron `ended`. Se llama antes de abrir la cámara.
    *   `withoutNetwork(AwaitableWebpage $page, bool $off = true)` (commit 19): `$page->script()` que reemplaza `window.fetch` por uno que rechaza con `TypeError('Failed to fetch')` (o lo restaura), fija `navigator.onLine` y, al restaurar, dispara `online`.

###### 43. `tests/Pest.php` (Modify, commit 4)
*   **Type / Scope**: Ayudantes compartidos de Pest.
*   **Proposed Changes**: extraer de `visitPage()` (línea 500) el bloque posterior a la afirmación del componente a `pageIsHealthy(AwaitableWebpage $page, bool $mobile): AwaitableWebpage` (lo llaman `visitPage` y `visitShell`, sin duplicar el script de animaciones); junto a la línea 948, `require_once __DIR__.'/Browser/pwa-helpers.php';`. `visitPage` se comporta igual.

###### 44. `tests/Browser/WorkerAppOfflineTest.php` (Create, commit 4; casos de la cola en el commit 19)
*   **Type / Scope**: Prueba de navegador (camino crítico).
*   **Proposed Changes**:
    *   Commit 4: el cascarón `/me/acme/offline` abre sano en claro y oscuro, teléfono y escritorio (`visitShell`), sin errores de JS ni de CSP. Tras abrir `/me/acme/punch` con red: `workerReady()`; `caches.keys()` contiene `humetria-pwa-acme-{revisión}` y esa caché guarda `/me/acme/offline` y sus `/build/assets/*`. Con red, un 404 de `/me/acme/no-existe` sigue siendo 404. Con `config(['worker_access.pwa_sw_kill' => true])` y una recarga, no quedan registros ni cachés de `acme` y otra recarga no lo vuelve a registrar.
    *   Commit 19: marca sin conexión de punta a punta: lector de huella virtual (`withFingerprintReader`), boletos traídos con una marca en línea, `withoutNetwork()`, «Entrada» sin QR → «Guardada sin conexión» y contador 1; recarga con la red de la página caída → el contador sigue en 1 (IndexedDB); `withoutNetwork(false)` → se entrega sola, «Recibida», contador 0, y en la base una fila `accepted` de `attendance_offline_marks` con `offline_declared`.
    *   Commit 19: un rechazo del servidor (acuerdo no aceptado) deja la marca «No aceptada» con su motivo; «Entendido» la quita; un 500 simulado no la borra; un elemento malformado no detiene a los demás.
    *   Commit 19: «¿No es usted?» con una marca pendiente: los boletos se borran, la marca se conserva y se entrega, y la siguiente persona ve contador 0; con `PWA_SW_KILL` activo, `POST /me/acme/punch/offline` sigue respondiendo.
    *   El arranque en frío real sin red (cascarón servido por el worker) se prueba en teléfonos, no aquí.

###### 45. `tests/Browser/QrScannerTest.php` (Create, commit 5)
*   **Type / Scope**: Prueba de navegador.
*   **Proposed Changes**: desde Inicio, una navegación de Inertia a Marcar deja `document.featurePolicy.allowsFeature('camera')` en `true` (toda página `/me/{slug}/*` lleva la cabecera; la política solo permite: el navegador sigue pidiendo permiso); cámara simulada con el QR vigente de `/me/acme/punch?q=…` → lo lee, cierra la cámara (`window.__camera` con pistas `ended`) y Marcar usa el código; QR de `/me/otra/punch` → «Ese código es de otra empresa.» y sigue leyendo; QR de la dirección vieja `/a/acme/punch` → no lo toma y sigue leyendo; «Cerrar cámara» apaga las pistas; salir de la página también. Dentro del cascarón, en el commit 19.

---

##### Necesita de archivos compartidos

*   `package.json` (A6) → `dependencies`: `"qr": "0.7.2"` (exacta; aprobada por el dueño, decisión 12; commit 5). `scripts.types:check`: `"tsc --noEmit && tsc --noEmit -p resources/js/sw"` (commit 4). `scripts.build:sw` con `PWA_WORKER=sw`, encadenado a `build` y `build:ssr` (commit 4).
*   `vite.config.ts` (A6) → `input` gana `'resources/js/offline.tsx'` (commit 4); con `PWA_WORKER=sw` compila solo `resources/js/sw/sw.ts` como `iife` a `public/build/pwa/sw.js`, sin `import` (el experimento de compilación va en el commit 4).
*   `tsconfig.json` (A6) → `"exclude": ["resources/js/sw/**"]`. `resources/js/sw/tsconfig.json` (A6) → `extends` la raíz, `lib: ["ESNext", "WebWorker"]`, `types: []`, `include: ["./*.ts", "../lib/pwa/sw-rules.ts"]`, **`exclude: []`**. NO VERIFICADO que `vp lint` con `typeAware` use ese `tsconfig`.
*   `resources/views/app.blade.php` y la vista del cascarón (A6) → `<meta name="pwa-sw-kill" content="1">` cuando `worker_access.pwa_sw_kill` está activo (lo lee `killedIn`, archivo 12).
*   Vista del cascarón (A6) → `<div id="offline-app" data-company data-home-url>` en el commit 4; `data-punch-url`, `data-deliver-url` y `data-tickets-url` en el commit 19.
*   `tests/Unit/FrontendArchitectureTest.php` (A6) → un caso «rejects the service worker importing anything but its rules» que fije la regla del archivo 27 (commit 4).
*   `resources/js/hooks/use-punch.ts` (A5b) → pasa `timeouts: PUNCH_TIMEOUTS` en sus `deps` (no un envoltorio propio del `fetcher`); `resources/js/lib/attendance/punch.ts` (A5b) usa `isNetworkFailure(error)` en el `catch` para devolver `reason: 'offline'` y guarda `{tickets, context}` con `storeTicketsFrom`.
*   `resources/js/components/worker-access/leave-button.tsx` (A5b) → «Salir» llama `forgetPerson()` y no pide revocar la llave del dispositivo.

##### Dependencias con otros segmentos

*   **A1**: rutas `attendance.device.punch.offline` y `attendance.device.tickets` (las dos sin sesión ni CSRF, respuesta JSON forzada) para Wayfinder; contrato de entrega `items[]` → `{results, tickets, context}` con los estados 200/413/422/403/404/429/5xx; `{tickets, context}` en la raíz de toda respuesta firmada; vectores `offline-challenge-v1.json` y `offline-delivery-v1.json`; propiedades de Marcar `slug`, `deliverUrl`, `ticketsUrl`.
*   **A2**: propiedades de Mi semana `slug` y `deliverUrl` (para `useOfflineQueue`).
*   **A3**: registro de la llave del dispositivo solo JSON (`options` → `store`, 201 `{deviceKeyId, pass, passId, passExpiresAt}`, 403 `{proofRequired: true}`), prueba de posesión sobre los 32 bytes del reto, renovación `worker.device-key.pass` con sesión y la misma forma, vectores `device-key-vectors.json`; propiedades de Inicio `deviceKey: {optionsUrl, storeUrl, proof}`.
*   **A4**: push sin cuerpo; la suscripción la hace A5b sobre el registro de este segmento.
*   **A5b**: `punch-offline-screen.tsx` usable dentro del cascarón (props sin página de Inertia); usa `useOfflineQueue → {pending, kept, notices, deliver}` (`kept[i].dismiss()`), `useOfflineMark`, `useTickets` (`stock`) y `storeTicketsFrom`, `QrScanner`, `forgetPerson`, `practice`/`savePractice`, `isInstalled`, `inAppBrowser`, `phoneOf`, `createDeviceKey`/`signBytes`/`passIn`. La renovación del pase es un hook de A5b en Inicio (la única pantalla con sesión), con `fetch` a la ruta Wayfinder de `worker.device-key.pass`, `X-XSRF-TOKEN`, `{device_key_id}` del almacén: 200 → `savePass(passIn(json))`; 404 → borra llave y pase locales; 401/403/419 → nada. La práctica del alta usa `offlineOptions(bytes, creation.rp.id)` y hasta 3 intentos antes de guardar `failed`. El reintento de la llave de acceso es solo el de `signWithPasskey`.
*   **A6**: ver «Necesita de archivos compartidos»; el worker de apagado y la cabecera `Permissions-Policy` de `/me/{slug}/*` son suyos.

##### Riesgos y pruebas en teléfonos

*   **Worker de un solo archivo.** Un worker clásico con `import` no se activa. Mitigación: compilación `iife` aparte y el experimento del commit 4; la regla de lint del archivo 27 y la prueba de A6 (el compilado sin `import`/`export`) lo vigilan. Si `iife` falla, registrar con `{ type: 'module' }` (NO VERIFICADO en iOS 16).
*   **CSP del cascarón en caché.** La respuesta guardada trae su propia CSP con el mismo nonce que sus `<script>`, así que funciona, pero ese nonce se repite mientras dure la caché (una revisión). Es el costo de no tener scripts incrustados; A6 lo documenta.
*   **Interruptor.** `context.offline` llega con los boletos y con toda respuesta firmada: hasta la primera respuesta con red la app no sabe si está apagado, y sin contexto no prepara marcas. La entrega nunca se apaga.
*   **Boletos agotados.** Con 16 boletos como tope, un apagón con más de 16 marcas deja el teléfono en «Aún no está listo» hasta volver a tener red; con ≤ 2 la pantalla avisa «Pocos boletos».
*   **Pase de 24 h.** Quien usa la llave del dispositivo debe abrir Inicio con sesión a diario para renovar el pase; si no, sus marcas sin conexión dan `pass-expired` en el teléfono.
*   **Teléfono compartido.** Las marcas de quien dijo «¿No es usted?» se siguen entregando, pero solo esa persona ve sus «No aceptada» al volver a entrar en ese teléfono.
*   **Justificación sin firma.** La nota ≤ 500 caracteres no va en el reto: el script de la página podría cambiarla. Es texto de apoyo para RH, no evidencia.
*   **Varias pestañas.** Sin Web Locks, dos pestañas pueden entregar el mismo lote; lo absorbe la idempotencia (`duplicate`).
*   **Pruebas que solo se hacen en teléfonos reales:**
    *   Arranque en frío sin internet desde el ícono (abre `/me/{slug}/punch`) en iPhone y Android con el cascarón de producción y su CSP en caché; dos despliegues seguidos (worker viejo, servidor nuevo) y `PWA_SW_KILL`.
    *   Reintento único ante `NotAllowedError` rápido (medido en la puerta de Android del commit 0); plan B con la llave del dispositivo en iPhone.
    *   Contador de marcas sin enviar tras cerrar la app (Android) y `navigator.storage.persist()` concedido en la app instalada.
    *   Entrega al reabrir, señal intermitente con respuesta perdida (`duplicate`), espera creciente sin agotar batería, «No aceptada» hasta «Entendido».
    *   Lector de QR: pantalla con moiré, poca luz, Android viejo; la cámara se apaga al salir (indicador del sistema).
    *   Aviso «Ábralo en Safari o Chrome» en Gmail, Outlook, Facebook e Instagram reales; detección de app instalada en iPhone.
    *   Push con pantalla bloqueada: texto fijo y toque que abre `/me/{slug}/punch`.


#### Segmento A5b · Frontend: pantallas de la persona trabajadora

Segmento: pantallas y componentes de la persona trabajadora («usted»): Marcar, Mi semana, Inicio y el alta de la llave, más `leave-button.tsx`, `not-you.tsx`, los enlaces Wayfinder de estas pantallas y la guía de instalación. Textos en español de México. Verificado contra el repo (fecha 2026-11-20); lo no verificado dice NO VERIFICADO. Las rutas de Wayfinder (`resources/js/routes`, `resources/js/actions`) están en `.gitignore` y se generan; Marcar y Mi semana reciben sus direcciones como props del controlador (`MarkController::show` pasa `optionsUrl`, `punchUrl`, `weekUrl`, `enterUrl`, `agreementOptionsUrl`, `agreementUrl`…; verificado en `MarkController.php:100-109`), así que la mudanza de `a/` a `me/` en el frontend solo toca literales de pruebas. Las rutas nuevas que ningún controlador pasa (push) se usan con Wayfinder (`@/routes/...`, patrón que ya usan `impersonation-banner.tsx` y `tenant-switcher.tsx`).

Decisión de forma: **no se crea ninguna página Inertia nueva**. «Listo para marcar sin internet», «Guardada sin conexión», plan B y la cola son estados de `attendance/punch`; la pantalla de listo del alta es un estado de `worker-access/key`; la llave del dispositivo y el permiso de push viven en `worker-access/home`. Así `lib/page-frame.ts`, `tests/Unit/PageFrameTest.php` y `tests/Unit/BrowserCoverageTest.php` no cambian por este segmento.

Cámara y ubicación: los enlaces entre pantallas siguen con `Link` de Inertia. La cabecera `Permissions-Policy: camera=(self), geolocation=(self)` la pone A6 en toda respuesta de documento bajo `/me/{slug}/*`, así que la página que se cargó primero ya permite la cámara y la navegación de Inertia la conserva. La política solo permite: el navegador sigue pidiendo permiso, y solo lo pide la pantalla que llama a `getUserMedia` (el lector).

Todo componente nuevo respeta los límites de frontend de CLAUDE.md: ≤ 300 líneas por archivo, ≤ 100 por función, complejidad ≤ 10, ≤ 3 parámetros (los componentes reciben un solo objeto de props), un componente por archivo, kebab-case, imports con `@/`, sin importar componentes de otro módulo. Medidas actuales que obligan a partir archivos: `lib/attendance/week.ts` 299 líneas, `lib/attendance/punch.ts` 259, `lib/attendance/week-text.ts` 243, `hooks/use-punch.ts` 156 (la función `usePunch` va de la línea 57 al final, ~99 líneas), `components/attendance/punch-screen.tsx` 101.

Cada entrada dice en qué commit del plan entra («(commit N)»); un subcambio que cae en otro commit lo dice aparte. Las entradas eliminadas dejan su número vacío (12, 16, 20, 32, 35, 36, 37, 46, 47, 50, 51); las demás conservan su número porque otras partes las citan.

---

##### A. Marcar

###### 1. `resources/js/pages/attendance/punch.tsx` (Modify) (commit 19)
*   **Type / Scope**: Página Inertia (delgada).
*   **Proposed Changes**: Props nuevas `slug: string`, `ticketsUrl: string`, `deliverUrl: string` (de `MarkController`). Desestructura `agreementOptionsUrl` y `agreementUrl`, que ya llegan sin usarse. Cambia `usePunch(...)` por `useOfflineAwarePunch(...)` (entrada 14). Sin lógica. No hay prop de interruptor ni de acuerdo aceptado: las dos cosas llegan en `context` (`context.offline` es el único interruptor y `context.agreementVersion` es la versión que la persona aceptó, o `null`), que guarda A5a a partir de cada respuesta firmada y de la de boletos.

###### 2. `resources/js/components/attendance/punch-screen.tsx` (Modify) (commit 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Un solo `if` nuevo después de `phase !== null`: `if (punch.offline.view !== null) return <PunchOfflineScreen …/>`. Recibe `ReturnType<typeof useOfflineAwarePunch>`. Nada más (los estados sin conexión viven en la entrada 3).

###### 3. `resources/js/components/attendance/punch-offline-screen.tsx` (Create) (commit 19)
*   **Type / Scope**: Componente React (elige el estado sin conexión).
*   **Proposed Changes**: `PunchOfflineScreen({ company, view, queue, onDone, onPlanB, onAcceptAgreement })`, `view: OfflineView` = `'saved' | 'plan_b' | 'plan_b_unavailable' | 'not_ready'`. Elige `PunchOfflineSaved`, `PunchPlanB` u `OfflineReadiness`. `offline-app.tsx` (A5a) lo monta en el cascarón, así que ni él ni nada de lo que importa usa Inertia: **no** usa `AttendancePersonFrame`, porque su marco base `components/person-frame.tsx` importa `Link` de `@inertiajs/react` (línea 1, verificado); dibuja su propio encabezado con el nombre de la empresa. `onAcceptAgreement` es opcional: en el cascarón no se pasa y la fila del acuerdo dice «Abra Humetría con internet».

###### 4. `resources/js/components/attendance/punch-offline-saved.tsx` (Create) (commit 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Sustituye a «Listo» cuando la marca quedó en la cola. Título «Guardada sin conexión» y, en lugar del reloj del servidor, «Usted indicó 08:02. Aún no la recibe Recursos Humanos.» (`HH:mm` de la hora declarada: la que la persona puso en la hoja sin QR, o la del teléfono al tocar con QR). Debajo, `OfflineQueueCount` y «Si borra los datos de la app antes de que se envíe, esta marca se pierde.». Botón «Listo». `role="status"` con la frase completa. Textos de `offline-text.ts`.

###### 5. `resources/js/components/attendance/punch-plan-b.tsx` (Create) (commit 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Se muestra cuando `useOfflineMark` (A5a) devuelve `cancelled` (el reintento único ya lo hizo `signWithPasskey`). Con llave del dispositivo y pase vigente: «Su llave no respondió sin internet», «Puede marcar con este teléfono. Recursos Humanos verá que se firmó con el teléfono.», botón principal «Marcar con este teléfono» (`disabled` mientras `busy` de A5a) y secundario «Intentar con mi llave otra vez». Sin llave del dispositivo, o con `pass-expired`: «Para marcar sin internet con este teléfono, entre una vez a Humetría con internet.», sin botón de marcar.

###### 6. `resources/js/components/attendance/punch-offline-sheet.tsx` (Create) (commit 19)
*   **Type / Scope**: Componente React (hoja inferior, como `correction-sheet.tsx`).
*   **Proposed Changes**: Solo para una marca **sin QR** y sin conexión: «Hora en que marca» (`components/humetria/time-picker.tsx`, existe y no importa Inertia; prellenado con la hora del teléfono; es `declared_at`) y «¿Qué pasó? (opcional)» con `maxLength={500}` y contador (la nota `justification` de la marca; solo la lee Recursos Humanos). Bajo la hora: «El servidor la acota con lo que sí puede comprobar y Recursos Humanos la verifica.». Botón «Guardar con su huella» (`disabled` si `busy`). Con QR no se abre: la hora declarada es la del teléfono al tocar y el QR solo pone un «no antes de».

###### 7. `resources/js/components/attendance/offline-queue-count.tsx` (Create) (commit 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Banda arriba de Marcar y de Mi semana con `pending > 0` (solo las marcas de la persona actual: la cola viene filtrada por persona): «1 marca sin enviar» / «3 marcas sin enviar» (`countOf` de `lib/plural.ts`, verificado) y, sin red, «Abra Humetría con internet para enviar su marca.»; con red, «Enviando…». Tras una entrega muestra una vez «Recibida. Cuenta en sus horas. Recursos Humanos la revisará.», o «Ya estaba registrada.» cuando el servidor respondió que esa marca ya existía en línea (el teléfono ya la borró en los dos casos). `role="status"`, tono `warning` por tokens. Sin nada que decir no renderiza nada.

###### 8. `resources/js/components/attendance/offline-queue-list.tsx` y `offline-queue-item.tsx` (Create) (commit 19)
*   **Type / Scope**: Componentes React (uno por archivo).
*   **Proposed Changes**: Lista de `kept` de `useOfflineQueue` (A5a), solo de la persona actual: las marcas que el servidor no aceptó y que **se quedan en el teléfono**. Cada fila: tipo de marca, «Usted indicó 08:02», día y `Badge` «No aceptada» con `refusedText(reason)`. Dos botones: «Entendido» y «Pedir corrección», que abre `PunchCorrection` (ya existe) con `{date, time}` de la hora declarada; al enviarse la corrección con éxito, se llama `item.dismiss()` (cada elemento de `kept` trae su propio `dismiss()`). «Entendido» llama `item.dismiss()` de una vez si el servidor guardó la marca rechazada (motivos guardados: `ticket_expired`, `person_inactive`, `agreement_not_accepted`, `sequence_conflict`, `wrong_center`, `pass_expired`); si el motivo es de los que no dejan fila en el servidor (`ticket_invalid`, `signature_invalid`, `ticket_reused`, `malformed`), la fila pide confirmar en su lugar, sin diálogo: «Recursos Humanos no tiene esta marca. Si la quita, se pierde.» con «Quitarla» y «Pedir corrección». Sin red, «Pedir corrección» se apaga con «Necesita internet para pedirla». Las marcas en cola no se listan: las cuenta la entrada 7. Sin Inertia (no usa `components/humetria/confirm-dialog.tsx`, que sí importa Inertia), para poder mostrarse también en el cascarón.

###### 9. `resources/js/components/attendance/offline-readiness.tsx` y `offline-readiness-row.tsx` (Create) (commit 19)
*   **Type / Scope**: Componentes React.
*   **Proposed Changes**: Pantalla «Listo para marcar sin internet» o «Aún no está listo» con las filas de `offlineReadiness()` (entrada 18): modo activado por la empresa, acuerdo aceptado, boletos, cámara, ubicación y quién firma (llave o teléfono). Arreglo de cada fila pendiente: «Leer y aceptar el acuerdo» (`PunchAgreement` con `onAcceptAgreement`, que acepta **sin marcar**, entrada 15; en el cascarón, «Abra Humetría con internet»), «Abra Humetría con internet» (sin boletos o sin contexto), «Permitir cámara» (abre el lector), «Permitir ubicación», «Preparar este teléfono» (`<a href>` a la pestaña Cuenta del portal). Con 0 boletos el título es «Aún no está listo: ábrala con internet»; con 1 o 2, aviso «Pocos boletos: abra Humetría con internet para recibir más.». Se abre desde «¿Puedo marcar sin internet?» en `PunchReady` y como vista `not_ready` cuando se intenta marcar sin red sin boletos, sin contexto o con el modo apagado. Al pie, `InstallGuide` (entrada 38).

###### 10. `resources/js/components/attendance/punch-scan-button.tsx` (Create) (commit 5)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Botón «Leer el código de la pantalla» que monta `QrScanner` (A5a, `@/components/attendance/qr-scanner`, props `{ slug, onCode, onClose }`). Con un código leído llama `punch.useCode(q)` (entrada 17) y muestra «Código leído. Marque ahora.». No navega. Un código de otro centro de la misma empresa se acepta: el servidor decide si fue en otro centro. Oculto con `mode === 'remote'` (`PunchMode` = `'remote' | 'onsite' | null` en `lib/attendance/punch.ts:12-13`, verificado; viene de `MarkController`: horario con `WorkMode::Remote`; a quien trabaja remoto no se le pide código ni zona). En el commit 19 el mismo botón se usa también sin conexión.

###### 11. `resources/js/components/attendance/punch-ready.tsx` (Modify) (commits 5, 18 y 19)
*   **Type / Scope**: Componente React (hoy 89 líneas).
*   **Proposed Changes**: Commit 5: `PunchScanButton` antes de `PunchHint`. Commit 18: `InstallGuide` compacto al final (no se muestra si la app ya está instalada). Commit 19: `OfflineQueueCount` arriba, `OfflineQueueList` cuando `kept.length > 0`, el aviso «Pocos boletos» y el enlace «¿Puedo marcar sin internet?» (solo con `context.offline === true`). Si la complejidad pasa de 10, `vp lint` lo detecta y el componente se parte (regla de CLAUDE.md).

###### 13. `resources/js/components/attendance/punch-hint.tsx` (Modify) (commit 5)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Prop `readInApp: boolean`: con un código leído dentro de la app dice «Leyó el código de la pantalla. Marque ahora.» en lugar de «Escaneó a las…» (esa hora es del servidor y sin red no existe).

###### 14. `resources/js/hooks/use-offline-aware-punch.ts` (Create) (commit 19)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**: `useOfflineAwarePunch(page)` compone `usePunch` (en línea; entrada 17), `useOfflineMark`, `useOfflineQueue` y `useTickets` (A5a). `mark(type)`: con `context.offline !== true` → solo en línea. Si no: `navigator.onLine === false`, o el intento en línea vuelve con `reason === 'offline'` (red caída, o `punch/options` sin respuesta en 4 s) → camino sin conexión: sin QR abre la hoja (entrada 6), con QR firma de una vez. Resultado de A5a → vista: `saved` → `'saved'`; `cancelled` → `'plan_b'` con llave del dispositivo o `'plan_b_unavailable'` sin ella; `pass-expired` → `'plan_b_unavailable'`; `not-ready` → `'not_ready'`. Expone `offline: { view, pending, kept, tickets, saved }` (`kept` tal cual llega, con el `dismiss()` de cada elemento).

###### 15. `resources/js/hooks/use-agreement-accept.ts` (Create) (commit 19)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**: Acepta el acuerdo **sin marcar** (para prepararse antes de un apagón, criterio 5.1): `sendSigned({ optionsUrl: agreementOptionsUrl, url: agreementUrl, body: { agreement_version } })` contra las rutas que ya existen (`attendance.device.agreement.options` y `.accept`, verificadas en `MarkController.php:108-109`). La respuesta firmada trae `{tickets, context}` en la raíz (lo agrega `AttachOfflineTickets`): se pasa a `storeTicketsFrom(json)` de A5a, que guarda los boletos y el contexto con la versión aceptada.

###### 17. `resources/js/hooks/use-punch.ts` (Modify) (commits 5, 17 y 19)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**:
    *   Commit 5: estado interno `code` iniciado con la prop `qr` y `useCode(q)` para el código leído en la app; `punch()` usa `code`.
    *   Commit 17: al recibir una marca aceptada escribe la señal «marcó una vez» de la oferta de push (entrada 45); `forget` («¿No es usted?») llama antes `forgetPush(slug)` (mejor esfuerzo).
    *   Commit 19: (a) un `useRef<boolean>` de petición pendiente: `mark` sale sin hacer nada si ya hay una (doble toque antes del re-render; por eso no hace falta `disabled` en `PunchButton` ni en `PunchEveryStep`: mientras `phase !== null` no se dibujan). (b) Opción `offline: boolean`: con `true`, el `fetcher` que recibe `sendSigned` pone `AbortSignal.timeout(NETWORK_TIMEOUT_MS)` (4 s, de A5a) a la petición a `optionsUrl` y `AbortSignal.timeout(STORE_TIMEOUT_MS)` (20 s, de A5a) a la de `punchUrl`; los 4 s solo aplican antes de que el servidor registre nada. Sin el modo, ninguna petición lleva tiempo de espera, como hoy. (c) `forget` llama también `forgetPerson()` de A5a (borra boletos, pase, llave del dispositivo local y contexto; conserva las marcas sin enviar).
    *   Para no pasar de 100 líneas, el armado de `deps` (`fetcher`, `timeouts: PUNCH_TIMEOUTS`, `getKey` con sus fases, `xsrf`) sale a una función de módulo `punchDeps(setPhase, offline)` en el mismo archivo.

###### 18. `resources/js/lib/attendance/offline-readiness.ts` (Create) (commit 19)
*   **Type / Scope**: Lógica pura (TS).
*   **Proposed Changes**: `offlineReadiness(input: { context: { offline: boolean; agreementVersion: number | null } | null; tickets: number; camera: PermissionState | 'unknown'; location: PermissionState | 'unknown'; practice: 'passkey' | 'failed' | null; passValid: boolean; remote: boolean }): { ready: boolean; fewTickets: boolean; rows: ReadinessRow[] }`, `ReadinessRow = { key; ok; blocking; text; fix: ReadinessFix | null }`. `ready` exige contexto, `context.offline === true`, `agreementVersion !== null` y `tickets > 0`; `fewTickets` = `tickets` entre 1 y 2. La edad de los boletos la decide el servidor. La cámara no cuenta para `remote` (en el cascarón no se sabe el modo: `remote: false`). Cámara, ubicación y firmante son filas que avisan pero no bloquean (`blocking: false`): la ubicación y el QR son opcionales para el servidor, y el resultado de la práctica de firma es solo una guía (`practice === 'failed'` sin pase vigente → fila «Preparar este teléfono»). Sin contexto: «Abra Humetría con internet». `offline === false`: «Su empresa no tiene activado marcar sin internet».

###### 19. `resources/js/lib/attendance/offline-text.ts` (Create) (commit 19)
*   **Type / Scope**: Lógica pura (textos de la persona, «usted»).
*   **Proposed Changes**: Única fuente de los textos: `savedText(declared)` → «Usted indicó 08:02. Aún no la recibe Recursos Humanos.»; `receivedText()` → «Recibida. Cuenta en sus horas. Recursos Humanos la revisará.»; `alreadyRecordedText()` → «Ya estaba registrada.»; `refusedText(reason)` → «No aceptada: {nota}. Puede pedir una corrección.»; `queueCount(n)`; `OPEN_ONLINE`; `LOSS_WARNING`; `FEW_TICKETS`; `unsavedOnServer(reason): boolean` (los cuatro motivos sin fila); y `refusalNote(reason)`: `ticket_expired` → «la app llevaba más de 35 días sin internet», `person_inactive` → «su registro no está activo», `agreement_not_accepted` → «faltaba aceptar el acuerdo de registro», `sequence_conflict` → «no sigue el orden de sus otras marcas (puede ser una marca repetida)», `wrong_center` → «el centro no es el suyo», `pass_expired` → «el permiso de este teléfono para marcar ya había vencido», `ticket_invalid` → «la app no pudo comprobar su permiso para marcar sin internet», `signature_invalid` → «no se pudo comprobar su llave, o ya no era válida a esa hora», `ticket_reused` → «ese permiso para marcar ya se había usado en otra marca», `malformed` → «la marca se guardó dañada en el teléfono»; cualquier otro → «Recursos Humanos la revisará».

###### 21. `resources/js/lib/attendance/punch.ts` (Modify) (commits 2 y 19)
*   **Type / Scope**: Lógica (TS).
*   **Proposed Changes**: Commit 19: en `punch()` el `catch` (línea 193) distingue la red con `isNetworkFailure(error)` de A5a (`TypeError` de `fetch` o `DOMException` `TimeoutError`/`AbortError`): → `reason: 'offline'`; lo demás sigue `'failed'`. `MESSAGES` gana `offline`: «No hay internet. Intente de nuevo cuando tenga señal.» (solo se ve con el modo apagado; encendido, `useOfflineAwarePunch` pasa al camino sin conexión). Queda < 270 líneas. Los tiempos de espera no se tocan aquí: `use-punch.ts` (entrada 17) pasa `timeouts: PUNCH_TIMEOUTS` (de A5a) en los `deps` de `SignDeps`, y `punch.ts` solo clasifica el error con `isNetworkFailure`.

###### 22. `resources/js/components/attendance/punch-correction.tsx` (sin cambio de contrato)
*   Se reutiliza tal cual desde `offline-queue-item.tsx` y desde Mi semana para un día sin cubrir. Listado solo para dejar claro que no se crea otro formulario.

---

##### B. Mi semana: días sin cubrir y marcas sin conexión

###### 23. `resources/js/pages/attendance/week.tsx` (Modify) (commit 19)
*   **Type / Scope**: Página Inertia.
*   **Proposed Changes**: Props nuevas `slug` y `deliverUrl` (de `WeekController`, para la cola). Las pasa a `useWeek`. Sin lógica.

###### 24. `resources/js/lib/attendance/week-messages.ts` (Create) (commits 13 y 19)
*   **Type / Scope**: Lógica pura.
*   **Proposed Changes**: Commit 13: se mudan aquí `MESSAGES` y `weekMessage()` de `week.ts` (líneas 260-299) y se agrega `uncovered_days` («Le faltan marcas de días que le tocaba trabajar. Pida una corrección para agregar la marca que falta o, si no trabajó, pida a Recursos Humanos que registre su ausencia como permiso.»). Commit 19: `queued_marks` («Tiene marcas sin enviar en este teléfono. Abra Humetría con internet para enviarlas antes de confirmar.»).

###### 25. `resources/js/lib/attendance/week.ts` (Modify) (commits 13 y 14)
*   **Type / Scope**: Lógica (TS).
*   **Proposed Changes**: Commit 13: quita `MESSAGES`/`weekMessage` (sin re-exportar; los importadores cambian, entradas 29 y 30); `WeekView` gana `uncoveredDays: string[]` (fechas `Y-m-d`) y `WeekDay` gana `uncovered: boolean`. Commit 14: `WeekMark` gana `offline: boolean` y `review: 'pending' | 'verified' | 'incorrect' | null`. Parsers tolerantes como los actuales (valor desconocido → `false`/`null`/`[]`). Con la mudanza (~40 líneas fuera) y los campos nuevos queda en unas 275 líneas, bajo el tope de 300.

###### 26. `resources/js/lib/attendance/week-text.ts` (Modify) (commits 13, 14 y 19)
*   **Type / Scope**: Lógica pura.
*   **Proposed Changes**: Commit 13: `BLOCKED` (línea 109, hoy `day_open` y `no_marks`) gana `uncovered_days`; función nueva `confirmBlock(view, queued: number): { reason: string; dates: string[] } | null`: con `blocked === 'uncovered_days'` → las fechas con `dayTitle`; si no, `null`. Commit 19: con `queued > 0` → `queued_marks` (gana). Commit 14: `markReview(mark)` → `pending`: «Sin conexión · por verificar»; `verified`: «Verificada»; `incorrect`: «Recursos Humanos la corrigió» (RH solo marca «incorrecta» junto con su corrección, que anula la marca o le pone otra hora, y Mi semana ya muestra la corrección como hoy).

###### 27. `resources/js/components/attendance/week-actions.tsx` (Modify) (commits 13 y 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Con `confirmBlock(...) !== null`, «Confirmar mi semana» se muestra apagado (`disabled`, `aria-describedby`) con la línea del motivo y las fechas («Falta el miércoles 18 de noviembre»). Hoy el botón solo existe con `confirmUrl !== null` (línea 45); como el servidor no manda `confirmUrl` con la semana bloqueada, con `blocked === 'uncovered_days'` (commit 13) o `queued > 0` (commit 19, prop nueva `queued: number`) se dibuja apagado igual. «No estoy de acuerdo» sigue disponible con días sin cubrir. Con el interruptor del bloqueo apagado en el servidor, `blocked` no llega y el botón funciona como hoy.

###### 28. `resources/js/components/attendance/week-uncovered-days.tsx` (Create) (commit 13)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Tarjeta «Días sin cubrir» arriba de los días, con `week.uncoveredDays.length > 0`. Por fecha: botón «Me faltó una marca» (abre la `CorrectionSheet` existente con `correctionTarget(null)` y la fecha). Debajo, una vez: «Si ese día no trabajó, pida a Recursos Humanos que lo registre como permiso. Cuando lo registren, el día quedará cubierto.». En esta versión no hay formulario para justificar una falta.

###### 29. `resources/js/hooks/use-week.ts` (Modify) (commits 13 y 19)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**: Commit 13: importa `weekMessage` de `week-messages.ts`. Commit 19: lee `pending` de `useOfflineQueue({ slug, deliverUrl })` (A5a; solo marcas de la persona actual, así que en un teléfono compartido la cola de otra persona no apaga el botón; montarlo aquí también entrega la cola al abrir Mi semana) y lo pasa como `queued` a `WeekActions`.

###### 30. `resources/js/hooks/use-punch-correction.ts` (Modify) (commit 13)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**: Solo cambia el import de `weekMessage` (línea 7) a `@/lib/attendance/week-messages`.

###### 31. `resources/js/components/attendance/week-screen.tsx` (Modify) (commits 13 y 19)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Monta `WeekUncoveredDays` (commit 13) y `OfflineQueueCount` (commit 19). «Atrás» a Marcar sigue con `Link`.

###### 33. `resources/js/components/attendance/week-mark.tsx` (Modify) (commit 14)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: Pasa `mark.offline ? 'offline' : null` a `markHow` cuando la marca no está corregida (la línea de evidencia «Sin conexión…» la define A2 en `lib/attendance/punch-view.ts`) y, con `mark.offline`, agrega bajo la línea `how` (líneas 20-21) `markReview(mark)`.

###### 34. `resources/js/components/attendance/week-day.tsx` (Modify) (commit 13)
*   **Type / Scope**: Componente React.
*   **Proposed Changes**: `Badge` «Día sin cubrir» con `day.uncovered`. Nada más.

---

##### C. Inicio, alta, llave del dispositivo, push e instalación

###### 38. `resources/js/components/humetria/install-guide.tsx` (Create) (commit 18)
*   **Type / Scope**: Componente del design system (solo ui, hooks y lib).
*   **Proposed Changes**: Según `phoneOf`, `isInstalled` e `inAppBrowser` (A5a, `lib/pwa/platform.ts`): **iPhone**, «1. Toque Compartir. 2. Elija "Agregar a pantalla de inicio". 3. Toque "Agregar".»; **Android**, botón «Instalar la app» con el aviso del navegador (`useInstallPrompt`) o, sin aviso, «Menú ⋮ → "Instalar app"»; **navegador interno de un correo o red social**, «Ábralo en Safari o Chrome» con «Copiar el enlace» (`useClipboard`, existe). Al final: «El ícono abre Marcar.» (la app instalada empieza en `/me/{slug}/punch`). Ya instalada: no se muestra. Vive en `humetria/` porque la usan Asistencia y WorkerAccess. Sin Inertia (también se ve en el cascarón).

###### 39. `resources/js/hooks/use-install-prompt.ts` (Create) (commit 18)
*   **Type / Scope**: Hook React.
*   **Proposed Changes**: Escucha `beforeinstallprompt` (lo guarda con `preventDefault`) y `appinstalled`; `prompt()` lo lanza una vez. El evento se reconoce con una guardia de tipo (sin `as`).

###### 40. `resources/js/pages/worker-access/key.tsx`, `resources/js/hooks/use-worker-key.ts`, `resources/js/lib/worker-access/key.ts` (Modify) (commit 19)
*   **Type / Scope**: Página, hook, lógica.
*   **Proposed Changes**: `useWorkerKey.register` ya no hace `router.visit(outcome.redirect)` (hoy al final de `register`): devuelve `done: { redirect, provider, rpId }` y la página muestra `KeyReady`. `registerKey` agrega `provider` (AAGUID de `getAuthenticatorData()` con `keyProvider()`, entrada 44) y `rpId`, tomado de `rp.id` de las opciones de creación que ya pide `askOptions` (línea 48) al servidor; así la práctica usa el mismo id de la parte que confía que el servidor, sin suponer que es `location.hostname`.

###### 41. `resources/js/components/worker-access/key-ready.tsx`, `resources/js/components/worker-access/key-practice.tsx` (Create) y `resources/js/hooks/use-key-practice.ts` (Create) (commit 19)
*   **Type / Scope**: Componentes y hook: la pantalla de listo del alta.
*   **Proposed Changes**: «Su llave está lista». Aviso por AAGUID (solo pista, nunca control): llave de Google en iPhone → «Su llave quedó guardada en Google. En iPhone no sirve sin internet: prepare este teléfono abajo.». **Firma de práctica sin conexión**, opcional y solo como guía: «1. Active el modo avión. 2. Toque "Probar sin internet". 3. Quite el modo avión.»; con `navigator.onLine === true` pide primero el modo avión. La práctica usa `offlineOptions(retoAleatorio, rpId)` y `signWithPasskey` de A5a; nada va al servidor. Permite hasta 3 intentos antes de concluir que la llave no firma sin conexión. Resultado guardado con A5a (`practice: 'passkey' | 'failed'`): éxito → «Su teléfono puede marcar sin internet»; tres fallos → `DeviceKeyCard` (entrada 42). Siempre: `InstallGuide` y «Continuar» (visita `redirect`). El hook impide dos prácticas a la vez.

###### 42. `resources/js/components/worker-access/device-key-card.tsx` (Create) y `resources/js/hooks/use-device-key.ts` (Create) (commit 19)
*   **Type / Scope**: Componente y hook: registro de la llave del dispositivo (plan B), solo JSON.
*   **Proposed Changes**: Tarjeta «Marcar sin internet con este teléfono»: «Si su llave no funciona sin internet, este teléfono puede firmar sus marcas. Prepárelo ahora, con internet. Puede preparar hasta 3 teléfonos; al preparar otro se quita el que lleva más tiempo sin usarse.», botón «Preparar este teléfono» (`disabled` mientras trabaja). El hook llama `registerDeviceKey` (entrada 55). Resultados: `ready` → guarda llave, `deviceKeyId`, pase, `passId` y `passExpiresAt` con A5a (`passIn`) y muestra «Este teléfono está listo para marcar sin internet. Para seguir usándolo, entre a Humetría con internet al menos una vez al día.» (el pase dura 24 h y se renueva con sesión); `proof` (403 `{proofRequired: true}`) → abre un `Dialog` con `RecentProofContent` (existe) y el `ProofStep` de la prop `deviceKey.proof`, y al probar reintenta solo desde el principio (un reto nuevo); `session` (401) → «Su sesión terminó. Entre de nuevo.»; `failed` → «No pudimos preparar este teléfono. Intente de nuevo.». No usa `router.post` ni el flash `proofRequired`: el 403 JSON de `RequireRecentProof` (`RequireRecentProof.php:39-41`, verificado) no trae el paso de prueba, por eso viaja en la prop.

###### 43. `resources/js/components/worker-access/home-account.tsx`, `resources/js/components/worker-access/home-screen.tsx`, `resources/js/components/worker-access/home-start.tsx`, `resources/js/pages/worker-access/home.tsx` (Modify) (commits 17 y 19)
*   **Type / Scope**: Componentes y página.
*   **Proposed Changes**: Props nuevas de `HomeController` pasadas hacia abajo: `slug: string` (commit 17: Inicio monta `PushOffer`, que toma sus rutas de Wayfinder) y `deviceKey: { optionsUrl: string; storeUrl: string; proof: ProofStep } | null` (commit 19: Cuenta muestra `DeviceKeyCard` en la sección «Marcar sin internet»).

###### 44. `resources/js/lib/worker-access/aaguid.ts` (Create) (commit 19)
*   **Type / Scope**: Lógica pura.
*   **Proposed Changes**: `keyProvider(authData: ArrayBuffer): 'icloud' | 'google' | 'other'`: bytes 37-52, compara con `fbfc3007-154e-4ecc-8c0b-6e020557d7bd` (iCloud) y `ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4` (Google); en ceros o datos cortos → `other`.

###### 45. `resources/js/components/worker-access/push-offer.tsx` (Create), `resources/js/hooks/use-push-offer.ts` (Create), `resources/js/lib/notifications/push.ts` (Create) (commit 17)
*   **Type / Scope**: Componente, hook, lógica pura.
*   **Proposed Changes**: Permiso de push solo desde Inicio y solo **después de la primera marca aceptada** (señal que escribe `use-punch.ts`). `shouldOfferPush({ supported, permission, standalone, ios, markedOnce, dismissedAt, now })`: en iPhone solo con la app instalada; nunca con `denied`; «Ahora no» la esconde 14 días. Frase antes del cuadro del sistema: «¿Le avisamos cuando Recursos Humanos revise sus marcas sin internet o responda sus correcciones? El aviso solo dirá "Tiene un aviso en Humetría". En seguida su teléfono le preguntará si lo permite.» Botones «Sí, avisarme» / «Ahora no». El aviso llega cuando RH verifica una marca, cuando la marca como incorrecta (con su corrección) y cuando resuelve una corrección, siempre con el mismo texto fijo; el detalle se ve en Mi semana. El hook: `Notification.requestPermission()` → `GET` de `show.url(slug)` (`{ publicKey }`) → `pushManager.subscribe({ userVisibleOnly: true, applicationServerKey })` sobre el registro del service worker de A5a → `POST` de `store.url(slug)` con `subscriptionBody(sub.toJSON())` (`endpoint` https y `keys`). Con permiso ya concedido y sin suscripción (p. ej. tras «Salir»), se vuelve a suscribir en silencio al abrir Inicio; en un teléfono compartido el aviso llega a la última persona suscrita en él (mejor esfuerzo). `forgetPush(slug)`: `DELETE` de `destroy.url(slug)` con el `endpoint` y `unsubscribe()`, mejor esfuerzo (lo llaman «Salir» y «¿No es usted?»). Las rutas salen de Wayfinder (`@/routes/portal/push`: `show`, `store`, `destroy`, nombres `portal.push.*` de A4; el módulo exacto lo fija `wayfinder:generate`, NO VERIFICADO hasta que exista la ruta). La señal «marcó una vez» y «Ahora no» viven en `localStorage` (un booleano y una fecha, ningún dato de la persona), cada acceso en `try/catch`.

---

##### D. Acuerdo de registro

El texto del acuerdo (`CurrentAgreement`) y su prueba Feature son solo de A1. Este segmento solo prueba que la pantalla de Marcar lo muestra (ver «Necesita → `tests/Browser/AttendancePunchTest.php`»).

---

##### E. Pruebas

###### 48. Vitest nuevos (`tests/js/lib/...`) (Create)
*   `tests/js/lib/attendance/offline-readiness.test.ts` (commit 19): todo listo; cada bloqueo solo (sin contexto, `offline: false`, `agreementVersion: null`, 0 boletos); 1 y 2 boletos → `fewTickets`, 3 → no; filas que no bloquean (cámara negada, ubicación `prompt`, práctica fallida sin pase); persona remota sin fila de cámara.
*   `tests/js/lib/attendance/offline-text.test.ts` (commit 19): textos exactos («Guardada sin conexión», «Usted indicó 08:02. Aún no la recibe Recursos Humanos.», «Recibida. Cuenta en sus horas. Recursos Humanos la revisará.», «Ya estaba registrada.», «No aceptada: … Puede pedir una corrección.», «Abra Humetría con internet para enviar su marca.»); 0, 1 y 2 marcas sin enviar; los diez motivos tienen nota y uno desconocido cae al genérico; `unsavedOnServer` verdadero solo para `ticket_invalid`, `signature_invalid`, `ticket_reused` y `malformed`; ningún texto tutea (`/\b(tu|tienes|puedes|abre)\b/i` no aparece).
*   `tests/js/lib/attendance/week-messages.test.ts` (commit 13): las pruebas de `weekMessage` mudadas de `week.test.ts`, más `uncovered_days` (nombra corrección y permiso de Recursos Humanos); `queued_marks` en el commit 19.
*   `tests/js/lib/notifications/push.test.ts` (commit 17): `shouldOfferPush` (iPhone no instalada → no; `denied` → no; sin primera marca → no; «Ahora no» hace 13 días → no, 14 → sí); `subscriptionBody` rechaza endpoint `http:` y llaves faltantes.
*   `tests/js/lib/worker-access/aaguid.test.ts` (commit 19): iCloud, Google, ceros, `authData` corto.
*   `tests/js/lib/worker-access/device-key-registration.test.ts` (commit 19): 201 → `ready` con `{deviceKeyId, pass, passId, passExpiresAt}`; 403 `{proofRequired: true}` en `options` y en `store` → `proof`; 401 → `session`; 422, 5xx y error de red → `failed`; 201 sin `passId` → `failed`; `signBytes` recibe los 32 bytes decodificados del reto y el cuerpo de `store` lleva `public_key`, el reto y la firma.

###### 49. Vitest existentes (Modify)
*   `tests/js/lib/attendance/punch.test.ts`: literales `/a/acme/punch` → `/me/acme/punch` (commit 2, sin efecto funcional); `fetch` que lanza `TypeError` → `reason: 'offline'`, `DOMException` `TimeoutError` → `'offline'`, otro error → `'failed'` (commit 19).
*   `tests/js/lib/attendance/week.test.ts`: `uncoveredDays` y `uncovered` leídos y sin las pruebas de `weekMessage` (commit 13); `offline` y `review` (commit 14); valores raros → `false`/`null`/`[]`.
*   `tests/js/lib/attendance/week-text.test.ts`: los objetos `WeekDay` y `WeekView` de las líneas 12 y 22 ganan `uncovered: false` y `uncoveredDays: []` (si no, `types:check` falla, porque el `tsconfig.json` raíz incluye `tests/js/**/*.ts` con `strict: true`); `confirmBlock` con `uncovered_days` de 1 y 3 fechas y sin bloqueo → `null` (commit 13); `markReview` en sus tres estados (commit 14); cola > 0 gana (commit 19).
*   `tests/js/lib/attendance/week-view.test.ts` (líneas 16 y 26) y `tests/js/lib/attendance/week-clock.test.ts` (el `WeekView` de la línea 33 en adelante): los mismos campos en sus objetos tipados (commit 13); `offline: false` y `review: null` en las marcas tipadas (commit 14).
*   `tests/js/lib/worker-access/key.test.ts` (commit 19): `registerKey` devuelve `provider` y el `rpId` de las opciones.

###### 52. `tests/Browser/WorkerEntryTest.php` (Modify) (commits 17, 18 y 19)
*   **Proposed Changes**: La falta de red se simula haciendo fallar `fetch` dentro de la página; no se exige WebAuthn virtual sin red. Commit 19: tras crear la llave aparece «Su llave está lista»; la práctica pide el modo avión si hay red; con `fetch` fallando y el lector virtual firma → «Su teléfono puede marcar sin internet»; tres fallos → tarjeta «Preparar este teléfono». Cuenta: «Preparar este teléfono» sin prueba reciente abre «Confirme que es usted» y, tras probar, registra solo y dice «Este teléfono está listo…». «Salir» no revoca la llave del dispositivo en el servidor (la fila sigue sin `revoked_at`). Commit 17: «Salir» manda el `DELETE` de push; sin marca previa no hay oferta de push; con la señal aparece la frase antes del cuadro del sistema (permiso concedido con el contexto de Playwright; `subscribe` con un doble en la página, porque `pushManager` real no está disponible en la prueba: NO VERIFICADO). Commit 18: guía de instalación con agente de iPhone y de un navegador interno: «Ábralo en Safari o Chrome».

---

##### F. Salir, «¿No es usted?» y la llave del dispositivo

###### 53. `resources/js/components/worker-access/leave-button.tsx` (Modify) (commits 17 y 19)
*   **Type / Scope**: Componente React (hoy `router.post(url)`).
*   **Proposed Changes**: Prop nueva `slug`. Al tocar «Salir»: `await forgetPush(slug)` (commit 17, mejor esfuerzo), `forgetPerson()` de A5a (commit 19: borra la llave del dispositivo **local**, el pase, los boletos y el contexto; conserva las marcas sin enviar) y luego `router.post(url)` como hoy. No manda `device_key_id` ni pide revocar nada: «Salir» no revoca la llave del dispositivo en el servidor, así las marcas ya firmadas con ella se siguen aceptando. Botón `disabled` mientras trabaja.

###### 54. `resources/js/components/attendance/not-you.tsx` (sin cambio)
*   Sigue siendo solo el botón; lo nuevo de «¿No es usted?» (push y `forgetPerson`) está en `forget` de `use-punch.ts` (entrada 17). Listado porque el plan lo asigna a este segmento.

###### 55. `resources/js/lib/worker-access/device-key-registration.ts` (Create) (commit 19)
*   **Type / Scope**: Lógica pura con dependencias inyectadas (cubierta por Vitest, entrada 48).
*   **Proposed Changes**: `registerDeviceKey(urls: { optionsUrl; storeUrl }, deps: HttpDeps & { subtle; label }): Promise<{ kind: 'ready'; key; deviceKeyId; pass; passId; passExpiresAt } | { kind: 'proof' } | { kind: 'session' } | { kind: 'failed' }>`. Pasos: `postJson(optionsUrl)` (reutiliza `lib/worker-access/http.ts`) → reto; `createDeviceKey(subtle)` de A5a (par P-256 no extraíble y `publicSpki`); `signBytes(subtle, privateKey, bytes)` de A5a sobre los **32 bytes crudos** del reto (decodificado de base64url) como prueba de posesión; `postJson(storeUrl, { public_key, challenge, signature, label })`. 201 leído con `passIn` de A5a → `ready`; 403 con `proofRequired: true` (en cualquiera de los dos pasos) → `proof`; 401 → `session`; lo demás → `failed`. Sin `as`.

---

##### Necesita de archivos compartidos

*   `app/Modules/Attendance/Http/Controllers/MarkController.php` (A1): props `'slug' => $tenant`, `'ticketsUrl' => route('attendance.device.tickets', $tenant)` y `'deliverUrl' => route('attendance.device.punch.offline', $tenant)`. Nada más: sin `offlineEnabled` ni `agreementAccepted`. `context.agreementVersion` (versión vigente si la persona la aceptó, si no `null`) y `context.offline` llegan con `{tickets, context}` en cada respuesta firmada y en la de boletos.
*   `tests/Browser/AttendancePunchTest.php` (entrada del dueño del archivo; A1): agregar los casos de estas pantallas, con la falta de red simulada haciendo fallar `fetch` dentro de la página (el arranque en frío sin red se verifica en teléfonos): (commit 5) leer el código en la app → «Código leído. Marque ahora.» y la marca usa ese código; (commit 6) el acuerdo abierto desde Marcar ya no dice «La hora la pone el servidor, no su teléfono» y muestra la frase nueva que fije A1; (commit 18) guía de instalación compacta en Marcar; (commit 19) sin red, marca con QR → «Guardada sin conexión», «Usted indicó», «1 marca sin enviar», sin el reloj grande; vuelve la red → «Recibida…» y el contador desaparece; respuesta `duplicate` con `stored_status: received` → «Ya estaba registrada.»; entrega rechazada (`agreement_not_accepted`) → «No aceptada: …» sigue tras recargar, «Entendido» la quita y «Pedir corrección» abre la hoja y al enviarse la quita; `ticket_invalid` → «Entendido» pide confirmar antes de quitarla; un 500 en la entrega no quita nada; llave que no firma → «Marcar con este teléfono»; doble toque rápido en «Entrada» manda una sola marca; «¿Puedo marcar sin internet?» muestra «Aún no está listo: ábrala con internet» sin boletos, «Pocos boletos» con 2 y «Listo para marcar sin internet» con todo; cero errores de JS/CSP y axe limpio en cada estado nuevo, 375 px, claro y oscuro.
*   `tests/Browser/AttendanceWeekTest.php` (entrada del dueño del archivo; A2 o A1, según quede): (commit 13) semana con un miércoles laborable sin marcas → «Confirmar mi semana» presente y `disabled`, con «Falta el miércoles…», la tarjeta «Días sin cubrir» y su texto de pedir permiso a Recursos Humanos; «Me faltó una marca» abre la hoja de corrección con esa fecha; «No estoy de acuerdo» sigue activo; (commit 14) una marca sin conexión se lee «Sin conexión · por verificar» y, revisada, «Verificada» o «Recursos Humanos la corrigió»; (commit 19) con una marca de esa persona en la cola local el botón se apaga con el texto de marcas sin enviar, y con una marca de otra persona en la cola, no. 375 px, claro y oscuro.
*   `app/Modules/Attendance/Support/CurrentAgreement.php` (A1): el texto completo vive allí; este segmento no propone párrafos.
*   Week JSON y `WeekController.php` (A2): props `slug` y `deliverUrl`; en la semana, `uncoveredDays` (lista de fechas), `days[].uncovered`, `marks[].offline`, `marks[].review` (`pending` para **toda** marca sin conexión sin decisión de RH; `verified` o `incorrect` según la última), `blocked: 'uncovered_days'` (solo con el bloqueo encendido); el 422 de confirmar con `uncovered_days` trae `week`. La línea de evidencia «Sin conexión…» de `markHow` en `lib/attendance/punch-view.ts` es de A2.
*   `app/Modules/WorkerAccess/Http/Controllers/HomeController.php` (A3): props `slug` y `deviceKey: { optionsUrl, storeUrl, proof }` (`proof` = `ProofSteps::for($session, $slug)`, porque el 403 JSON no trae el paso). Registro: `POST worker.device-key.options` → `{ challenge }` (base64url de 32 bytes); `POST worker.device-key.store` con `{ public_key, challenge, signature, label }`, firma ECDSA sobre los 32 bytes crudos → 201 `{deviceKeyId, pass, passId, passExpiresAt}`; la cuarta llave reemplaza la que lleva más tiempo sin usarse; `EntryController::leave` no revoca llaves del dispositivo.
*   A4: rutas `portal.push.show` (`GET` → `{ publicKey }`), `portal.push.store` y `portal.push.destroy` bajo `me/{slug}/push` (commit 16), que este segmento usa por Wayfinder en el commit 17.
*   A5a: `useOfflineQueue({ slug, deliverUrl }) → { pending, kept, notices, deliver }`, filtrado por la persona del contexto, con `kept[i] = { seq, type, declaredAt, reason, dismiss() }`; los `kept` incluyen `ticket_invalid`, `signature_invalid`, `conflict` (`ticket_reused`), `malformed` y `duplicate` con `stored_status: rejected`; una señal de la última entrega para decir «Recibida» o «Ya estaba registrada» (los `duplicate` con `stored_status: received` sin fila); `useOfflineMark` (resultados `saved | cancelled | pass-expired | not-ready`, `busy`); `useTickets` con el conteo de boletos y `context`; `storeTicketsFrom` (guarda `{tickets, context}`); `QrScanner` con el texto de `wrong` «Ese código no es de su empresa.» (otro centro de la misma empresa es válido); `signWithPasskey` y `offlineOptions(hash, rpId)`; `createDeviceKey`, `signBytes` y `passIn`; campo `practice` del almacén; `forgetPerson()`; `isNetworkFailure`; `NETWORK_TIMEOUT_MS` (4 s) y `STORE_TIMEOUT_MS` (20 s) exportados desde un solo archivo; `isInstalled`, `inAppBrowser`, `phoneOf`; la renovación del pase con sesión al abrir `/me` (`usePassRenewal`) la monta A5a. `offline-app.tsx` (de A5a, también su versión mínima del commit 4) monta `PunchOfflineScreen`, `OfflineQueueList` e `InstallGuide` en el commit 19, ninguno con Inertia.
*   A6: la cabecera `Permissions-Policy: camera=(self), geolocation=(self)` en toda respuesta de documento bajo `/me/{slug}/*` (commit 5); nada de páginas Inertia nuevas.

##### Riesgos y pruebas en teléfonos

*   **Dónde ve la persona cada estado**: «Guardada sin conexión» y «No aceptada» (con su motivo, hasta «Entendido» o «Pedir corrección») en Marcar, desde el teléfono; «Recibida» o «Ya estaba registrada» en la banda de Marcar o Mi semana justo después de la entrega (el teléfono ya la borró); «Sin conexión · por verificar», «Verificada» y «Recursos Humanos la corrigió» en Mi semana, desde el servidor. El push no dice cuál: solo «Tiene un aviso en Humetría», para la verificación, para «incorrecta» con su corrección y para una corrección resuelta.
*   **Reloj sin red**: `useServerNow` sigue el desfase calculado al cargar; en el cascarón no hay `now`. Las vistas sin conexión no muestran reloj: solo «Usted indicó HH:MM».
*   **Boletos**: cada marca gasta uno; con 0 no se puede marcar sin internet y la pantalla lo dice; con 1 o 2 avisa. Quien pasa días sin abrir la app con internet se queda sin boletos.
*   **Pase de 24 h**: quien depende del teléfono para firmar (plan B) debe entrar a Humetría con internet y con sesión una vez al día; la tarjeta lo dice.
*   **Teléfono compartido**: la cola se filtra por persona y «Salir» borra lo local de la persona, pero el aviso de push llega solo a la última persona suscrita en ese teléfono (mejor esfuerzo).
*   **Práctica sin conexión**: depende de que la persona active el modo avión; `navigator.onLine` puede mentir en Android con wifi sin internet (peor caso: prueba con red y sale «puede»). El resultado es solo una guía; la puerta de Android del commit 0 decide si la llave de acceso firma sin conexión. El bloqueo de pantalla no tiene API web.
*   **Pruebas en teléfonos reales** (se juntan con la lista única del plan): iPhone instalado y Android, marcar en modo avión con QR y sin QR, ver «Guardada sin conexión» y el contador, cerrar y reabrir y que el contador siga; un doble toque no manda dos marcas; plan B en iPhone con llave de Google (práctica falla tres veces, «Preparar este teléfono», «Marcar con este teléfono»); una marca «No aceptada» sigue tras cerrar la app; el ícono instalado abre Marcar; guía de instalación en Safari, Chrome Android y el navegador interno de Gmail/Outlook; push con pantalla bloqueada («Tiene un aviso en Humetría»); Inicio → Marcar por Inertia abre la cámara al tocar «Leer el código de la pantalla»; legibilidad a 375 px, claro y oscuro, con letra grande del sistema.


#### Segmento A6 · Plataforma y archivos compartidos

Raíz: `.claude/worktrees/pwa-offline-qr-reader-0077c1` (Laravel `v13.42.0` según `composer.lock`). Verificado contra el repo
(fecha 2026-11-20): todo lo marcado «Modify» se abrió y se cita con su línea; todo «Create» se comprobó que no existe. Lo no
verificado dice NO VERIFICADO con la comprobación que falta. Los números de entrada no son consecutivos (los que ya no aplican no
se reusan). Cada entrada dice su commit; un subcambio que cae en otro commit lo dice entre paréntesis: «(commit N)».

**Capa HTTP de la app instalable.** El controlador, las vistas y sus pruebas viven en `WorkerAccess` (ya es dueño de `/me/{slug}`
y de `ResolveWorkerTenant`); las rutas son de `routes/worker-access.php` (A3). Manifest, service worker y cascarón no importan nada
de Attendance: el cascarón solo nombra rutas de Attendance por su nombre. Las rutas se llaman `worker.app.*`, caen en
`ErrorPage::WORKER_ROUTES` (`worker.*`, ya existe) y hablan de «usted».

**Rutas sin sesión: el motivo.** La entrega de marcas, los boletos y las tres rutas de la app instalable van en un grupo
`withoutMiddleware` como `routes/complaints-public.php:31-42` (sin cookies, sesión ni CSRF) con `RejectCrossSiteRequests` por ruta.
La entrega y los boletos, porque tras un apagón la sesión y su cookie pueden no existir o haber vencido, y los navegadores sin
Fetch Metadata (iOS anterior a 16.4) no mandan `Sec-Fetch-Site`, así que el atajo de Laravel (`PreventRequestForgery::hasValidOrigin`,
líneas 143-160 del vendor, leído en la copia principal: acepta `Sec-Fetch-Site: same-origin` sin token) no basta; la defensa es la
firma, el boleto de un solo uso y los límites. La app instalable, porque con `SESSION_DRIVER=database` cada petición sin cookie
escribiría una fila en `sessions` (dirección y navegador), y cada teléfono instalado revisa el worker en cada navegación. No hay
exención por ruta en `bootstrap/app.php`.

---

##### Capa HTTP de la app instalable (WorkerAccess)

###### 1. `app/Modules/WorkerAccess/Http/Controllers/InstallableAppController.php` (Create) — commits 3 y 4
*   **Type / Scope**: Controlador PHP (`final readonly class`), tres métodos. Recibe `Companies` (contrato público de Tenancy).
*   **Proposed Changes**:
    *   `manifest(string $tenant): JsonResponse` (commit 3): `Content-Type: application/manifest+json`. `id` = `/me/{slug}/punch`;
        `scope` = `/me/{slug}`; `start_url` = `/me/{slug}/punch` (el ícono abre Marcar, no la entrada) si
        `Companies::current()->allows('attendance')`, y `/me/{slug}` si no (Marcar respondería 404 sin el módulo). `name` =
        `tradeName`, `short_name` = `Str::limit($name, 12, '')`, `lang: es-MX`, `dir: ltr`, `display: standalone`,
        `background_color` y `theme_color` = `#FBFDFC` (constante de la clase; el mismo valor que `app.blade.php:33`), `icons`:
        `/apple-touch-icon-180.png` (180, `any`), `/app-icon-192.png` (192, `any`), `/app-icon-512.png` (512, `any`),
        `/app-icon-maskable-512.png` (512, `maskable`). Nada de nadie: solo el nombre comercial que ya muestra la entrada.
    *   `serviceWorker(string $tenant): Response` (commit 4): el cuerpo es `public/build/pwa/sw.js` (38) tal cual; la versión llega
        en la dirección (`?v=` con `<meta name="app-revision">`, la pone el registro de A5a) y el controlador la ignora. Con
        `config('worker_access.pwa_sw_kill')` en `true` el cuerpo es un **worker de apagado que genera el propio controlador**
        (una cadena, sin compilación): `install` → `skipWaiting()`; `activate` → borra las cachés cuyo nombre empieza con
        `humetria-pwa-{slug}-` (el slug va con `json_encode`), `self.registration.unregister()` y `client.navigate(client.url)` en
        cada ventana abierta; sin manejador `fetch`. Encabezados: `Content-Type: text/javascript; charset=utf-8`,
        `Cache-Control: no-cache`, `Service-Worker-Allowed: /me/{slug}`. La CSP, el `Permissions-Policy` y
        `X-Robots-Tag: noindex, nofollow` los pone `SecurityHeaders`.
    *   `offline(string $tenant): Response` (commit 4): `view('worker-access.offline', [...])` con `company` (`tradeName`),
        `homeUrl` (`route('worker.home', $tenant)`) y `swKill`. (commit 19) Con el módulo `attendance`, además `punchUrl`
        (`attendance.device.punch.show`), `deliverUrl` (`attendance.device.punch.offline`) y `ticketsUrl`
        (`attendance.device.tickets`); sin el módulo van vacíos y la app sin conexión solo muestra el inicio. Nunca persona,
        sesión, token CSRF ni la hora.
    *   Lee solo `Companies`, `config('worker_access.pwa_sw_kill')` y `File`: sin internos de otros módulos (`ArchitectureTest`).

###### 2. `resources/views/worker-access/offline.blade.php` (Create) — commit 4
*   **Type / Scope**: Vista Blade (cascarón estático que el service worker guarda en `{scope}/offline`).
*   **Proposed Changes**: documento completo `<!DOCTYPE html>`, `lang="es-MX"`, `<meta charset>`, el viewport de `app.blade.php`
    (línea 5), `<meta property="csp-nonce">` y `<meta name="app-revision">` como `app.blade.php` (líneas 6 y 8),
    `<meta name="pwa-sw-kill" content="1">` solo con el apagado activo, `<title>{{ $company }}</title>`, `<link rel="manifest">` a
    `worker.app.manifest`, `<link rel="apple-touch-icon" href="/apple-touch-icon-180.png">`,
    `@vite(['resources/css/app.css', 'resources/js/offline.tsx'])` y un único `<div id="offline-app" data-company data-home-url>`;
    (commit 19) suma `data-punch-url`, `data-deliver-url` y `data-tickets-url`. Sin analítica (`/me` no se mide), sin
    `@viteReactRefresh` y **ningún `<script>` sin `src`**: el modo oscuro lo resuelve el bundle `offline` (`initializeTheme()`, A5a;
    en el commit 4 `offline.tsx` es mínimo y solo pinta el inicio). `@fonts` solo si no emite código en línea (NO VERIFICADO: lo
    decide la prueba 6; si lo emite, el cascarón usa la fuente del sistema). Textos en «usted».

###### 6. `tests/Feature/WorkerAccess/InstallableAppTest.php` (Create) — commits 3, 4, 11 y 19
*   **Type / Scope**: Prueba Feature (Pest), `mutates(InstallableAppController::class)`.
*   **Proposed Changes** (casos):
    *   Manifest (commit 3): 200, `application/manifest+json`, `id` = `/me/acme/punch`, `scope` = `/me/acme`, `start_url` =
        `/me/acme/punch` con el módulo `attendance` y `/me/acme` sin él; `name` = nombre comercial, `lang` `es-MX`,
        `display: standalone`, cuatro íconos y **cada archivo existe en `public_path()`**; empresas A y B dan nombres y
        direcciones distintos; con la cookie de sesión de una persona el manifest es idéntico al de un visitante, no trae su
        nombre, no responde `Set-Cookie` y no agrega filas a `sessions`; empresa desconocida y bloqueada: el mismo 404.
    *   Service worker (commit 4): 200, `text/javascript`, `Cache-Control` contiene `no-cache`, `Service-Worker-Allowed: /me/acme`,
        `X-Robots-Tag: noindex, nofollow`, CSP presente; el cuerpo es igual a `public/build/pwa/sw.js` y **no tiene `import` ni
        `export`** (resolverían contra `/me/acme/` y el worker clásico no se activaría); `?v=abc` no cambia el cuerpo; sin
        `Set-Cookie`. Con `config(['worker_access.pwa_sw_kill' => true])`: el cuerpo trae `unregister`, `caches.delete` y
        `humetria-pwa-acme-`, no trae `addEventListener('fetch'` ni el compilado, y el de la empresa B nombra `humetria-pwa-b-`.
        (commit 11) Con el apagado activo la entrega sigue respondiendo (un POST de lote vacío a `attendance.device.punch.offline`
        da 422, no 404/503).
    *   Cascarón (commit 4): 200 HTML; ningún `<script>` sin `src` (XPath, como `PageMetaTest::xpathOf`, línea 31); los `src`
        apuntan a `/build/assets/`; `data-company` y `data-home-url` presentes; `meta[name=pwa-sw-kill]` solo con el apagado
        activo; con la cookie de una persona no aparece su nombre ni un token CSRF (`_token`, `csrf-token`); sin `Set-Cookie`;
        empresa desconocida, 404. (commit 19) Con el módulo `attendance`, `data-punch-url`, `data-deliver-url` y
        `data-tickets-url` con las rutas de A1; sin él, vacíos.

###### 7. `resources/views/app.blade.php` (Modify) — commits 3 y 4
*   **Type / Scope**: Vista Blade del documento de Inertia.
*   **Proposed Changes**: junto a `<link rel="apple-touch-icon">` (línea 51), solo cuando
    `request()->is('me/*') && ($page['component'] ?? '') !== 'error' && is_string($slug = request()->route('tenant'))`:
    `<link rel="manifest" href="{{ route('worker.app.manifest', $slug) }}">` (commit 3) y, con
    `config('worker_access.pwa_sw_kill')`, `<meta name="pwa-sw-kill" content="1">` (commit 4: el registro de A5a lo lee, no
    registra el worker y desregistra el que haya). No se ensancha la CSP (`default-src 'self'` cubre `manifest-src`). No cambia
    `<meta name="app-revision">` (línea 8) ni el `@vite`.

###### 8. `tests/Feature/PageMetaTest.php` (Modify) — commits 3 y 4
*   **Type / Scope**: Prueba Feature.
*   **Proposed Changes**: (commit 3) «links the manifest of its company only in the person's pages»: `/me/acme` (sin sesión) y
    Marcar (`/me/acme/punch`) traen `link[rel=manifest]` con `/me/acme/manifest.webmanifest`; con dataset, ningún enlace en `/`,
    `/privacy-notice`, `/admin` (con usuario), la pantalla del quiosco `a/{slug}/kiosk` ni el 404 de `/me/no-existe`. (commit 4)
    «tells the person's pages the worker is switched off»: `meta[name=pwa-sw-kill]` en `/me/acme` solo con el apagado activo.

###### 9. Íconos: `public/app-icon-192.png` y `public/app-icon-maskable-512.png` (Create) — commit 3
*   **Type / Scope**: Archivos binarios.
*   **Proposed Changes**: generados una vez desde `public/app-icon.svg` (existe), junto a `public/app-icon-512.png` y
    `public/apple-touch-icon-180.png` (existen). El maskable deja el logo dentro del 80 % central sobre `#FBFDFC`. Sin
    dependencias nuevas (se exportan con una herramienta local; no entran al build). **Pendiente del dueño**: aprobar el dibujo.
    La prueba 6 exige que existan.

---

##### Seguridad HTTP

###### 10. `app/Http/Middleware/SecurityHeaders.php` (Modify) — commit 5
*   **Type / Scope**: Middleware (global, primero de la pila).
*   **Proposed Changes**: hoy el `Permissions-Policy` es `camera=(), microphone=(), geolocation=(), payment=(), usb=()` para todo
    (línea 60) y se conserva el que la respuesta ya trae (líneas 75-79). Regla nueva: toda respuesta a un `GET` bajo `me/*`
    (`$request->isMethod('GET') && $request->is('me/*')`) lleva
    `camera=(self), microphone=(), geolocation=(self), payment=(), usb=()`; el resto de la app sigue cerrado. La política solo
    **permite**: el navegador sigue pidiendo permiso, y una página que no llama a `getUserMedia` ni a la ubicación no pide nada.
    Así una navegación de Inertia dentro de `/me` (Inicio → Marcar, Mi semana → Marcar) ya tiene cámara y ubicación sin enlaces
    de documento completo, y se arregla la ubicación de hoy (`home-sections.tsx` llega a Marcar con `<Link>`, y el encabezado de
    `MarkController::show` solo vale en una carga completa). `MarkController.php:119` deja de poner el suyo (A1; si no, la regla
    «se conserva el de la respuesta» lo dejaría con `camera=()`). El de `AttendanceController.php:63` (mapa del panel) no cambia.

###### 11. `tests/Feature/SecurityHeadersTest.php` (Modify) — commit 5
*   **Type / Scope**: Prueba Feature.
*   **Proposed Changes**: caso nuevo «opens camera and position to the person's pages only», con dataset: `/me/{slug}` sin y con
    sesión, Marcar `/me/{slug}/punch`, Mi semana `/me/{slug}/week`, el cascarón `/me/{slug}/offline` y el 404 de `/me/no-existe` →
    `camera=(self), microphone=(), geolocation=(self), payment=(), usb=()`; `/`, `/admin` con usuario, la pantalla del quiosco
    `a/{slug}/kiosk` (con su cookie, como `AttendanceKioskTest`) y un `POST` bajo `/me` → el valor cerrado de la línea 60. Los casos
    existentes (líneas 54 y 107) no cambian. La aserción de `PunchTest.php:174-179` la actualiza A1 en este commit.

###### 13. `tests/Feature/RouteSecurityTest.php` (Modify) — commits 2, 3, 4, 9 y 11
*   **Type / Scope**: Prueba Feature. «The public allowlist has no stale entries» (líneas 149-155) obliga a que cada nombre entre en
    el commit de su ruta.
*   **Proposed Changes**:
    *   (commit 2) `publicRouteNames()` tiene hoy **17** entradas `attendance.device.*` (líneas 58-74: tres del quiosco y catorce de
        la persona). Se **elimina** `attendance.device.punch.home` (línea 61); las otras trece conservan nombre y razón (ninguna
        razón nombra la dirección `a/`).
    *   (commit 3) `worker.app.manifest` («guest, sin sesión ni cookies: manifest de la empresa del slug: nombre comercial,
        direcciones e íconos; nada de nadie; empresa desconocida o bloqueada, el mismo 404; throttled por IP»).
    *   (commit 4) `worker.app.sw` («guest, sin sesión ni cookies: service worker de la empresa; no lee datos; solo guarda archivos
        del build y el cascarón; con PWA_SW_KILL se desregistra; throttled por IP») y `worker.app.offline` («guest, sin sesión ni
        cookies: cascarón estático sin scripts en línea ni datos de nadie; throttled por IP»).
    *   (commit 9) `attendance.device.tickets` («guest, sin sesión, cookies ni CSRF: cambia un boleto válido de la persona, de
        cualquier edad dentro del horizonte, por un lote nuevo y el contexto; un boleto no sirve sin la firma de una marca, y uno
        más nuevo solo estrecha la cota; la empresa del slug, 404 sin el módulo; otro sitio, 403 (RejectCrossSiteRequests);
        throttled por IP»).
    *   (commit 11) `attendance.device.punch.offline` («guest, sin sesión, cookies ni CSRF: cada marca del lote (≤ 20) la autoriza
        su firma, con llave de acceso o con la llave del dispositivo y su pase, más un boleto HMAC de un solo uso que nombra
        empresa y persona; la persona sale de la firma, la empresa del slug, 404 sin el módulo; otro sitio, 403
        (RejectCrossSiteRequests); responde por marca; throttled por IP y, tras verificar la firma, por persona»). Quedan 18
        entradas `attendance.device.*`.
    *   Prueba nueva, con un dataset que crece con los mismos commits (3, 4, 9, 11): «the sessionless routes take no session,
        cookies nor forgery check»: `Route::gatherRouteMiddleware(...)` de `worker.app.manifest`, `worker.app.sw`,
        `worker.app.offline`, `attendance.device.tickets` y `attendance.device.punch.offline` no contiene `StartSession`,
        `EncryptCookies`, `AddQueuedCookiesToResponse`, `ShareErrorsFromSession` ni `PreventRequestForgery`; las dos últimas además
        contienen `App\Http\Middleware\RejectCrossSiteRequests`. (`gatherRouteMiddleware` aplica `excludedMiddleware()`:
        `Router.php:847`, leído en la copia principal.) El 403 con `Sec-Fetch-Site: cross-site` lo prueba A1 por HTTP.
    *   Siguen con `RequireWorkerSession` (las cuenta `requiresAuthentication()`, líneas 121-126, y la prueba de las líneas 157-168
        exige `me/{tenant}/…` con `ResolveWorkerTenant` antes): el registro y el pase de la llave del dispositivo
        (`worker.device-key.*`) y la suscripción push (`portal.push.*`).
    *   `addressesWithCredentials()` (líneas 231-240) y la lista `$personal` (línea 250) no cambian: ninguna ruta nueva lleva un
        parámetro fuera de `tenant`, una fecha o un UUID con `whereUuid`.

###### 14. `app/Http/ErrorPage.php` (sin cambios) y `tests/Feature/ErrorPagesTest.php` (Modify) — commit 2
*   **Type / Scope**: Clase del kernel y su prueba.
*   **Proposed Changes**: `WORKER_ROUTES` (línea 27) ya incluye `attendance.device.*`, `worker.*` y `portal.*`: Marcar, Mi semana,
    boletos y entrega conservan el prefijo, y `worker.app.*` y `portal.push.*` caen solos. En `ErrorPagesTest.php:135` el dataset
    cambia `['attendance.device.punch.home', false]` por `['attendance.device.punch.show', false]`.

###### 37. `app/Http/Middleware/RejectCrossSiteRequests.php` (Create, se mueve) y `routes/complaints-public.php` (Modify) — commit 9
*   **Type / Scope**: Middleware del kernel compartido. Entra con la ruta de boletos, la primera de Attendance que lo usa.
*   **Proposed Changes**: mover tal cual `app/Modules/Complaints/Http/Middleware/RejectCrossSiteRequests.php` (hoy
    `abort_if($request->header('Sec-Fetch-Site') === 'cross-site', 403)`, línea 22) a `App\Http\Middleware` y borrar el original;
    `routes/complaints-public.php:19` cambia su `use`. Sus únicos usos hoy son esa línea y las rutas de las líneas 48 y 53. No
    depende de nada de un módulo, así que cumple «App\Http does not depend on module internals» (`ArchitectureTest.php:93-99`).
    Refactor sin cambio de comportamiento: lo cubren las pruebas de 403 entre sitios
    (`tests/Feature/Complaints/SubmitComplaintTest.php:250` y `FollowUpTest.php`).

---

##### Archivos compartidos: arranque, rutas, dependencias, entorno, build

###### 15. `bootstrap/providers.php` (Modify) — commit 16
*   **Type / Scope**: Registro de proveedores.
*   **Proposed Changes**: `use App\Modules\Notifications\Providers\NotificationsServiceProvider;` y
    `NotificationsServiceProvider::class,` después de `AttendanceServiceProvider::class` (línea 47; `WorkerAccessServiceProvider`
    está en la 46), porque escucha eventos de los dos.

###### 16. `routes/web.php` (Modify) — commit 16
*   **Type / Scope**: Rutas.
*   **Proposed Changes**: `require __DIR__.'/notifications-portal.php';` después de `require __DIR__.'/worker-access.php';`
    (línea 56). Las rutas de push llevan la sesión de la persona, como todo `*-portal.php`. Marcar, Mi semana, boletos y entrega
    siguen en `routes/attendance-device.php` (línea 55): sin línea nueva.

###### 17. `composer.json` (Modify) — commit 16
*   **Type / Scope**: Dependencias (aprobadas por el dueño en el brief).
*   **Proposed Changes**: en `require` (líneas 7-26, orden alfabético) `"minishlink/web-push": "^11.0"` entre
    `maxmind-db/reader` y `spatie/laravel-permission`; `composer.lock` cambia con ella. En el mismo commit,
    `config.allow-plugins."php-http/discovery"` (línea 172) pasa de `true` a `false` si la instalación trae ese paquete: hoy ningún
    paquete del lock lo trae (verificado: 0 coincidencias en `composer.lock`), y con el plugin activo Composer podría agregar
    implementaciones por su cuenta. NO VERIFICADO que web-push v11 dependa de `php-http/discovery`: se ve en `composer.lock` tras
    `composer require`; si no lo trae, la línea 172 no se toca en este PR. NO VERIFICADO si pide `ext-gmp` o `ext-bcmath`: la imagen
    (`Dockerfile:17`, `install-php-extensions gd exif`) se amplía en este commit si `composer require` lo exige. `composer audit`
    lo revisa el CI (`tests.yml:138`).

###### 18. `package.json` y `package-lock.json` (Modify) — commits 4 y 5
*   **Type / Scope**: Dependencias y scripts del frontend.
*   **Proposed Changes**:
    *   (commit 4) `scripts.types:check` (línea 12, hoy `tsc --noEmit`): `"tsc --noEmit && tsc --noEmit -p resources/js/sw"`. Lo
        corren `composer types:check` y el CI (`tests.yml:145,152`).
    *   (commit 4) `scripts.build:sw`: `"PWA_WORKER=sw vp build"` (ver 38); `build` pasa a `"vp build && npm run build:sw"` y
        `build:ssr` a `"vp build && npm run build:sw && vp build --ssr"`. El worker se compila **después** del build principal,
        que vacía `public/build`. La asignación en línea (`PWA_WORKER=sw …`) es de shell POSIX: corre en el CI (Ubuntu), en el
        Dockerfile (Debian) y en macOS y Linux locales; en Windows sin WSL no corre, y no se agrega dependencia para eso.
    *   (commit 5) `dependencies`: `"qr": "0.7.2"` (versión exacta, como `driver.js` y `sonner`, líneas 42 y 53; aprobada por el
        dueño). `package-lock.json` lo regenera la instalación. `bash scripts/npm-audit.sh` lo revisa el CI (`tests.yml:139`).

###### 51. `.github/actions/setup/action.yml`, `Dockerfile` y `scripts/worktree-setup.sh` (sin cambios)
*   **Type / Scope**: Quienes compilan el frontend.
*   **Proposed Changes**: ninguno. `action.yml:72` y `Dockerfile:44` corren `npm run build:ssr`, y `scripts/worktree-setup.sh:13`
    corre `npm run build`; los dos scripts ya encadenan `build:sw` (18). El artefacto del CI sube `public/build` completo
    (`tests.yml:101`), con `public/build/pwa/`.

###### 19. `.env.example` y `.env.production.example` (Modify) — commits 4, 9, 10, 13 y 16
*   **Type / Scope**: Configuración de ejemplo, sin secretos. `tests/Unit/EnvExampleTest.php` falla si una variable que lee
    `config/*.php` falta aquí, así que cada línea entra en el commit que crea su `env(…)`.
*   **Proposed Changes**:
    *   (commit 4) Bloque nuevo `PWA_SW_KILL=false` con el comentario «apaga el service worker en todos los teléfonos; nunca la
        entrega» (lo lee `config/worker_access.php`, A3).
    *   (commit 9) Al final del bloque `ATTENDANCE_*` (líneas 131-141): `ATTENDANCE_OFFLINE_ENABLED=true`. Los boletos usan
        `APP_KEY` y `APP_PREVIOUS_KEYS`: no hay variable propia de llaves. Las demás llaves de `attendance.offline.*` son
        constantes de `config/attendance.php` sin `env` (A1).
    *   (commit 10) Debajo de `WORKER_ACCESS_MAX_KEYS=3` (línea 153): `WORKER_ACCESS_DEVICE_KEYS_MAX=3` y
        `WORKER_ACCESS_OFFLINE_PASS_HOURS=24` (A3).
    *   (commit 13) `ATTENDANCE_WEEK_BLOCK_UNCOVERED=true` («false: calcula los días sin cubrir y los registra, sin bloquear»).
    *   (commit 16) Bloque de push: `VAPID_PUBLIC_KEY=`, `VAPID_PRIVATE_KEY=`, `VAPID_SUBJECT=` con el comentario «genera las llaves
        con `sail artisan notifications:vapid-keys`; el sujeto es `mailto:` con el correo de soporte; vacías = sin push
        (`push.unconfigured`)». En `.env.production.example` (junto a `SUPPORT_EMAIL`, línea 14): `VAPID_PUBLIC_KEY=<…>`,
        `VAPID_PRIVATE_KEY=<…>` y `VAPID_SUBJECT=mailto:<soporte@dominio>` (no hay correo de soporte fijo en el repo: es el
        `SUPPORT_EMAIL` de cada ambiente).

###### 38. `vite.config.ts` (Modify) — commit 4
*   **Type / Scope**: Build del frontend.
*   **Proposed Changes**:
    *   `laravel({ input })` (línea 15, hoy `['resources/css/app.css', 'resources/js/app.tsx']`) suma `'resources/js/offline.tsx'`
        (`tests/TestCase.php:25` fuerza el build real, así que el cascarón no se puede probar sin esta entrada). `ssr` no cambia.
    *   **Compilación aparte del worker**, en el mismo archivo: si `process.env['PWA_WORKER'] === 'sw'`, el archivo exporta otra
        configuración (sin el plugin de Laravel ni Wayfinder): `resolve.alias` `@` → `resources/js`,
        `build.outDir: 'public/build/pwa'`, `emptyOutDir: false`, `copyPublicDir: false`,
        `rolldownOptions: { input: 'resources/js/sw/sw.ts', output: { format: 'iife', entryFileNames: 'sw.js' } }`: un archivo
        clásico sin `import` y de nombre fijo. Sin la variable (build, lint, fmt, test), la configuración actual queda igual. La
        salida está bajo `/public/build`, que ya ignora git (`.gitignore:4`). El worker de apagado no se compila (1).
    *   Se confirma con un experimento corto en este commit: que Rolldown respete `iife` sin partir el archivo y que `vp build` lea
        la variable (NO VERIFICADO para `vp`). La prueba 6 vigila la falta de `import`/`export`.

###### 39. `tsconfig.json` (Modify) — commit 4
*   **Type / Scope**: TypeScript del proyecto raíz.
*   **Proposed Changes**: agregar `"exclude": ["resources/js/sw"]` (hoy no tiene `exclude` y su `include` cubre
    `resources/js/**/*.ts`): los tipos DOM y WebWorker juntos chocan. `resources/js/lib/pwa/sw-rules.ts` sigue en los dos proyectos y
    por eso no usa tipos de ninguno.

###### 40. `resources/js/sw/tsconfig.json` (Create) — commit 4
*   **Type / Scope**: TypeScript del service worker.
*   **Proposed Changes**: `extends: "../../../tsconfig.json"`, `compilerOptions: { lib: ["ESNext", "WebWorker"], types: [] }`,
    `include: ["./sw.ts", "../lib/pwa/sw-rules.ts"]` y **`exclude: []`**: TypeScript 5.9.3 hereda `exclude` del archivo extendido, y
    sin esta línea el proyecto se quedaría sin archivos (TS18003). Lo revisa `types:check` (18). NO VERIFICADO que `vp lint` (con
    tipos) lea este `tsconfig` anidado: si no, A5a saca `resources/js/sw/**` del lint con tipos.

###### 41. `config/services.php` (Modify) — commit 16
*   **Type / Scope**: Configuración de servicios externos.
*   **Proposed Changes**: junto a `posthog` (línea 52):
    `'vapid' => ['public_key' => env('VAPID_PUBLIC_KEY'), 'private_key' => env('VAPID_PRIVATE_KEY'), 'subject' => env('VAPID_SUBJECT')],`.
    Sin cualquiera de los tres, A4 registra `push.unconfigured` y no manda nada.

###### 42. `app/Logging/RedactSensitiveData.php` y `tests/Feature/Logging/StructuredLogTest.php` (Modify) — commits 9, 10, 11 y 16
*   **Type / Scope**: Redacción de logs y su prueba.
*   **Proposed Changes**: ya están `ticket`, `credential`, `credential_id`, `lat`, `lng`, `q`, `public_key`, `token` y `note` (lista
    de las líneas 33-150 en main al 2026-11-24). Se agregan, con un comentario por grupo y cada una en el dataset de «redacts each sensitive key»
    (`StructuredLogTest.php:73`): (commit 9) `tickets`; (commit 10) `challenge`, `signature`, `pass`, `device_key_id`; (commit 11)
    `qr`, `justification`, `lat_e5`, `lng_e5`; (commit 16) `endpoint`, `p256dh`, `auth` (hoy existe `authorization`, no `auth`).
    La redacción es por llave exacta a cualquier profundidad, así que alcanza a cada `items.*`.

###### 43. `lang/es/validation.php` → `attributes` (Modify) — commits 9, 10, 11 y 16
*   **Type / Scope**: Traducciones de atributos; es la única lista: las demás partes la piden aquí.
*   **Proposed Changes**:
    *   (commit 9) Boletos: `ticket` «boleto», `queue.count` «marcas en cola», `queue.oldest_age_minutes` «antigüedad de la cola».
    *   (commit 10) Llave del dispositivo: `public_key` «llave del teléfono», `challenge` «reto», `signature` «firma»,
        `device_key_id` «llave del teléfono» (`label` ya existe, línea 345).
    *   (commit 11) Entrega: `items` «marcas», `items.*.seq` «número de marca», `items.*.type` «tipo de marca», `items.*.ticket`
        «boleto», `items.*.center_id` «centro de trabajo», `items.*.qr` «código QR», `items.*.declared_at` «hora declarada»,
        `items.*.lat_e5` «latitud», `items.*.lng_e5` «longitud», `items.*.accuracy_m` «precisión», `items.*.device_clock_at`
        «hora del teléfono», `items.*.justification` «nota», `items.*.agreement_version` «versión del acuerdo», `items.*.signer`
        «firma», `items.*.credential` «llave de acceso», `items.*.device_key_id` «llave del teléfono», `items.*.signature`
        «firma», `items.*.pass` «pase».
    *   (commit 16) Push: `endpoint` «dirección de avisos», `keys.p256dh` y `keys.auth` «llave de avisos».
    *   La nota de Recursos Humanos usa `note` (ya existe, línea 457).

---

##### Norma y la prueba que la expone

###### 20. `docs/normas/jornada/criterios.md` (Modify) — commit 1
*   **Type / Scope**: Documentación que la app lee (Humi, `config/humi.php:58`); ya es código para CI (`scripts/ci-code-changed.sh`).
*   **Proposed Changes** (texto para revisión del dueño; «⚠ pendiente del usuario» donde el diseño propone):
    *   Encabezado (línea 3, hoy «Versión 0.1 (borrador) · 7 de noviembre de 2026» aunque el historial llega a v0.3): «Versión 0.4
        · 20 de noviembre de 2026».
    *   **3.3 Hora del servidor** (línea 124) se reescribe: la hora de una marca en línea la pone el servidor; la de una marca sin
        conexión la declara la persona y el servidor la acota (3.3.1); el reloj del teléfono nunca es prueba.
    *   **3.3.1 Marca sin conexión** · Humetría · (con el formato `**3.3.1 Marca sin conexión** · Humetría ·`): una marca hecha sin
        conexión es válida, cuenta en horas y horas extra, y lleva la etiqueta «Sin conexión · por verificar» hasta que Recursos
        Humanos la revisa. La persona declara la hora y el servidor la acota: no puede ser **anterior** al último contacto del
        teléfono con el servidor (el boleto), al inicio de la ventana del QR del centro si lo leyó (el QR prueba que el código
        existía: la marca es «no antes de» esa ventana, no «a esa hora»), ni a la marca anterior; y no puede ser **posterior** a la
        recepción ni a la marca siguiente. Cuenta la hora declarada llevada a ese intervalo, si entre el boleto y la recepción
        pasaron hasta 72 horas; si pasaron más, cuenta la cota probada (la más temprana posible) hasta que Recursos Humanos la
        verifique o la corrija. La hora declarada es **no probada**. Un QR leído más de 30 minutos antes de la hora declarada no
        sube la evidencia (la marca queda como sin QR). Si el intervalo queda vacío, la marca no se acepta. Se señalan para revisión
        primero las marcas con intervalo de más de 240 minutos, con un QR viejo, sin posición, fuera de la zona del centro o cuya
        hora contada difiere de la declarada. El boleto vale hasta 35 días (el horizonte de la corrección); más viejo, no se acepta.
        Una marca declarada después de que se revocó la llave que la firmó no se acepta. Toda marca no aceptada queda en el teléfono
        como «No aceptada» con su motivo; si traía boleto y firma válidos, también queda registrada para Recursos Humanos. Nada
        aceptable se pierde en silencio (salvo que se borren los datos del teléfono antes de entregarla).
    *   **3.2** (línea 121): las pausas también pueden marcarse sin conexión, con la regla de 3.3.1.
    *   **3.4**: la posición se toma en el teléfono al marcar, sin conexión, viaja con la marca y se guarda igual; nunca en segundo
        plano.
    *   **4.2**: «bloquear fuera de zona» no se aplica sin conexión: la marca se señala, no se bloquea.
    *   **2.3**: una marca sin conexión cuenta para horas extra y topes con su hora contada, también mientras está por verificar.
    *   **2.6** (retardo, línea 81): una marca de entrada sin conexión cuenta para el retardo con su hora contada, también mientras
        está por verificar.
    *   **2.9**: un día sin cubrir no es un día corto ni cambia el cálculo del extra; solo impide confirmar la semana (5.2). La regla
        «sin horario» de v0.3 sigue: sin horario no hay días sin cubrir.
    *   **5.1**: versión nueva del acuerdo (el texto vive en `CurrentAgreement`, A1): la marca puede guardarse en el teléfono hasta
        entregarse; la hora la declara la persona y el servidor la acota; la posición se captura en el teléfono; existe un plan B con
        una llave propia del teléfono. Una versión publicada mientras la persona estaba sin conexión no rechaza una marca anterior.
    *   **5.2**: no se confirma una semana con **días sin cubrir**: un día laborable de su horario, ya terminado, sin marcas, que no
        sea de descanso, festivo ni anterior a su fecha de alta, ni tenga una ausencia aprobada en Vacaciones y permisos. Se suma a
        los bloqueos que ya existen (`no_marks`, y «Día abierto», `day_open`: la última jornada sin marca de fin,
        `ConfirmWeek.php:84-88`): «Día abierto» habla de una jornada empezada; «día sin cubrir», de un día sin ninguna marca. Aplica
        a **toda empresa**; la plataforma puede apagar el bloqueo (`attendance.week.block_uncovered_days`) y entonces solo lo
        registra. La persona puede objetar la semana, pedir una corrección (agregar la marca que falta) o pedir a Recursos Humanos
        que registre la ausencia como permiso. Persona sin horario: no tiene días sin cubrir. Una marca sin conexión que llega
        después de confirmar una semana la reabre, y la persona la confirma de nuevo.
    *   **5.3**: métodos `offline_qr` (Media) y `offline_declared` (Básica); la evidencia es el mínimo entre la base de tiempo y la
        identidad (llave de acceso fuerte, llave del teléfono media); la revisión de Recursos Humanos no la sube.
    *   **5.4**: la revisión es un registro nuevo enlazado a la marca, de solo inserción; la vigente es la última. «Correcta» solo
        agrega esa revisión y no cambia la hora. «Incorrecta» siempre va con su corrección (anular la marca o una marca nueva a la
        hora que escribe Recursos Humanos), en una sola operación.
    *   **6.1**: el reporte muestra la etiqueta «Sin conexión» (también en una marca corregida, por su marca original), cuántas
        siguen por verificar, quién revisó y cuándo, y las marcas no aceptadas que quedaron registradas.
    *   **7.1**: la nota de la persona en una marca sin conexión (hasta 500 caracteres) se conserva con el registro, solo la ve
        Recursos Humanos y nunca va a logs; las marcas sin conexión se conservan como las marcas, sin poda en esta versión.
    *   **8.1**: la persona ve en su teléfono y en Mi semana el estado de cada marca sin conexión: guardada sin enviar, por
        verificar, verificada, incorrecta (con la nota de Recursos Humanos) o no aceptada (con su motivo).
    *   **Historial**: «2026-11-20 · v0.4» con cada punto y **⚠ pendiente del usuario** en: los umbrales de 240 minutos, 30
        minutos, 72 horas y 35 días; el plan B con evidencia media; el bloqueo por días sin cubrir para toda empresa; la marca tardía
        en una semana objetada (sigue objetada); el texto del acuerdo 5.1.

###### 21. `app/Modules/Assistant/Support/RegulationIndex.php` (Modify) — commit 1
*   **Type / Scope**: Clase de soporte de Humi (la que trocea los criterios).
*   **Proposed Changes**: el patrón de la línea 55, `'/^\*\*(\d+\.\d+) (.+?)\*\*(?: · ([^·]+?))?(?: · (.*))?$/u'`, reconoce solo dos
    niveles: «3.3.1» quedaría pegado a 3.3 y Humi no lo hallaría por su número. El grupo pasa a `(\d+\.\d+(?:\.\d+)?)`.

###### 22. `tests/Feature/Assistant/RegulationSearchToolTest.php` (Modify) — commit 1
*   **Type / Scope**: Prueba Feature (ya `mutates(SearchRegulations::class, RegulationIndex::class)`, línea 11).
*   **Proposed Changes**: caso nuevo «reads a sub-point of the working time criteria, as the offline mark»:
    `search('marca sin conexión hora declarada no antes de', 1)[0]` → `norm` `Jornada`, `id` `3.3.1`, `title`
    `Marca sin conexión`, `source` `Humetría`, `text` contiene «no probada». Se ve fallar antes de 21 por la razón correcta (id `3.3`).

###### 23. `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md` (Modify) — commit 1
*   **Type / Scope**: Documentación histórica (existe).
*   **Proposed Changes**: una línea bajo el título: «> **Superado** por `2026-11-20-worker-pwa-offline-design.md` (marcas sin
    conexión, boleto, push): donde choquen, manda el diseño nuevo, el código y las pruebas.» No la lee ninguna prueba.

###### 24. `docs/superpowers/specs/2026-11-20-worker-pwa-offline-design.md` y `…-brief.md` (Create, mover) — commit 1
*   **Type / Scope**: Documentación.
*   **Proposed Changes**: mover `design_draft.md` (ya resuelto) y `concepts_brief.md` (hoy sin seguimiento en la raíz) a esta
    carpeta; los reportes de revisión de la raíz (`*_report.md`) no entran al PR.

---

##### Operación, privacidad, bitácora y reglas del proyecto

###### 25. `docs/operations/pwa.md` (Create) — commit 21
*   **Type / Scope**: Manual de operación (lo lee `OperationsDocsTest`; `docs/operations/*` ya es código para CI). Hoy existen
    `demo.md`, `observability.md` y `ssr.md`.
*   **Proposed Changes** (secciones):
    1.  **Qué es**: manifest, service worker (`/me/{slug}/sw.js`, alcance `/me/{slug}`, `Service-Worker-Allowed`), cascarón
        (`/me/{slug}/offline`), cola de marcas en el teléfono, boletos (`attendance.device.tickets`) y entrega
        (`attendance.device.punch.offline`), las dos sin sesión.
    2.  **Parámetros**: la tabla de parámetros autoritativa del diseño, con llave de configuración, variable (si la tiene) y valor;
        `OperationsDocsTest` ata cada cifra a la configuración.
    3.  **Interruptores y qué no apagan**: `ATTENDANCE_OFFLINE_ENABLED=false` apaga la cola y la interfaz sin conexión del cliente
        (lo lee por `context.offline`); `PWA_SW_KILL=true` sirve el worker de apagado, que borra sus cachés y se desregistra, y las
        páginas dejan de registrarlo; `ATTENDANCE_WEEK_BLOCK_UNCOVERED=false` deja de bloquear la semana por días sin cubrir.
        **Ninguno apaga la entrega**: lo ya guardado en los teléfonos se entrega igual. Cambiar uno exige reiniciar `web` (la
        configuración se cachea al arrancar).
    4.  **Llaves**: boletos y pases se firman con `APP_KEY`; rotarla con la anterior en `APP_PREVIOUS_KEYS` conserva los boletos y
        pases ya emitidos, que deben quedar ahí al menos 35 días (el horizonte). VAPID con `sail artisan notifications:vapid-keys`
        (A4), cargadas en Dokploy; rotarlas invalida toda suscripción (la app se vuelve a suscribir al abrir). Hosts permitidos de
        push: los de `PushHosts` (A4).
    5.  **Señales en logs** (como las escribe el código): `attendance.offline_mark_received`, `attendance.offline_mark_rejected`
        (con `reason`, `basis`, `interval_minutes`, `queue_age_minutes`, `signer_type`), `attendance.offline_mark_already_recorded`,
        `attendance.offline_queue_reported` (conteo y edad de la cola que manda el teléfono al pedir boletos, nada de personas),
        `attendance.week_uncovered_days_computed`, `push.unconfigured` y las demás `push.*` de A4.
    6.  **Medidas de éxito**: solo lo que ve el servidor, desde esos logs en PostHog Logs (ya redactados como todo log; ningún
        evento de analítica ni nada contado en el teléfono). Umbrales iniciales, **⚠ pendientes del dueño**: marcas entregadas por
        día (tendencia, sin umbral); proporción no aceptada por motivo, se revisa si pasa de 5 % en un día con 20 entregas o más, o si
        `signature_invalid` pasa de 1 %; ancho del intervalo p50/p95 (`interval_minutes`), si el p95 pasa de 240 durante una semana
        se revisa el umbral de intervalo ancho; edad en cola al entregar p95 (`queue_age_minutes`), si pasa de 1 440 (24 h) la app
        no está entregando sola al volver la conexión; colas reportadas con más de 24 h que no entregan, se revisan el mismo día.
        Responden a «nadie reporta que no pudo marcar» porque una marca que no llega deja rastro: en la cola reportada, en un
        rechazo o en su edad al entregar.
    7.  **Despliegue y reversa**: versión de la caché = `app-revision` (`humetria-pwa-{slug}-{revision}`); un teléfono con el
        worker viejo y el servidor nuevo sigue entregando (reto `v1`, boleto `v1`); el esquema solo avanza (no se borran columnas);
        el worker se compila con `npm run build:sw`, que corre en shell POSIX (Linux y macOS).
    8.  **Riesgos aceptados**: con conexión, alguien puede usar un boleto viejo y declarar una hora anterior: sin bandera gana a lo
        más 240 minutos; con `wide_interval`, hasta 72 horas contadas hasta que Recursos Humanos verifique; más allá cuenta solo la
        cota probada. Quien usa el plan B debe entrar a `/me` con sesión a diario (el pase vale 24 h). Las marcas sin conexión no se
        podan.
    9.  **Revisión en teléfonos reales** (la lista consolidada del PR; cada fila: teléfono, sistema, acción, resultado esperado,
        umbral; quien la corre anota resultado y fecha):

        | Teléfono / sistema | Acción | Resultado esperado | Umbral |
        |---|---|---|---|
        | ≥ 2 Android (uno viejo o con Play services desactualizado), app instalada | Firmar sin conexión con la llave de acceso (commit 0), con «reintentar una vez» | Firma | ≥ 4 de 5 intentos por teléfono; si no, solo llave del dispositivo en Android |
        | Android, app instalada | `navigator.storage.persist()` y cerrar la app con marcas en cola | Concedido; el contador sigue | 3 de 3 |
        | iPhone (Safari, manual) y Android (Chrome) | Instalar | Nombre de la empresa; ícono 192 y maskable sin recorte | 1 de 1 por sistema |
        | iPhone y Android, instalada | Cerrar la app, modo avión, abrir desde el ícono | Abre Marcar sin conexión (el cascarón) | 3 de 3 |
        | iPhone y Android | Marcar con QR y sin QR en modo avión; volver la conexión | «Guardada sin conexión», contador, se entrega sola y Recursos Humanos la ve | 3 de 3 |
        | iPhone y Android | Señal intermitente: modo avión justo después de enviar | Al reintentar, `duplicate`; se borra del teléfono | 2 de 2 |
        | iPhone y Android | Un fin de semana sin entregar (≈ 63 h) con varias marcas | Entran con `wide_interval`, cuenta la hora declarada acotada | 1 de 1 |
        | iPhone con llave de iCloud; Android con Google Password Manager | Firma del reto v1 | Verifica en el servidor | 3 de 3 |
        | iPhone con llave de Chrome / Google | Alta: la práctica falla, «Preparar este teléfono», marcar sin conexión | Plan B: marca con la llave del dispositivo | 2 de 2 |
        | iPhone y Android | Llave del dispositivo tras cerrar y reiniciar; Safari y Chrome (dos motores) | Sobrevive y verifica | 2 de 2 |
        | iPhone y Android | Reemitir el acceso con marcas en cola | Las firmadas antes de la revocación entran; las nuevas no se firman | 1 de 1 |
        | iPhone y Android | Lector de QR con moiré, poca luz, Android viejo | Lee; la cámara se apaga al salir | ≥ 4 de 5 |
        | iPhone y Android | Inicio → Marcar y Mi semana → Marcar (navegación de Inertia) | Cámara y ubicación funcionan | 2 de 2 |
        | iPhone y Android | Semana con un día laborable vacío; Recursos Humanos registra el permiso | «Confirmar» apagado con la fecha; después confirma | 1 de 1 |
        | iPhone y Android | Semana confirmada y luego entrega de una marca de esos días | La semana queda pendiente otra vez | 1 de 1 |
        | iPhone (iOS ≥ 16.4, instalada) y Android | Permitir avisos tras la primera marca; Recursos Humanos revisa | Aviso con pantalla bloqueada: solo «Tiene un aviso en Humetría»; tocar abre Marcar | 3 de 3; la suscripción sigue tras varios envíos |
        | iPhone y Android | «Salir» con avisos activos | Ya no llega nada | 1 de 1 |
        | Producción (Dokploy) | Envío real a FCM, Apple y Mozilla | 201 | 1 de 1 por servicio |
        | iPhone y Android | Dos despliegues seguidos con la app abierta | El worker nuevo toma la versión nueva; el cascarón no queda roto | 2 de 2 |
        | iPhone y Android | `PWA_SW_KILL=true` | La app vuelve a la red, las cachés se borran, las marcas guardadas se entregan | 1 de 1 |
        | Un teléfono con dos empresas instaladas | Abrir cada una sin conexión | Cada una abre su cascarón y no borra la caché de la otra | 1 de 1 |
        | Gmail, Outlook, Facebook, Instagram | Abrir el enlace dentro de la app | Aviso «Ábralo en Safari o Chrome» | 4 de 4 |
        | iPhone y Android, 375 px, claro y oscuro, letra grande | Marcar, Mi semana, cascarón | Legible, sin desborde | 1 de 1 |

###### 26. `tests/Unit/OperationsDocsTest.php` (Modify) — commit 21
*   **Type / Scope**: Prueba Unit (hoy con casos para `ssr.md`, `observability.md` y `demo.md`, líneas 20-73).
*   **Proposed Changes**:
    *   «documents in the PWA manual every variable and signal the app installs with»: el manual nombra `PWA_SW_KILL`,
        `ATTENDANCE_OFFLINE_ENABLED`, `ATTENDANCE_WEEK_BLOCK_UNCOVERED`, `APP_PREVIOUS_KEYS`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`,
        `VAPID_SUBJECT`, `push.unconfigured`, `attendance.offline_mark_received`, `attendance.offline_mark_rejected`,
        `attendance.offline_mark_already_recorded`, `attendance.offline_queue_reported`, `attendance.week_uncovered_days_computed`,
        `interval_minutes`, `queue_age_minutes`, `Service-Worker-Allowed`; cada variable nueva aparece en `.env.example` y en
        `config/*.php` con `env('…'`; y `CLAUDE.md` contiene `docs/operations/pwa.md`.
    *   «gives in the PWA manual the figures the code has» (como el de `demo.md`, línea 73): el manual contiene los valores de
        `config('attendance.offline.*')` (lote y tope de boletos, horizonte, intervalo ancho, tope de 72 h, ventana del QR, ya
        registrada, antigüedad de revisión, lote de entrega) y de `worker_access.device_keys_max` y `pass_hours`.

###### 27. `resources/js/components/landing/privacy-notice-content.ts` (Modify) — commit 20
*   **Type / Scope**: Texto del aviso de privacidad (154 líneas; queda bajo 300).
*   **Proposed Changes**: sección nueva `id: 'app-de-la-persona-trabajadora'`, título «La app de las personas trabajadoras», antes
    de `'conservacion'` (línea 123): (1) **almacenamiento en el teléfono**: las marcas hechas sin conexión, los boletos del servidor
    y, en algunos teléfonos, una llave propia de la app se guardan en el almacenamiento del navegador del teléfono hasta entregarse;
    no se guardan páginas ni datos de otras personas; se borran al borrar los datos del sitio, y las marcas sin enviar se pierden si
    se borran antes; (2) **posición**: se toma en el teléfono al marcar, viaja con la marca y se guarda como cualquier marca; nunca en
    segundo plano; (3) **avisos push**: solo si la persona los permite; el aviso pasa por el servicio de avisos del fabricante del
    navegador (Apple, Google o Mozilla), que recibe un aviso **vacío** y una dirección de suscripción, nunca su nombre ni el
    contenido; en la pantalla bloqueada dice solo «Tiene un aviso en Humetría»; se dan de baja al salir de la app o cuando la
    empresa le retira el acceso; en un teléfono compartido recibe los avisos la última persona que los activó. Sin «pendiente de
    revisión legal» nuevo (la prueba exige exactamente uno, línea 85 de la prueba). Texto para revisión del dueño.

###### 28. `tests/Feature/PrivacyNoticeMatchesCodeTest.php` (Modify) — commit 20
*   **Type / Scope**: Prueba Feature (ata el aviso al código).
*   **Proposed Changes**: caso nuevo «says what the workers' app keeps on the phone and what a push says»: el aviso contiene
    «Tiene un aviso en Humetría» y también `resources/js/lib/pwa/sw-rules.ts` (A5a, donde el worker fija ese texto); contiene
    «almacenamiento del navegador del teléfono» y `resources/js/hooks/use-offline-store.ts` (A5a) llama `indexedDB.open(`;
    contiene «nunca en segundo plano»; y contiene «vacío» mientras `App\Modules\Notifications\Support\PushNotifier::notify` (A4)
    recibe un solo parámetro (`ReflectionMethod::getNumberOfParameters() === 1`: no hay por dónde pasar contenido).

###### 50. `app/Http/ToolContent/Attendance.php` y `tests/Feature/ToolPagesTest.php` (Modify) — commit 20
*   **Type / Scope**: Contenido público de la página de Asistencia y su prueba.
*   **Proposed Changes**: hoy la página promete algo que deja de ser cierto sin conexión: `proof` (línea 28) «La hora la pone el
    servidor, …» y el bloque de las líneas 211-212 «La hora es la del servidor» / «El reloj del teléfono no cuenta: nadie adelanta ni
    atrasa una marca.». Pasan a: `proof` «La hora la pone el servidor (sin conexión, la acota entre el último contacto y la
    entrega), ninguna marca se edita ni se borra, y cada persona confirma su semana.»; título «El reloj del teléfono no es prueba» y
    texto «Con conexión, la hora es la del servidor. Sin conexión, la persona declara la hora y el servidor la acota; la marca queda
    por verificar para Recursos Humanos.». Caso nuevo en `ToolPagesTest` «does not promise server time for marks made without
    connection»: el texto de la página de asistencia no contiene «El reloj del teléfono no cuenta» ni «nadie adelanta» y contiene
    «por verificar». Los casos existentes (longitudes, sin «garantiza», privacidad) siguen pasando con el texto nuevo.

###### 48. `resources/js/lib/audit.ts` (Modify) — commits 10, 11 y 14
*   **Type / Scope**: Etiquetas de la bitácora. `tests/Unit/AuditEventLabelsTest.php` (líneas 29-40) exige una etiqueta en español,
    distinta de su clave, para cada evento que el código manda a `->handle('x.y', …)` de `RecordAudit`; las familias `attendance` y
    `worker_access` ya están en `AuditPages::FAMILIES` (líneas 51-52).
*   **Proposed Changes**: (commit 10) `'worker_access.device_key_registered': 'Llave del teléfono registrada'` (A3,
    `RegisterDeviceKey`); (commit 11) `'attendance.offline_mark_accepted': 'Marca sin conexión recibida'` (A1, `RecordOfflineMark`);
    (commit 14) `'attendance.offline_mark_reviewed': 'Marca sin conexión revisada'` (A2, al verificar, también en bloque, y al marcar
    incorrecta). `attendance.week_reopened`, `worker_access.access_reissued` y `worker_access.not_me_reported` ya tienen etiqueta
    (líneas 53, 56 y 58). Si A1, A2 o A3 auditan otro evento, entra aquí en su commit.

###### 49. `app/Modules/Tenancy/Support/AuditPages.php` y `tests/Feature/Tenancy/AuditConsoleTest.php` (Modify) — commits 10, 11 y 14
*   **Type / Scope**: Nombres en español de las claves y valores de la bitácora. `AuditEventLabelsTest` (líneas 94-104) exige que
    toda clave `'x' =>` de una llamada de bitácora (o de los arreglos `$record`, `$context`… que le pasa) esté en `KEYS` (línea 120).
*   **Proposed Changes**:
    *   `KEYS`: ya existen `employee_id`, `mark_id`, `decision`, `corrects_mark_id` y `keys_revoked`. Se agregan (commit 10)
        `replaced_id` «Llave reemplazada», `device_keys_revoked` «Llaves del teléfono revocadas»; (commit 11) `time_basis` «Base de
        la hora», `signer_type` «Firmante», `interval_minutes` «Minutos del intervalo», `review_flags` «Banderas de revisión»;
        (commit 14) `corrected` «Con corrección». Ninguna etiqueta lleva `_` (la prueba lo rechaza).
    *   `VALUES` (línea 67; los valores que no estén se muestran como se guardaron): (commit 10) `replaced` «Reemplazada»; (commit 11)
        `qr_window` «Ventana del QR», `declared_interval` «Intervalo declarado», `passkey` «Llave de acceso», `device_key` «Llave
        del teléfono», `wide_interval` «Intervalo ancho», `old_window` «QR viejo», `no_position` «Sin posición», `outside_zone`
        «Fuera de zona», `declared_adjusted` «Hora ajustada»; (commit 14) `verified` «Verificada», `incorrect` «Incorrecta».
        `review_flags` es una lista: `value()` (línea 504) ya la une con comas.
    *   (commit 11) Caso nuevo en `AuditConsoleTest`: una entrada `attendance.offline_mark_accepted` muestra «Base de la hora: Ventana
        del QR» y «Banderas de revisión: Intervalo ancho, Sin posición».
    *   La lista sale de lo que A1, A2 y A3 escriben hoy en sus entradas; si cambian sus metadatos, el cambio entra aquí en su
        commit.

###### 29. `CLAUDE.md` (Modify) — commits 2, 16 y 21
*   **Type / Scope**: Reglas del proyecto (código para CI). Fuente única de su texto: las demás partes no lo redactan.
*   **Proposed Changes**:
    *   (commit 2) «Rutas», línea 87 («`attendance-device.php`, los checadores.»; la frase de `messaging.php` que main agregó en las líneas 88-89 se queda tal cual) pasa a: «`attendance-device.php`, los checadores: la
        pantalla del QR de un centro (`/a/{slug}`, con su cookie) y Marcar y Mi semana de la persona (`/me/{slug}/…`), firmados por
        acción; los boletos y la entrega de marcas sin conexión van sin sesión, cookies ni CSRF, como `complaints-public.php`
        (`RejectCrossSiteRequests`), autorizados por la firma de cada marca y un boleto de un solo uso». Y en la línea 83, tras
        `worker-access.php`: «que además sirve, sin sesión, el manifest, el service worker y el cascarón de la app instalable».
    *   (commit 16) Rutas, línea 82: tras «`*-portal.php` son el **portal** de la persona trabajadora», agregar «(también
        `notifications-portal.php`, la suscripción a avisos push)». «Módulos»: `Attendance` (línea 94 en main al 2026-11-24) suma «marcas sin conexión y su
        revisión»; nueva línea entre `Messaging` y `Organization` (alfabético; `Messaging` es de WhatsApp y de eventos de correo y no se toca): «- `Notifications`: avisos push (Web Push con VAPID) sin contenido a la
        persona trabajadora: suscripciones y envío; reacciona a eventos públicos de Attendance y WorkerAccess; nunca manda correo (ver
        su README).» Línea 121 («No hay módulo de notificaciones: cada módulo manda sus correos con `Notifications/`. No hay SMS. WhatsApp sale
        solo por `Messaging`.», texto de main desde #147) pasa a: «Cada módulo manda sus correos con su carpeta `Notifications/`; el
        módulo `Notifications` solo manda avisos push, nunca correo ni WhatsApp. No hay SMS. WhatsApp sale solo por `Messaging`.»
    *   (commit 21) «Seguridad», viñeta nueva: «La app instalable de la persona (`docs/operations/pwa.md`): el service worker vive en
        `/me/{slug}/sw.js` con alcance `/me/{slug}`, guarda solo archivos del build y el cascarón, nunca HTML de Inertia ni datos de
        personas; `PWA_SW_KILL` lo apaga sin apagar la entrega. Todo `GET` bajo `/me/` permite cámara y ubicación a su propio origen
        (`SecurityHeaders`); el resto de la app las cierra.»
    *   La suite completa local antes de empujar **no** entra a CLAUDE.md: es regla del dueño (2026-11-18) y CLAUDE.md sigue diciendo
        que la verificación completa es el CI.

###### 30. `tests/Feature/Assistant/ModuleCoverageTest.php` (Modify) — commit 16
*   **Type / Scope**: Prueba Feature. Fuente única de la razón (A4 no la redacta).
*   **Proposed Changes**: en `humiExemptCodeModules()` (junto a `'WorkerAccess'`, línea 32; `'Messaging'` ya está en la 29):
    `'Notifications' => 'Solo manda avisos push sin contenido a la persona trabajadora: no guarda nada de la empresa que Humi informe, y Humi nunca ve suscripciones.'`
    No va en `humiExemptCompanyModules()`: no es un módulo del catálogo `Module`. Sin la línea, «reads every module of the code
    through its public contracts» falla al aparecer `app/Modules/Notifications`.

###### 31. `tests/Unit/ArchitectureTest.php` (Modify) — commit 16
*   **Type / Scope**: Prueba de arquitectura.
*   **Proposed Changes**: los internos de `Notifications` ya los cubre el ciclo de `modules()` (líneas 15-18 y 105-128). Agregar
    `it('has the Notifications module registered in the architecture rules')` (como el de Attendance, línea 42) y una regla de un
    solo destino: `arch('only Notifications talks to the push library')->expect('Minishlink\WebPush')->toOnlyBeUsedIn('App\Modules\Notifications')`.
    El nombre no choca con las carpetas de correo: `MailBagTest` recorre `app/Modules/*/Notifications/*.php`
    (`tests/Unit/MailBagTest.php:13`), que en el módulo nuevo solo tocaría una subcarpeta `Notifications/`, que no tendrá.
    `RejectCrossSiteRequests` en `App\Http` (37) no necesita regla nueva.

###### 32. `tests/Feature/TenantScopedModelsTest.php` (sin cambios)
*   **Type / Scope**: Prueba Feature. Las partes que crean modelos no le piden entradas.
*   **Proposed Changes**: ninguno. `modelClasses()` (líneas 84-92) recorre `app/Models/*.php` y `app/Modules/*/Models/*.php`, y el
    caso de las líneas 94-103 falla con todo modelo cuya tabla tenga `tenant_id` y no use `BelongsToCurrentTenant`, salvo los de
    `TENANT_MODELS_WITHOUT_SCOPE` (línea 35). `AttendanceOfflineMark`, la revisión de A2, `WorkerDeviceKey` y `PushSubscription`
    usan `BelongsToCurrentTenant`: los cubre sin tocar el archivo. Si una parte necesitara un modelo sin ámbito, su entrada con razón
    iría aquí.

###### 34. `tests/Unit/BrowserCoverageTest.php` (sin cambios)
*   **Type / Scope**: Prueba Unit.
*   **Proposed Changes**: es automática (todo archivo de `resources/js/pages` exige su `visitPage()`). No hay páginas Inertia nuevas
    (la revisión de Recursos Humanos son pestañas de Correcciones). El cascarón no es Inertia: su prueba de navegador es de A5a,
    haciendo fallar `fetch` dentro de la página; el arranque en frío real sin conexión se verifica en teléfonos.

###### 35. `scripts/ci-code-changed.sh` y `tests/Unit/CiCodeChangedTest.php` (sin cambios)
*   **Type / Scope**: Harness de CI.
*   **Proposed Changes**: ninguno. `docs/normas/*`, `docs/operations/*` y `CLAUDE.md` ya son código para el script; los documentos
    de diseño no los lee nada y CI los salta, como debe.

###### 44. `tests/Feature/FormRequestAuthorizationTest.php` (Modify) — commit 14
*   **Type / Scope**: Prueba Feature (un viewer del panel recibe 403 antes de validar).
*   **Proposed Changes**: dataset `'review an offline mark' => ['post', 'tenant.attendance.offline.verify', ['mark' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']]`,
    `'mark an offline mark incorrect' => ['post', 'tenant.attendance.offline.reject', [...]]` y la verificación en bloque (nombre de
    ruta de A2). Hoy el dataset no tiene ninguna ruta de Attendance; NO VERIFICADO si la empresa de la fábrica tiene el módulo
    `attendance` (sin él la ruta podría responder 404 antes del FormRequest): se comprueba al escribir el caso y, si hace falta, se
    crea con el módulo. Los FormRequests de la entrega y de los boletos no entran: no hay miembro del panel que rechazar, y su
    `authorize()` devuelve `true` a propósito (la firma y el boleto son la credencial, como `MarkRequest`).

###### 45. `tests/Feature/PaginatedListsTest.php` (Modify) y `tests/Unit/PaginatedListsTest.php` (sin cambios) — commit 14
*   **Type / Scope**: Pruebas de listas paginadas.
*   **Proposed Changes**: junto a `'time clock correction requests'` (línea 110), dos filas: `'time clock offline marks' =>
    ['attendance/corrections', fn () => route('tenant.attendance.corrections.index', [listsCompany()[0], 'status' => 'offline']), ['offlineMarks']]`
    y `'time clock marks not accepted'` igual con `'status' => 'not_accepted'`: las dos pestañas se paginan con la prop
    `offlineMarks`. La prueba Unit no cambia: no hay prop de arreglo nueva.

###### 46. `tests/Unit/FrontendArchitectureTest.php` (Modify) — commit 4
*   **Type / Scope**: Prueba Unit (corre `vp lint` sobre archivos de prueba, `lintFrontendProbe`, línea 19).
*   **Proposed Changes**: caso nuevo «rejects the service worker importing anything but its rules»: un archivo de prueba en `sw/`
    que importa `@/lib/utils` da `no-restricted-imports`; uno que importa solo `@/lib/pwa/sw-rules` pasa. Fija la regla que A5a
    agrega en `frontend-architecture.ts`.

###### 47. `tests/Unit/EnvExampleTest.php` (sin cambios)
*   **Type / Scope**: Prueba Unit (está en `tests/Unit`, no en `tests/Feature`).
*   **Proposed Changes**: ninguno; es la guarda de 19 (falla si una variable de `config/*.php` falta en `.env.example`).

---

##### Necesita de archivos de otro dueño
*   `routes/worker-access.php` (A3): las tres rutas de la app instalable en un grupo `Route::withoutMiddleware([...])` con la misma
    lista que `routes/complaints-public.php:31-41`, más `->middleware(['throttle:worker-enter', ResolveWorkerTenant::class])`
    (300 por minuto por IP; `ResolveWorkerTenant` no lee sesión, verificado): (commit 3) `GET me/{tenant:slug}/manifest.webmanifest`
    → `[InstallableAppController::class, 'manifest']` → `worker.app.manifest`; (commit 4) `GET me/{tenant:slug}/sw.js` →
    `'serviceWorker'` → `worker.app.sw` y `GET me/{tenant:slug}/offline` → `'offline'` → `worker.app.offline`.
*   `config/worker_access.php` (A3), commit 4: `'pwa_sw_kill' => (bool) env('PWA_SW_KILL', false)`.
*   `app/Modules/WorkerAccess/README.md` (A3): un párrafo sobre la app instalable (manifest, service worker, cascarón,
    `Service-Worker-Allowed`, `PWA_SW_KILL`).
*   `routes/attendance-device.php` (A1): boletos (commit 9) y entrega (commit 11) en un grupo `withoutMiddleware` como
    `routes/complaints-public.php:31-41`, con `App\Http\Middleware\RejectCrossSiteRequests` por ruta.
*   `app/Modules/Attendance/Http/Controllers/MarkController.php` (A1), commit 5: quitar el `Permissions-Policy` de la línea 119 (lo
    pone `SecurityHeaders`).
*   A5a: el texto fijo en `resources/js/lib/pwa/sw-rules.ts` y la llamada `indexedDB.open(` en
    `resources/js/hooks/use-offline-store.ts` (prueba 28); la regla del worker en `frontend-architecture.ts` (prueba 46); el registro
    que lee `meta[name=pwa-sw-kill]`.
*   `routes/notifications-portal.php` (A4): ese nombre, para el `require` de 16.

##### Verificación (comandos por commit)
1.  En cada commit, solo lo que toca (tests en local contra el Postgres de Docker del worktree; comandos de la app con `sail`),
    viendo primero el RED: commit 1, `php artisan test --compact tests/Feature/Assistant/RegulationSearchToolTest.php`; commit 2,
    `--filter='RouteSecurity|ErrorPages'`; commits 3-4, `tests/Feature/WorkerAccess/InstallableAppTest.php` y
    `--filter='RouteSecurity|PageMeta|FrontendArchitecture'`; commit 5, `--filter=SecurityHeaders`; commits 9-11 y 14,
    `--filter='RouteSecurity|StructuredLog|AuditEventLabels|AuditConsole|FormRequestAuthorization|PaginatedLists|EnvExample'`;
    commit 16, `tests/Unit/ArchitectureTest.php tests/Feature/Assistant/ModuleCoverageTest.php`; commits 20-21,
    `tests/Feature/PrivacyNoticeMatchesCodeTest.php tests/Feature/ToolPagesTest.php tests/Unit/OperationsDocsTest.php`; o
    `composer test:fast`.
2.  `vendor/bin/pint --test` y `vendor/bin/phpstan analyse` (nivel max, sin supresiones) sobre lo cambiado.
3.  Frontend (commits 4, 5, 19): `npm run check`, `npm run types:check` (los dos proyectos), `npm run build` (incluye `build:sw`)
    antes de `composer test:browser`.
4.  Dependencias (commits 5 y 16): `composer audit` y `bash scripts/npm-audit.sh`.
5.  Antes de empujar: la suite completa local en verde, por **regla del dueño (2026-11-18)**; CLAUDE.md deja la verificación
    completa al CI (`ci` de `.github/workflows/tests.yml`), que la confirma. `tests/.pest/shards.json` y `--update-shards` **no**
    entran a este PR. Mutación: semanal, no en el PR.
6.  Revisión en el navegador (claro, oscuro, 375 px) del cascarón y de Marcar, y la lista de teléfonos de `docs/operations/pwa.md`.

##### Riesgos y pruebas en teléfonos
*   **Alcance sin barra final.** El alcance por omisión de `/me/acme/sw.js` es `/me/acme/`; por eso `Service-Worker-Allowed:
    /me/{slug}` y `scope: '/me/{slug}'`, que cubren `/me/acme` y `/me/acme/punch`. Como `/me/acme` también es prefijo de
    `/me/acme-industrial`, el worker de A5a atiende solo la ruta exacta del alcance o las que empiezan con alcance + `/`, y su caché
    `humetria-pwa-{slug}-{revision}` se limpia solo para su slug (el apagado, igual: 6 lo prueba con dos empresas).
*   **Worker de un solo archivo.** Servido desde `/me/{slug}/sw.js`, un `import` resolvería contra `/me/{slug}/` y el worker no se
    activaría; lo evitan la compilación aparte (38), la regla de lint (46) y la prueba 6.
*   **Compilación del worker solo en shell POSIX** (18): Windows sin WSL no compila el worker; no hay quien compile ahí.
*   **`minishlink/web-push ^11.0`**: NO VERIFICADO si sus dependencias chocan con `web-auth/webauthn-lib ^5.3` o si pide
    `ext-gmp`/`ext-bcmath` en la imagen; se ve con `composer require` en su commit (17).
*   **`@fonts`** en el cascarón: si emite código en línea, se omite (2).
*   **Cámara y ubicación en todo `/me`** (10): la política solo permite; el navegador pide permiso cuando una página los usa, así que
    Inicio y Mi semana no piden nada aunque podrían.
*   **Bloqueo por días sin cubrir para toda empresa** (20, 5.2): cambia el comportamiento de quien no marca sin conexión; tiene
    interruptor y queda marcado como pendiente del dueño en el historial de la norma.
*   **Textos de la norma, del aviso de privacidad y de la página pública**: propuestas para el dueño; el historial lo marca.
*   **Harness aparte**: regenerar `tests/.pest/shards.json` (`docs/testing.md:153-157`) va en su propio PR de harness, después de
    integrar este; sin regenerarlo, los archivos nuevos van por turno y no rompe nada.
*   **Pruebas en teléfonos reales**: la tabla consolidada de `docs/operations/pwa.md` §9 (25); este segmento no lleva lista propia.

--------------------------------------------------------------------------------

<!-- Provenance: Indicates initial structural drafting via EGM. -->

*Drafted with EGM*

### Orden de commits (un solo PR por decisión del dueño; cada commit = un comportamiento con su prueba, en verde)

Cada entrada del plan dice «(commit N)» cuando un subcambio no cae en el commit natural de su archivo. Lo que una prueba afirma debe existir ya en el commit donde esa prueba entra.

0. **Puerta previa (sin código): firma sin conexión desde la app instalada de Android.** Criterio: la llave de acceso firma sin conexión en **≥ 4 de 5 intentos** en la app instalada, en **≥ 2 teléfonos Android** (uno viejo o con Play services desactualizado), más `navigator.storage.persist()`. Si no se cumple, la ruta de llave de acceso sin conexión sale y la llave del dispositivo es el único firmante en Android (Alternativa AE). Es también donde se mide el «reintentar una vez».

**Grupo 1 · se sostiene solo (1-9); aditivo**

1. **Norma v0.4**: `docs/normas/jornada/criterios.md`, `RegulationIndex` y su prueba, banner y documentos de diseño (A6).
2. **Mudanza de Marcar y Mi semana a `/me/{slug}`**: rutas, controladores, URL del QR del quiosco, 404 de las `a/` viejas (A1); enlaces Wayfinder (A5b); pruebas que usan `punch.home` y `HomeTest` (A1, A2, A3); `RouteSecurityTest`, `ErrorPagesTest` y la línea de rutas de `CLAUDE.md` (A6). Sin `AttachOfflineTickets`, sin cabecera de cámara y sin el token nuevo del QR. Va junto: Wayfinder rompe el build si se parte.
3. **Manifest e íconos**: `worker.app.manifest`, `InstallableAppController` y su prueba (A3 rutas, A6 controlador).
4. **Cascarón y service worker**: `offline.tsx` mínimo y su entrada de Vite (solo `data-company` y `data-home-url`), `worker.app.offline` y `worker.app.sw`, el worker (sin manejador `push`), el apagado que genera el controlador, `tsconfig` del worker, registro, Vitest de la tabla de decisión y prueba de arranque en frío (A5a, A6). Incluye el experimento corto de compilación aparte.
5. **Cabecera `camera=(self), geolocation=(self)` en los documentos GET de `/me/{slug}/*` y lector de QR** (`SecurityHeaders` en A6; `qr` 0.7.2 y `QrScanner` en A5a; integración en Marcar, A5b; la aserción de cabecera de `PunchTest`, A1).
6. **Acuerdo: versión nueva (criterio 5.1)** (A1, con su prueba de navegador en A5b).
7. **Esquema sin conexión**: métodos `offline_qr` y `offline_declared`, `attendance_offline_marks`, `attendance_mark_reviews`, enums, `ReviewFlag`, modelos, factories, aislamiento A/B, solo inserción y excepción de la empresa demo (A1, A2).
8. **Reto canónico v1**: vectores compartidos PHP/JS y `WorkerSignatures::verifyChallenge` (A1, A5a, A3).
9. **Boletos y token del QR**: emisión, verificación, `POST me/{slug}/tickets` sin sesión (con `RejectCrossSiteRequests` ya movido a `App\Http\Middleware`), `AttachOfflineTickets` (`{tickets, context}`) en las respuestas firmadas, token `{windowIndex}.{hmac}` en `QrToken` y el quiosco, y la aserción de `KioskTest` (A1, A6).

**Grupo 2 · servidor sin conexión; va junto (10-15)**

10. **Llave del dispositivo y pase sin conexión**, con las propiedades de Inicio (A3).
11. **Entrega**: `OfflineTime`, `RecordOfflineMark`, ruta `attendance.device.punch.offline` al estilo de `complaints-public`, limitadores, `OfflineMarkAccepted` dentro de la transacción, logs, bitácora, `RedactSensitiveData`, etiquetas de `audit.ts` y claves de `AuditPages` (A1, A6).
12. **Reapertura de la semana confirmada**: `ReopenOnOfflineMark` y su `Event::listen` (A2).
13. **`WeekCompleteness` y `uncovered_days` en `ConfirmWeek`**, con `Absences::holidaysBetween`, `hiredOn` de `Roster`, el interruptor y las correcciones de las pruebas TS y de navegador de la semana que se rompen (A2, A5b).
14. **Verificación de RH**: pestañas «Sin conexión» y «No aceptadas» en Correcciones, verificar (también en bloque) y marcar incorrecta con su corrección, insignia, reporte, CSV y evidencia, `OfflineMarkReviewed` y `CorrectionResolved` (A2).
15. **Humi y atención**: `AttendanceReader` con conteos de marcas con banderas o sin revisar con antigüedad (A2).

**Grupo 3 · avisos, guía y cliente**

16. **Módulo `Notifications`** y el evento `WorkerAccessRevoked` que lo alimenta (A3, A4), con `minishlink/web-push` (y `allow-plugins` de `php-http/discovery` en `false`), proveedor, rutas `portal.push.*`, `ModuleCoverageTest`, `ArchitectureTest`, línea de módulos y «No hay módulo de notificaciones» en `CLAUDE.md`, VAPID en `.env.example` (A4, A6).
17. **Cliente de push**: permiso tras la primera marca, manejador `push` en el worker con texto fijo, `forgetPush` (A5a, A5b).
18. **Guía de instalación** y aviso «Ábralo en Safari o Chrome» (A5b).
19. **Cliente sin conexión**: cola IndexedDB, entrega con espera, estados de Marcar («No aceptada» hasta «Entendido»), contador, «Listo para marcar», registro de la llave del dispositivo, Mi semana bloqueada con la cola no vacía, `context.offline`, y los atributos `data-deliver-url` y `data-tickets-url` del cascarón (A5a, A5b).
20. **Aviso de privacidad y contenido público de Asistencia** (A6).
21. **Manual `docs/operations/pwa.md`**, `OperationsDocsTest` y línea de seguridad de `CLAUDE.md` (A6).

**Línea de corte y reversa.** Los commits 1-9 se sostienen solos. Los 10-15 van juntos (la verificación y la semana dependen de que la entrega exista). El 19 sale solo y deja un teléfono que marca en línea como hoy; los 16-18 son independientes de lo sin conexión y se revierten sin tocar el servidor de marcas. Si la puerta del commit 0 o la revisión en teléfonos reales falla, el cliente sin conexión (19) sale del PR en lugar de entregarse apagado. `tests/.pest/shards.json` y su regeneración **no** forman parte de este PR (PR de harness aparte).

### Plan de verificación

*   **Compilación**: `npm run build` (incluye `build:sw`: el worker y el bundle `offline`); la build SSR no debe tocarlos.
*   **Pruebas unitarias y de funcionalidad**: Pest filtrado por lo que toca cada commit (`composer test:fast` o `php artisan test --compact --filter=…`); Vitest de `resources/js/lib/pwa` y del resto de `lib`; pruebas de navegador con `visitPage()` (`composer test:browser`, tras `npm run build`). La falta de red se simula haciendo fallar `fetch` dentro de la página; el arranque en frío real sin red se verifica en teléfonos.
*   **Análisis estático y estilo**: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse` (nivel max, sin supresiones), `npm run check`, `npm run types:check` (los dos proyectos: el principal y el del worker).
*   **Cobertura**: ≥ 90 % en Unit/Feature y en Vitest (no se baja el umbral); toda página nueva con prueba de humo, de desborde a 375 px y de «props sin respuestas individuales».
*   **Antes de empujar**: la suite completa local en verde (**regla del dueño, 2026-11-18**: CLAUDE.md deja la verificación completa al CI). Después, el CI completo (`ci` de `.github/workflows/tests.yml`). Mutación: no en el PR (semanal).
*   **Puerta de integración**: la lista de «Pruebas en teléfonos reales antes de integrar» de este documento, empezando por el commit 0.

El detalle de comandos por commit está en la subsección «Verificación» del segmento A6.
