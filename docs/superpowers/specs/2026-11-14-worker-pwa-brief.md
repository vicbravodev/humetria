# App instalable para la persona trabajadora (PWA)

* **Author:** Victor Jesus Bravo de la Peña
* **Date:** 2026-11-14
* **Revisión:** 7 de 7 revisores corrieron (diseño y seguridad, exactitud del código, alcance mínimo, comprensión y experiencia, puesta en producción, estructura, ideas alternativas). Varios avisaron que no abrieron todos los archivos sugeridos; lo que afirman del código se contrastó con el revisor de exactitud, que sí citó archivo y línea. Después hubo tres revisiones dirigidas: seguridad de la marca por la pantalla del centro, y seguridad y estructura de la identidad única. Todos los revisores corrieron en Sonnet.

## Overview

Humetría ya está en producción, pero todavía no tiene empresas clientes (2026-11-14). La persona trabajadora (no Recursos Humanos) usa dos entradas web sin cuenta:

- `/a/{slug}`: Marcar y Mi semana (módulo Attendance). Cada acción la firma una llave de acceso (WebAuthn) guardada en su teléfono.
- `/portal/{token}`: su portal personal (vacaciones, equipo, Ley Silla, política, teletrabajo). El enlace llega por correo y es la credencial.

Hoy no existe ninguna PWA: no hay manifest ni service worker. El diseño de asistencia la prometió (`docs/superpowers/specs/2026-11-07-attendance-design.md`, línea 31) y no se construyó.

**Qué se propone.** Primero, una sola identidad para la persona trabajadora: entra con su llave de acceso o con un enlace de un solo uso, y el enlace estable del portal desaparece. Después, sobre esa identidad, una app instalable con Marcar, Mi semana y el portal, con ícono, pantalla completa y accesos directos. Dentro de la app hay un lector del código del centro, avisos push y dos formas de marcar cuando no hay señal. Se entrega en fases.

**Para quién.** Personas en planta, bodega, tienda u oficina, con Android viejo o iPhone, que no son técnicas y a quienes la app trata de "usted".

**Palabras que usa este documento**

| Palabra | Qué es |
|---|---|
| Pantalla del centro | La tableta fija del centro de trabajo que muestra un QR. |
| Código del centro | El contenido de ese QR. Cambia cada 30 segundos y solo el servidor puede calcularlo. |
| Llave de acceso | La credencial WebAuthn de la persona; se desbloquea con huella, rostro o PIN. |
| Marca | Un registro de entrada, salida o pausa. Su hora la pone el servidor (criterio 3.3). |
| Solicitud de corrección | El flujo que ya existe: la persona propone una hora y Recursos Humanos la acepta o la rechaza. |
| Hora declarada | La hora que la persona dice, sin prueba. |

## Analysis

### Hechos que acotan el diseño

**En el código (confirmados con archivo y línea)**

- El código del centro es un HMAC-SHA256 sobre empresa, centro y ventana de 30 s (`app/Modules/Attendance/Support/QrToken.php`). El servidor acepta la ventana actual y la anterior.
- Marcar firma un reto aleatorio que da el servidor, vive 60 s, se usa una vez y está ligado a la sesión (`WebAuthnCeremony`).
- Criterio 3.3 (`docs/normas/jornada/criterios.md`): la hora de cada marca la pone el servidor, nunca el reloj del teléfono.
- `SecurityHeaders.php:57` envía `Permissions-Policy: camera=()` en todas las respuestas. **Ninguna página puede abrir la cámara hoy.** Hay precedente para abrir un permiso en una sola respuesta: `MarkController` ya pone `geolocation=(self)` en la suya.
- La CSP lleva un nonce nuevo por petición, incrustado en `resources/views/app.blade.php`. Una página guardada en caché lleva un nonce viejo y sus scripts quedan bloqueados.
- La página de Marcar incluye en sus datos a la última persona que marcó en ese navegador (10 minutos) y el token CSRF.
- La cookie de la pantalla del centro dura 5 años y solo permite leer el código (`KioskController`).
- `RequestCorrection` exige un motivo de 10 a 500 caracteres, no admite horas futuras ni de más de 35 días, y limita a 10 las solicitudes pendientes por persona. No tiene dónde guardar una ventana de tiempo probada.
- Las notificaciones salen por correo, pero `SurveyInvitation` ya arma sus canales desde una lista de rutas pensada para agregar más. El contador de cuota (`Billing/Listeners/CountSentMail.php`) solo cuenta correo.
- Piezas reutilizables: `resources/js/hooks/use-resend-when-online.ts`, los componentes `*-offline.tsx`, `DeviceLinked` como plantilla de aviso en cola, y los comandos diarios de `routes/console.php`.
- Pruebas que tocan esto: `RouteSecurityTest` (cada ruta pública con su razón), `TenantScopedModelsTest`, `BrowserCoverageTest`, `PageFrameTest`, `ArchitectureTest`.

