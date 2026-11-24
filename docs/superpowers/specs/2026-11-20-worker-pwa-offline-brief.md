# App de la persona trabajadora: instalable, lector de QR, avisos push y marcas sin conexión

* **Author:** Victor Jesus Bravo de la Peña
* **Date:** 2026-11-20
* **Estado:** concepto confirmado por el dueño del producto el 2026-11-20; revisado por 7 lentes (Sonnet). Los revisores no corrieron código ni pruebas, y no había `vendor/` instalado, así que lo que dicen de `HandleInertiaRequests::version()` y de compatibilidad de paquetes no está verificado.
* **Reemplaza en parte a:** `docs/superpowers/specs/2026-11-14-worker-pwa-brief.md` (ver «Qué cambió»).

## Overview

Hoy marcar asistencia exige llegar al servidor dos veces (pedir el reto de la firma y enviar la marca). Sin señal, la página dice «Revise su conexión» y no se guarda nada; la solicitud de corrección tampoco funciona sin red. No existe manifest, service worker, lector de QR ni push. Lo verifiqué en el código; el video de asistencia que dice «Sin señal, igual se guarda como media» habla de que falta el GPS, no el internet.

Esta rama entrega, en un solo PR y sin separar fases:

1. **App instalable** de la persona trabajadora bajo `/me/{slug}` (Marcar y Mi semana se mueven ahí desde `/a/{slug}`; no hay clientes, no se conservan las direcciones viejas), con manifest por empresa y service worker propio.
2. **Lector de QR dentro de la app** para el código de la pantalla del centro.
3. **Avisos push** con la API estándar de Web Push (llaves VAPID). Confirmado por el dueño el 2026-11-20 (lo que dictó como «MAPI» es VAPID).
4. **Marcas sin conexión** firmadas con la llave de acceso. Se guardan en el teléfono y se entregan al volver la señal. **Cuentan en las horas** y quedan señaladas «sin verificar» en el panel; la persona puede escribir una justificación y Recursos Humanos las verifica después.

Para quién: personas en planta, bodega, tienda u oficina, con iPhone o Android viejo, que no son técnicas y a quienes la app trata de «usted». Éxito, según el dueño: nadie reporta que no pudo marcar, y existe un camino de marca sin red que él valida en un iPhone y un Android reales. Los revisores piden medidas que sí detecten una pérdida silenciosa (intentos contra entregas, edad de las marcas sin entregar, proporción rechazada).

## Analysis

### Lo que ningún diseño puede lograr

No existe una hora del teléfono que no se pueda manipular: el reloj del sistema se cambia en dos toques, y el reloj interno del navegador (`performance.now`) se pausa cuando el teléfono duerme, lo que resta tiempo y favorece a quien llega tarde. Solo se puede confiar en una hora que el servidor pueda derivar o acotar después. Tampoco se puede probar nada si ni el teléfono ni la pantalla tienen luz.

### Cómo se obtiene la hora de una marca sin conexión (tres bases, guardadas en cada marca)

| Base | Qué prueba el servidor | Hora que cuenta | Evidencia máxima |
|---|---|---|---|
| `server` (marca en línea de hoy) | Él mismo vio el acto | Hora del servidor | Fuerte |
| `qr_window` | El servidor deriva la ventana de 30 s del código (HMAC) cuando recibe la marca | Inicio de la ventana (±30 s) | Media |
| `declared_interval` | Un intervalo: del último instante en que vio ese teléfono (**ancla**) al instante en que recibe la marca (**recibo**) | La hora que la persona declaró, solo si cae dentro del intervalo | Básica |

La firma de la llave prueba **quién** fue, no **cuándo**. El panel y el acuerdo de la persona deben decirlo.

**Ancla sin tabla.** Los revisores proponen un **boleto sin estado**: el servidor firma (HMAC) `empresa, persona, hora, valor aleatorio` y lo entrega en cada respuesta firmada en línea y en cada página de `/me`; el teléfono guarda el más reciente. Sin conexión, la llave firma un hash que incluye el boleto y los datos de la marca. Así, una sola pieza da la cota inferior, evita repetir una marca y entra en el reto. Evita la carrera del latido (el ping de reconexión avanza el ancla antes de entregar la marca y el intervalo queda en cero) y evita que un latido reinicie el reloj de inactividad de la sesión de 30 minutos. Un boleto viejo solo ensancha el intervalo; es la misma libertad que tiene quien se desconecta temprano. Refrescarlo al abrir la app, al volver a primer plano, al reconectar y en cada firma en línea basta; un temporizador cada minuto es más estricto de lo necesario.

### Problemas que el diseño de la hipótesis no resuelve solo

**C1. El criterio 3.3 no queda cubierto (bloqueante).** «La hora la pone siempre el servidor» se cumple en letra con la ventana del QR, pero con menos fuerza: en línea el servidor ve el acto; sin conexión solo sabe que existió un código de esa ventana. Y la base `declared_interval` es una hora dicha por la persona. Esto reabre lo que el brief anterior rechazó (el «sello» del servidor, el QR como límite inferior, y sus decisiones 3, 4, 9, 10 y 11). Se resuelve enmendando `docs/normas/jornada/criterios.md` como primer commit: dejar 3.3 igual para marcas en línea y agregar 3.3.1 «marca sin señal», con las tres bases, la hora declarada descrita como «no probada» y los techos de evidencia en 5.3. También hay que tocar 5.1 (el acuerdo debe decir que se guarda en el teléfono, que hay horas declaradas y que se captura la ubicación en el dispositivo; sube la versión y obliga a volver a aceptar, que debe poder hacerse antes de un apagón), 3.4, 4.2 (la política «bloquear fuera de zona» no se puede evaluar sin red), 6.1, 7.1 (la justificación puede contener datos de salud: solo Recursos Humanos, con límite de largo, nunca en logs) y el historial.

**C2. «Marca real» contra marcas de solo inserción (bloqueante).** `attendance_marks` tiene un trigger que impide actualizar y borrar. Verificar no puede ser un UPDATE. Propuesta: una tabla de revisión de solo inserción (`verified` o `rejected`, quién, nota); «sin verificar» es la ausencia de revisión; un rechazo pasa por el flujo `CorrectMark` y la marca original queda. Dos métodos nuevos, `offline_qr` (Media) y `offline_declared` (Básica), porque la evidencia sale del método. Una revisión de Recursos Humanos no sube la evidencia. Tocar un método nuevo implica: la restricción CHECK de la base, `MarkMethod` y `EvidenceLevel`, los arreglos de evidencia de `BuildAuthorityReport`, `PersonWeek`, `markHow()` en el frontend, la prueba `MarksAppendOnlyTest` y el lector de Humi. `MarkPolicyCheck` con QR válido devuelve `QrAndGeofence`, que es Fuerte: la marca sin conexión no puede pasar por ese camino.