**En las plataformas (noviembre de 2026)**

- iPhone abre los enlaces en Safari o en el navegador interno del correo, nunca en la app instalada. Solo una notificación abre la app. Android con Chrome sí captura los enlaces dentro del alcance de la app.
- iPhone no trae activo el lector nativo de códigos (`BarcodeDetector`). Hace falta una librería.
- En iPhone el push solo funciona con la app en la pantalla de inicio (iOS 16.4 o posterior), y la instalación es manual.
- iPhone no envía en segundo plano. Lo que se guarde sin señal sale cuando la persona vuelve a abrir la app.
- En iPhone, la app instalada y Safari guardan sus datos por separado. La llave de acceso sirve en ambos.
- **Sin verificar:** que la llave de acceso firme sin conexión en iPhone y Android reales.

### Lo que la revisión cambió

**1. El service worker no puede guardar páginas.** Cuatro revisores llegaron a lo mismo por caminos distintos. Una página de Marcar en caché mostraría a la persona anterior en un teléfono compartido, llevaría un nonce y un token CSRF viejos, y tras un despliegue apuntaría a archivos que ya no existen. El service worker solo puede guardar un cascarón estático y los archivos del build. Los datos de la persona se piden en vivo y nunca se guardan. Se sirve bajo el prefijo de la app (una ruta en la raíz cubriría `/admin` y las páginas anónimas), sin caché del navegador, con la versión de `HandleInertiaRequests::version()`, y con un interruptor de apagado: un service worker que se desregistra y borra sus cachés.

**2. El "sello" de la marca en espera no prueba nada.** La propuesta anterior guardaba un sello del servidor como límite inferior de tiempo. El ataque es directo: la persona toma un sello a las 07:00 con wifi, llega a las 08:30, pone modo avión y declara 08:02. Todo cae dentro del tope. Mostrarle a Recursos Humanos "firmada entre las 07:40 y las 18:05" se leería como prueba y no lo es. Se elimina el sello. Usar el código del centro como límite inferior tampoco resuelve: prueba "no antes de", que es justo la mentira que conviene a quien llega tarde con una foto reenviada.

**3. Sin conexión no existe una hora segura en una página web.** Solo hay dos resultados honestos:

- **Constancia sin señal** (antes "Camino B"): la persona declara la hora, la llave firma esa declaración y la ubicación del momento, y al volver la señal entra como solicitud de corrección marcada "hora declarada, no probada". Decide Recursos Humanos. Es una comodidad, no una prueba.
- **Marca a través de la pantalla del centro**: el teléfono sin señal entrega una firma a la pantalla del centro, que sí tiene internet, y el servidor pone la hora. Es la única marca real sin señal y cumple el criterio 3.3 sin cambiarlo. Tuvo dos variantes; ver el punto 4.

**4. La marca a través de la pantalla del centro: se descartó el relevo de dos escaneos y se adopta la variante de un escaneo con el código de seis dígitos.**

*Relevo de dos escaneos (descartado por el dueño del producto, 2026-11-14).* El teléfono lee el código del centro, firma, y muestra un QR que la pantalla lee. Son unas 8 acciones por persona; a 15–20 segundos cada una, 40 personas hacen una fila de 10 a 13 minutos.

*Variante de un escaneo (propuesta por el dueño del producto; adoptada el 2026-11-14 con los cambios de la revisión de seguridad).* La persona abre la app sin señal, pone su huella y el teléfono muestra un QR. La pantalla del centro lo lee y lo envía. Solo la usa quien no tiene datos ni wifi; los demás marcan como hoy.

Condición: la pantalla tiene internet y el teléfono no. Ocurre cuando la persona no tiene saldo o la empresa no comparte su wifi.

Un revisor de seguridad dedicado (Sonnet, 2026-11-14) intentó romperla. Veredicto: **no se adopta como estaba escrita; se puede adoptar con los cambios de abajo.**

*Lo que falla en la versión original (firma sobre tipo de marca, ubicación y hora del teléfono):*

- **Nada prueba que la persona esté en el centro.** La llave prueba quién la desbloqueó, no que la hora y la ubicación escritas en la firma sean ciertas. La persona adelanta el reloj en casa, firma y manda la captura a un compañero que la muestra a esa hora. Pasa con una app que falsifica el GPS, con el permiso de ubicación negado o con una política sin geocerca.
- **El código actual no puede verificar un reto armado por el teléfono.** `WebAuthnCeremony::consume()` solo acepta retos que el servidor emitió y guardó 60 s. Las llaves sincronizadas siempre reportan contador 0, así que el contador no protege contra la repetición.
- **La comprobación del reloj rechazaría a gente honesta.** Un teléfono varios días sin conexión, o con la hora automática apagada, se desvía más de un minuto.

*Cambios con los que se sostiene:*

1. **Un código corto de la pantalla dentro de la firma.** La pantalla muestra, junto a su QR, seis dígitos derivados de la misma ventana de 30 s (`QrToken`). La persona los teclea, o los lee con el lector de la app, antes de poner su huella. El servidor los recalcula. Un QR preparado en casa deja de ser posible y el reloj del teléfono ya no se usa para nada. Costo: 5 a 8 segundos por persona. Ataque que queda: un compañero en el centro le dicta el código a alguien con datos, que es el mismo ataque de hoy con la foto reenviada.
2. **Verificación propia en el servidor.** El servidor reconstruye el reto como un hash de empresa, centro, tipo de marca, ubicación, código y valor aleatorio, lo compara con el que firmó la llave, y guarda `(tenant_id, credential_id, valor aleatorio)` con índice único en la misma transacción que la marca. El camino normal de Marcar no cambia.
3. **Credencial de escritura aparte para la pantalla.** Ligada a la pantalla y al centro, se renueva cada 30 días mientras la pantalla siga activa y se revoca por separado. Límite por pantalla, `kiosk_id` en cada marca y rechazo de personas de otro centro.
4. **Copia firmada en el teléfono.** Si la pantalla descarta, retrasa o desordena un envío, la persona no tiene cómo demostrarlo: el recibo está en una pantalla del patrón. El teléfono guarda cada firma; al recuperar la señal ofrece "mi marca no aparece en Mi semana" y la envía como solicitud de corrección. Prueba la intención, no la hora.
5. **Nivel de evidencia propio.** Un método nuevo (`screen_relay`): evidencia básica sin el código; media con el código y la zona; nunca fuerte. Recursos Humanos ve cómo se hizo la marca. Requiere agregarlo a los criterios.
6. **Detalles.** La ubicación viaja en el QR hacia una tableta del patrón: se reduce a la zona y una bandera de distancia, y se dice en el aviso de privacidad. `camera=(self)` solo en la página de la pantalla, con su prueba. Eventos `screen_relay.received` y `screen_relay.rejected` con el motivo, y bitácora en cada aceptación.