**C3. La ceremonia de firma actual no puede verificar una firma sin conexión (bloqueante).** Hoy solo acepta un reto que el servidor emitió y guardó 60 s, ligado a la sesión y de un solo uso; y `signed-request.ts` empieza pidiendo `punch/options`, que falla sin red. Hace falta `verifyChallenge(esperado, respuesta)` en el contrato `WorkerSignatures`, sin caché, y un reto canónico y versionado (`humetria-offline-mark-v1|` más empresa, centro, tipo, base, código o ventana, valor aleatorio, hora declarada, número de secuencia, valor anterior, hash de la ubicación, versión del acuerdo y boleto). Sin la hora declarada y la ubicación dentro de los bytes firmados, el script de la página podría cambiarlas después de la huella (hoy la latitud y la longitud no se firman). Las llaves sincronizadas siempre reportan contador 0, así que el contador no protege: la repetición se evita con un índice único `(tenant_id, credential_id, nonce)` en la misma transacción que la marca. Un archivo de vectores de prueba compartido por Pest y Vitest evita que PHP y JS armen el reto distinto.

**C4. Marcas con fecha anterior rompen `RecordMark` (bloqueante).** `RecordMark` pone `marked_at = now()` y revisa la secuencia contra la última marca, suponiendo que el orden de llegada es el orden en el tiempo. Una acción nueva `RecordOfflineMark` (no una sobrecarga) debe: correr bajo `MarkLock`, revisar la marca anterior y la siguiente efectivas, exigir `marked_at` mayor que la última, y tener un CHECK `intervalo_desde <= marked_at <= intervalo_hasta <= created_at`. El teléfono entrega en orden de `seq` y antes de cualquier marca en línea. Si la marca k tiene conflicto, las siguientes también: se guardan como reclamos rechazados, nunca se tiran. Si llega una marca en línea entre la reconexión y la entrega, el resultado es «ya registrada», no un error (el caso en que la solicitud llegó y la respuesta se perdió). `CloseDay` y `HoursCalculator` recalculan desde las marcas, así que los días pasados se corrigen solos. Pero una semana ya confirmada queda vieja (su digest es un SHA de ids de marcas), y hoy solo `MarkCorrected` la reabre: hay que agregar un evento `OfflineMarkReceived` con un listener que use `ReopenWeeks`.

**C5. «Guardada» no puede prometer «aceptada» (mayor).** Al entregar, el servidor puede rechazar por: zona, precisión, acuerdo vencido, llave revocada, persona inactiva, conflicto de secuencia, muy vieja o límite de tasa. Perderla en silencio contradice el éxito que pide el dueño. Se acepta con señal siempre que se pueda; si no, se guarda un reclamo rechazado visible para Recursos Humanos, y el teléfono conserva la marca con su motivo y ofrece «pedir corrección». Solo se borra del teléfono tras un 2xx o un 422 definitivo.

**C6. `camera=(self)` no aplica al navegar con Inertia (mayor).** `Permissions-Policy` se evalúa por documento. Los enlaces de la pantalla de inicio a Marcar son `<Link>` de Inertia (XHR), así que el encabezado de la respuesta nueva se ignora. Y `/me/{slug}` es el `start_url` de la app: es la entrada principal. Opciones: (a) `<a href>` o `reloadDocument` hacia Marcar, con `camera=(self)` solo en el GET de Marcar (la más angosta, recomendada); (b) una regla de ruta en `SecurityHeaders` para todo `/me/{slug}/*`. Los revisores señalan que lo mismo podría afectar hoy a la geolocalización; no lo he comprobado.

**C7. Marcar bajo `/me` sin sesión (mayor).** Una sesión de trabajador dura 30 min sin uso y 8 h, y no se renueva sin red. Decisión que debe quedar escrita: Marcar y Mi semana siguen **sin sesión**, con una firma por acción. Mudanza mínima: cambiar solo el prefijo `a/` por `me/` en las rutas de Marcar y Mi semana, conservando los nombres `attendance.device.*`; las rutas de la pantalla del centro se quedan en `/a/`. Hay que cambiar la URL del QR en `KioskController.php:120` (si no, mueren todos los QR) y el texto de la pantalla «Abra la app y toque Escanear», más las pruebas con literales `/a/acme`, y agregar pruebas de 404 para las direcciones viejas.

**C8. El costo de derivar la ventana del QR (bloqueante en costo).** `QrToken::expired` prueba hasta ~2880 HMAC por día y por centro candidato, y `centerOf` recorre todos los centros. Con 35 días serían ~100 mil por centro, en una ruta sin sesión. El cliente debe mandar el `center_id` (también dentro del reto firmado), la búsqueda debe acotarse a 48–72 h (o el token debe llevar su ventana: `w.hmac`), con límite por IP y por llave, y orden de revisión: valor aleatorio, límites, firma. Hace falta `QrToken::windowOf(token, tenant, center, desde, hasta)`.

**C9. Marcar sin QR: marcar «contando» no basta como control (mayor).** El ataque: sin conexión desde las 07:00, llega a las 09:30, declara las 08:00; cae dentro del intervalo. Un ancla vieja es normal (la mayoría abre la app solo para marcar), así que un tope estricto rechazaría a gente honesta y ningún tope deja declarar cualquier hora desde ayer. Los revisores proponen que el ancho del intervalo decida cómo cuenta:

| Ancho W = recibo − ancla | Tratamiento propuesto |
|---|---|
| hasta 30 min | Cuenta a la hora declarada. Señal de baja prioridad. |
| 30 min a 4 h | Cuenta a la hora declarada. Señalada; Recursos Humanos la resuelve antes de que la confirmación semanal sea definitiva. |
| más de 4 h, o sin boleto | Cuenta solo la cota probada (entrada al recibo, salida al ancla); la hora declarada pasa a reclamo por el flujo de corrección. |

Esto acota lo que gana quien miente a lo ancho de su tramo, pero **contradice «cuenta siempre»**: es decisión del dueño. Otros controles baratos: informar horas probadas y horas declaradas por separado («ventana 07:00 a 09:30, declaró 08:00, 90 min sin probar»); ubicación capturada en el dispositivo y sellada en el reto (el GPS funciona sin red, se puede falsear, no sube la evidencia); contexto para Recursos Humanos con datos que ya existen (otras marcas del centro en esa ventana, `last_seen_at` de la pantalla: usar «sin QR» con la pantalla viva es mala excusa; agrupar por apagón de centro y verificar en bloque, si no se van a firmar decenas de marcas a ciegas); tope duro de edad (35 días, como `RequestCorrection`) o de ancho (~48 h). Los topes por frecuencia (~2 al día) los piden dos lentes y los rechaza uno; se dejan fuera por ahora salvo un `max_age`.