Con el cambio 1 esta variante vuelve a parecerse al relevo descartado: la pantalla da algo fresco y el teléfono da la firma. La diferencia es que el primer paso son seis dígitos tecleados en lugar de un escaneo, y que solo la usa quien no tiene conexión.

El dueño del producto aceptó el ataque que queda. Para lograrlo, un compañero tiene que dictar el código, recibir la captura en menos de un minuto y acercar a la pantalla un teléfono que muestra el QR de otra persona, mientras la pantalla enseña el nombre de la persona ausente en el recibo. Además, la ubicación firmada de la persona ausente cae fuera de la zona, así que la marca queda con evidencia básica y señalada.

Sigue dependiendo de que la llave firme sin conexión en teléfonos reales (sin verificar), del service worker de la fase 3 y de medir en tabletas reales la lectura de un QR de 350 a 900 bytes.

*Riesgo que ya existe hoy y esta revisión destapó:* quien copie la cookie de lectura de una pantalla puede pedir el código del centro desde cualquier lugar durante 5 años. Conviene que el servidor registre cuándo vio a cada pantalla por última vez y señale las marcas que llegan mientras la pantalla lleva tiempo sin aparecer.

**5. El lector del código del centro sí es necesario, por una razón distinta a la del borrador.** El revisor de alcance propuso no construirlo: en Android la app captura el enlace y en iPhone Safari funciona. Pero en iPhone la app instalada no tiene forma de leer el código: la persona tendría que salir a la cámara y terminaría en Safari. Sin lector, instalar la app en iPhone empeora marcar en un centro con pantalla. Con lector, la app instalada sirve en ambos sistemas. Requiere abrir `camera=(self)` solo en Marcar y un decodificador en JavaScript puro en el hilo principal, para no tocar la CSP.

**6. El push no tenía dueño.** Lo usarían Attendance, Leave y Telework. Si vive en Attendance, los otros módulos tendrían que importarlo. Va en un módulo `Notifications` pequeño (ya previsto en la arquitectura) que guarda las suscripciones y envía, detrás de un contrato. Los correos actuales no cambian.

**7. El recorrido de la persona tenía huecos.**

- El enlace de alta se abre en el navegador interno del correo, donde no se puede instalar, y `enroll-done.tsx` termina sin decir qué sigue.
- Al abrir la app instalada por primera vez en iPhone no hay datos: la persona debe ver "Entrar con mi huella", y la firma le devuelve su nombre y su centro.
- Quien escanea con la cámara del teléfono teniendo la app instalada cae en Safari sin saber por qué.
- Después de una constancia sin señal, la persona no puede ver "Listo": no es una marca.
- El permiso de notificaciones pedido al abrir, sin contexto, se rechaza y es difícil de revertir.

**8. "Mi portal" con la llave abre un riesgo nuevo.** Entregar el enlace estable del portal tras una firma convierte una llave sincronizada en la entrada a datos de Ley Silla y teletrabajo, y deja la credencial en la barra de direcciones y el historial. Lo resuelve el proyecto previo de identidad: el portal entra por sesión y las vistas sensibles piden una firma nueva.

### Riesgos que quedan abiertos

- La firma sin conexión no está probada en teléfonos reales. La fase 3 depende de eso.
- En iPhone, una constancia sin señal se queda en el teléfono hasta que la persona reabra la app; el sistema puede borrar ese almacenamiento.
- La mayoría de las personas con iPhone no instalará la app. Todo debe funcionar igual en Safari.
- Una suscripción push liga un teléfono con una persona. En un teléfono compartido, el aviso de una persona puede aparecer a otra si no se borra al cambiar de persona.

## Goals vs. Non-Goals

**In-Scope Goals**

- Proyecto previo: una sola identidad para la persona trabajadora (módulo propio, sesión, enlace de un solo uso, portal sin credencial en la dirección).
- Fase 1: app instalable con manifest por empresa, guía de instalación, captura de enlaces en Android y lector del código del centro dentro de la app.
- Fase 2: avisos push para la persona, con un service worker que solo recibe avisos y no guarda nada.
- Fase 3: marcar sin señal. Donde hay pantalla del centro, marca real a través de ella con el código de seis dígitos. Donde no hay, constancia sin señal como solicitud de corrección firmada. Ambas con un service worker que guarda solo el cascarón estático.
- Una medida de éxito: proporción de marcas hechas desde la app instalada y segundos desde abrir hasta "Listo".

**Out-of-Scope Non-Goals**

- Encuestas anónimas, canal de denuncias y página de trabajadores: nunca dentro del alcance de la app ni de su service worker.
- El panel `/admin` y la pantalla del centro como app instalada.
- El detalle de la identidad única: lleva su propio diseño formal y su propia revisión.
- Confiar en el reloj del teléfono para la hora de una marca.
- Guardar en caché páginas o respuestas con datos de una persona.
- Soporte de teléfono compartido entre dos personas para avisos push.

## Alternatives Weighed

- **Confiar en la hora del teléfono al reconectar.** Rechazada por el autor: basta cambiar el reloj; contradice el criterio 3.3.
- **Sello del servidor como límite inferior de tiempo.** Rechazada por el autor tras la revisión: un sello tomado temprano deja declarar cualquier hora posterior, y la etiqueta "firmada entre X y Y" aparenta una prueba que no existe.
- **Código del centro como límite inferior en la constancia sin señal.** Rechazada por el autor: prueba "no antes de", que favorece a quien llega tarde con una foto reenviada. El servidor no debe adivinar la hora.
- **Pantalla del centro que funciona sin conexión con una llave diaria.** Rechazada por el autor: obliga a confiar en el reloj de una tableta que controla el patrón.
- **Relevo de dos escaneos por la pantalla del centro.** Rechazada por el dueño del producto: donde la pantalla tiene wifi lo común es que la persona también lo tenga, y dos escaneos por marca hacen fila al inicio del turno.
- **Un compañero con señal como testigo en lugar de la tableta.** Rechazada por el autor: es una variante del relevo de dos escaneos, que se descartó.
- **QR personal fijo que la pantalla del centro lee.** Rechazada por el autor: con una captura de pantalla, otra persona marcaría por ella.
- **Marca por la pantalla del centro sin el código de seis dígitos.** Rechazada por el dueño del producto: la evidencia sería básica, igual que una marca remota, y deja abierto el QR preparado en casa.
- **QR firmado que usa la hora del teléfono para descartar los viejos.** Rechazada por el autor tras la revisión de seguridad: el reloj se puede adelantar para preparar el QR en casa, y un reloj desajustado rechaza a gente honesta.
- **No construir el lector de QR.** Rechazada por el autor: en iPhone la app instalada no podría marcar en un centro con pantalla.
- **No construir push; dejar solo el correo.** Rechazada por el autor: el dueño del producto pidió avisos. Se acota a un service worker sin caché y a una lista corta.
- **Service worker que guarda las páginas de Marcar.** Rechazada por el autor: rompe la CSP con nonce, sirve datos de una persona a otra y queda roto tras cada despliegue.
- **Un service worker en la raíz del sitio.** Rechazada por el autor: cubriría `/admin` y las páginas anónimas.
- **Decodificador de QR en WebAssembly o en un worker.** Rechazada por el autor: obligaría a aflojar la CSP (`wasm-unsafe-eval` o `blob:`).
- **Llave de acceso como credencial general, con el enlace del portal solo como arranque.** Adoptada como proyecto previo, con un cambio que pidió la revisión: el arranque es un enlace de un solo uso, no el enlace estable del portal.
- **Encuestas dentro de la app con boletas ciegas (RFC 9474).** Rechazada por el autor por ahora: agrega criptografía y una boleta que se pierde si se borran los datos, y no elimina la correlación por hora e IP.
- **Calendario personal (ICS) con alarmas para recordar la salida.** Rechazada por el autor: suena aunque la persona ya haya marcado y expone el horario si el enlace se filtra.
- **Entrar al portal con la llave ("Mi portal").** Absorbida por el proyecto previo de identidad: el portal entra por sesión y deja de ser una pieza aparte.
- **App primero sobre `/a/{slug}`, identidad después.** Rechazada por el dueño del producto: saldría solo para empresas con asistencia y después habría que mover el manifest, el service worker y las suscripciones.
- **Camino intermedio (módulo de identidad y prefijo, luego la app, luego el portal a sesión).** Rechazada por el dueño del producto: deja vivo el enlace estable del portal mientras tanto.