**C10. Textos de la persona.** El texto aprobado del brief anterior decía «aún no cuenta como marca»; ya no es cierto. Por estado: en el teléfono «Guardada en su teléfono» (aún no cuenta); tras la entrega «Recibida. Cuenta en sus horas. Recursos Humanos la revisará.»; luego «Verificada» o «No aceptada: [nota]. Puede pedir una corrección.». Sin conexión, las pantallas «Verificando» y «Listo» (con el reloj grande del servidor) mentirían: «Listo» debe decir «Usted indicó 08:02. Aún no la recibe Recursos Humanos.». Un contador permanente «n marcas sin enviar». Una pantalla de preparación («Listo para marcar sin internet» / «Aún no está listo») y una primera visita con internet; la cámara, la ubicación, el bloqueo de pantalla, un acuerdo vencido o una sesión caducada pueden impedir marcar sin red sin que la persona lo sepa hasta el apagón.

**C11. Vocabulario.** «Marca», «marca sin conexión» y «corrección» se usan en tres sentidos. Se fija: **ancla** (último instante visto por el servidor), **recibo** (instante de recepción), **intervalo probado** [ancla..recibo], **hora declarada**, y una sola etiqueta en pantalla: **sin verificar** (o «por verificar», que suena menos a acusación; decide el dueño). «Señalada» ya significa «fuera de zona» (4.2) y no se mezcla. «Fase» desaparece: se usan grupos de commits.

### Service worker, manifest, push (resumen de los revisores)

* **Service worker** por empresa en una ruta de Laravel `/me/{slug}/sw.js` (un archivo estático necesitaría el encabezado `Service-Worker-Allowed`, que CI no puede probar), con `Cache-Control: no-cache`, la CSP y noindex; escrito a mano (~60 líneas), sin Workbox ni `vite-plugin-pwa`. Reglas: archivos `/build/assets/*` primero de caché; un cascarón estático (vista Blade sin scripts ni estilos incrustados, porque un nonce guardado rompería la CSP) que se sirve solo ante un error de red y nunca reemplaza un 4xx/5xx; todo lo demás pasa directo, y nunca se guarda HTML de Inertia (lleva nonce, token CSRF, `person` y `now`). Se necesita aunque la página ya esté abierta: el arranque en frío sin señal es el caso dominante y el push también lo exige. Interruptor `PWA_SW_KILL` que sirve un worker que se desregistra y borra cachés, **sin apagar nunca** la ruta de entrega (si no, las marcas pendientes quedan varadas). Hay que probar dos versiones desplegadas (teléfono viejo, servidor nuevo).
* **Manifest** por empresa (`application/manifest+json`, `scope`, `start_url /me/{slug}`, `lang es`), solo en el marco de `/me`. Solo existen íconos de 512 y 180: hacen falta 192 y uno «maskable».
* **Web Push.** Tabla de suscripciones con `tenant_id`, única por `endpoint`, solo https; borrar en 404/410, en «¿No es usted?», al revocar la llave y en «Salir». Texto fijo en pantalla bloqueada («Tiene un aviso en Humetría»). Cada push debe llamar a `showNotification`, o iOS revoca la suscripción. La librería candidata, `minishlink/web-push`, usa Guzzle directo: `Http::fake()` no la intercepta, así que va detrás de un contrato `PushTransport`. Si faltan las llaves VAPID, degrada con log `push.unconfigured` para que CI y local pasen. El push a la empresa demo no se bloquea hoy (`BlockDemoMail` solo cubre correo). Se recomienda recortar los tipos a uno o dos (verificada o rechazada la marca sin conexión; corrección resuelta) y descartar «falta marcar salida», que se dispararía en falso mientras la marca espera en la cola.
* **Plataformas** (de los hechos del brief anterior, sin verificar en teléfono real): iPhone no tiene `BarcodeDetector` (decodificador en JS puro; sin WASM ni workers por la CSP), no envía en segundo plano (lo guardado sale al reabrir la app y el sistema puede borrarlo), y el push solo funciona con la app en la pantalla de inicio (iOS 16.4+).

### El riesgo que decide si existe la función

**La firma con llave sin conexión no está probada en un iPhone ni en un Android reales.** Hay tres lentes que piden hacerlo antes de construir: una página estática https que llama `navigator.credentials.get` con un reto generado en el teléfono y `userVerification: 'required'`, en modo avión, instalada y en pestaña, con una llave sincronizada y una atada al dispositivo (las híbridas, de otro dispositivo, no funcionan sin red). Son ~20 líneas y una hora. Si falla, el camino de marca sin conexión queda sin prueba de identidad al momento de marcar. Como la rama es una sola, la regla de fases del brief anterior desaparece, así que debe convertirse en una **puerta explícita antes de integrar**.

### Primeros resultados de la prueba (iPhone, app instalada, modo avión, 2026-11-20, versión 2 de la página)

* **La app instalada abre en frío sin internet** (cierre completo, modo avión, reapertura): sí abrió, desde el cascarón guardado.
* **Llave de iCloud / Contraseñas de Apple: firmó sin internet** con un reto armado en el teléfono, con rostro verificado y reto, origen y tipo correctos. La comprobación de la firma contra su propia llave pública quedó sin hacer (la página v2 solo guardaba la última llave creada); la versión 3 la corrige y debe repetirse.
* **Llave guardada en Chrome / Google en el iPhone: no firmó.** La hoja del sistema se abre y se cierra sin error visible. Causa no confirmada (pudo ser que Chrome necesita red, o la forma en que la página indicó la llave). Si se confirma, una persona con su llave en Chrome no podría marcar sin conexión, y la app no puede saber dónde guardó su llave: hay que decidirlo (probar una firma de práctica al dar de alta, o guiar a la persona a guardar la llave en iCloud / Android en Google).
* **El lector de QR de la v2 dio un falso positivo.** `QRCamera.readFrame` es asíncrona (devuelve una promesa) y el README de `qr` no lo dice; la página la tomó por un código leído, imprimió `{}` y cerró la cámara tras un cuadro. Verificado en un Chrome real con una cámara simulada: con `await` lee bien (199 ms). Aviso para la construcción: leer el código fuente de `qr`, no solo su README, y cubrir el tipo `Promise<string | DecodeResult[] | undefined>` con una prueba.
* Android: sin probar.

### Resultados de la versión 3 de la prueba (iPhone, app instalada, 2026-11-20 1:46 a 1:51 p. m.)