## Key Decisions & Staging for Next Steps

### Decisiones tomadas

1. La app es para la persona trabajadora. Recursos Humanos no la usa.
2. Las encuestas anónimas quedan fuera (confirmado por el dueño del producto).
3. El criterio 3.3 no cambia: ninguna pieza usa el reloj del teléfono como hora de una marca.
4. Sin señal, lo que la persona registra es una solicitud de corrección con hora declarada, no una marca.
5. El service worker nunca guarda páginas ni datos de una persona, vive bajo el prefijo de la app y tiene interruptor de apagado.
6. El push vive en un módulo `Notifications` pequeño, detrás de un contrato.
7. Cada fase se entrega en su propio PR. Como no hay empresas clientes, las migraciones pueden cambiar tablas existentes y no hacen falta interruptores por fase. El interruptor de apagado del service worker sí se conserva, porque protege a los teléfonos una vez que haya gente usándolo.
8. Dependencias nuevas aprobadas por el dueño: un decodificador de QR en JavaScript puro y una librería de web push para PHP.
9. El relevo de dos escaneos no se construye. La marca sin señal por la pantalla del centro lleva el código de seis dígitos dentro de la firma, con evidencia media cuando además la zona coincide y nunca fuerte.
10. Orden por fases aceptado: app instalable y lector, luego avisos push, luego marcar sin señal. La fase 3 espera a la prueba de firma sin conexión en un iPhone y un Android reales.
11. Las constancias sin señal se cuentan aparte de las 10 solicitudes de corrección pendientes, con un tope propio de 5.
12. Los avisos push no tienen cuota por empresa por ahora.
13. Todavía no hay empresas clientes (2026-11-14). El diseño puede cambiar lo que ya existe sin migrar a nadie y debe quedar bien desde el inicio.
14. La cookie de 5 años de la pantalla del centro se sustituye por una sola credencial que se renueva cada 30 días y se revoca por separado, tanto para leer el código como para enviar marcas. Cierra el riesgo que ya existía.
15. La identidad única se diseña y se construye primero, como proyecto propio. La app se construye después, sobre un prefijo propio y para toda empresa, tenga o no asistencia.

### Orden de entrega