* **La llave de iCloud / Contraseñas de Apple firma sin internet y la firma es válida** contra su propia llave pública (reto armado en el teléfono, rostro verificado, reto, origen y tipo correctos), en 2.2 s. Otras firmas con llaves de iCloud de la ronda anterior también funcionaron (el dueño reporta que cualquiera de las dos de iCloud funciona).
* **Las dos llaves guardadas en Chrome / Google (en el iPhone) no firman sin internet:** la hoja del administrador de contraseñas se abre y se cierra sin dar firma ni error visible (`NotAllowedError`, ~20 s). No se ha probado con internet, así que no se sabe si es un límite de la ausencia de red o de esa llave en general. Importa para Android, cuyo proveedor por omisión es el mismo Google Password Manager: **la prueba en Android decide si la función existe allí.**
* Al crear la llave, la hoja del sistema ofrece iCloud y Chrome y **marca Chrome por omisión** en el teléfono del dueño (tiene Chrome habilitado como proveedor). Una persona con la configuración por omisión no vería esa elección, pero una que use Chrome, 1Password u otro administrador sí podría guardar su llave donde no firma sin conexión, y la app no puede saber dónde la guardó.
* **El lector de QR funciona en la app instalada, con y sin internet:** leyó 3 de 3 veces en 1.2 a 1.5 s, a unos 20 cuadros por segundo en un iPhone reciente (iOS 18.7). La cámara se abre en menos de 1 s. Sin medir: Android viejo, poca luz, QR de pantalla con moiré en un centro real.
* La app instalada abre en frío sin internet; la nueva versión de la página llegó al teléfono con dos aperturas con internet.
* Opciones para una persona cuya llave no puede firmar sin conexión (sin decidir): guiar en el alta a guardarla en iCloud o en Google según el teléfono, hacer una firma de práctica al dar de alta, o aceptar la marca sin conexión con la sesión de la persona como única identidad (vence a los 30 min sin uso, 8 h máximo), con evidencia más baja.
* **Lo que dice la documentación (investigado el 2026-11-20; después de la prueba, no antes):** ninguna fuente que se encontró dice si una llave de Google Password Manager (GPM) firma sin conexión, ni en Android ni en Chrome para iOS. Lo que sí dice: GPM guarda y sincroniza las llaves cifradas de punta a punta; la documentación de Android dice que la llave privada se guarda en el dispositivo, en el proveedor de credenciales (lo que sugiere que firmar es local, sin confirmarlo); en un dispositivo nuevo hace falta el bloqueo de pantalla de Android o el PIN de GPM; Chrome para iOS exige iOS 17 o superior, activar Chrome en Ajustes → General → Autocompletar y contraseñas, y crear un PIN de GPM al guardar la primera llave (si se pide en cada firma o solo una vez, no está dicho). El artículo de seguridad de Google de 2022 no se pudo leer completo. Una discusión del foro de Apple describe un síntoma parecido (la hoja de la llave se cierra sin resultado tras desbloquear) en Safari y Chrome con iOS 18.6.2 y 26.2, sin relación con estar sin conexión; no se leyó el hilo completo. **Conclusión: solo las pruebas en teléfonos reales lo resuelven.** Pendiente probar la llave de Chrome con internet en el iPhone y la prueba completa en Android.

**Confirmación en el iPhone (dueño, 2026-11-20, después de la investigación):** con la llave de Chrome / Google, **con o sin red**, la hoja pide el **PIN de Google Password Manager y nunca el rostro**. **Con red** el PIN aparece; **sin red** la hoja se cierra de inmediato como si no alcanzara a abrir el panel del PIN. Es decir, en iPhone una llave de Chrome / Google **no puede firmar sin conexión**, coincide con el paso de recuperación de llaves del código de Chrome y obliga al plan B para esas personas.

**Resultado en Android (una persona del dueño, Chrome 157 en una pestaña, versión de Android y modelo aún sin confirmar, 2026-11-20 4:38 a 4:44 p. m., versión 4 de la página):**

* **Una llave de Google Password Manager firmó sin internet 3 veces de 5 intentos de firma** (4:39:41, 4:39:57 y 4:41:02), con la firma válida contra su propia llave pública, rostro/huella/PIN verificado y reto, origen y tipo correctos, en 2.1 a 2.9 s. Esto confirma, en un teléfono, la estimación de 65–70 % de la investigación. **En Android el camino principal (la llave firma sin conexión) funciona; en iPhone solo con llaves de iCloud / Contraseñas de Apple.**
* **También se creó una llave sin internet** (4:39:35 y 4:41:06): GPM la guardó sin red.
* **Fallos intermitentes:** 2 de 5 firmas y 1 de 2 creaciones con `NotAllowedError` (una firma con internet a los 4.6 s, una sin internet a los 0.6 s, una creación a los 12.5 s). No se sabe si la persona cerró la hoja a propósito. La aplicación debe tratar `NotAllowedError` como «cancelado o reintentable», nunca como fallo definitivo, y desactivar el botón mientras haya una petición pendiente.
* **La página abrió sin internet en una pestaña** (4:40:50, «Red real al abrir: no»): el service worker la sirvió desde el cascarón guardado.
* **App instalada en Android, captura de pantalla de la persona (5:23 a 5:24 p. m., modo avión, «instalada»):** la app **abrió sin internet** (5:23:53, «Red real al abrir: no»). Luego dos intentos de **crear** una llave sin internet en la app instalada fallaron con `NotAllowedError` a los 1.9 s y 1.3 s (en la pestaña, crear sin internet había funcionado dos veces). La captura no muestra ninguna firma ni el contador de marcas guardadas: faltan esos resultados. Patrón observado en todo el registro: los fallos rápidos de `NotAllowedError` (menos de 2 s) fueron seguidos por un éxito en un reintento a los pocos segundos en 3 de 3 casos en la pestaña; hipótesis sin probar, que la app reintente una vez antes de mostrar error.
* Se guardaron 17 marcas de prueba en IndexedDB; `persist()` no se concedió (era una pestaña, no la app instalada). **Sin probar:** la app instalada en Android (WebAPK) abriendo en frío sin internet, que el contador sobreviva al cierre de la app, y teléfonos Android viejos o con Play services desactualizado.

### Investigación profunda sobre llaves de acceso sin conexión (5 investigadores, ~190 búsquedas y lecturas, 2026-11-20)

Lo que quedó **confirmado en código o documentación oficial**:

* **Chrome en iPhone (Google Password Manager como proveedor):** la firma se hace en el teléfono (`ios/chrome/credential_provider_extension/passkey_util.mm`, `PerformPasskeyAssertion`), pero antes la extensión debe pedir las llaves del «trusted vault» a código cerrado de Google (`CheckEnrolled`, `FetchKeys`, `CheckDegradedRecoverability`) y solo después pide rostro. Si ese paso devuelve error o vacío, la extensión sale con `ASExtensionErrorCodeFailed` sin mostrar pantalla: la hoja se cierra y Safari entrega `NotAllowedError`. Ese es el síntoma observado. El código público no comprueba conectividad en ningún punto. **Que ese paso necesite red es una inferencia fuerte (~70 %), no un hecho:** el código real es cerrado.
* **Chrome de escritorio con GPM sí necesita internet en cada firma:** firma dentro de un «enclave» en la nube por WebSocket (`device/fido/enclave/enclave_authenticator.cc`, `GetAssertion`). **La prueba en el Chrome de la Mac que se propuso antes no sirve como sustituto de un teléfono y queda descartada.**
* **iCloud / Contraseñas de Apple:** la llave se genera y vive en el dispositivo y se sincroniza cifrada de punta a punta (soporte de Apple). Apple no dice «funciona sin conexión», pero la prueba del dueño lo confirmó.
* **Android:** Chrome entrega la petición a Google Play services o al Credential Manager (Android 14+); el código de Chrome no comprueba red. Google dice que las llaves privadas de GPM están cifradas en reposo en el dispositivo con una clave protegida por hardware. **No existe ningún reporte, de nadie, de una firma de GPM en Android en modo avión** (ni a favor ni en contra). Estimación de los investigadores: 65–70 % de que firme sin conexión. El primer uso en un dispositivo nuevo o restaurado sí necesita red para descifrar. En Android 9–13 solo existe GPM (sin proveedores de terceros). La versión de Play services importa (mínimos por función en `GmsCoreUtils.java`).
* **Proveedores de terceros (1Password, Bitwarden, Dashlane, Samsung Pass, Proton Pass):** sin evidencia a favor ni en contra. Bitwarden reporta que sus llaves dejan de servir sin conexión después de un tiempo en iOS con servidor propio.
* **Especificación WebAuthn:** ningún paso de `get()` exige red, pero §13.4.3 dice que el reto **debe** generarlo la parte que confía (el servidor) en un entorno confiable, §7.2 supone un reto conocido, y el contador de firmas de llaves sincronizadas es siempre 0, así que **no protege contra la repetición**. Un reto armado solo por el teléfono rompe esa regla y varios proyectos quedaron abiertos a repetición por eso. Un reto derivado de un nonce emitido por el servidor más el hash de la marca es lo defendible (SimpleWebAuthn lo admite con `expectedChallenge` como función; Yubico documenta firmar un hash en el reto). Consecuencia para el diseño: el **boleto firmado por el servidor** debe entrar en el reto y consumirse una sola vez; conviene entregar un lote pequeño de boletos con cada contacto en línea, para varias marcas sin conexión.
* **Extensiones:** `devicePubKey` se eliminó de la especificación (PR #2109); PRF y `largeBlob` no ayudan (PRF necesita el mismo `get()` que falla); `getClientCapabilities` no informa del estado de red.
* **Cómo saber dónde está guardada una llave:** el AAGUID de iCloud es `fbfc3007-154e-4ecc-8c0b-6e020557d7bd` y el de GPM `ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4`, pero con atestación `none` Apple y Android no lo garantizan (un AAGUID de tercero puede venir en ceros): sirve para avisar en pantalla, nunca como control de seguridad. La mejor comprobación es una **firma de práctica sin conexión durante el alta**. Un sitio **no puede** impedir que la llave se guarde en un proveedor de terceros (ingeniero de Apple, foro de desarrolladores, hilo 737161); `hints: ['client-device']` es solo una sugerencia (Chrome 129, Safari 18.4).
* **Prior art:** no se encontró ningún sistema de asistencia que firme marcas sin conexión con llaves de acceso. Factorial, Humanforce, Jibble, Deputy y Oracle usan «modo sin conexión» tras al menos un inicio de sesión en línea, marcan la entrada como sin conexión y casi siempre confían en la hora del dispositivo; Deputy limita el guardado sin conexión a 24 horas.

**Plan B recomendado por la investigación, para quien tenga una llave que no firma sin conexión:**
1. **Llave del dispositivo no extraíble** (WebCrypto, ECDSA P-256, `extractable: false`) generada en la app justo después de un inicio de sesión con llave en línea, registrada en el servidor y guardada en IndexedDB. Firma las marcas sin conexión. Riesgos: sin presencia de usuario (cualquiera con el teléfono desbloqueado puede usarla), código inyectado en la página podría usarla, y se pierde si la persona borra los datos o desinstala la app. En iOS las apps instaladas en la pantalla de inicio tienen su propio contador de días (no aplica el borrado a los 7 días de Safari) y reciben `persist()` por heurística; en Chrome se concede solo a sitios instalados.
2. **«Pase sin conexión» firmado por el servidor** y atado a esa llave del dispositivo, válido por unas 24 horas, emitido con cada contacto en línea tras una llave. Limita cuánto sirve un teléfono robado.
3. **Boletos de tiempo y cadena de hashes** (el mismo mecanismo del plan principal) para acotar la hora y detectar marcas borradas o reordenadas.
4. Evidencia **media** (frente a la del plan principal) y marca visible «sin conexión» para Recursos Humanos.

Descartado por la investigación: PRF/largeBlob, `devicePubKey`, el reloj monótono del navegador (se pausa con el teléfono dormido, bug 225610 de WebKit).

**Fuentes principales:** [código de Chromium en iOS](https://source.chromium.org/chromium/chromium/src/+/main:ios/chrome/credential_provider_extension/), [enclave de escritorio](https://chromium.googlesource.com/chromium/src/+/HEAD/device/fido/enclave/), [ruta de Android en Chrome](https://chromium.googlesource.com/chromium/src/+/HEAD/components/webauthn/android/java/src/org/chromium/components/webauthn/Fido2CredentialRequest.java), [seguridad de GPM (Google, 2022)](https://security.googleblog.com/2022/10/SecurityofPasskeysintheGooglePasswordManager.html), [Unit 42 sobre GPM](https://unit42.paloaltonetworks.com/passwordless-authentication/), [WebAuthn nivel 3](https://www.w3.org/TR/webauthn-3/), [w3c/webauthn#1856](https://github.com/w3c/webauthn/issues/1856), [Yubico, firmar con WebAuthn](https://developers.yubico.com/WebAuthn/Concepts/Using_WebAuthn_for_Signing.html), [SimpleWebAuthn, retos propios](https://simplewebauthn.dev/docs/advanced/server/custom-challenges), [guía para proveedores de credenciales de Android](https://developer.android.com/identity/sign-in/credential-provider).

## Goals vs. Non-Goals

* **In-Scope Goals:**
  * App instalable bajo `/me/{slug}` (manifest por empresa, service worker propio con interruptor de apagado, guía de instalación y aviso «ábralo en Safari o Chrome» en navegadores internos).
  * Marcar y Mi semana bajo `/me/{slug}`, sin sesión, con una firma por acción; las rutas de la pantalla del centro se quedan en `/a/`.
  * Lector de QR dentro de la app, con `camera=(self)` solo en Marcar.
  * Marcas sin conexión que cuentan, firmadas con la llave, con las bases `qr_window` y `declared_interval`, la justificación de la persona y la verificación de Recursos Humanos.
  * Enmienda de `docs/normas/jornada/criterios.md` (3.3.1, 5.1, 5.3, 5.4, 6.1, 7.1) antes de la primera línea de código.
  * Avisos push (Web Push, texto genérico), con su manejo de baja.
  * Marcador «sin verificar» en el panel, con el diseño móvil existente de la persona y el panel de «tú».
  * Humi al corriente (lector, criterios en `docs/normas`, `ModuleCoverageTest`).
  * Una pantalla de preparación para saber si el teléfono puede marcar sin internet.
* **Out-of-Scope Non-Goals:**
  * Encuestas anónimas, canal de denuncias y página de trabajadores dentro de la app o de su service worker.
  * El panel `/admin` y la pantalla del centro como app instalada.
  * Guardar en caché páginas o respuestas con datos de una persona.
  * Usar el reloj del teléfono como hora de una marca en línea.
  * Probar que la hora declarada es cierta (se declara «no probada»).
  * Relevo por la pantalla del centro (`screen_relay` / 3b del brief anterior): se difiere de forma explícita.
  * Soporte de teléfono compartido entre dos personas para los avisos push.
  * Topes de frecuencia por persona (se difieren salvo un `max_age`).

## Alternatives Weighed

* **Confiar en la hora del teléfono al reconectar.** Rechazada por el autor: basta cambiar el reloj; contradice el criterio 3.3.
* **Reloj monótono del navegador (`performance.now`) como hora de la marca.** Rechazada por la revisión: se pausa con el teléfono dormido y resta tiempo; favorece a quien llega tarde.
* **Latido cada minuto con tabla de presencia (el ancla de la hipótesis).** Sustituida por la revisión con el boleto sin estado: evita la carrera con la entrega, evita reiniciar el reloj de inactividad de la sesión y no guarda un registro de presencia.
* **Sello del servidor como límite inferior sin más** (brief anterior). Reabierta con mitigaciones: solo se acepta como intervalo [ancla, recibo], con la hora declarada dentro, límites por ancho y marca visible «sin verificar».
* **Solicitud de corrección como único camino sin conexión** (brief anterior). Rechazada por el dueño el 2026-11-20: quiere que la marca cuente, señalada.
* **Relevo de dos escaneos por la pantalla del centro y variante de un escaneo con seis dígitos.** La primera, rechazada por el dueño el 2026-11-14; la segunda queda diferida: esta rama no la incluye.
* **Cachear páginas de Marcar en el service worker.** Rechazada: rompe la CSP con nonce, sirve datos de una persona a otra y queda rota tras cada despliegue.
* **Service worker en la raíz del sitio.** Rechazada: cubriría `/admin` y las páginas anónimas.
* **Decodificador de QR en WebAssembly o en un worker.** Rechazada: obligaría a aflojar la CSP.
* **Workbox o `vite-plugin-pwa`.** Rechazada: dependencias nuevas para ~60 líneas.
* **Cola de Horizon propia para push.** Rechazada: la cola `default` basta.
* **Pantalla de verificación nueva para Recursos Humanos.** Rechazada por la revisión de alcance: un botón «Verificar» en la lista de marcas más la insignia.
* **Dejar la verificación como un UPDATE de `attendance_marks`.** Imposible: el trigger solo permite insertar.
* **Entregar desde el service worker.** Rechazada por los revisores: iPhone no tiene sincronización en segundo plano; la entrega sale de la página con su cookie CSRF. (Un revisor propone lo contrario, sin CSRF; se decide en el diseño formal.)

## Key Decisions & Staging for Next Steps

### Decididas por el dueño (2026-11-20)

1. Todo en una rama y un PR; no se separa en fases. No hay clientes: se pueden cambiar tablas y direcciones.
2. La marca sin conexión **cuenta** y queda señalada como no verificada; la persona puede dar una justificación; Recursos Humanos verifica.
3. El push entra en esta rama.
4. Marcar y Mi semana pasan a `/me/{slug}`.
5. Todo con llave de acceso y con el diseño móvil existente de la persona trabajadora.
6. El dueño valida la función en un iPhone y un Android reales.
7. (Segunda ronda, 2026-11-20) **Se enmienda el criterio 3.3 con un 3.3.1:** una marca sin conexión es válida y cuenta, pero queda con una marca propia («sin conexión») que la distingue de las demás. No exige validación manual para contar.
8. **La verificación de Recursos Humanos es un registro nuevo y relacionado con la marca**, no una edición de ella (la tabla de marcas es de solo inserción). Dice si la marca es correcta o incorrecta y quién lo decidió. No es otra marca que sume horas.
9. **Primero la prueba de la llave sin conexión.** Antes de construir nada se valida en un iPhone y un Android reales con una página desechable (`scratchpad/spike-offline-passkey`). Si falla, se busca otra forma.
10. **Notificaciones es un módulo propio** (`Notifications`), separado de `WorkerAccess`; dos avisos confirmados; Web Push con VAPID.
11. **Semana:** el dueño pide que una semana no se pueda confirmar si le faltan las marcas requeridas. Queda por precisar con un ejemplo (ver «Preguntas abiertas», pregunta 4).
12. **Paquetes aprobados por nombre el 2026-11-20** tras la evaluación exhaustiva (mantenimiento, seguridad, compatibilidad): `qr` (paulmillr) para leer el QR y `minishlink/web-push` en `^11.0` para el push. Su lectura de QR se prueba también en el iPhone y el Android del dueño con la página de prueba antes de construir.
13. **Semana: no se puede confirmar si faltan marcas sin justificación.** Decisión del dueño (2026-11-20): una semana se bloquea cuando a un día que le tocaba trabajar le faltan marcas (ninguna, o entrada sin salida) y la falta no está justificada, ni por el horario (día de descanso o sin turno) ni por vacaciones o permiso. Hoy Asistencia ya sabe cada cosa: `PersonWeek` calcula por día `scheduledMinutes`, `restDay` y `absence`, y las vacaciones y permisos vienen del contrato `Absences` de `Leave` (que dice «vacaciones» o «permiso», nunca el tipo). Pero `ConfirmWeek::blocker` solo bloquea sin ninguna marca en la semana o con la última jornada sin salida, así que hoy se puede confirmar con días vacíos. Se añade además el bloqueo con marcas sin enviar en el teléfono y la reapertura si llega una marca tarde. Consecuencia que falta decidir: qué desbloquea un día sin marcas y sin justificación (ver la pregunta 15 de las abiertas).
14. **Intervalos de las marcas sin QR:** se quedó la recomendación (los tres tramos: hasta 4 h cuenta a la hora declarada; más de 4 h cuenta desde lo probado y la hora declarada pasa a contar cuando la verificación la confirme). El dueño dijo primero «opción A» (siempre la hora declarada) y en el mismo mensaje corrigió «nos quedamos con tu recomendación»; se interpretó como B. **Confirmado por el dueño el 2026-11-20** («agarra la recomendación que tú me des»).
15. **El plan B va en esta misma rama** (decisión del dueño, 2026-11-20): llave del dispositivo no extraíble, pase sin conexión firmado por el servidor y evidencia media, para quien tenga una llave que no firma sin conexión (por ejemplo una guardada en Chrome / Google en iPhone). Además, una firma de práctica sin conexión durante el alta y aviso en pantalla según el AAGUID.
16. **El lector de QR ya no necesita más pruebas del dueño en Android** (se validó en iPhone; se mide en teléfonos reales al final).
17. **La prueba en Android la hace otra persona** con una guía sencilla; la guía y el registro (`Copiar resultados`) están en la página de prueba.
18. **La validación de llaves en teléfonos se cierra con los datos que hay (decisión del dueño, 2026-11-20).** Verificado: iPhone con llave de iCloud firma sin conexión; iPhone con llave de Chrome / Google no firma (obliga al plan B); Android con Google Password Manager firmó sin conexión 3 de 5 intentos en una pestaña y la app instalada abrió sin conexión. **No verificado, y pasa a la lista de revisión en teléfonos reales antes de integrar:** firma sin conexión desde la app instalada de Android, Android viejo o con Play services desactualizado, que el contador de marcas sobreviva al cierre de la app en Android, y la hipótesis de reintentar una vez ante un `NotAllowedError` rápido. El túnel y la página de prueba se cerraron.
19. **Marca sin conexión que Recursos Humanos nunca verifica:** sigue contando; se muestra cuánto lleva sin verificar y hay aviso a Recursos Humanos cuando hay marcas viejas sin revisar. Ni se acepta ni se rechaza sola.
20. **Un solo total** para horas extra y retardos: el que ya cuenta según el tramo (decisión 14). Sin segundo total «declarado». El reporte marca esos días como «con marcas por verificar».
21. **Marca sin conexión con QR:** no necesita verificación (hora exacta ±30 s, evidencia media, etiqueta «sin conexión»). Solo la marca sin QR espera verificación. Un QR de más de 72 horas al llegar pasa a solicitud para Recursos Humanos.
22. **Pérdida por borrado de datos del teléfono:** se acepta el riesgo, con almacenamiento persistente solicitado, contador visible «n marcas sin enviar» y texto honesto («si borra los datos de la app antes de que se envíe, esta marca se pierde»).
23. **Acuerdo de registro (criterio 5.1):** versión nueva que diga que una marca puede guardarse en el teléfono, llevar una hora declarada y capturar la ubicación; obliga a aceptar de nuevo y debe poder hacerse antes de un apagón. El texto lo redacta el diseño formal para revisión del dueño.
24. **Etiquetas.** Para la persona: «Guardada sin conexión» → «Recibida» → «Verificada» o «No aceptada». Para Recursos Humanos: insignia «Sin conexión · por verificar» (no «sin verificar»).
25. **La hora del reloj del teléfono** se muestra a Recursos Humanos solo como pista discreta, nunca como evidencia ni contada.
26. **Interruptor `ATTENDANCE_OFFLINE_ENABLED`** solo para la cola del cliente y la interfaz sin conexión; la ruta que recibe marcas nunca se apaga.
27. **Medida de éxito:** cuatro conteos sin personas: intentos contra entregas, edad de lo no entregado, proporción rechazada y ancho típico del intervalo.
28. **Delegación de las decisiones de arquitectura (dueño, 2026-11-20):** «en esta y siguientes seguiré tu recomendación». Con ella, el diseño formal tomó las recomendaciones de sus revisiones y cambió cinco decisiones de arriba; el dueño puede revertir cualquiera:
    * **Decisión 14 (tres tramos):** la hora que cuenta es la hora declarada, acotada entre la emisión del boleto (y la ventana del QR), las marcas vecinas y el recibo, cuando el intervalo entre emisión y recibo es de hasta 72 h; con más de 72 h cuenta solo la cota probada hasta que Recursos Humanos la revise. Más de 240 min enciende la bandera «intervalo ancho» (por verificar, primero en la lista).
    * **Justificación de falta (decisión 13 y pregunta 15):** no se construye la solicitud nueva; un día sin cubrir se resuelve con una corrección o pidiendo a Recursos Humanos que registre la ausencia como permiso en Vacaciones y permisos. La solicitud nueva queda para después. El bloqueo de la semana se queda, con un interruptor para apagarlo. El plan B (decisión 15) no cambia.
    * **Decisión 19 (marcas nunca verificadas):** siguen contando; «Necesita tu atención» y Humi cuentan las que tienen bandera o llevan más de 7 días sin revisar; el correo a Recursos Humanos queda para después.
    * **Decisión 21 (QR sin verificación):** toda marca sin conexión, con o sin QR, queda «por verificar». El QR prueba que la marca no fue antes de su ventana, no la hora exacta; un QR leído más de 30 min antes de la hora declarada cuenta como marca sin QR (evidencia básica). Se quita la regla de las 72 h del QR.
    * **Decisión 27 (medidas de éxito):** se miden solo lo que ve el servidor (marcas entregadas por día, proporción rechazada por motivo, ancho del intervalo p50 y p95, edad en la cola al entregar) más el número y la edad de las marcas en cola que el teléfono informa al pedir boletos. «Intentos contra entregas» se quita.

**Propuestas técnicas de la revisión** (boleto sin estado, `RecordOfflineMark` separado, mudanza del prefijo `a/` a `me/`, service worker por empresa, `camera=(self)` con enlace completo a Marcar, reapertura de la semana confirmada): pasan al diseño formal (`/egm:egm-draft`), donde se revisan.

### Propuestas de la revisión que necesitan su visto bueno

* Enmienda de la norma como primer commit (C1) y tabla de revisión de solo inserción (C2).
* Boleto sin estado en lugar de un latido con tabla (C4 de la revisión, explicado en «Cómo se obtiene la hora»).
* `RecordOfflineMark` separado de `RecordMark`, con evento y reapertura de la semana confirmada.
* Mudar solo el prefijo `a/` → `me/` de Marcar y Mi semana, manteniendo los nombres de ruta.
* `<a href>` completo hacia Marcar, con `camera=(self)` solo en ese GET.
* Service worker por empresa, escrito a mano, con interruptor que no apaga la entrega.

### Preguntas abiertas para el dueño (cada una dice qué bloquea)

**Estado al 2026-11-20:** resueltas las preguntas 1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14 y 15 (decisiones 12 a 27 de arriba). Sigue por precisar en el diseño formal la 4 (marca tardía en una semana confirmada: se reabre; una semana objetada sigue objetada y Recursos Humanos ve la marca nueva), la solicitud de justificación de falta (decisión 13) y la medida de éxito en producción. El texto de abajo conserva las preguntas como se hicieron.

1. **Resuelta y confirmada (2026-11-20):** los tres tramos (decisión 14). Los cortes de 30 min y 4 h son configurables.
2. **¿Qué pasa con una marca que Recursos Humanos nunca verifica?** Se vence, se acepta sola, se rechaza o solo se marca su antigüedad. Un atraso sin límite es la mayor deuda a largo plazo.
3. **¿Cuál total alimenta horas extra y retardos (2.3, 2.9): el probado o el declarado?** ¿Cuenta una marca sin verificar para el retardo?
4. **¿Qué hacer con una marca tardía en una semana ya confirmada o disputada?** Se recomienda reabrirla; ¿la confirmación semanal (5.2) se bloquea mientras haya marcas sin verificar?
5. **¿Una marca con código QR también necesita verificación,** o solo la declarada? ¿Cuántos días puede tener un QR al reconectar, y si el código no deriva una ventana se degrada o se rechaza?
6. **Resuelta (2026-11-20):** `Notifications` es un módulo propio, con los dos avisos (marca sin conexión verificada o rechazada; corrección resuelta).
7. **Resuelta:** «MAPI» es VAPID, es decir Web Push con llaves VAPID (confirmado el 2026-11-20).
8. **Paquetes con nombre** (regla 5 de CLAUDE.md), evaluados el 2026-11-20 contra npm, Packagist, GitHub y OSV; **aprobados por el dueño el 2026-11-20** (qr y minishlink/web-push):
   * **Decodificador de QR: `qr` (paulmillr), importando solo `qr/dom.js` y `qr/decode.js`.** 0 dependencias, provenance SLSA, 9 versiones desde junio 2025 (última 2026-09-28), MIT OR Apache-2.0, sin avisos en OSV, sin WASM, workers de blob ni eval (compatible con la CSP), tipos que pasan en modo estricto sin casts. Un solo mantenedor. La calidad de lectura (pantallas con moiré, poca luz, Android viejo) la reporta su propio autor y no está verificada en teléfonos. Respaldo: `jsqr` (abandonado desde 2021, más lento). Descartados: `zxing-wasm`/`barcode-detector` y `@yudiel/react-qr-scanner` (WASM, la CSP lo bloquea), `qr-scanner` de nimiq (worker de blob), `html5-qrcode` (abandonado), `@zxing/*` (modo mantenimiento), `@undecaf/zbar-wasm` (WASM y LGPL). `@paulmillr/qr` está obsoleto: el nombre vigente es `qr`.
   * **Web push: `minishlink/web-push` en `^11.0`.** Solo la v11 instala con el Guzzle 8 del lock; resuelve sin cambiar ninguna versión (6 paquetes nuevos), MIT, 3.6 M de instalaciones al mes, sin avisos, `composer audit` limpio; acepta un cliente PSR-18, así que se prueba con `Http::fake()` mediante un adaptador de ~25 líneas. Hay que indicar `aes128gcm` en cada suscripción y manejar `Retry-After` a mano. Descartados: `laravel-notification-channels/webpush` (su modelo no lleva `tenant_id` y supone un usuario con cuenta), v10 de minishlink (exige Guzzle 7), `spomky-labs/web-push-lib` (3.7 mil instalaciones al mes, pensado para Symfony). Alternativa si se rechaza toda dependencia: un emisor propio de ~200 líneas con riesgo de errores silenciosos con Apple.
   * Íconos de 192 px y uno maskable.
9. **Almacenamiento en iPhone:** ¿se acepta que una marca en cola se pierda si el sistema borra los datos? Mitigación: `navigator.storage.persist()`, instalar la app y un texto honesto.
10. **Texto del acuerdo (5.1):** versión nueva; ¿el aviso de privacidad debe cubrir push y el almacenamiento en el teléfono?
11. **Etiqueta:** «sin verificar» o «por verificar», y una sola palabra para «sin señal» o «sin conexión».
12. **¿La señal del reloj del teléfono es una pista para Recursos Humanos (nunca evidencia) o no se usa?** Los revisores no coinciden.
13. **Interruptor por función:** un `ATTENDANCE_OFFLINE_ENABLED` solo para la cola del cliente y la interfaz sin conexión (nunca para la ruta de entrega), contra la decisión anterior de «sin interruptores por fase».
14. **Medida de éxito:** ¿se aceptan las medidas de entregas contra intentos, edad de lo no entregado, proporción rechazada y ancho de intervalo p50/p95?
15. **Resuelta (2026-11-20):** un día que le tocaba trabajar, sin marcas y sin vacaciones ni permiso, se desbloquea de dos maneras juntas: la persona pide una corrección (agrega las marcas que olvidó) o justifica la falta con un motivo que Recursos Humanos aprueba. Falta diseñar la solicitud de justificación de falta (no existe hoy).

### Orden propuesto en un solo PR (commits atómicos, cada uno en verde)

Antes de todo: la prueba de firma sin conexión en teléfono real (puerta de integración) y las respuestas a las preguntas 1, 4, 5, 8 y 11.

1. Norma: `criterios.md` y su prueba, aviso en el brief anterior y notas en CLAUDE.md.
2. Mudanza de rutas de Marcar y Mi semana a `/me`, URL del QR del centro, pruebas de 404.
3. Manifest e íconos.
4. Service worker (sin comportamiento sin conexión), registro, interruptor, versión.
5. Lector de QR, `camera=(self)` y su prueba de navegador con cámara simulada.
6. Migración, modelos, enums, restricciones, índice único.
7. Reto canónico en PHP y JS con vectores compartidos y `verifyChallenge`.
8. Boleto y ruta de renovación.
9. `QrToken::windowOf`, `RecordOfflineMark`, ruta de entrega, evento y reapertura de semana, límite de tasa, logs y bitácora.
10. Panel de Recursos Humanos: marcador, tabla de revisión, reporte y CSV, Mi semana, Humi.
11. Cola y pantallas sin conexión en el cliente, con los textos de cada estado y la pantalla de preparación.
12. Notificaciones: módulo o sección, migración, contratos, tarea con baja en 404/410, configuración VAPID.
13. Cliente de push y guía de instalación.
14. `docs/operations/pwa.md` (variables, interruptor, reversión, lista de pruebas en teléfonos reales).

Lo que solo se prueba en teléfonos reales (lista corta para el dueño): instalación en iPhone y Android; marca en modo avión con QR y sin QR; señal intermitente con una respuesta perdida; app cerrada y reabierta; borrado de datos con el aviso «n marcas sin enviar»; llave sincronizada y atada al dispositivo; entrega fuera de orden; push con pantalla bloqueada y su baja; QR con poca luz; interruptor de apagado; direcciones viejas con 404; teléfono compartido; captura de enlaces en Android y correos en navegadores internos. Si la fila de la llave sin conexión falla, la función sin conexión sale del PR en lugar de entregarse apagada.

Si falla la validación en teléfono real a mitad de camino: se recorta primero el cliente sin conexión, luego el servidor sin conexión, luego el push; la instalación y el lector no se tocan porque cargan el criterio de éxito. Las migraciones del grupo sin conexión son aditivas y solo hacia adelante.