| Fase | Contenido | Qué necesita antes de salir |
|---|---|---|
| 0 | Proyecto previo: identidad única, con su propio diseño formal | Los seis pasos de "Proyecto previo"; su propia revisión de seguridad sobre recuperación y duración de la sesión |
| 1 | Manifest por empresa en el prefijo de la app, guía de instalación en `enroll-done` y en Marcar, aviso "ábralo en Safari o Chrome" en navegadores internos, lector del código del centro | `camera=(self)` solo en Marcar con su prueba de encabezado; entrada en `RouteSecurityTest`; prueba de navegador con cámara simulada; revisión en iPhone y Android reales |
| 2 | Módulo `Notifications`, tabla de suscripciones con `tenant_id`, service worker solo de avisos, permiso pedido después de la primera marca exitosa | Llaves VAPID en `config` y `.env.example`; cola propia en Horizon; borrar suscripción en 404/410, al tocar "¿No es usted?" y al revocar el dispositivo; textos genéricos de pantalla bloqueada; límite de envíos (el contador de correo no cuenta push) |
| 3a | Constancia sin señal: cascarón estático en caché, estado "Pendiente, no registrada" en el teléfono y en Mi semana, envío al reabrir | Firma sin conexión probada en teléfonos reales; interruptor de apagado; prueba de dos versiones desplegadas; envío autenticado por la firma, sin sesión ni CSRF; regla de orden y duplicados; decisión sobre el tope de 10 pendientes |
| 3b | Marca por la pantalla del centro: seis dígitos en la pantalla, QR firmado en el teléfono, lectura con la cámara de la pantalla, recibo con nombre y hora del servidor | Fase 3a en producción; método `screen_relay` agregado a los criterios; verificación propia con registro único de cada firma; credencial de escritura de la pantalla, renovable cada 30 días; `camera=(self)` solo en la página de la pantalla; copia firmada en el teléfono para disputas; lectura del QR medida en tabletas reales |
### Avisos push propuestos para la fase 2

1. Falta marcar la salida (no existe hoy; comando diario nuevo).
2. Semana por confirmar.
3. Solicitud de corrección resuelta.
4. Solicitud de vacaciones decidida.
5. Documentos por confirmar.

Texto fijo en pantalla bloqueada, por ejemplo: "Tiene un aviso en Humetría". Nunca nombra un instrumento.

### Proyecto previo: una sola identidad para la persona trabajadora

> El diseño formal ya existe: `docs/superpowers/specs/2026-11-14-worker-access-design.md`. Donde difiera de esta sección, manda el diseño. Difiere en cuatro puntos que el autor decidió al reconciliar: los enlaces vencen en 48 horas (emitidos por Recursos Humanos) o 30 minutos (pedidos por la persona); el módulo `WorkerAccess` también es dueño del inicio, las secciones y la página "Accesos"; todo va en un solo PR; y el prefijo es `/me/{slug}`.

El dueño del producto avisó que aún no hay empresas clientes y que es el momento de los cambios bruscos. Eso quita la razón para posponer la identidad única: hoy no hay a quién migrar, y después habría que volver a dar de alta a cada persona de cada empresa. Dos revisores en Sonnet (seguridad y estructura, 2026-11-14) la evaluaron.

**Veredicto de ambos:** conviene hacerla antes de la app, pero **no cabe como fase 0 de este trabajo. Es un proyecto propio.**

*Tamaño (conteos por búsqueda, no un rastreo completo):* nueve módulos (uno nuevo, Attendance, Telework y los seis que cuelgan del portal), unas 29 rutas del portal con `{token}`, unas 5 rutas de alta de asistencia, 2 o 3 tablas nuevas, y entre 85 y 100 archivos antes de las pruebas.

*Por qué no se puede esconder detrás del contrato actual:* `PortalAccess` recibe el token de la dirección en sus tres métodos (`open`, `stopped`, `token`), y los correos de seis módulos llaman a `token()` para armar enlaces. Con una sesión no hay token que pasar. El contrato cambia una vez y después hay un barrido mecánico, un PR por módulo.

*Forma que resulta de juntar las dos revisiones:*

1. **Un modelo de identidad con dos entradas, no dos modelos.** La persona tiene una sesión. Entra con su llave de acceso o con un enlace de un solo uso que llega por correo y vence en 48 horas. El enlace estable del portal desaparece, así que ninguna credencial queda en la dirección ni en el historial.
2. **El enlace estable no puede servir de arranque.** Hoy cada correo lleva el mismo enlace hasta que Recursos Humanos lo revoca. Si ese enlace registrara una llave, quien tenga un correo viejo o reenviado se quedaría con la identidad y dejaría fuera a la persona real. Eso es peor que lo de hoy, donde quien tiene el enlace solo lee.
3. **Controles al registrar una llave:** aviso a la persona con un botón "No fui yo" que revoca la llave y las sesiones y avisa a Recursos Humanos; bitácora visible para Recursos Humanos; una llave por persona al inicio, y otra más solo desde una sesión ya abierta; Recursos Humanos puede revocar y reemitir, y eso anula todas las llaves anteriores.
4. **Sesión corta con firma nueva para lo sensible.** Una firma abre una sesión de 15 a 30 minutos sin uso y 8 horas como máximo. Piden firma nueva el mensaje de apoyo de la NOM-035, confirmar documentos y pedir vacaciones. Marcar y corregir siguen firmando cada acción. Hay botón "Salir" y la pantalla de entrada nunca muestra el nombre de la persona anterior.
5. **El acuerdo de asistencia (criterio 5.1) se separa de la llave.** Hoy se aceptan en la misma transacción. Una persona puede tener identidad sin haber aceptado el acuerdo, pero entonces no puede marcar: las rutas de Marcar revisan el acuerdo, no solo la firma.
6. **Un módulo nuevo y pequeño es el dueño** (nombre tentativo `WorkerAccess`): llave, sesión, enlace de arranque y revocación, detrás de contratos. Si se quedara en Attendance, una empresa sin asistencia no tendría credencial, que es justo el hueco a cerrar.
7. **La app abre en un prefijo propio** (por ejemplo `/m/{slug}`), no en `/a/{slug}`. Las rutas de asistencia y de la pantalla del centro se quedan donde están; Marcar y Mi semana pasan a ser secciones de la app.

*Orden que deja el sistema funcionando en cada paso:* (1) crear el módulo y mover la ceremonia de firma y la tabla de la llave, sin cambiar comportamiento; (2) separar la llave del acuerdo; (3) agregar la sesión, el enlace de un solo uso y el prefijo de la app; (4) pasar el portal a sesión, un módulo por PR; (5) borrar las rutas con `{token}`; (6) empezar las fases de la app.

*Lo que cambia en la app al adoptarla:* el manifest y el service worker viven en el prefijo de la app y no en `/a/{slug}`; las suscripciones de push se ligan a la llave y a la persona, porque el diseño de identidad no guarda un registro por sesión; las empresas sin asistencia entran en la primera entrega; "Mi portal" deja de ser una pieza aparte.

*Riesgos que siguen abiertos:* una cuenta de iCloud o Google compartida en familia comparte la llave (ya aceptado para asistencia; el portal tiene datos más delicados). El correo sigue siendo el eslabón débil de la recuperación.

### Decisiones pendientes

Ninguna del dueño del producto. El diseño formal de la identidad tiene que resolver, con su propia revisión: la recuperación con teléfono nuevo o perdido, la duración exacta de la sesión, el nombre del módulo y del prefijo, y qué hace quien no tiene un dispositivo con llaves de acceso cuando una vista pide firma nueva.

### Para el diseño formal (`/egm:egm-draft`)

- Escribir los textos que ve la persona, en "usted": cámara negada, ubicación negada, código vencido, sin señal, firma cancelada, "ábralo en Safari", constancia guardada ("Guardado sin señal. Se enviará a Recursos Humanos cuando tenga conexión. Aún no cuenta como marca.").
- Línea impresa bajo el QR de la pantalla del centro: "Abra la app y toque Escanear."
- Lógica de navegador detrás de adaptadores en `resources/js/lib` (lector de QR, capacidades de la plataforma, guía de instalación), para que los cambios de iOS y Android toquen un solo archivo y Vitest pueda cubrirlos.
- Ampliar los ayudantes de navegador de las pruebas con modo sin conexión y cámara simulada.
- Lista de lo que solo se prueba en teléfonos reales: instalación y push en iPhone, firma sin conexión, captura de enlaces en Android, navegador interno del correo.
- Eventos de log con nombre fijo (`push.sent`, `push.expired`, `offline_claim.received`, `offline_claim.rejected`) y bitácora para alta de suscripción y para cada constancia aceptada o rechazada.
