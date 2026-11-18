# WhatsApp como canal de notificaciones de salida

*   **Autor:** Victor Jesus Bravo de la Peña
*   **Estado:** Propuesto (las decisiones de Victor del 2026-11-18 están en "Decisiones tomadas")
*   **Fecha:** 2026-11-18

--------------------------------------------------------------------------------

## Resumen

Hoy Humetria manda invitaciones a encuestas y otros avisos solo por correo. Este diseño agrega WhatsApp (API de Meta, Cloud API) como segundo canal de salida. Un solo número de Humetria, con su nombre y logo, manda por todas las empresas. Solo se usan plantillas de categoría UTILITY. No hay marketing.

Cada empresa activa o desactiva canales en un ajuste "Canales de comunicación". Al activar WhatsApp se muestra un aviso de que tiene costo adicional. El costo se mide por empresa, se cobra por Billing y se registra como costo variable en la vista de rentabilidad de la plataforma. El número destino es el campo `Employee.mobile` que ya existe (formato E.164). La empresa da el opt-in cuando carga a su gente.

La versión 1 manda solo invitaciones a encuestas (NOM-035 primero, luego clima y pulso). Recordatorios, enlaces del portal de la persona trabajadora, avisos de asistencia y de vacaciones siguen en fases posteriores, un módulo a la vez.

Todo se valida primero en local (gateway falso y `Http::fake()`), después con el número de prueba de Meta y al final con el número real +52 81 8687 5767. Nada llega a producción antes de eso.

## Decisiones tomadas

Decisiones de Victor del 2026-11-18, ya vigentes en este diseño:

*   Módulo nuevo `Messaging`, solo de transporte.
*   Un número central, plantillas UTILITY, solo salida (sin conversaciones).
*   Ajuste por empresa en la tabla `tenant_channels`, con aviso de costo adicional.
*   Cobro: costo de Meta más margen, con tope mensual por empresa. El cobro se cuenta al aceptarse el envío (se reserva cupo antes, se liquida al aceptarse, se libera si falla y se concilia cada mes contra Meta). Esta regla se llama D4.
*   El canal se guarda solo en `invited_via` de la invitación, nunca en el enlace ni en tablas de respuestas.
*   Se guarda el flujo completo de cada mensaje con hora exacta; RH y la plataforma ven solo agregados por canal.
*   Respaldo solo de WhatsApp a correo, sin enviar la misma invitación por dos canales. El correo es el canal por omisión y no tiene respaldo; WhatsApp es un canal adicional con costo extra.
*   Versión 1: solo invitaciones a encuestas. El resto de los avisos va después, por fases.
*   Se guarda `+52`; el gateway se adapta al formato de Meta.
*   El webhook de Resend sale en el mismo PR que WhatsApp, construido a la par en una segunda sesión.

## El problema

Las personas trabajadoras y las empresas reciben los recordatorios y avisos proactivos de Humetria solo por correo. Quien no tiene correo de trabajo no recibe nada, y el correo no es el único lugar donde la gente lee sus mensajes. WhatsApp da a Humetria una segunda vía para llegar a esas personas, y la efectividad de cada vía se mide por canal (ver Metas).

Tres cosas hacen que esto no sea solo "llamar a la API de WhatsApp":

1.  **Anonimato.** El enlace de la encuesta llega a un teléfono conocido, y Humetria sabe qué enlace es de quién. Nadie del lado de la empresa debe poder saber qué respuesta anónima es de quién. El canal, los acuses de entrega y la hora pueden reducir un grupo chico a una sola persona si se guardan sin cuidado.
2.  **Costo.** Meta cobra por cada mensaje de plantilla. Humetria hoy no tiene el concepto de costo variable, y su medición de correo está hecha solo para correo.
3.  **Consentimiento y un número compartido.** Todas las empresas mandan por un mismo número. Las quejas de una empresa bajan la calidad del número para todas. Que la empresa marque una casilla de opt-in es una prueba más débil que el consentimiento de la propia persona.

La solución propuesta es un módulo nuevo `Messaging` que se encarga solo del transporte (gateway, webhook, plantillas, consentimiento). El módulo que manda un aviso sigue eligiendo sus canales en su propio `via()`. El canal usado se guarda solo en el registro de la invitación, nunca en las tablas de respuestas. Billing se generaliza para que un segundo medidor conviva con el de correo.

## Metas

*   Mandar mensajes de WhatsApp con plantillas UTILITY desde un número central a personas con opt-in y un `mobile` válido, detrás de un ajuste por empresa y de un interruptor de apagado de toda la plataforma.
*   Mantener intacto el anonimato de las respuestas, demostrado con pruebas: ninguna tabla de respuestas tiene una columna de canal, id de mensaje, token o teléfono; las props de los resultados no llevan datos de entrega por persona; el canal nunca es un filtro de resultados.
*   Medir y reservar el uso de WhatsApp por empresa como hoy se reserva el correo (todo o nada antes de un envío masivo), y guardar en cada línea el costo unitario de Meta con su fecha de vigencia para poder calcular el margen de la plataforma.
*   Guardar el flujo completo de cada mensaje (todos los estados de la regla 8 de "Reglas de comportamiento", con su hora y su causa). Es la fuente de las métricas de efectividad de cada canal.
*   Mostrar a RH y a la plataforma solo agregados por campaña y por canal: total de mensajes, cuántos se entregaron y cuántos no, en un indicador (medidor o barra de progreso) por canal activo (correo y WhatsApp). Nunca el detalle por persona. Se aplica la regla 10 (mínimo de 5). La lectura subestima, porque quien apaga los acuses de lectura nunca aparece como leído: sirve para comparar canales, no como prueba individual.
*   Llevar un rastro de consentimiento y de baja suficiente para la política de Meta y la ley mexicana de datos personales (LFPDPPP): quién declaró el opt-in, cuándo, con qué versión del texto, en qué activación (cada vez que el dueño acepta el texto en el ajuste de canales). Quien responda STOP (o BAJA, ALTO, CANCELAR) deja de recibir mensajes de la empresa que lo mandó.
*   Guardar el mismo recorrido completo para el correo (Resend avisa enviado, entregado, retrasado, rebotado, queja), de modo que cada comunicación con una persona tenga su historial por canal.
*   Usar el correo como respaldo cuando WhatsApp falla por una causa conocida (por ejemplo, el número no tiene cuenta de WhatsApp), siempre que la persona tenga correo y la empresa tenga el correo activo. Un rebote de correo no tiene respaldo: queda registrado y RH puede reenviar. Una persona nunca tiene dos enlaces de un solo uso vivos para la misma invitación.
*   Seguir el orden de validación acordado: pruebas locales exhaustivas, después el número de prueba de Meta, después el número real. `WHATSAPP_ENABLED=false` por omisión.

## Fuera de alcance

*   SMS, plantillas de marketing, conversaciones entrantes, respuestas automáticas, mensajes de texto libre.
*   Un número o cuenta de negocio de WhatsApp por empresa.
*   Una pantalla para administrar plantillas. Las plantillas viven en un registro en código y se aprueban en Meta.
*   Mostrar a RH o a la consola de la plataforma cualquier estado de entrega o de lectura por persona. Se guarda, pero solo se expone agregado.
*   Un sistema de pruebas A/B de canales o un tablero de efectividad más allá de los indicadores por canal de cada campaña.
*   Avisos de la versión 1 distintos de las invitaciones a encuestas (recordatorios, portal, asistencia, vacaciones).
*   Un flujo de consentimiento por persona (la empresa lo declara; el flujo por persona es una opción posterior).

## Glosario

*   **Victor:** Victor Jesus Bravo de la Peña, autor del diseño y dueño del producto.
*   **CUJ:** recorrido crítico de usuario (*critical user journey*).
*   **Módulo público:** de cada módulo del repositorio solo `Contracts`, `Events` y `Data` pueden usarse desde otro módulo.
*   **`BelongsToCurrentTenant`:** trait que limita un modelo a la empresa en curso.
*   **`#[UseFactory]`:** atributo que enlaza un modelo con su factory de pruebas.
*   **`visitPage()`:** prueba de humo de navegador que cada página debe tener.
*   **`RouteSecurityTest`:** prueba que lista las rutas que no exigen sesión.
*   **`WorkerInvitation`:** notificación que invita a una persona trabajadora al portal y no pone su token en la cola.
*   **Tronco común** (o **sesión base**): lo que las dos sesiones comparten y se construye antes de repartirlas.
*   **Línea de uso:** una fila de `usage_events` de Billing, es decir, un consumo de un medidor con su cantidad.
*   **`MailQuota`:** contrato que reserva el cupo de correo de campaña del mes, todo o nada.
*   **`CountSentMail`:** listener que cuenta cada correo cuando Laravel lo manda.
*   **`LedgerMailQuota`:** implementación de `MailQuota` contra el libro de uso.
*   **`ReleaseMailHolds`:** acción que libera cada noche las reservas que nadie usó.
*   **`UsageQuota`:** calcula lo que una empresa puede usar de un medidor en el mes.
*   **`PlatformOverview`:** pantalla de la consola de la plataforma con sus cifras.
*   **`BlockDemoMail`:** cancela el correo de la empresa demo antes de que salga.
*   **`RecordAudit`:** escribe en `audit_entries`, la bitácora que solo admite inserciones.
*   **`RedactSensitiveData`:** borra de los logs las llaves sensibles conocidas.
*   **`PhoneNumber`:** `App\Support\PhoneNumber`, normaliza teléfonos a E.164.
*   **Dokploy:** plataforma donde Humetria corre en producción.
*   **Ventana de 24 horas:** periodo en que, tras un mensaje de la persona, Meta permite mensajes libres. Humetria solo manda plantillas; "fuera de ventana" aparece como error cuando Meta lo exige para ese mensaje.
*   **Código 131026 de Meta:** el número no tiene cuenta de WhatsApp.
*   **Número de prueba de Meta:** número con hasta 5 destinatarios permitidos, para la Fase 2.
*   **Ping del webhook:** prueba que se dispara desde el panel de la app de Meta para el campo `messages`.
*   **+52 81 8687 5767:** número de Humetria que administra Victor.

## Preguntas abiertas

Cada pregunta tiene un responsable. Las reglas marcadas "por omisión" en "Reglas de comportamiento" son supuestos que Victor debe confirmar.

*   **Tarifa y categoría de Meta** (Victor, antes del commit 8): ¿cuánto cobra Meta por plantillas UTILITY en México, y clasificará la plantilla de invitación como UTILITY o la reclasificará como MARKETING? Hace falta para fijar el margen y el tope mensual. Plan alterno en "Riesgos y mitigaciones".
*   **Texto del opt-in** (Victor, antes de la Fase 2): qué texto acepta la empresa al activar WhatsApp y qué dice el aviso de privacidad sobre teléfonos, WhatsApp y la transferencia a Meta.
*   **Cerrada: abrir el enlace no lo consume.** `OpenSurvey` (Campaigns) y `OpenInvitation` (Engagement) solo leen (con `SESSION_DRIVER=database` se escribe la fila de `sessions`, nada más); el consumo ocurre en el POST (`submit`), así que una vista previa de WhatsApp no gasta el enlace. Se verificó leyendo `app/Modules/Campaigns/Actions/OpenSurvey.php`, `app/Modules/Engagement/Actions/OpenInvitation.php` y `SurveyController::show`. Falta solo la prueba que lo fije (sesión base, antes del commit 10).
*   **Webhook de Resend** (la sesión Resend lo verifica; el proveedor de correo de producción es Resend, confirmado por Victor el 2026-11-18): eventos disponibles y sus nombres, esquema de firma (se espera Svix: `svix-id`, `svix-timestamp`, `svix-signature`) y cómo correlacionar un evento con nuestra notificación (id de Resend en el transporte de Laravel, o un encabezado o etiqueta con nuestro id).
*   **Cobro al entregar** (Victor): con el historial completo, D4 podría contar al entregarse, como cobra Meta. Queda al aceptarse hasta que él lo reabra.
*   **Webhook, STOP y comparación de canales en la v1** (Victor, al llegar al commit 11): se mantienen. Si las pruebas de anonimato (regla 10 y "Riesgos y mitigaciones") no se pueden escribir antes del commit 11, la comparación de canales sale de la v1.

Supuestos pendientes de confirmar por Victor (marcados "por omisión" en las reglas): el canal que gana y el orden de preferencia (regla 1); la baja amplia y la baja global (regla 6); el crédito o la absorción en la conciliación del cobro (regla 11); la retención de 12 meses y 7 días (regla 14); la pausa automática y su umbral, el tope diario y el tope global (reglas 11 y 15), cuyas cifras por omisión están en el plan («Valores por omisión (supuestos por confirmar)») y se verifican antes del commit 4 con los límites vigentes de Meta.

## Recorridos críticos (CUJ)

### CUJ 1: El dueño activa WhatsApp

*   **Dado que**: una empresa con plan, con correo activo y sin WhatsApp.
*   **Cuando**: el dueño abre "Canales de comunicación", activa WhatsApp, lee el aviso de costo adicional y acepta el texto del opt-in.
*   **Entonces**: se crea la fila `tenant_channels` (canal `whatsapp`, activado, quién, cuándo, versión del texto) y una entrada de bitácora. Un rol sin permiso recibe 403 y una empresa ajena recibe 404.

### CUJ 2: Invitación a encuesta por WhatsApp

*   **Dado que**: una campaña con personas que tienen `mobile` válido, WhatsApp activo en la empresa y cupo suficiente.
*   **Cuando**: RH lanza la campaña.
*   **Entonces**: se reservan todas las invitaciones o ninguna. Cada persona recibe el enlace por un solo canal. La invitación guarda `invited_via` y nada más; ninguna tabla de respuestas lleva canal, teléfono ni id de mensaje.

### CUJ 3: Un número sin WhatsApp

*   **Dado que**: Meta responde que el número no tiene cuenta de WhatsApp (131026).
*   **Cuando**: el job de envío recibe ese error.
*   **Entonces**: no se reintenta, se libera la reserva, y si la persona tiene correo se rota el token y se manda por correo. `invited_via` pasa a `mail` y la invitación sigue su curso; ni el teléfono ni el mensaje se escriben en logs.

### CUJ 4: Baja con STOP

*   **Dado que**: una persona recibió mensajes de la empresa A (y, quizá, de otras) dentro del periodo de retención de la regla 14.
*   **Cuando**: responde STOP, BAJA, ALTO o CANCELAR al número central.
*   **Entonces**: el webhook (firma válida) calcula el `recipient_hash` del teléfono (HMAC con la clave de la app, la misma que usa `canSend` para comparar), marca la baja en cada empresa que le escribió en ese periodo (aquí la A) o, si no hay filas, de forma global, no responde nada y los envíos posteriores de esas empresas a ese teléfono se omiten. Una firma ausente o alterada se rechaza.

### CUJ 5: Un correo que rebota

*   **Dado que**: una invitación enviada por correo a una persona, con Resend como proveedor.
*   **Cuando**: Resend avisa un rebote definitivo (firma válida).
*   **Entonces**: el evento queda en el historial con estado `Bounced`, el indicador de correo cuenta un no entregado, y no se manda nada por WhatsApp (el correo no tiene respaldo). RH puede reenviar con `ResendInvitations`.

### CUJ 6: RH revisa el alcance de una campaña

*   **Dado que**: una campaña con correo y WhatsApp activos.
*   **Cuando**: RH abre el avance de la campaña.
*   **Entonces**: ve un indicador por canal con el total de mensajes, cuántos se entregaron y cuántos no. No ve ningún nombre ni estado por persona, y un canal con menos de 5 mensajes no muestra su conteo.

## Plan técnico (arquitectura)

### Piezas nuevas

*   **Módulo `Messaging`** (`app/Modules/Messaging`): solo transporte. Públicos: `Contracts/WhatsAppGateway` y `Contracts/ChannelConsent`. Internos: gateway HTTP (Cloud API) y `FakeWhatsAppGateway`, registro de plantillas, normalizador de teléfono hacia el formato de Meta, job de envío, controlador del webhook, manejador de bajas.
*   **Tabla `tenant_channels`** (Tenancy): una fila por empresa y canal (`tenant_id`, `channel`, `enabled`, `enabled_by`, `enabled_at`, `consent_wording_version`). Único por (`tenant_id`, `channel`). Un contrato de Tenancy (`CompanyChannels`) la lee. Una fila más: `preference` (1 = canal que se usa primero). Sin fila rige el valor por omisión de cada canal: correo activo con preferencia 1, WhatsApp apagado con preferencia 2 (ver "Reglas de comportamiento").
*   **Mensajes** (`messaging_deliveries`, con `tenant_id`): una fila por mensaje con su `id` propio (UUID v4, es la llave de idempotencia), `provider_message_id` (nulo hasta que el proveedor acepta el mensaje; único cuando no es nulo), `recipient_hash` (HMAC del teléfono con la clave de la app; nunca el teléfono), `invitation_type` e `invitation_id` (referencia a la invitación de Campaigns o Engagement que originó el mensaje; la invitación ya identifica a la persona y no tiene llave a ninguna respuesta, así que este vínculo no agrega riesgo de anonimato), plantilla, canal y estado actual. No guarda cuerpo, teléfono ni parámetros. Es la llave para encontrar la empresa desde el webhook: por `provider_message_id` en los estados y por `recipient_hash` en las bajas.
*   **Historial de estados** (`messaging_delivery_events`, con `tenant_id`, solo inserción): una fila por cada cambio de estado (cualquiera de los estados de la regla 8 de "Reglas de comportamiento", incluido `unknown`) con su hora exacta y la causa del fallo. Nada se sobrescribe ni se descarta. Solo una Action de agregados lo lee; ninguna pantalla ni prop de Inertia entrega filas por persona.
*   **Bajas** (`messaging_opt_outs`, con `tenant_id`): `recipient_hash` (HMAC del teléfono con la clave de la app, nunca el teléfono ni un hash simple) por empresa.
*   **Webhook de Resend** (en `Messaging`): recibe los eventos del correo (enviado, entregado, retrasado, rebotado, queja) y los guarda en el mismo `messaging_deliveries` y `messaging_delivery_events` con `channel = mail`. Misma regla de seguridad que el de Meta: firma verificada sobre el cuerpo crudo antes de procesar nada.
*   **Deduplicación de webhooks** (`messaging_webhook_events`, sin tenant, para Meta y Resend): excepción documentada en `TenantScopedModelsTest`.

### Cambios en módulos existentes

*   **Campaigns y Engagement:** columna `invited_via` en `campaign_participants` y `engagement_invitations`; `routes()` agrega la ruta `whatsapp`; el estado `NoEmail` se renombra `Unreachable` (ninguna vía de contacto activa); indicadores por canal (enviados, entregados, no entregados) con el mínimo de 5, y tabla de contestadas por canal que se oculta entera si cualquier celda tiene menos de 5 (regla 10).
*   **Billing:** el medidor se vuelve genérico (las reservas de un medidor que no pasa por la liberación nocturna nunca vencen y el cupo se pierde). `LedgerMailQuota` y `ReleaseMailHolds` dejan de usar `CountSentMail::METER` y el texto "correos"; `UsageQuota` conserva su rama del correo por asiento y agrega la cuota del medidor `whatsapp`. Se agrega el medidor `whatsapp` con reservar, liquidar y liberar. Se guarda por línea el costo unitario de Meta con su fecha de vigencia y se agrega un lado de costo junto al contrato `PlatformRevenue` que ya existe (`app/Modules/Billing/Contracts/PlatformRevenue.php`, con `monthlyContractedCents()`), visible en `PlatformOverview` y en el estado de cuenta.
*   **Tenancy:** ajuste "Canales de comunicación" (FormRequest con `authorize()`, aviso de costo adicional, entrada de bitácora).
*   **Observabilidad y privacidad:** eventos de log fijos `whatsapp.*`; `RedactSensitiveData` agrega `phone`, `mobile`, `wa_id`, `msisdn`, `recipient_hash`, `app_secret`, `verify_token`, `hub_verify_token` y los `components` de la plantilla (no `to` ni `from`, que usan logs existentes); bloqueo de WhatsApp en la empresa demo; el aviso de privacidad nombra a Meta.

### Flujo de datos

```text
RH lanza campaña
   │  (Campaigns) reserva cupo del canal elegido para cada persona (todo o nada)
   ▼
SurveyInvitation::via()  ── ruta mail ────────────► correo (como hoy)
   │                    └─ ruta whatsapp (si el canal está activo,
   │                       hay opt-in, mobile válido y no hay baja)
   ▼
Messaging: job de envío (tries=3, backoff 60/300)
   │  crea fila messaging_deliveries ANTES de llamar a Meta (idempotencia)
   ▼
WhatsAppGateway ──► Cloud API de Meta (plantilla UTILITY, botón con URL dinámica = token)
   │
   │  aceptado ► Billing cuenta 1 (fromHold)   falla ► libera la reserva
   │  sin cuenta de WhatsApp ► rota token ► correo
   ▼
Webhook de Meta ─► verifica firma (cuerpo crudo, hash_equals) ─► deduplica
   ├─ estado de entrega o lectura ► fila nueva en messaging_delivery_events y estado actual en messaging_deliveries
   ├─ STOP/BAJA/ALTO/CANCELAR ► messaging_opt_outs de ESA empresa, sin respuesta
   └─ campo desconocido / wamid desconocido ► 200 e ignorar
```

### Reglas de comportamiento

Estas reglas fijan lo que el resto del documento daba por sabido. Las marcadas **por omisión** son supuestos pendientes de confirmación de Victor; las demás salen de decisiones ya tomadas o de hechos del código.

1.  **Qué canal gana.** Cada persona recibe una invitación por un solo canal: el de menor `preference` entre los activos de su empresa para los que tiene dato de contacto. Por omisión el correo es el primero y WhatsApp el segundo (WhatsApp es un canal adicional con costo extra), y el dueño puede invertirlo en el ajuste. Si el canal de la persona es WhatsApp y falla, el correo es su respaldo (regla 9); el correo no tiene respaldo.
2.  **Valores por omisión de los canales.** Las filas de `tenant_channels` son independientes: sin fila, un canal tiene su valor predeterminado (correo activo con preferencia 1, WhatsApp apagado con preferencia 2). Una fila solo cambia ese canal; `preference` se compara únicamente entre canales activos. Apagar el correo crea su fila. Una empresa existente no pierde su correo.
3.  **Cupo.** El lanzamiento reserva todo o nada solo los envíos del canal elegido para cada persona. Los respaldos y los reenvíos reservan 1 en ese momento; si no hay cupo, no se manda y la persona cuenta como no entregada. Por eso agotar el cupo a media campaña solo afecta a respaldos y reenvíos, nunca a la campaña ya lanzada.
4.  **Persona sin `mobile` con WhatsApp como único canal activo** (por ejemplo, el correo apagado). Queda `Unreachable` y la pantalla de la campaña la cuenta como inalcanzable, igual que hoy con quien no tiene correo. Una prueba lo cubre.
5.  **Consentimiento.** La empresa da el opt-in en cada activación: el dueño acepta el texto en "Canales de comunicación" y queda guardado quién, cuándo y con qué versión. No se guarda un opt-in por persona. `ChannelConsent.canSend` responde que sí cuando el canal está activo para la empresa con una versión del texto aceptada y el teléfono no tiene una baja para esa empresa. Un flujo de consentimiento por persona es una opción posterior.
6.  **Cómo se resuelve STOP.** El mensaje entrante trae solo el teléfono. Se calcula su `recipient_hash` y se registra la baja en cada empresa que aparezca en `messaging_deliveries` con ese hash, por todo el tiempo que esa tabla conserve filas (regla 14), sin responder nada (criterio amplio, por omisión). El hash es un HMAC con una clave secreta, y cada fila guarda su `key_version`. Como solo se guarda el hash y nunca el teléfono, una clave no se puede rotar recalculando: al rotar se agrega la clave nueva y se conservan las anteriores en la lista de claves vigentes. Para buscar una baja se calcula el hash con cada clave de la lista, y las filas nuevas usan la más reciente. Una clave anterior no se retira mientras exista una baja con esa versión. Si un STOP llega de un teléfono sin filas en `messaging_deliveries` (por ejemplo, porque ya pasó la retención), la baja se registra como global: una fila de `messaging_opt_outs` con `tenant_id` nulo, que aplica a todas las empresas y es una excepción documentada en `TenantScopedModelsTest`. Como los teléfonos mexicanos son pocos, mientras la clave no se filtre el hash no permite recuperar el teléfono; si se filtran la base y la clave juntas, sí. Ese caso queda fuera del modelo de amenaza del proyecto (RH y la aplicación, no quien tiene acceso a la base), según la sección Privacidad de `CLAUDE.md`. Como el mismo teléfono tiene el mismo hash en todas las empresas, una consulta de plataforma podría ligar empresas; se acepta porque solo la Action de bajas usa el hash y no hay pantalla que lo exponga.
7.  **Idempotencia.** El job crea primero la fila de `messaging_deliveries` con su `id` propio y `provider_message_id` nulo; si el job se reintenta, reutiliza esa fila. Cuando Meta acepta, se guarda `provider_message_id`.
8.  **Estados y orden.** Orden: `created` < `sent` < `delayed` < `delivered` < `read`. Los terminales de fallo son `failed`, `bounced` y `complained`; una vez en uno de ellos el estado actual ya no cambia, aunque los eventos posteriores se sigan guardando en el historial. Un `delayed` que llega después de `delivered` se guarda en el historial y no cambia el estado actual. `unknown` y `paused` son transitorios y se guardan en el historial: `unknown` lo deja un tiempo agotado después de enviar, y `paused` lo deja la parada de emergencia. Meta no permite consultar el estado de un mensaje; cada mensaje se asocia con sus avisos de estado por `biz_opaque_callback_data`. Mientras un `unknown` no se resuelve se conserva la reserva de cupo; si a las 24 horas sigue sin resolverse, pasa a `failed` con causa `unresolved`, se liquida como usado (el costo se asume) y no dispara respaldo. Los eventos de webhook cuyo `provider_message_id` todavía no existe (porque el job aún no lo guardó) se guardan en `messaging_webhook_events` y se reprocesan cuando el id se escribe, durante 1 hora; pasado ese tiempo se descartan (la regla 14 da el otro plazo, el de deduplicación). Los eventos de Resend se mapean a esta lista: retrasado es `delayed`, rebotado es `bounced`, queja es `complained`.
9.  **Respaldo (solo de WhatsApp a correo).** Lo dispara un fallo definitivo de WhatsApp: sin cuenta (131026), plantilla o token que no se pueden reintentar, una baja del destinatario (`opted_out`) o tres intentos agotados (estado final `failed` con causa `retries_exhausted`). El correo no tiene respaldo: un rebote queda registrado como `bounced` y RH puede reenviar con `ResendInvitations`, que ya existe en Campaigns. El respaldo rota el token y manda por correo si la empresa tiene el correo activo, la persona tiene correo y hay cupo de correo; `invited_via` pasa a `mail`. Una persona nunca tiene dos enlaces vivos. Si el respaldo no es posible, el último estado queda como no entregado. Al quitar `WHATSAPP_PAUSED` no se reenvía nada solo. El respaldo corre como una sola secuencia: en una transacción se rota el token, se reserva 1 de cupo de correo y se crea la fila del mensaje de correo, y solo después se encola el envío; si el envío falla, se libera la reserva. El token anterior queda inválido desde la rotación. Si el mensaje de WhatsApp quedó en `unknown`, no se dispara el respaldo hasta que la conciliación lo resuelva.
10. **Mínimo de 5 (definición normativa).** Un indicador de canal solo aparece si el canal tiene al menos 5 mensajes. Muestra el total y el número de no entregados; si los no entregados son menos de 5 muestra "menos de 5" en lugar del número, y no muestra los entregados por separado. Así el indicador no permite señalar a una persona concreta. Los indicadores se calculan por campaña y nunca como diferencia entre campañas. El cruce de canal con "contestó" exige al menos 5 en cada celda (canal y contestó o no). Si cualquier celda de la tabla, de cualquier canal, tiene menos de 5, se oculta la tabla entera: con las celdas restantes y el total de contestadas que la página muestra en otro lugar se podría obtener por resta la celda chica y aislar a una persona (decisión de Victor, 2026-11-18). Una prueba cubre los conteos 4, 5 y 6.
11. **Presupuesto y precio.** Lo que se cobra a la empresa por mensaje es el costo unitario de Meta de ese momento por (1 + margen), con el margen en `config/billing.php`. El tope mensual lo fija el dueño por empresa en el ajuste y corta los envíos adicionales del mes. Hay además un tope global de la plataforma (`WHATSAPP_PLATFORM_MONTHLY_CAP`, por omisión). Cada línea de uso guarda el costo unitario y la moneda de ese momento; el margen de la plataforma es lo cobrado menos ese costo. Meta cobra por mensaje entregado y Humetria cuenta al aceptarse el envío (D4). La conciliación mensual compara lo liquidado con lo que Meta facturó: si a una empresa se le cobró un mensaje que nunca se entregó, se le acredita con un ajuste del estado de cuenta (`AdjustStatement`); si Meta cobró más de lo liquidado, Humetria lo absorbe. Es un supuesto pendiente de Victor.
12. **Proveedor de correo.** Producción manda por Resend, confirmado por Victor el 2026-11-18. Si la firma o la correlación de Resend no son las esperadas, se usa lo que su documentación ofrezca (por ejemplo, una etiqueta propia en el correo); el historial y el indicador de correo dependen de esa correlación.
13. **Formato del número.** Se guarda `+52…` y `PhoneForMeta` (commit 3) lo convierte al formato que la documentación vigente de Meta exija para México. Quien construye el commit 3 lo verifica antes de escribirlo; el gateway del commit 4 solo lo consume y el resto de las fases no depende de ese dato.
14. **Retención (por omisión).** `messaging_deliveries` y `messaging_delivery_events` se conservan 12 meses (`MESSAGING_RETENTION_MONTHS`) y un comando programado borra lo anterior. `messaging_webhook_events` se conserva 7 días, que es la ventana contra eventos repetidos; el plazo de 1 hora de la regla 8 es solo para reprocesar eventos huérfanos. A petición de una persona (derechos ARCO) se borran sus filas por `recipient_hash`. Las bajas (`messaging_opt_outs`) no se borran con la retención, porque son lo que impide volver a escribirle.
15. **Volumen y número compartido.** Los envíos de WhatsApp usan una cola propia con limitador de velocidad (`WHATSAPP_MAX_PER_SECOND`, valor a verificar contra los límites vigentes de Meta) y lotes de tamaño fijo; un 429 de Meta reintenta con el backoff del job y no pierde la reserva. Hay un tope diario de mensajes por empresa. Se vigila el porcentaje de fallos por bloqueo o spam por hora y, si pasa de un umbral (por omisión), se activa una pausa automática de toda la función y se avisa a Victor; solo la quita un operador. Si Meta avisa por webhook cambios de calidad del número (a verificar el campo), también se registra y alerta.
16. **Token y webhooks.** El enlace de la encuesta es de un solo uso y vence al cerrar la campaña, así que un número reciclado no puede usarlo después de ese cierre. La firma de Meta no lleva marca de tiempo; la protección contra repetición es la deduplicación por id de evento durante 7 días (regla 14). Al rotar el secreto de la app se aceptan los dos secretos durante la rotación.

### Ajustes de la revisión de código (2026-11-18)

La revisión del código implementado cambió estos comportamientos respecto a las reglas de arriba. Donde haya diferencia, manda esta lista.

*   **Regla 8, en tránsito.** Antes de llamar a Meta, la fila de WhatsApp pasa a `unknown` (sin evento en el historial) y solo se envía desde `created`. Si Meta acepta y falla el guardado, la fila queda `unknown` y el webhook (por `biz_opaque_callback_data`) o `messaging:reconcile-unknown` la resuelven. Si la causa es reintentable, vuelve a `created`.
*   **Regla 8, correo.** Cuando el correo sale, nuestro lado lo pasa a `sent`. Una fila de correo que sigue en `created` es un intento fallido, y el reintento la reutiliza para la misma invitación.
*   **Regla 8, topes.** Un mensaje que se liquida como `unresolved` recibe `accepted_at`, así que cuenta para los topes diario y de plataforma.
*   **Webhooks.** El id del evento y su efecto se guardan en una sola transacción, para que un reintento tras un fallo no se tome por repetido (vale para los estados de Meta, el STOP y los eventos de Resend). El secreto de Resend se acepta con o sin el prefijo `whsec_`; uno inválido deja un log fijo, `mail.webhook_secret_invalid`.
*   **Regla 9, respaldo.** Es idempotente por reintento: si la invitación ya tiene `invited_via = mail` y `token_version > 0`, el reintento reutiliza el enlace y la reserva y solo vuelve a mandar el correo. Si se agotan los tres intentos, la persona queda en estado fallido, con una señal fija (`campaign.fallback_failed` o `engagement.fallback_failed`), para que RH reenvíe.
*   **Regla 10, «sin entregar».** Cuenta solo lo que falló (`failed`, `bounced`, `complained` y `paused`). Lo que sigue en camino (`created`, `sent`, `delayed`, `unknown`) no es ni entregado ni sin entregar y no se muestra; aplica igual al correo. El mínimo de 5 y «menos de 5» no cambian. La conciliación mensual con Meta sigue contando como no entregado todo lo que no llegó a `delivered` o `read`.
*   **Ajuste de canales.** Las validaciones de función y de plan se aplican solo cuando WhatsApp se activa de nuevo o cuando cambia la versión del texto; guardar el tope o el orden con WhatsApp ya activo no las dispara.
*   **Correo por la API de Resend.** El From conserva su nombre (se toma del encabezado From, no del return path).

### Reglas de seguridad y privacidad

*   **Canal solo en la invitación.** Una prueba de esquema falla si cualquier tabla de respuestas tiene una columna parecida a canal, wamid, token o teléfono. Las props de resultados no llevan estado de entrega por persona.
*   **Se guarda todo el flujo, se muestra solo el agregado.** El webhook persiste cada estado, incluida la lectura. Una prueba falla si alguna página de RH o de la consola recibe filas por persona (props de Inertia) o si una tabla de respuestas referencia estas tablas.
*   **Token solo en el botón de URL.** El token no viaja en los parámetros serializados del job; el envío conserva el criterio de `WorkerInvitation` (sin token en la cola).
*   **Mínimo de 5.** Se aplica la regla 10 de "Reglas de comportamiento" a todo indicador y a todo cruce con el canal.
*   **Webhook protegido.** Aunque la ruta no pide sesión (Meta no tiene cuenta en Humetria), el POST exige la firma `X-Hub-Signature-256` (HMAC con el secreto de la app, sobre el cuerpo crudo, con `hash_equals`) y el GET compara el token de verificación. Sin firma válida no se procesa nada.
*   **Interruptor de función:** `WHATSAPP_ENABLED=false` por omisión usa un gateway nulo. Significa que la función no existe en ese despliegue. Lo opera Victor en Dokploy.
*   **Parada de emergencia:** `WHATSAPP_PAUSED` detiene todos los envíos de WhatsApp de todas las empresas sin quitar la función. Los mensajes quedan sin mandar en estado `paused` y el indicador los cuenta como no entregados mientras sigan así; su reserva de cupo se libera y no se activa el respaldo por correo, porque la pausa es operativa y no un fallo del mensaje. Hay también una pausa automática (regla 15).
*   **Ruta pública del webhook:** se agrega a `RouteSecurityTest` con su razón, exenta de CSRF, con throttle y límite de tamaño del cuerpo.

## Riesgos y mitigaciones

| Riesgo | Mitigación | Responsable |
| --- | --- | --- |
| **El número compartido es un punto único de falla y de reputación.** Las quejas o bloqueos de una empresa bajan la calidad del número para todas. | Consentimiento declarado con rastro (regla 5), STOP (regla 6), tope diario por empresa y pausa automática ante fallos (regla 15), parada de emergencia `WHATSAPP_PAUSED`. Un número por empresa queda fuera de alcance. | Victor |
| **Consentimiento débil ante Meta y la LFPDPPP.** La casilla de la empresa no es el consentimiento de la persona. | Texto de opt-in revisado por Victor, aviso de privacidad que nombra a Meta, STOP que se respeta, y flujo por persona como opción posterior. Aceptar este riesgo y la posible suspensión del número es decisión de Victor. | Victor (por confirmar) |
| **Meta reclasifica la plantilla como MARKETING.** Cambia el costo y exige otro opt-in; rompe "solo UTILITY". | Enviar las plantillas a Meta antes de construir el cobro; si se reclasifica, WhatsApp queda limitado a las invitaciones NOM-035 (ligadas a una obligación legal) y se recalcula el presupuesto antes de activarlo. | Victor |
| **Anonimato: un no entregado entre 5 o más se identifica por exclusión; la hora exacta por persona es un canal lateral para quien lea la base.** | Solo agregados por campaña (regla 10), nunca diferencia entre campañas, canal solo en la invitación, pruebas de esquema y de props, retención de 12 meses (regla 14). Quien lee las tablas es solo la Action de agregados; el acceso directo a la base queda fuera del modelo de amenaza del proyecto. | Sesión WhatsApp (pruebas) |
| **Costo y volumen sin estimar.** Meta cobra por mensaje y cambia sus precios. | Tarifa en config con fecha de vigencia y costo guardado por línea; tope por empresa y tope global de plataforma (regla 11); conciliación mensual contra Meta. El volumen esperado de mensajes se estima como personas con `mobile` por campaña; el almacenamiento, como un evento por estado por mensaje (hasta unos seis). Se verifica con el primer volumen real. | Victor |
| **Límites de la Cloud API (velocidad, calidad, 429, límite diario del número).** | Cola propia con limitador, lotes, backoff y reserva conservada (regla 15). Valores concretos por verificar contra la documentación vigente de Meta antes del commit 4. | Sesión WhatsApp |
| **Supuestos sin verificar** (`+52`/`+521`, tarifa y categoría de Meta, y la firma y la correlación de Resend). | Cada uno está en "Preguntas abiertas" con responsable y momento. | Ver Preguntas abiertas |
| **Carreras y estados dudosos** (`unknown`, webhook antes del id, eventos fuera de orden). | Regla 7 y regla 8: fila previa idempotente, conciliación en 24 horas, reproceso 1 hora, estado terminal. | Sesión base |
| **Retención y derechos ARCO.** | Regla 14: 12 meses por omisión, borrado por `recipient_hash` a petición, bajas que no se borran. | Victor (confirma el plazo) |
| **Diferencia entre lo que se cobra al aceptar y lo que Meta factura al entregar.** | Regla 11: conciliación mensual con crédito a la empresa o absorción por Humetria. | Victor |
| **El mismo teléfono tiene el mismo hash en todas las empresas.** | Regla 6: solo la Action de bajas lo usa, no se expone en pantallas, y quien lea la base queda fuera del modelo de amenaza. Hash por empresa o teléfono cifrado se evaluaron como alternativas. | Sesión base |

## Alternativas consideradas

### Alternativa A: Sin módulo nuevo (código en cada módulo, o dentro de Tenancy o Billing)

*   **Ventajas**: no cambia la lista de módulos; cada módulo controla su envío.
*   **Desventajas**: el gateway, los dos webhooks, las plantillas y el consentimiento se repetirían en 5 o más módulos. Billing no puede importar Organization ni Campaigns, y mandar mensajes no es cobrar.
*   **Razón**: Un módulo `Messaging` dueño solo del transporte evita la duplicación y deja la elección del canal en el `via()` de cada módulo.

### Alternativa B: Marcar el canal en el enlace (parámetro de URL o sufijo del token)

*   **Ventajas**: es la idea inicial de un UTM interno y permite medir clics por canal.
*   **Desventajas**: el parámetro llega a los logs de acceso y a PostHog; un sufijo rompe el token determinista (`hash_hmac`) que hoy es el mismo para todos los canales; además es redundante, porque el servidor ya sabe el canal por la invitación.
*   **Razón**: El canal se guarda en `invited_via` de la invitación y nunca en tablas de respuestas. Se pierde detectar el canal por clic; se compensa con el historial de entrega por canal.

### Alternativa C: Guardar solo un estado grueso o ningún estado de entrega por persona

*   **Ventajas**: menos datos sensibles y menos riesgo de canal lateral por la hora.
*   **Desventajas**: no permite medir qué se entregó, se leyó o falló, que es la métrica con la que Victor quiere comparar canales; el cobro quedaría ciego a lo que Meta realmente cobró.
*   **Razón**: Se guarda el flujo completo con hora exacta en un historial de solo inserción, y la privacidad se protege exponiendo solo agregados (ver Alternativa D).

### Alternativa D: Mostrar a RH el estado de entrega o lectura por persona

*   **Ventajas**: RH sabría exactamente a quién no le llegó y podría reenviar.
*   **Desventajas**: con la hora de lectura, en un grupo pequeño podría deducirse qué respuesta es de quién, y expone el estado de una persona concreta.
*   **Razón**: RH y la plataforma ven solo indicadores agregados por canal, con mínimo de 5, y las páginas no reciben filas por persona (prueba de props).

### Alternativa E: Dejar el webhook y STOP para después y mandar correo en paralelo

*   **Ventajas**: v1 más chica, sin ruta pública nueva.
*   **Desventajas**: sin webhook no hay baja ni señal de fallo; los bloqueos y reportes bajan la calidad del número compartido para todas las empresas.
*   **Razón**: El webhook (con firma verificada) y STOP salen juntos en una rebanada temprana. El de Resend sigue la misma regla.

### Alternativa F: Sin respaldo entre canales, o mandar siempre por los dos

*   **Ventajas**: sin respaldo es lo más simple; por los dos canales maximiza el alcance.
*   **Desventajas**: sin respaldo, un número sin WhatsApp o un correo rebotado deja a la persona sin invitación; por los dos canales hay dos enlaces vivos por persona y se paga dos veces.
*   **Razón**: Respaldo solo de WhatsApp a correo tras un fallo confirmado, con el token rotado, y nunca doble envío; el correo es el canal por omisión y no necesita un respaldo que cueste dinero.

### Alternativa G: Todos los avisos en la versión 1

*   **Ventajas**: valor completo desde el inicio.
*   **Desventajas**: los enlaces del portal necesitan su propia revisión de seguridad (credencial al portador en un canal de terceros, números reciclados) y los avisos de vacaciones y asistencia pueden filtrar información en la pantalla bloqueada.
*   **Razón**: La v1 manda solo invitaciones a encuestas; el resto sigue por fases, un módulo por commit.

### Alternativa H: Consentimiento por persona en lugar de la declaración de la empresa

*   **Ventajas**: la evidencia de consentimiento es más fuerte ante Meta y la LFPDPPP.
*   **Desventajas**: exige un flujo nuevo para cada persona antes del primer mensaje, que retrasa la v1.
*   **Razón**: La empresa declara el opt-in al cargar a su gente, se guarda el rastro (quién, cuándo, versión del texto, activación) y STOP corta los envíos. El flujo por persona queda como opción posterior.

### Alternativa I: Guardar el ajuste de canales en columnas de `tenants` o reusando `TenantModule`

*   **Ventajas**: columnas es lo más chico; `TenantModule` no agrega tabla.
*   **Desventajas**: las columnas crecen con cada canal y no guardan el rastro del opt-in; `TenantModule` mezcla un canal de salida con las funciones del plan.
*   **Razón**: Se eligió la tabla `tenant_channels`, una fila por empresa y canal, con quién, cuándo y versión del texto.

### Alternativa J: Pantalla para administrar plantillas

*   **Ventajas**: cambiar plantillas sin desplegar.
*   **Desventajas**: Meta aprueba las plantillas, no la app; una pantalla agrega superficie y permisos sin que un cambio pueda saltarse esa aprobación.
*   **Razón**: Un registro en código (nombre, `es_MX`, número de parámetros, nombres versionados) con una prueba que falla si el registro y el código que llena los parámetros no coinciden.

### Alternativa K: Bolsa prepagada o cupo incluido por asiento

*   **Ventajas**: la bolsa da un gasto acotado; el cupo incluido es fácil de explicar.
*   **Desventajas**: Meta cobra por mensaje y cambia sus precios; un cupo fijo puede dejar a Humetria perdiendo, y la bolsa pide un flujo de recarga sin clientes todavía.
*   **Razón**: Costo de Meta más margen con tope mensual por empresa. El cupo incluido se puede agregar después.

### Alternativa L: Proveedor intermediario de WhatsApp (Twilio, 360dialog u otro) en lugar de la Cloud API directa

*   **Ventajas**: el proveedor absorbe parte de los límites y del alta del número, y ofrece panel de reportes.
*   **Desventajas**: agrega un intermediario con su propio cobro por mensaje además del de Meta, otro proveedor que recibe teléfonos de personas trabajadoras y otra firma de webhook. No cambia las reglas de consentimiento ni de calidad del número.
*   **Razón**: Rechazada para la v1: el cobro de Meta ya viene por mensaje y no hay clientes aún; se reevalúa si los límites de la Cloud API no alcanzan.

### Alternativa M: Cobrar al entregarse en lugar de al aceptarse el envío

*   **Ventajas**: coincide con lo que factura Meta y evita créditos en la conciliación.
*   **Desventajas**: obliga a liquidar sobre eventos de webhook que llegan tarde o en desorden, y deja la reserva abierta hasta que llegan.
*   **Razón**: Rechazada por ahora: la liquidación al aceptarse es más simple y la diferencia se concilia cada mes (regla 11). Victor puede reabrirla.

### Alternativa N: Teléfono cifrado o hash por empresa en lugar de un hash global

*   **Ventajas**: el teléfono cifrado permitiría rotar la clave y listar las bajas; el hash por empresa evita ligar empresas.
*   **Desventajas**: el cifrado deja el teléfono recuperable con una sola clave; el hash por empresa obliga a calcular un hash por cada empresa en cada STOP.
*   **Razón**: Rechazada: el hash global con claves versionadas (regla 6) no guarda el teléfono y resuelve STOP con una sola consulta.

--------------------------------------------------------------------------------

## Plan de implementación detallado

Un solo PR con commits atómicos, cada uno en verde. El PR se abre solo cuando Victor diga "crea el PR". Las rutas son relativas a la raíz del repositorio. Este documento no trae PHP ni TypeScript de producción: solo firmas, esquemas, el SQL de un trigger y valores de configuración. No se agrega ninguna dependencia de Composer ni de npm (`resend/resend-php` no está instalado y no se instala; las firmas se verifican con `hash_hmac` y `hash_equals` de PHP).

### Reparto entre sesiones y orden

Los commits se numeran por dependencia. Cada sesión hace los suyos en el orden de su lista; entre sesiones solo cuentan las dependencias que se indican.

*   **Sesión base (una sola, primero):** commits 1, 2 y 3. Deja el módulo, la configuración de las dos integraciones, las tablas, la escritura de estados (`RecordDeliveryEvent`), la deduplicación de webhooks, las rutas de los dos webhooks (con controladores que responden 404), sus entradas en `RouteSecurityTest`, el limitador y el límite de tamaño. Cuando está en verde se abren las dos sesiones siguientes, cada una en su worktree (`composer worktree:setup`).
*   **Sesión WhatsApp:** commits 4, 5, 6, 7, 8, 9a, 10, 11, 12, 13, 14, 15 y, al final, 16.
*   **Sesión Resend:** commit 9b. Solo depende de la sesión base.

Archivos por sesión (ningún archivo lo tocan dos sesiones paralelas):

*   La sesión Resend solo crea o modifica: `app/Modules/Messaging/Http/Controllers/ResendWebhookController.php`, `app/Modules/Messaging/Support/ResendSignature.php`, `app/Modules/Messaging/Actions/HandleResendEvents.php`, `app/Modules/Messaging/Listeners/OpenMailDelivery.php`, `app/Modules/Messaging/Listeners/StampMailDelivery.php`, `app/Modules/Messaging/Support/MailDeliveryStash.php`, `app/Modules/Messaging/Providers/MailTrackingServiceProvider.php`, una línea de `bootstrap/providers.php`, `docs/operations/resend-webhook.md` y sus pruebas (`tests/Feature/Messaging/ResendWebhookTest.php`, `tests/Unit/Messaging/ResendSignatureTest.php`, `tests/Feature/Messaging/MailDeliveryTrackingTest.php`).
*   La sesión WhatsApp no toca ninguno de esos archivos.
*   La sesión Resend no toca `MessagingServiceProvider` (la sesión WhatsApp lo amplía en los commits 4, 6, 7 y 11) ni `routes/messaging.php`, `RouteSecurityTest`, `config/*.php`, `.env.example` o `.env.production.example`, que la base deja completos para las dos integraciones.
*   `bootstrap/providers.php`: la base agrega `MessagingServiceProvider` al final de la lista y la sesión Resend agrega `MailTrackingServiceProvider` en la línea siguiente; el merge solo junta esas dos líneas.

Al terminar, la rama del PR es la de la sesión WhatsApp: esa sesión integra la rama Resend con `git merge` local (sin push), resuelve lo que haga falta y escribe el commit 16 (prueba del CUJ 5 y documentación de las señales de Resend), que necesita el commit 9b y el commit 11. Así cada commit queda en verde con solo lo que hay en su rama.

Todas las migraciones nuevas van después de la última existente (hoy `2027_01_26_200000_create_sso_tables.php`), con los nombres que se dan abajo.

### Valores por omisión (supuestos por confirmar)

Viven en configuración y se pueden cambiar sin código. Cada uno está marcado así en el archivo de configuración con un comentario `Supuesto por confirmar (Victor)`. Ninguno bloquea un commit; el momento en que se confirma cada uno es el de la columna «Quién confirma y cuándo».

| Valor | Clave | Por omisión | Quién confirma y cuándo |
| --- | --- | --- | --- |
| Envíos por segundo | `whatsapp.max_per_second` (`WHATSAPP_MAX_PER_SECOND`) | 20 | Victor, antes de la Fase 2 (c), contra el límite vigente del número |
| Tope diario por empresa | `whatsapp.daily_cap_per_company` (`WHATSAPP_DAILY_CAP_PER_COMPANY`) | 1000 mensajes | Victor, antes de la Fase 3 |
| Tope mensual de la plataforma | `whatsapp.platform_monthly_cap` (`WHATSAPP_PLATFORM_MONTHLY_CAP`) | 20000 mensajes | Victor, antes de la Fase 3 |
| Pausa automática | `whatsapp.auto_pause.failure_rate_percent`, `.minimum_messages`, `.window_minutes` | 5 %, 50 mensajes, 60 minutos | Victor, antes de la Fase 3 |
| Cupo mensual por plan | `billing.versions.v1.plans.{plan}.quotas.whatsapp` (lo lee `Catalog::quota()`) | 1000 mensajes en los cuatro planes | Victor, antes de la Fase 3 |
| Cupo sin plan | `billing.unplanned_quotas.whatsapp` | 0 (sin plan no hay WhatsApp) | Victor, antes de la Fase 3 |
| Costo de Meta por mensaje | `billing.whatsapp.costs` (lista con vigencia) | `[['from' => '2026-07-01', 'currency' => 'MXN', 'unit_cost_micros' => 170_000]]` (0.17 MXN) | Victor, antes del commit 8 si ya tiene la tarifa; si no, antes de la Fase 3 |
| Margen | `billing.whatsapp.margin_percent` | 30 | Victor, antes de la Fase 3 |
| Formato del número hacia Meta | `PhoneForMeta::toMeta()` | `52` + 10 dígitos, sin `+`; un `wa_id` entrante `521` + 10 dígitos se lee como `+52` + 10 dígitos | Fase 2 (a), con un envío y un STOP reales |
| Versión de la Graph API | `whatsapp.api_version` (`WHATSAPP_API_VERSION`) | `v23.0` | Quien escribe el commit 4, contra la documentación vigente |
| Retención | `messaging.retention_months`, `messaging.webhook_event_days`, `messaging.orphan_minutes`, `messaging.unknown_hours` | 12 meses, 7 días, 60 minutos, 24 horas | Victor (reglas 8 y 14) |
| Webhooks | `messaging.webhook_per_minute`, `messaging.webhook_max_bytes` | 6000 por minuto por dirección; 262144 bytes | Victor, antes de la Fase 3 |
| Textos del opt-in, del aviso de costo y de las plantillas | `whatsapp.consent.version`, `whatsapp.consent.text`, `whatsapp.cost_notice`, `TemplateRegistry` | los de «Textos provisionales», versión `2026-11-18` | Victor, antes de la Fase 2 (pregunta abierta); el código se escribe con los provisionales |

### Textos provisionales (supuesto por confirmar, Victor)

Se escriben literales en el código y las pruebas los afirman; Victor los cambia antes de la Fase 2 sin tocar la lógica.

*   Opt-in (`whatsapp.consent.text`, `{empresa}` = nombre comercial): «Declaro que las personas cuyo celular {empresa} registra en Humetria aceptaron recibir por WhatsApp, desde el número de Humetria, las invitaciones a sus encuestas, y que pueden darse de baja respondiendo STOP.»
*   Aviso de costo (`whatsapp.cost_notice`, `{precio}` = precio vigente por mensaje): «WhatsApp tiene costo adicional: cada mensaje aceptado se cobra en tu estado de cuenta a {precio} más IVA.»
*   `survey_invite_v1`, cuerpo: «{{1}} le invita a contestar una evaluación confidencial sobre su trabajo. Tiene hasta el {{2}}. El enlace es personal y sirve una sola vez.»; botón «Contestar».
*   `engagement_invite_v1`, cuerpo: «{{1}} le invita a dar su opinión en una encuesta anónima. Tiene hasta el {{2}}. El enlace es personal.»; botón «Contestar».

### Empresa en curso fuera de una petición

`Context` lleva `tenant_id` a los jobs y listeners en cola que se despachan dentro de una empresa (así funciona hoy `SendSurveyInvitations`). Para no depender de quién despachó, todo job, listener en cola o comando de este plan que lea o escriba datos de una empresa empieza con `Tenancy\Contracts\Companies::enter($tenantId)`, con el `tenant_id` de la fila de `messaging_deliveries` (leída sin scope) o el del evento; un comando que recorre varias empresas entra a cada una antes de tocar sus filas.

### Contratos públicos nuevos

Solo `Contracts`, `Events` y `Data` son públicos (`tests/Unit/ArchitectureTest.php`, que ya recoge el módulo nuevo por glob y no cambia).

| Contrato | Ubicación | Firma | Implementa | Usa |
| --- | --- | --- | --- | --- |
| `WhatsAppGateway` | `Messaging/Contracts` | `send(OutboundMessage $message): SendOutcome` | `Messaging/Support/CloudApiWhatsAppGateway`, `NullWhatsAppGateway`, `FakeWhatsAppGateway` (pruebas) | `SendWhatsAppMessage` |
| `ChannelConsent` | `Messaging/Contracts` | `canSend(string $tenantId, string $channel, string $phone): bool` | `Messaging/Support/ChannelRules` | `SendWhatsAppMessage`, `WhatsAppChannel` |
| `ChannelPicker` | `Messaging/Contracts` | `route(?string $email, ?string $mobile, bool $whatsAppAllowed = true): array<string, string>` (cero o una entrada: canal => dirección) | `Messaging/Support/ChannelRules` | `SurveyInvitation::routes()` de Campaigns y Engagement |
| `TracksDelivery` | `Messaging/Contracts` | `deliveryReference(): ?InvitationReference` (nulo = esa notificación no se sigue) | `SurveyInvitation` de Campaigns y Engagement | `WhatsAppChannel`, `OpenMailDelivery` |
| `InvitationLinks` | `Messaging/Contracts` | `const TAG = 'messaging.invitation-links'`; `type(): string`; `whatsApp(string $invitationId): ?WhatsAppInvitation` | `Campaigns/Support/CampaignInvitationLinks`, `Engagement/Support/EngagementInvitationLinks` (etiquetadas con `$this->app->tag()`, como `worker.sections`) | `SendWhatsAppMessage` |
| `DeliverySummary` | `Messaging/Contracts` | `forSource(string $sourceType, string $sourceId): list<ChannelDeliveryTally>` | `Messaging/Support/ChannelDeliveryTallies` | `CampaignPages`, `Engagement/Support/Participation` |
| `DeliveryOutcomes` | `Messaging/Contracts` | `undelivered(list<string> $deliveryIds): list<string>` | `Messaging/Support/DeliveryLedger` | comando de conciliación de Billing |
| `CompanyChannels` | `Tenancy/Contracts` | `settings(string $tenantId): list<ChannelSetting>`; `monthlyCap(string $tenantId, string $channel): ?int` | `Tenancy/Support/EloquentCompanyChannels` | `ChannelRules`, `LedgerWhatsAppQuota` |
| `WhatsAppQuota` | `Billing/Contracts` | `reserve(int $count): void` (empresa en curso; `@throws WhatsAppQuotaExceeded`) | `Billing/Support/LedgerWhatsAppQuota` | `QueueSurveyInvitations`, `Engagement/Actions/QueueInvitations` |
| `PlatformCost` | `Billing/Contracts` | `whatsAppMonth(CarbonImmutable $month): MessagingMargin` | `Billing/Support/LedgerPlatformCost` | `Tenancy/Support/PlatformOverview` |

Datos (`final readonly`): `Messaging/Data/OutboundMessage(string $to, string $template, string $language, list<string> $bodyParameters, string $buttonSuffix, string $callbackData)`; `SendOutcome` (`accepted(string $providerMessageId)` o `failed(FailureCause $cause)`); `FailureCause` (enum, ver commit 2); `InvitationReference(string $type, string $id, string $sourceType, string $sourceId)` con constantes `CAMPAIGN_PARTICIPANT = 'campaign_participant'`, `ENGAGEMENT_INVITATION = 'engagement_invitation'`, `CAMPAIGN = 'campaign'`, `ENGAGEMENT_SURVEY = 'engagement_survey'`; `WhatsAppInvitation(string $mobile, string $template, list<string> $bodyParameters, string $buttonSuffix)`; `ChannelDeliveryTally(string $channel, ?int $total, ?int $delivered, ?int $undelivered, bool $undeliveredBelowMinimum)` (regla 10, ver commit 11); `Tenancy/Data/ChannelSetting(string $channel, bool $enabled, int $preference, ?string $consentVersion, ?int $monthlyCap)`; `Billing/Data/MessagingMargin(int $messages, int $costCents, int $chargedCents)`.

Eventos (`Messaging/Events`, clases `final readonly`): `WhatsAppMessageAccepted(string $deliveryId, string $tenantId)`, que disparan el job, el webhook o la conciliación cuando un mensaje queda aceptado y que alimenta el conteo de Billing; `WhatsAppMessageFailed(string $deliveryId, string $tenantId, CarbonImmutable $createdAt, InvitationReference $invitation, FailureCause $cause)`, con `fallsBackToMail(): bool` (delegado a la causa), que libera la reserva en Billing y dispara el respaldo en Campaigns y Engagement.

### Esquemas

Todas las tablas usan `uuid('id')->primary()` con UUID v4 (`Str::uuid()`, nunca v7) y `timestampTz`.

**`messaging_deliveries`** (commit 3)

| Columna | Tipo | Nulo | Nota |
| --- | --- | --- | --- |
| `tenant_id` | uuid, FK `tenants` `restrictOnDelete` (como el libro de uso) | no | |
| `channel` | string(10) | no | CHECK `IN ('mail', 'whatsapp')` |
| `template` | string(64) | sí | CHECK `channel <> 'whatsapp' OR template IS NOT NULL` |
| `provider_message_id` | string(128) | sí | único con `channel`; el `wamid` de Meta o el `email_id` de Resend |
| `recipient_hash` | char(64) | sí | HMAC-SHA256 del teléfono E.164 |
| `key_version` | smallint | sí | CHECK `(recipient_hash IS NULL) = (key_version IS NULL)` y `channel <> 'whatsapp' OR recipient_hash IS NOT NULL` |
| `invitation_type` | string(40) | no | CHECK `IN ('campaign_participant', 'engagement_invitation')` |
| `invitation_id` | uuid | no | sin FK (es de otro módulo) |
| `source_type` | string(40) | no | CHECK `IN ('campaign', 'engagement_survey')` |
| `source_id` | uuid | no | sin FK |
| `status` | string(12) | no | CHECK con los valores de `DeliveryStatus` |
| `accepted_at` | timestampTz | sí | cuando el proveedor aceptó; base de los topes diario y mensual |
| `created_at`, `updated_at` | timestampTz | no | |

Índices: `unique(['id', 'tenant_id'])`; `unique(['channel', 'provider_message_id'])`; `index(['tenant_id', 'source_type', 'source_id', 'channel'])`; `index('recipient_hash')`; `index(['status', 'updated_at'])`; `index(['invitation_type', 'invitation_id'])`; `index(['tenant_id', 'channel', 'accepted_at'])`; `index(['channel', 'accepted_at'])`.

**`messaging_delivery_events`** (commit 3): `tenant_id` uuid no nulo; `delivery_id` uuid no nulo, FK compuesta `['delivery_id', 'tenant_id']` → `messaging_deliveries(['id', 'tenant_id'])` `cascadeOnDelete`; `status` string(12) no nulo con el mismo CHECK; `failure_cause` string(32) nulo, CHECK `failure_cause IS NULL OR failure_cause IN (…valores de FailureCause…)`; `occurred_at` timestampTz no nulo (hora del proveedor); `created_at` timestampTz no nulo. La FK de `tenant_id` a `tenants` es `restrictOnDelete`. Índice `['delivery_id', 'occurred_at']` y `['status', 'occurred_at']`. Sin `updated_at`.

Trigger de solo inserción, como `audit_entries_append_only()` en `2026_10_26_222825_create_audit_entries_table.php`, con la excepción de la empresa demo de `2027_01_19_100000_let_the_demo_company_be_wiped.php` (el reinicio de la demo borra toda tabla con `tenant_id`) y otra para la retención y ARCO:

```sql
CREATE OR REPLACE FUNCTION messaging_delivery_events_append_only() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF EXISTS (SELECT 1 FROM tenants WHERE id = OLD.tenant_id AND is_demo) THEN
            RETURN OLD;
        END IF;
        IF current_setting('messaging.purge', true) = 'on' THEN
            RETURN OLD;
        END IF;
    END IF;
    RAISE EXCEPTION 'messaging_delivery_events is append-only';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER messaging_delivery_events_no_update_delete BEFORE UPDATE OR DELETE ON messaging_delivery_events
    FOR EACH ROW EXECUTE FUNCTION messaging_delivery_events_append_only();
CREATE TRIGGER messaging_delivery_events_no_truncate BEFORE TRUNCATE ON messaging_delivery_events
    FOR EACH STATEMENT EXECUTE FUNCTION messaging_delivery_events_append_only();
```

Solo `messaging:purge` y `messaging:forget` (commit 15) hacen `SET LOCAL messaging.purge = 'on'` dentro de su transacción. Una empresa real con mensajes no se puede borrar (FK `restrictOnDelete`), igual que con `usage_events`.

**`messaging_opt_outs`** (commit 3): `tenant_id` uuid **nulo** (nulo = baja global), FK `tenants` `restrictOnDelete`; `recipient_hash` char(64) no nulo; `key_version` smallint no nulo; `source` string(10) no nulo, CHECK `IN ('stop', 'operator')`; `created_at` timestampTz no nulo. Único `['tenant_id', 'recipient_hash']` con `->nullsNotDistinct()` (Postgres 15 o más; el de Sail es 18). Índice `recipient_hash`.

**`messaging_webhook_events`** (commit 3, sin `tenant_id`): `provider` string(10) CHECK `IN ('meta', 'resend')`; `event_id` string(191) no nulo; único `['provider', 'event_id']`; `received_at` timestampTz no nulo; `orphan` jsonb nulo (solo para un estado de Meta cuyo mensaje aún no existe: `provider_message_id`, `callback_id`, `status`, `timestamp`, `error_code`; nunca el teléfono); `processed_at` timestampTz nulo. Índice `received_at` e índice parcial `received_at WHERE orphan IS NOT NULL AND processed_at IS NULL`.

**`messaging_pauses`** (commit 6, sin `tenant_id`): `reason` string(20) CHECK `IN ('failure_rate', 'platform_cap')`; `started_at` timestampTz no nulo; `ended_at` timestampTz nulo. Índice único parcial `CREATE UNIQUE INDEX messaging_pauses_one_open ON messaging_pauses ((true)) WHERE ended_at IS NULL`: a lo más una pausa abierta.

**`tenant_channels`** (commit 5): `tenant_id` uuid FK `tenants` `cascadeOnDelete`; `channel` string(10) CHECK `IN ('mail', 'whatsapp')`; `enabled` boolean no nulo; `preference` smallint no nulo CHECK `BETWEEN 1 AND 2`; `monthly_cap` integer nulo, CHECK `monthly_cap IS NULL OR (channel = 'whatsapp' AND monthly_cap > 0)`; `consent_wording_version` string(20) nulo; `enabled_by` `foreignId` a `users` nulo `nullOnDelete`; `enabled_at` timestampTz nulo; `timestampsTz`; único `['tenant_id', 'channel']`; CHECK `channel <> 'whatsapp' OR NOT enabled OR (consent_wording_version IS NOT NULL AND enabled_at IS NOT NULL)`.

**Cambios a tablas existentes:** `usage_*` (commit 8) y `campaign_participants`/`engagement_invitations` (commit 10), descritos en su commit.

`DeliveryStatus` (enum interno `Messaging/Enums/DeliveryStatus`): `created`, `sent`, `delayed`, `delivered`, `read`, `failed`, `bounced`, `complained`, `unknown`, `paused`. Rango para el orden de la regla 8: `created`, `unknown` y `paused` = 0; `sent` = 1; `delayed` = 2; `delivered` = 3; `read` = 4. Terminales: `failed`, `bounced`, `complained`. Entregado = `delivered` o `read`; no entregado = cualquier otro.

### Fase 1: solo local (gateways falsos, `Http::fake()`)

#### Commit 1 (sesión base): documentación, esqueleto del módulo y rutas de los webhooks

1.  `CLAUDE.md` (Modificar). En «Módulos», agregar `Messaging`: «transporte de WhatsApp y de eventos de correo: gateway, webhooks, plantillas, consentimiento, historial de estados (ver su README)». Cambiar «No hay SMS ni WhatsApp» por «No hay SMS. WhatsApp sale solo por `Messaging`». En «Rutas», agregar que en `messaging.php` la credencial es la firma del proveedor. No hace falta tocar `scripts/ci-code-changed.sh` ni `tests/Unit/CiCodeChangedTest.php`: el script ya trata `CLAUDE.md`, `docs/operations/*` y todo archivo dentro de un directorio como código.
2.  `app/Modules/Messaging/README.md` (Crear). Qué hace el módulo, su API pública (las tablas de «Contratos públicos nuevos»), el invariante de anonimato aplicado aquí (historial completo guardado, solo agregados expuestos, sin llave hacia respuestas) y el orden de validación de las fases.
3.  `app/Modules/Messaging/Providers/MessagingServiceProvider.php` (Crear) y `bootstrap/providers.php` (Modificar, una línea). En este commit solo define el limitador `RateLimiter::for('messaging-webhooks', fn (Request $request): Limit => Limit::perMinute(config()->integer('messaging.webhook_per_minute'))->by('messaging-webhooks:'.$request->ip()))`.
4.  `config/messaging.php` (Crear) con `webhook_per_minute` y `webhook_max_bytes` (resto en el commit 2).
5.  `app/Modules/Messaging/Http/Middleware/LimitWebhookBody.php` (Crear). Responde 413 si `Content-Length` o `strlen($request->getContent())` pasa de `messaging.webhook_max_bytes`, antes de leer el JSON.
6.  `app/Modules/Messaging/Http/Controllers/MetaWebhookController.php` (Crear, métodos `verify` y `receive`) y `ResendWebhookController.php` (Crear, método `receive`). En este commit los tres responden `abort(404)`; el commit 9a reescribe el de Meta y el 9b el de Resend.
7.  `routes/messaging.php` (Crear) y `routes/web.php` (Modificar: `require __DIR__.'/messaging.php';` junto a `workers.php`). Mismo patrón que `routes/workers.php`: `Route::withoutMiddleware([EncryptCookies, AddQueuedCookiesToResponse, StartSession, ShareErrorsFromSession, PreventRequestForgery, EndRevokedImpersonation, AssignRequestContext, ForbidWhileImpersonating, HandleAppearance, HandleInertiaRequests])->middleware(['throttle:messaging-webhooks', LimitWebhookBody::class])`. Rutas: `GET webhooks/whatsapp` → `messaging.webhooks.meta.verify`; `POST webhooks/whatsapp` → `messaging.webhooks.meta.receive`; `POST webhooks/resend` → `messaging.webhooks.resend.receive`. Sin sesión ni cookie, así que no hay CSRF que eximir y `bootstrap/app.php` no cambia para esto.
8.  `tests/Feature/RouteSecurityTest.php` (Modificar). Las tres rutas en la lista pública con su razón: «guest: el proveedor no tiene cuenta; la firma del cuerpo crudo es la credencial; sin sesión ni cookies; throttled por dirección; tamaño máximo».
9.  `tests/Feature/Messaging/WebhookRoutesTest.php` (Crear). Un POST sin token CSRF no responde 419; la respuesta no trae `Set-Cookie`; con `webhook_per_minute` en 2 dentro de la prueba, la tercera petición del mismo minuto y dirección responde 429; un cuerpo de `webhook_max_bytes + 1` responde 413. No afirma 404, para seguir valiendo después de los commits 9a y 9b.
10. `tests/Feature/Messaging/LinkPreviewTest.php` (Crear). Fija la pregunta cerrada de «Preguntas abiertas»: abrir el enlace no lo consume. Con `config(['session.driver' => 'database'])` dentro de la prueba (producción usa `SESSION_DRIVER=database`; `phpunit.xml` usa `array`), un GET a `survey.show` y a `engagement.survey.show` con un token válido solo escribe en `sessions` (`DB::listen` no ve INSERT, UPDATE ni DELETE sobre ninguna otra tabla) y el mismo token sigue sirviendo para contestar. Debe pasar sin cambiar código; si falla, se detiene y se avisa a Victor.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/WebhookRoutesTest.php tests/Feature/Messaging/LinkPreviewTest.php tests/Feature/RouteSecurityTest.php tests/Unit/ArchitectureTest.php tests/Unit/OperationsDocsTest.php`.

#### Commit 2 (sesión base): contrato del gateway, configuración y redacción de logs

11. `app/Modules/Messaging/Contracts/WhatsAppGateway.php` (Crear). Ver «Contratos públicos nuevos».
12. `app/Modules/Messaging/Data/OutboundMessage.php`, `SendOutcome.php` y `FailureCause.php` (Crear). `FailureCause` (enum string): `invalid_token`, `template_not_approved`, `no_whatsapp_account`, `outside_window`, `rate_limited`, `server_error`, `timed_out`, `retries_exhausted`, `spam_limited`, `ecosystem_blocked`, `opted_out`, `invitation_closed`, `paused`, `disabled`, `unresolved`, `unknown_error`. Métodos: `retryable(): bool` (verdadero para `rate_limited` y `server_error`) y `fallsBackToMail(): bool` (verdadero para `no_whatsapp_account`, `template_not_approved`, `invalid_token`, `retries_exhausted` y `opted_out`, que son los fallos definitivos de la regla 9).
13. `app/Modules/Messaging/Support/FakeWhatsAppGateway.php` (Crear). `script(SendOutcome ...$outcomes)` fija las respuestas en orden; `sent(): list<OutboundMessage>` devuelve lo enviado.
14. `app/Modules/Messaging/Support/NullWhatsAppGateway.php` (Crear). No hace ninguna petición, escribe `whatsapp.send_skipped` y devuelve `SendOutcome::failed(FailureCause::Disabled)`.
15. `MessagingServiceProvider` (Modificar). Enlaza `WhatsAppGateway` con `NullWhatsAppGateway` cuando `whatsapp.enabled` es falso (el `CloudApiWhatsAppGateway` se enlaza en el commit 4). Al arrancar con `whatsapp.enabled` verdadero y `messaging.recipient_keys` vacío lanza `LogicException`.
16. `config/whatsapp.php` (Crear). Claves: `enabled` (`WHATSAPP_ENABLED`, `false`), `paused` (`WHATSAPP_PAUSED`, `false`), `base_url` (`https://graph.facebook.com`), `api_version`, `access_token` (`WHATSAPP_ACCESS_TOKEN`), `phone_number_id` (`WHATSAPP_PHONE_NUMBER_ID`), `app_secrets` (lista sin nulos de `WHATSAPP_APP_SECRET` y `WHATSAPP_APP_SECRET_PREVIOUS`: durante una rotación se aceptan las dos), `verify_token` (`WHATSAPP_VERIFY_TOKEN`), `language` (`es_MX`), `timeout_seconds` (10), `max_per_second`, `daily_cap_per_company`, `platform_monthly_cap`, `auto_pause`, `consent` (ver la tabla de supuestos).
17. `config/messaging.php` (Modificar). `recipient_keys`: se lee de `MESSAGING_RECIPIENT_KEYS` con forma `1:<base64>,2:<base64>` y queda `array<int, string>`; la clave vigente es la de versión mayor; una clave no se quita mientras exista una baja con su versión (regla 6). Además `retention_months` (`MESSAGING_RETENTION_MONTHS`), `webhook_event_days`, `orphan_minutes`, `unknown_hours`.
18. `config/services.php` (Modificar). En `resend`: `webhook_secrets` (lista sin nulos de `RESEND_WEBHOOK_SECRET` y `RESEND_WEBHOOK_SECRET_PREVIOUS`) y `webhook_tolerance_seconds` (300).
19. `.env.example` y `.env.production.example` (Modificar). Todas las variables de los tres archivos de configuración, vacías o con su valor por omisión; ningún secreto.
20. `app/Logging/RedactSensitiveData.php` (Modificar) y `tests/Feature/Logging/StructuredLogTest.php` (Modificar: el dataset de «redacts each sensitive key»). Agregar `phone`, `mobile`, `wa_id`, `msisdn`, `recipient_hash`, `app_secret`, `verify_token`, `hub_verify_token` y `components` (`access_token` ya está en la lista). No se agregan `to` ni `from`: hoy los usan logs de cambio de estado (`complaints.status_changed`, `engagement.commitment_status_changed`, `intervention.progress_recorded`) y quedarían ilegibles; en su lugar, ningún código de `Messaging` registra el payload de Meta ni de Resend. `whatsapp` y `contact_phone` ya se redactan, así que ningún log usa `whatsapp` como llave de contexto.
21. `tests/Unit/Messaging/NullGatewayTest.php` (Crear). Con `WHATSAPP_ENABLED` sin definir, el contenedor resuelve `NullWhatsAppGateway`; con `Http::preventStrayRequests()` no sale ninguna petición y el resultado es `disabled`. Arrancar con la función encendida y sin claves de hash falla.

Pruebas acotadas: `php artisan test --compact tests/Unit/Messaging tests/Feature/Logging/StructuredLogTest.php`.

#### Commit 3 (sesión base): tablas, modelos, hash del teléfono y escritura de estados

22. `database/migrations/2027_01_27_100000_create_messaging_tables.php` (Crear). Las cuatro tablas de «Esquemas» (todas menos `messaging_pauses` y `tenant_channels`) con el trigger. `down()` las borra y borra la función.
23. `app/Modules/Messaging/Models/Delivery.php`, `DeliveryEvent.php`, `OptOut.php`, `WebhookEvent.php` (Crear) y `database/factories/Messaging/DeliveryFactory.php`, `DeliveryEventFactory.php`, `OptOutFactory.php`, `WebhookEventFactory.php` (Crear) con `#[UseFactory]`. Ningún modelo usa `BelongsToCurrentTenant`: `Delivery`, `DeliveryEvent` y `OptOut` se leen entre empresas (webhooks, bajas, conciliación, retención), igual que `UsageCounter`. Toda lectura dentro de una empresa filtra `tenant_id` con `CurrentTenant::id()` de forma explícita. Las llaves se generan con `Str::uuid()` (v4), no con `HasUuids` por omisión (v7).
24. `tests/Feature/TenantScopedModelsTest.php` (Modificar). En `TENANT_MODELS_WITHOUT_SCOPE`: `Delivery` («read across companies by the webhooks, the reconciliation and the retention»), `DeliveryEvent` (misma razón) y `OptOut` («a STOP may cover every company, and a global opt-out has no company»). En `PLATFORM_MODELS`: `WebhookEvent` («deduplication of provider callbacks, before any company is known»).
25. `app/Modules/Messaging/Enums/DeliveryStatus.php` (Crear). Valores, rango y terminales de «Esquemas».
26. `app/Modules/Messaging/Support/RecordDeliveryEvent.php` (Crear). `handle(Delivery $delivery, DeliveryStatus $status, CarbonImmutable $occurredAt, ?FailureCause $cause = null): void`. Siempre inserta el evento. Cambia `deliveries.status` solo si el actual no es terminal y además el nuevo es terminal, o su rango es mayor que el del actual, o el actual es `created`. Escribe `messaging.delivery_status_changed` con `delivery_id`, `channel`, `status` y `cause`.
27. `app/Modules/Messaging/Support/RecipientHash.php` (Crear). `current(string $e164): array{hash: string, version: int}` con la clave vigente y `all(string $e164): list<string>` con cada clave de la lista. HMAC-SHA256 con `hash_hmac`.
28. `app/Modules/Messaging/Support/PhoneForMeta.php` (Crear). Reusa `App\Support\PhoneNumber::e164()`. `toMeta(?string $e164): ?string` devuelve `52` + 10 dígitos sin `+` y nulo para cualquier otra cosa (vacío, mal formado o de otro país: la v1 solo manda a celulares de México; `ChannelRules` trata el nulo como «sin celular»); `fromWaId(string $waId): ?string` devuelve `+52` + 10 dígitos (quita el `1` de un `521` + 10 dígitos) o el E.164 tal cual para otro país; nulo si no es un número.
29. `app/Modules/Messaging/Data/InvitationReference.php` y `app/Modules/Messaging/Contracts/TracksDelivery.php` (Crear). Los usan las dos sesiones.
30. `app/Modules/Messaging/Actions/CreateDelivery.php` (Crear). `handle(string $channel, InvitationReference $invitation, ?string $template, ?string $e164): Delivery`, en la empresa en curso, con `status = created` y su evento `created`. La usan `WhatsAppChannel` (commit 7) y `OpenMailDelivery` (commit 9b).
31. `app/Modules/Messaging/Support/WebhookInbox.php` (Crear). `firstTime(string $provider, string $eventId, ?array $orphan = null): bool` con `insertOrIgnore`; falso si ya se vio.
32. Pruebas (Crear): `tests/Feature/Messaging/RecordDeliveryEventTest.php` (orden, `delayed` después de `delivered`, nada sale de un terminal, el historial guarda todo, el trigger rechaza UPDATE y DELETE y acepta DELETE con `SET LOCAL messaging.purge = 'on'`), `tests/Unit/Messaging/RecipientHashTest.php` (mismo teléfono y clave dan el mismo hash; dos versiones dan hashes distintos), `tests/Unit/Messaging/PhoneForMetaTest.php` (vacío, mal formado, con espacios, `+52`, `521…` y otro país, que en `toMeta` da nulo), `tests/Feature/Messaging/WebhookInboxTest.php` (el segundo `firstTime` del mismo id es falso) y `tests/Feature/Messaging/DeliveryTablesTest.php` (cada CHECK rechaza su valor inválido; dos bajas globales del mismo hash chocan; borrar una empresa real con mensajes falla por la FK). Modificar `tests/Feature/Demo/DemoDeleteExceptionTest.php`: fila nueva en `insertOnlyTables()` `'messaging_delivery_events' => ['messaging_delivery_events', fn (): string => DeliveryEventFactory::new()->createOne()->id, 'messaging_delivery_events is append-only', 'status = status', true]`.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging tests/Unit/Messaging tests/Feature/TenantScopedModelsTest.php`.

#### Commit 4 (sesión WhatsApp): gateway HTTP hacia Meta y registro de plantillas

Antes de escribirlo se verifica en la documentación vigente de Meta: la versión de la Graph API, la forma del cuerpo de `POST /{version}/{phone_number_id}/messages` con plantilla y botón de URL, el campo `biz_opaque_callback_data` (cadena propia que Meta devuelve en los estados del webhook), los códigos de error de la tabla de abajo y el límite de envíos por segundo del número. Si algo difiere, se ajusta la tabla y la configuración en este commit y se anota en `docs/operations/whatsapp.md` (commit 15).

33. `app/Modules/Messaging/Support/CloudApiWhatsAppGateway.php` (Crear). `Http` de Laravel con `timeout(config whatsapp.timeout_seconds)`, `withToken(access_token)`, URL `{base_url}/{api_version}/{phone_number_id}/messages`, cuerpo `messaging_product = whatsapp`, `to = PhoneForMeta::toMeta()`, `type = template`, `template.name`, `template.language.code = es_MX`, un componente `body` con los parámetros de texto, un componente `button` (`sub_type = url`, `index = 0`) con el sufijo, y `biz_opaque_callback_data` = id del mensaje. 200 con `messages[0].id` es `accepted`. Una `ConnectionException` es `timed_out` (no se sabe si salió). Cualquier otra respuesta pasa por `WhatsAppErrorMap`.
34. `app/Modules/Messaging/Support/WhatsAppErrorMap.php` (Crear). `fromResponse(int $httpStatus, ?int $metaCode): FailureCause` (gateway) y `fromCode(int $metaCode): FailureCause` (webhook, sin estado HTTP; usa solo la parte de códigos de la tabla): 190 → `invalid_token`; 132000, 132001, 132015 y 132016 → `template_not_approved`; 131026 → `no_whatsapp_account`; 131047 → `outside_window`; 130429, 131056 y 80007, o HTTP 429 → `rate_limited`; 131048 → `spam_limited`; 131049 → `ecosystem_blocked`; 131000, 131016 o HTTP 5xx → `server_error`; cualquier otro → `unknown_error`.
35. `app/Modules/Messaging/Support/TemplateRegistry.php` (Crear). Dos plantillas: `survey_invite_v1` (Campaigns) y `engagement_invite_v1` (Engagement), idioma `es_MX`, categoría `UTILITY`, 2 parámetros de cuerpo (`{{1}}` nombre comercial de la empresa, `{{2}}` último día, por ejemplo «12 de mayo») y un botón de URL con base `{APP_URL}/survey/{{1}}` y `{APP_URL}/engagement/{{1}}`. `get(string $key): TemplateDefinition` lanza si no existe. El texto provisional del cuerpo vive en el registro con la marca `Supuesto por confirmar (Victor)`; Victor lo revisa y envía las dos plantillas a Meta en cuanto existe este commit (su aprobación es requisito de la Fase 2 (c), no de ningún commit).
36. `MessagingServiceProvider` (Modificar). Con `whatsapp.enabled` verdadero enlaza `WhatsAppGateway` con `CloudApiWhatsAppGateway`.
37. `tests/Unit/Messaging/TemplateRegistryTest.php` (Crear). El número de parámetros del registro coincide con el que arma cada `InvitationLinks` (se completa en el commit 10) y la base del botón coincide con el prefijo de `route('survey.show', 'x')` y `route('engagement.survey.show', 'x')`.
38. `tests/Feature/Messaging/CloudApiGatewayTest.php` (Crear). Un dataset de Pest corre cada caso contra `FakeWhatsAppGateway` y contra el HTTP con `Http::fake()`: cuerpo enviado (incluye `biz_opaque_callback_data`, no incluye el token fuera del botón), aceptado, y cada código de la tabla.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/CloudApiGatewayTest.php tests/Unit/Messaging`.

#### Commit 5 (sesión WhatsApp): canales por empresa (datos)

39. `database/migrations/2027_01_27_100100_create_tenant_channels_table.php` (Crear). Esquema de «Esquemas».
40. `app/Modules/Tenancy/Models/TenantChannel.php` (Crear, con `BelongsToCurrentTenant`) y `database/factories/Tenancy/TenantChannelFactory.php` (Crear, estados `whatsAppEnabled()` y `mailDisabled()`).
41. `app/Modules/Tenancy/Contracts/CompanyChannels.php`, `app/Modules/Tenancy/Data/ChannelSetting.php` y `app/Modules/Tenancy/Support/EloquentCompanyChannels.php` (Crear); enlace en `app/Modules/Tenancy/Providers/TenancyServiceProvider.php` (Modificar). `settings()` devuelve siempre los dos canales, ordenados por `preference` y luego correo primero; sin fila rige el valor por omisión (correo activo, preferencia 1; WhatsApp apagado, preferencia 2). Lee con `withoutGlobalScope('tenant')` y filtra por el `$tenantId` recibido, para servir a jobs y webhooks.
42. `tests/Feature/Tenancy/CompanyChannelsDataTest.php` (Crear). Valores por omisión sin filas; una fila solo cambia su canal; la empresa A no lee las filas de B (por consulta); los CHECK de la tabla.

Pruebas acotadas: `php artisan test --compact tests/Feature/Tenancy/CompanyChannelsDataTest.php tests/Feature/TenantScopedModelsTest.php`.

#### Commit 6 (sesión WhatsApp): consentimiento, bajas, elección de canal y pausas

43. `database/migrations/2027_01_27_100200_create_messaging_pauses_table.php` (Crear) y `app/Modules/Messaging/Models/Pause.php` (Crear); `Pause` va en `PLATFORM_MODELS` de `TenantScopedModelsTest` («an operational stop of the whole feature, for every company»).
44. `app/Modules/Messaging/Support/WhatsAppSwitch.php` (Crear). `available(): bool` es `whatsapp.enabled` y no `whatsapp.paused` y sin fila abierta en `messaging_pauses`; `pause(string $reason): void` inserta con `insertOrIgnore` (el índice único impide dos abiertas) y escribe `whatsapp.paused_automatically` (nivel error) con la razón.
45. `app/Modules/Messaging/Contracts/ChannelConsent.php` y `ChannelPicker.php` (Crear) y `app/Modules/Messaging/Support/ChannelRules.php` (Crear; implementa los dos; enlazado como `scoped`).
    *   `canSend()` es verdadero solo si `WhatsAppSwitch::available()`, la empresa no es la demo (`Tenancy\Contracts\DemoCompanies::isDemo`; esto reemplaza al `BlockDemoWhatsApp` del plan anterior y protege desde el primer envío), el canal está activo en `CompanyChannels` con `consentVersion` igual a `whatsapp.consent.version`, y no hay baja del teléfono para esa empresa ni global (con `RecipientHash::all()`).
    *   `route()`: si `whatsapp.enabled` es falso no hace ninguna consulta y devuelve `['mail' => $email]` o `[]` (así las pruebas existentes con conteo de consultas no cambian). Si no, recorre `settings()` en orden y toma el primer canal activo para el que hay dato: correo si `$email` no está vacío; WhatsApp si `$whatsAppAllowed`, `PhoneForMeta::toMeta($mobile)` no es nulo y pasa la misma comprobación que `canSend()` (un método privado común, con la empresa de `CurrentTenant::id()`). Carga una sola vez por empresa las filas de canales y los hashes de bajas (de la empresa y globales) y los compara en memoria.
    *   Una baja nunca se borra por una nueva activación del dueño.
46. `app/Modules/Messaging/Actions/RecordOptOut.php` (Crear). `handle(string $e164, string $source): int`. Busca en `messaging_deliveries` (canal `whatsapp`, sin scope) las empresas con alguno de los hashes de `RecipientHash::all()`; inserta una baja por empresa con la clave vigente, o una global si no hay ninguna; escribe `whatsapp.opt_out_recorded` con `companies` y `global`, sin teléfono ni hash. Solo registra la baja: la bitácora de activación es de Tenancy (commit 12).
47. `tests/Feature/Messaging/ConsentTest.php` (Crear). Sin activación o con otra versión del texto no se envía; la demo nunca envía; `WHATSAPP_PAUSED` y una pausa abierta cortan; una baja de A no corta a B; una baja global corta a todas; una nueva activación no borra la baja.
48. `tests/Feature/Messaging/ChannelPickerTest.php` (Crear). Regla 1 (gana el de menor preferencia entre los activos con dato), regla 2 (valores por omisión), regla 4 (sin `mobile` y WhatsApp como único canal activo: `[]`), con la función apagada no hay consultas, y `$whatsAppAllowed = false` nunca elige WhatsApp.
49. `tests/Feature/Messaging/OptOutTest.php` (Crear). Varias empresas, global sin filas, dos versiones de clave.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/ConsentTest.php tests/Feature/Messaging/ChannelPickerTest.php tests/Feature/Messaging/OptOutTest.php tests/Feature/TenantScopedModelsTest.php`.

#### Commit 7 (sesión WhatsApp): canal de notificación, job de envío y eventos

50. `app/Modules/Messaging/Support/WhatsAppChannel.php` (Crear) y `MessagingServiceProvider` (Modificar: `Notification::extend('whatsapp', fn (Application $app): WhatsAppChannel => $app->make(WhatsAppChannel::class))`). `send(object $notifiable, Notification $notification): void`: exige `TracksDelivery` con `deliveryReference()` no nulo (si no, `LogicException`); toma el teléfono de `$notifiable->routeNotificationFor('whatsapp')`; si `canSend()` es falso no crea nada y escribe `whatsapp.send_refused`; si no, `CreateDelivery` con la plantilla que corresponde a `invitation->type` y despacha `SendWhatsAppMessage($delivery->id)` con `DB::afterCommit`. Ni el teléfono ni el token entran en la cola.
51. `app/Modules/Messaging/Contracts/InvitationLinks.php` y `app/Modules/Messaging/Data/WhatsAppInvitation.php` (Crear).
52. `app/Modules/Messaging/Events/WhatsAppMessageAccepted.php` y `WhatsAppMessageFailed.php` (Crear).
53. `app/Modules/Messaging/Jobs/SendWhatsAppMessage.php` (Crear). Constructor `(string $deliveryId)`; `onQueue('whatsapp')`; `$maxExceptions = 3`; `backoff(): [60, 300]`; `retryUntil()` en 3 días; middleware `new RateLimited('whatsapp-send')` (limitador `Limit::perSecond(config whatsapp.max_per_second)` en `MessagingServiceProvider`). Con `maxExceptions` en lugar de `tries`, ni el limitador ni el tope diario gastan intentos: solo cuentan los tres fallos reintentables. `handle()`, en orden:
    1.  Lee la fila sin scope y hace `Companies::enter($delivery->tenant_id)`. Sale si el mensaje ya tiene `provider_message_id` o un estado terminal (idempotencia, regla 7).
    2.  Si `WhatsAppSwitch::available()` es falso: estado `paused` y `WhatsAppMessageFailed` con causa `paused`.
    3.  Si la plataforma llegó a `whatsapp.platform_monthly_cap` (filas de `messaging_deliveries` con `channel = whatsapp` y `accepted_at` desde el día 1 del mes en `app.display_timezone`, convertido a UTC): `WhatsAppSwitch::pause('platform_cap')` y sigue como el paso 2.
    4.  Si la empresa llegó a `whatsapp.daily_cap_per_company` (las mismas filas de la empresa con `accepted_at` desde las 00:00 de hoy en `app.display_timezone`, convertido a UTC): `release()` hasta las 00:05 del día siguiente y escribe `whatsapp.daily_cap_reached`.
    5.  Busca la implementación etiquetada de `InvitationLinks` cuyo `type()` es `invitation_type`; `whatsApp(invitation_id)` nulo significa invitación contestada, cancelada o cerrada: estado `failed`, causa `invitation_closed`, evento fallido.
    6.  Si `canSend()` es falso (una baja posterior al lanzamiento): `failed`, `opted_out`, evento fallido. Sí hay respaldo a correo (la baja es del canal WhatsApp, no de la invitación; regla 9).
    7.  Llama al gateway. Aceptado: en una transacción guarda `provider_message_id` y `accepted_at` y registra `sent`; después del commit dispara `WhatsAppMessageAccepted` y escribe `whatsapp.message_accepted`. `timed_out`: registra `unknown` y termina sin reintentar ni liberar la reserva. Causa reintentable: lanza `RetryableWhatsAppFailure` (clase en `Messaging/Exceptions`). Otra causa: `failed` con esa causa y evento fallido.

    `failed(Throwable $exception)` (fuera de `handle()`): entra a la empresa, registra `failed` con `retries_exhausted` y dispara el evento fallido, que lleva al respaldo a correo.
54. `config/horizon.php` (Modificar). En `defaults`, `supervisor-whatsapp` con las mismas claves que `supervisor-1`: `connection` `redis`, `queue` `['whatsapp']`, `balance` `false`, `autoScalingStrategy` `time`, `maxProcesses` 1, `maxTime` 0, `maxJobs` 0, `memory` 128, `tries` 0 (el job decide), `timeout` 30, `nice` 0; `'supervisor-whatsapp' => []` en `production` y `local`; y `'redis:whatsapp' => 60` en `waits`, para que `queue.long_wait` cubra la cola nueva.
55. `app/Modules/Messaging/Actions/ReconcileUnknown.php` (Crear, `handle(): int`, cuántos resolvió; entra a la empresa de cada fila) y en `routes/console.php` (Modificar) el comando `messaging:reconcile-unknown`, `->hourly()->onOneServer()->withoutOverlapping()`. Meta no permite consultar el estado de un mensaje: un `unknown` se resuelve cuando llega un estado del webhook que trae su id en `biz_opaque_callback_data` (commit 9a). Pasadas `messaging.unknown_hours` sin estado, registra `failed` con causa `unresolved` y dispara `WhatsAppMessageAccepted` (se liquida como usado, regla 8); no dispara el respaldo.
56. `tests/Feature/ScheduleTest.php` (Modificar). Agrega `messaging:reconcile-unknown` con su cron.
57. `tests/Feature/Messaging/SendWhatsAppMessageTest.php` (Crear). Con `FakeWhatsAppGateway` y una `InvitationLinks` de prueba etiquetada en la prueba: éxito y evento aceptado; cada causa; tres reintentables terminan en `retries_exhausted`; `timed_out` queda `unknown`; pausa y tope global; tope diario libera el job hasta el día siguiente; idempotencia (dos ejecuciones, un envío); la carga serializada del job no contiene el token ni el teléfono; un `unknown` de más de 24 horas se liquida sin respaldo; una baja posterior al lanzamiento falla con `opted_out` y sí dispara el respaldo a correo.
58. `tests/Feature/Messaging/WhatsAppChannelTest.php` (Crear). `Notification::routes(['whatsapp' => …])` crea la fila y encola el job; sin consentimiento no crea nada.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/SendWhatsAppMessageTest.php tests/Feature/Messaging/WhatsAppChannelTest.php tests/Feature/ScheduleTest.php`.

#### Commit 8 (sesión WhatsApp): medidor de WhatsApp en Billing y costo por línea

59. `database/migrations/2027_01_27_100300_allow_whatsapp_meter_and_unit_cost.php` (Crear). Mismo patrón que `2026_12_21_100000_allow_mail_and_storage_meters.php`: en `usage_counters`, `usage_reservations`, `usage_events` y `usage_grants` reemplaza `*_meter_known` por `CHECK (meter IN ('assistant_tokens', 'email_campaign', 'storage_bytes', 'whatsapp'))`. Agrega a `usage_events` (el trigger de solo inserción no impide un `ALTER TABLE`): `unit_cost_micros` bigint nulo, `unit_price_micros` bigint nulo, `cost_currency` char(3) nulo y `CHECK usage_events_cost_complete ((unit_cost_micros IS NULL AND unit_price_micros IS NULL AND cost_currency IS NULL) OR (unit_cost_micros >= 0 AND unit_price_micros >= unit_cost_micros AND cost_currency IS NOT NULL))`. `down()` regresa los tres medidores y quita las columnas (falla si ya se contó WhatsApp, como su precedente).
60. `app/Modules/Billing/Contracts/UsageMeter.php` (Modificar). Constantes `EMAIL_CAMPAIGN = 'email_campaign'` y `WHATSAPP = 'whatsapp'`. `app/Modules/Billing/Listeners/CountSentMail.php` (Modificar) quita `METER` y usa `UsageMeter::EMAIL_CAMPAIGN`; lo mismo en `LedgerMailQuota.php` y `UsageQuota.php` (el literal). `UsageQuota` conserva la rama del correo por asiento; `whatsapp` usa la rama general del catálogo.
61. `app/Modules/Billing/Support/CounterHold.php` (Crear). Extrae de `LedgerMailQuota::reserve()` el `insertOrIgnore` del contador y el incremento condicional: `take(string $tenantId, string $meter, int $count, int $limit): ?int` devuelve nulo si apartó o lo que queda si no cabe. `LedgerMailQuota` (Modificar) lo usa y conserva su texto y su firma; ningún llamador de `MailQuota` cambia.
62. `app/Modules/Billing/Contracts/WhatsAppQuota.php`, `app/Modules/Billing/Support/LedgerWhatsAppQuota.php` y `app/Modules/Billing/Exceptions/WhatsAppQuotaExceeded.php` (Crear); enlace en `BillingServiceProvider` (Modificar). Límite = el menor entre `UsageQuota::for($tenantId, 'whatsapp', $period)` y `CompanyChannels::monthlyCap($tenantId, 'whatsapp')`. A diferencia del correo, una empresa sin plan tiene cupo 0. Mensaje: «Tu cupo de WhatsApp del mes es de N mensajes y te quedan M; esto manda K. No se envió nada.» más `SupportContact::sentence()`. `bootstrap/app.php` (Modificar) agrega `WhatsAppQuotaExceeded` al render que hoy atiende `StorageQuotaExceeded|MailQuotaExceeded`.
63. `app/Modules/Billing/Support/UnitCost.php` (Crear, `final readonly`: `costMicros`, `priceMicros`, `currency`) y `app/Modules/Billing/Support/MessagingCost.php` (Crear). `at(CarbonImmutable $when): UnitCost` toma de `billing.whatsapp.costs` la entrada más reciente con `from` anterior o igual; precio = costo × (100 + `margin_percent`) / 100, redondeado a la micra hacia arriba; lanza `LogicException` si no hay entrada vigente.
64. `app/Modules/Billing/Actions/RecordUsage.php` (Modificar). Parámetro opcional final `?UnitCost $cost = null` que llena las tres columnas nuevas.
65. `app/Modules/Billing/Listeners/CountWhatsApp.php` (Crear); `BillingServiceProvider` (Modificar) lo registra para los dos eventos. `WhatsAppMessageAccepted`: `RecordUsage::handle($tenantId, 'whatsapp', 1, $deliveryId, fromHold: true, cost: MessagingCost::at(now()))`, idempotente por la llave. `WhatsAppMessageFailed`: si no hay `usage_events` con esa llave, resta 1 de `reserved` del contador `whatsapp` del periodo que contiene `$event->createdAt` (`UsagePeriod::containing()`; nunca bajo cero); si ya se liquidó (fallo que llega por webhook después de aceptado), no hace nada y queda para la conciliación (regla 11).
66. `app/Modules/Billing/Actions/ReleaseMailHolds.php` (Borrar) → `app/Modules/Billing/Actions/ReleaseBulkHolds.php` (Crear). Libera `reserved` de `email_campaign` y `whatsapp`; escribe `usage.holds_released` con `released`. `routes/console.php` (Modificar): el comando `billing:release-mail-holds` pasa a `billing:release-holds` con salida «Reservas liberadas: N», mismo horario 03:50.
67. `config/billing.php` (Modificar). `unplanned_quotas.whatsapp`, `versions.v1.plans.{jornada,cumplimiento,integral,enterprise}.quotas.whatsapp` y el bloque `whatsapp` (`margin_percent`, `costs`), con sus valores de la tabla de supuestos.
68. `app/Modules/Tenancy/Http/Controllers/CompanyModulesController.php` (Modificar). Tercer medidor en `other_usage`: `['meter' => 'whatsapp', 'label' => 'Mensajes de WhatsApp', 'unit' => 'mensajes']` (`formatAmount` de `resources/js/lib/usage.ts` ya trata cualquier unidad distinta de `bytes`).
69. Pruebas. Crear `tests/Feature/Billing/WhatsAppMeterTest.php` (reserva todo o nada, tope del dueño menor que el plan, sin plan cupo 0, liquidación con costo y precio, liberación por fallo, fallo después de liquidado no libera, idempotencia) y `tests/Unit/Billing/MessagingCostTest.php` (vigencias y redondeo). Modificar, porque el diseño generaliza el medidor de Billing (decisión de cobro de «Decisiones tomadas»; Victor lo confirma al revisar, CLAUDE.md, regla 2): `tests/Feature/ScheduleTest.php` (`billing:release-holds`), `tests/Feature/Billing/MailQuotaTest.php` (el `use` y el `resolve(ReleaseMailHolds::class)->handle()` pasan a `ReleaseBulkHolds`, `mutates(LedgerMailQuota::class, ReleaseMailHolds::class)` pasa a `mutates(LedgerMailQuota::class, ReleaseBulkHolds::class)`, y el caso del comando cambia el nombre, la salida y el log), `tests/Feature/Tenancy/CompanyLimitsConsoleTest.php` (`other_usage` pasa de 2 a 3) y `tests/Unit/PaginatedListsTest.php` (la razón de `tenancy/company:other_usage` dice tres medidores). Lo demás de `MailQuotaTest` y `MailMeteringTest` debe pasar sin cambios.

Pruebas acotadas: `php artisan test --compact tests/Feature/Billing tests/Unit/Billing tests/Feature/Tenancy/CompanyLimitsConsoleTest.php tests/Feature/ScheduleTest.php tests/Unit/PaginatedListsTest.php`.

#### Commit 9a (sesión WhatsApp): webhook de Meta

70. `app/Modules/Messaging/Support/MetaSignature.php` (Crear). `valid(string $rawBody, ?string $header): bool`: el encabezado `X-Hub-Signature-256` tiene la forma `sha256=<hex>`; compara con `hash_equals` contra `hash_hmac('sha256', $rawBody, $secret)` para cada secreto de `whatsapp.app_secrets`.
71. `app/Modules/Messaging/Http/Controllers/MetaWebhookController.php` (Modificar, reemplaza el 404). `verify`: si `hub_mode` es `subscribe` y `hash_equals(verify_token, hub_verify_token)`, responde 200 `text/plain` con `hub_challenge`; si no, 403. `receive`: firma inválida o ausente → 403 sin leer el cuerpo; JSON que no decodifica a arreglo → 400 y `whatsapp.webhook_malformed`; si no, `HandleMetaEvents` y 200 vacío, incluso con campos desconocidos.
72. `app/Modules/Messaging/Actions/HandleMetaEvents.php` (Crear). `handle(array $payload): void`. Recorre `entry[].changes[]`:
    *   `field = messages`, `value.statuses[]`: llave de deduplicación `status:{id}:{status}:{timestamp}` con `WebhookInbox`. Busca el mensaje por `biz_opaque_callback_data` (id propio) o por `id` (`wamid`) en `messaging_deliveries` sin scope; Entra a su empresa con `Tenancy\Contracts\Companies::enter()`. Si lo encuentra por el id propio y aún no tiene `provider_message_id` (un `unknown` o un mensaje cuyo job no alcanzó a guardarlo), lo guarda con `accepted_at`, y si el estado es `sent`, `delivered` o `read` dispara `WhatsAppMessageAccepted` (Billing liquida la reserva; es idempotente por la llave). Mapea `sent`, `delivered`, `read` y `failed` (con `errors[0].code` por `WhatsAppErrorMap::fromCode()`) y escribe con `RecordDeliveryEvent`, con `occurred_at` del `timestamp`. Un `failed` dispara `WhatsAppMessageFailed`; si la causa lo permite hay respaldo, también para un `unknown` que así se resuelve (regla 9). Si no lo encuentra, guarda el evento como huérfano (columna `orphan`) y no hace nada más.
    *   `field = messages`, `value.messages[]`: deduplica por `message:{id}`. Si `type = text` y el texto, sin espacios a los lados, en mayúsculas y sin `.` ni `!` al final, es `STOP`, `BAJA`, `ALTO` o `CANCELAR`, llama a `RecordOptOut::handle(PhoneForMeta::fromWaId($from), 'stop')`. No responde nada. Cualquier otro mensaje entrante se ignora y no se guarda.
    *   `field = phone_number_quality_update`: escribe `whatsapp.quality_changed` (nivel warning) con `event` y `current_limit`; no pausa (regla 15).
    *   Cualquier otro `field`: se ignora.
73. `app/Modules/Messaging/Actions/ReplayOrphanEvents.php` (Crear) y en `routes/console.php` (Modificar) `messaging:replay-orphans` `->everyMinute()->onOneServer()->withoutOverlapping()`. Reprocesa los huérfanos sin `processed_at` cuyo mensaje ya existe; los de más de `messaging.orphan_minutes` se marcan procesados sin aplicar y escribe `whatsapp.orphans_discarded` con el conteo.
74. Pruebas (Crear): `tests/Unit/Messaging/MetaSignatureTest.php`; `tests/Feature/Messaging/MetaWebhookTest.php` (GET con token correcto e incorrecto; POST con firma válida, ausente, alterada y con el secreto anterior durante una rotación; payload malformado; duplicado; `delivered` antes de `sent`; campo desconocido; `wamid` desconocido queda huérfano y se reprocesa; estado de un mensaje de otra empresa se escribe en esa empresa; `unknown` resuelto por `biz_opaque_callback_data` con `delivered` dispara `WhatsAppMessageAccepted` una sola vez y con `failed` 131026 dispara el evento fallido; `failed` con 131026 dispara el evento con respaldo); `tests/Feature/Messaging/StopTest.php` (CUJ 4: STOP de un teléfono que recibió de A y de B registra las dos bajas; sin filas, baja global; no se manda respuesta; `stop`, `Baja.` y `ALTO!` cuentan; «no gracias» no). `tests/Feature/ScheduleTest.php` (Modificar) con `messaging:replay-orphans`.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/MetaWebhookTest.php tests/Feature/Messaging/StopTest.php tests/Unit/Messaging/MetaSignatureTest.php tests/Feature/Messaging/WebhookRoutesTest.php tests/Feature/ScheduleTest.php`.

#### Commit 9b (sesión Resend): webhook de Resend y seguimiento del correo

Bloqueado hasta verificar en la documentación vigente de Resend, antes de escribir código: (a) los nombres de los eventos (se esperan `email.sent`, `email.delivered`, `email.delivery_delayed`, `email.bounced` y `email.complained`) y cómo distinguir un rebote definitivo; (b) el esquema de firma (se espera Svix: encabezados `svix-id`, `svix-timestamp` y `svix-signature`; contenido firmado `{svix-id}.{svix-timestamp}.{cuerpo}`; clave = base64 de lo que sigue a `whsec_`; firma `v1,<base64>`; varias separadas por espacio); (c) qué valor propio, puesto al enviar por SMTP (producción usa `MAIL_MAILER=smtp`), devuelve el evento: un encabezado propio (`X-Humetria-Delivery`) o una etiqueta. Si (c) no existe, se detiene y se avisa a Victor: según la regla 12, el historial y el indicador del correo salen de la v1, y el resto del plan sigue igual.

75. `app/Modules/Messaging/Support/MailDeliveryStash.php` (Crear, `scoped`). Guarda el id del mensaje de correo entre los dos listeners de una misma notificación.
76. `app/Modules/Messaging/Listeners/OpenMailDelivery.php` (Crear). En `NotificationSending` con `channel = mail`, notificación `TracksDelivery` con `deliveryReference()` no nulo y empresa en curso que no es la demo (`DemoCompanies::isCurrent()`; su correo no sale, `BlockDemoMail`): `CreateDelivery('mail', …, null, null)` y guarda el id en el stash. Cada correo seguido es un mensaje: invitación, recordatorio, reenvío y respaldo.
77. `app/Modules/Messaging/Listeners/StampMailDelivery.php` (Crear). En `Illuminate\Mail\Events\MessageSending`: si el stash tiene id, lo pone en el encabezado o la etiqueta que haya confirmado el paso (c) y vacía el stash.
78. `app/Modules/Messaging/Providers/MailTrackingServiceProvider.php` (Crear) y `bootstrap/providers.php` (Modificar, una línea). Registra los dos listeners. Un proveedor propio evita que las dos sesiones editen `MessagingServiceProvider`.
79. `app/Modules/Messaging/Support/ResendSignature.php` (Crear). `valid(string $id, string $timestamp, string $signatureHeader, string $rawBody, int $now): bool`: rechaza si la marca de tiempo difiere más de `services.resend.webhook_tolerance_seconds`; acepta si alguna firma `v1` coincide con `base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$rawBody}", $key, true))` con `hash_equals`, para cada secreto de `services.resend.webhook_secrets`.
80. `app/Modules/Messaging/Http/Controllers/ResendWebhookController.php` (Modificar, reemplaza el 404). Firma inválida, ausente o vencida → 403; JSON inválido → 400 y `mail.webhook_malformed`; si no, `HandleResendEvents` y 200.
81. `app/Modules/Messaging/Actions/HandleResendEvents.php` (Crear). Deduplica por `svix-id` con `WebhookInbox`. Mapa: `email.sent` → `sent`; `email.delivered` → `delivered`; `email.delivery_delayed` → `delayed`; `email.bounced` definitivo → `bounced`, temporal → `delayed`; `email.complained` → `complained`; otro → se ignora. Busca el mensaje por el valor del paso (c); en el primer evento guarda `data.email_id` como `provider_message_id`; entra a su empresa y escribe con `RecordDeliveryEvent`. Un mensaje desconocido se ignora con 200 (la fila del correo existe antes de enviarlo, así que no hay huérfanos). Un rebote nunca dispara nada por WhatsApp.
82. `docs/operations/resend-webhook.md` (Crear). Alta del endpoint en Resend, los eventos suscritos, el secreto y su rotación (dos secretos a la vez).
83. Pruebas (Crear): `tests/Unit/Messaging/ResendSignatureTest.php` (firma válida, alterada, vencida, secreto anterior); `tests/Feature/Messaging/ResendWebhookTest.php` (firma válida, ausente y alterada; malformado; duplicado; cada tipo de evento; rebote temporal y definitivo; mensaje de otra empresa); `tests/Feature/Messaging/MailDeliveryTrackingTest.php` (una notificación de prueba con `TracksDelivery` crea la fila y el mensaje sale con el valor de correlación; una notificación sin el contrato, con referencia nula o de la empresa demo no crea nada).

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/ResendWebhookTest.php tests/Feature/Messaging/MailDeliveryTrackingTest.php tests/Unit/Messaging/ResendSignatureTest.php tests/Feature/Messaging/WebhookRoutesTest.php`.

#### Commit 10 (sesión WhatsApp): canal en las invitaciones y respaldo a correo

`NoEmail` pasa a `Unreachable` en este commit porque su significado cambia: ya no es «sin correo» sino «sin vía de contacto entre los canales activos de la empresa». Las pruebas listadas como «Modificar» cambian porque Victor cambió el comportamiento esperado (CLAUDE.md, regla 2).

84. `database/migrations/2027_01_27_100400_add_channel_to_invitations.php` (Crear). En `campaign_participants` y `engagement_invitations`: `invited_via` string(10) nulo con CHECK `invited_via IS NULL OR invited_via IN ('mail', 'whatsapp')`, y `token_version` smallint no nulo `default 0`. Valor persistido: quita `campaign_participants_invitation_status_check`, hace `UPDATE campaign_participants SET invitation_status = 'unreachable' WHERE invitation_status = 'no_email'` y lo vuelve a crear con `('queued', 'sent', 'failed', 'unreachable')`; lo mismo con `engagement_invitations_status_known` (`invitation_status IS NULL OR invitation_status IN ('queued', 'sent', 'failed', 'unreachable')`). `down()` hace lo inverso.
85. `app/Modules/Campaigns/Enums/InvitationStatus.php` y `app/Modules/Engagement/Enums/InvitationStatus.php` (Modificar). `case Unreachable = 'unreachable'` en lugar de `NoEmail`; docblock nuevo. Usos que cambian de nombre: `Campaigns/Actions/InviteMissing.php:102`, `Campaigns/Actions/QueueSurveyInvitations.php:41`, `Campaigns/Jobs/SendSurveyInvitations.php:98`, `Campaigns/Support/InstrumentTally.php:55`, `Engagement/Actions/QueueInvitations.php:40`, `Engagement/Jobs/SendInvitations.php:88` y `Engagement/Support/Participation.php:38`. (`no_email` de `Sso/Actions/CompleteSsoSignIn.php` es otra cosa y no cambia.)
86. `app/Modules/Organization/Data/RosterEntry.php` (Modificar) agrega `public ?string $mobile = null` como último parámetro; `app/Modules/Organization/Support/CompanyRoster.php` (Modificar) lo llena con `$employee->mobile` en `entry()`. `tests/Feature/Organization/RosterTest.php` (Modificar) afirma que llega. Así Campaigns y Engagement leen el teléfono por el contrato `Roster` que ya usan.
87. `app/Modules/Campaigns/Models/Participant.php` y `app/Modules/Engagement/Models/Invitation.php` (Modificar). `surveyToken()` agrega `":{$this->token_version}"` al mensaje del HMAC solo cuando `token_version > 0` (los enlaces vigentes no cambian). Método `rotateToken(): string` que incrementa `token_version`, guarda el `token_hash` nuevo y devuelve el token. Agregar `invited_via` y `token_version` a su `#[Fillable]`, sus `@property` (`string|null $invited_via`, `int $token_version`) y el cast `token_version => 'integer'`; sin eso `shouldBeStrict()` rechaza los `update()`.
88. `app/Modules/Campaigns/Notifications/SurveyInvitation.php` (Modificar). Implementa `TracksDelivery`. El constructor agrega un último parámetro opcional `private readonly ?InvitationReference $reference = null`, que devuelve `deliveryReference()`; así `tests/Feature/MailInventoryTest.php` y las demás pruebas que la construyen por posición no cambian. El único lugar de producción que la instancia pasa a ser `app/Modules/Campaigns/Support/InvitationComposer.php` (Crear): `compose(Participant $participant, RosterEntry $person, string $token, SurveyEmail $kind): SurveyInvitation` arma empresa, contacto, centro, último día, minutos, portal y la referencia (tipo `campaign_participant`, fuente `campaign`); lo usan `SendSurveyInvitations` y el respaldo. `routes(RosterEntry $person, bool $reminder = false): array<string, string>` delega en `ChannelPicker::route($person->email, $person->mobile, ! $reminder)`. `channel(RosterEntry $person): ?string` = primera llave de `routes()`. `toWhatsApp()` no hace falta: el canal solo necesita la referencia.
89. `app/Modules/Engagement/Notifications/SurveyInvitation.php` (Modificar). Lo mismo con tipo `engagement_invitation` y fuente `engagement_survey`, un `routes()` estático nuevo y `app/Modules/Engagement/Support/InvitationComposer.php` (Crear): `compose(Invitation $invitation, Survey $survey, RosterEntry $person, string $token, bool $reminder): SurveyInvitation`, que usan `SendInvitations` y el respaldo. `tests/Feature/Engagement/InvitationsTest.php` y `tests/Feature/Engagement/Pulse/PulseDraftTest.php` no cambian.
90. `app/Modules/Campaigns/Support/CampaignInvitationLinks.php` y `app/Modules/Engagement/Support/EngagementInvitationLinks.php` (Crear), etiquetadas con `InvitationLinks::TAG` en `CampaignsServiceProvider` y `EngagementServiceProvider` (Modificar). `whatsApp($id)` devuelve nulo si la invitación ya no espera (sin guías pendientes o contestada, cancelada, campaña o encuesta cerrada o vencida, persona fuera del padrón); si no, el `mobile` del padrón, la plantilla, los dos parámetros (nombre comercial y último día) y `surveyToken()` como sufijo.
91. `app/Modules/Campaigns/Actions/QueueSurveyInvitations.php` (Modificar). El parámetro pasa de `array<string, bool>` a `array<string, ?string>` (participante => canal o nulo). Reserva `WhatsAppQuota::reserve(conteo de whatsapp)` y `MailQuota::reserve(conteo de mail)` antes de marcar nada, dentro de la transacción del llamador (si una no cabe, no se reserva ninguna). Los nulos quedan `Unreachable`. Llamadores que pasan `SurveyInvitation::channel($person)` en lugar de `routes($person) !== []`: `LaunchCampaign.php:61`, `LaunchSeatingSurvey.php:76`, `LaunchTraumaCampaign.php:75`, `DrawReplacements.php:54`, `InviteMissing.php:46` y `ResendInvitations.php:50`. Los que solo preguntan si hay vía (`CampaignPeople.php:103`, `CampaignPages.php:275`, `TraumaCampaignController.php:45`, `InviteMissing.php:71` y `:84`) siguen igual.
92. `app/Modules/Campaigns/Jobs/SendSurveyInvitations.php` (Modificar). Arma la notificación con `InvitationComposer`. Calcula `routes($person, reminder: $kind === SurveyEmail::Reminder)`. Un recordatorio sin ruta (la persona solo tiene WhatsApp) no se manda y la persona sigue `Sent`, porque ya tiene su enlace; lo cuenta en `skipped_reminders` del log. Al mandar escribe `invited_via` con el canal usado. El canal elegido al enviar puede diferir del reservado al lanzar (por ejemplo, por una baja); el envío se mide igual y la reserva sobrante se libera esa noche.
93. `app/Modules/Engagement/Actions/QueueInvitations.php` y `app/Modules/Engagement/Jobs/SendInvitations.php` (Modificar). Lo mismo: decide por `SurveyInvitation::routes()` en lugar de `email`; reserva `WhatsAppQuota` para los de WhatsApp (el correo de Engagement hoy no reserva cupo y sigue igual); recordatorios sin WhatsApp; escribe `invited_via`.
94. `app/Modules/Campaigns/Listeners/FallBackToMail.php` y `app/Modules/Engagement/Listeners/FallBackToMail.php` (Crear), registrados en su proveedor para `WhatsAppMessageFailed`. Listeners en cola con `tries = 3` y `backoff = [60, 300]`. Si `fallsBackToMail()` es falso o el tipo no es el suyo, salen; si no, primero `Companies::enter($event->tenantId)`. Si la invitación ya no espera o `routes($person)` con `mobile` nulo no da correo, escriben `campaign.fallback_unavailable` o `engagement.fallback_unavailable` y salen (el mensaje queda como no entregado). Si no, en una transacción: `rotateToken()`, `invited_via = mail` y `MailQuota::reserve(1)` (si no cabe, se revierte todo y escriben `campaign.fallback_without_quota` o `engagement.fallback_without_quota`); después del commit mandan por correo la notificación de su `InvitationComposer` con el token nuevo (Campaigns con `SurveyEmail::Invitation`, o el tipo de su campaña; Engagement con `reminder: false`). Los dos módulos reservan 1 de correo en el respaldo, como pide la regla 9, aunque los envíos normales de Engagement sigan sin reservar. El token anterior deja de servir desde la rotación. Si el envío falla, la reserva se libera en la liberación nocturna.
95. Texto visible: «sin correo» pasa a «sin contacto» en `resources/js/lib/campaigns.ts:112-116`, `resources/js/lib/engagement/panel.ts:33-37`, `resources/js/components/engagement/unreachable-list.tsx:23,44`, `resources/js/components/campaigns/people-table.tsx:75` («Con contacto» / «Sin contacto»; nunca el canal de la persona), `resources/js/components/campaigns/person-check-row.tsx:29` y `app/Modules/Campaigns/Enums/Standing.php:24`. `CampaignPages::contact()` devuelve además `whatsapp` (cuántos tendrían WhatsApp); `resources/js/types/campaigns.ts` (`DraftContact`) lo agrega y `resources/js/components/campaigns/launch-figures.tsx` muestra «N correos», «M WhatsApp» (solo si M > 0) y «K sin contacto».
96. Pruebas que se modifican por el renombre y el texto: `tests/Feature/Campaigns/SendSurveyInvitationsTest.php:79`, `tests/Feature/Campaigns/SeatingSurveyTest.php:59`, `tests/Feature/Campaigns/CampaignPagesTest.php:242,329,347` (la etiqueta pasa a «Sin contacto»; el nombre de la persona de prueba «Sin correo» no cambia), `tests/Feature/Engagement/LaunchSurveyTest.php:89-90`, `tests/Feature/Engagement/InvitationsTest.php:165`, `tests/Feature/Engagement/SurveyPagesTest.php:236`, `tests/js/lib/campaigns.test.ts:195,205`, `tests/js/lib/engagement/panel.test.ts:154,157`, `tests/js/lib/evaluation.test.ts:142` y `tests/Browser/CampaignsTest.php:196` (`'16 correos|2 sin contacto'`).
97. Pruebas que se crean: `tests/Feature/Campaigns/WhatsAppInvitationsTest.php` (CUJ 2: reserva todo o nada por canal, un solo canal por persona, `invited_via`, regla 4, recordatorio no sale por WhatsApp, con la función apagada todo sigue por correo); `tests/Feature/Engagement/WhatsAppInvitationsTest.php` (lo mismo para clima y pulso); `tests/Feature/Campaigns/FallbackToMailTest.php` y `tests/Feature/Engagement/FallbackToMailTest.php` (CUJ 3: 131026 rota el token, el anterior ya no abre la encuesta, `invited_via = mail`, sale un solo correo; sin correo, sin cupo o con causa sin respaldo no manda nada; el log no lleva teléfono ni mensaje); `tests/Unit/Messaging/TemplateRegistryTest.php` (Modificar) ya compara con las dos `InvitationLinks` reales.

Pruebas acotadas: `php artisan test --compact tests/Feature/Campaigns tests/Feature/Engagement tests/Feature/Organization/RosterTest.php tests/Unit/Messaging/TemplateRegistryTest.php` y `npx vitest run tests/js/lib/campaigns.test.ts tests/js/lib/engagement/panel.test.ts tests/js/lib/evaluation.test.ts`.

#### Commit 11 (sesión WhatsApp): indicadores por canal, contestadas por canal y pruebas de anonimato

Punto de decisión de Victor (ver «Preguntas abiertas»): si las pruebas de anonimato de este commit no se pueden escribir, la comparación de canales sale de la v1 y este commit se reduce a ellas.

98. `app/Modules/Messaging/Contracts/DeliverySummary.php`, `app/Modules/Messaging/Data/ChannelDeliveryTally.php` y `app/Modules/Messaging/Support/ChannelDeliveryTallies.php` (Crear; enlace en `MessagingServiceProvider`). Una consulta agrupada por canal con `tenant_id = CurrentTenant::id()`, `source_type` y `source_id`. Cuenta mensajes (filas), no personas: invitación, recordatorio, reenvío y respaldo suman uno cada uno, y la tarjeta dice «mensajes». Aplica la regla 10 tal como está escrita, con `m = config('results.min_group')`: si el total es menor que `m`, los tres números son nulos; si los no entregados son menos que `m`, `undelivered` y `delivered` son nulos y `undeliveredBelowMinimum` es verdadero (se muestra el total y «menos de 5»); si no, los tres números. Nunca devuelve filas por persona.
99. `app/Support/SmallCells.php` (Crear) y `tests/Unit/SmallCellsTest.php` (Crear). `answeredByChannel(array<string, array{answered: int, unanswered: int}> $cells, int $min): ?list<array{channel: string, answered: int, unanswered: int}>`, ordenado `mail` y luego `whatsapp`. Regla 10: si cualquier celda de cualquier canal es menor que `$min`, devuelve `null` (la tabla se oculta entera); si no, devuelve todas las filas. Casos de la prueba: 4, 5 y 6 en una celda de un canal con el otro canal en 50; una sola celda chica oculta ambos canales; todas las celdas en 5 se muestran; sin canales devuelve lista vacía.
100. `app/Modules/Campaigns/Support/ChannelAnswers.php` (Crear). Una consulta sobre `campaign_participants` de la campaña agrupada por `invited_via` y por «contestó» (al menos una guía `Completed`), pasada por `SmallCells`; `handle(Campaign $campaign): ?list<array{channel: string, answered: int, unanswered: int}>` (nulo cuando la tabla se oculta). `app/Modules/Campaigns/Support/CampaignPages.php` (Modificar): si la campaña no es borrador, prop `reach` = `{channels: list<{channel, total, delivered, undelivered, undeliveredBelowMinimum}>, answers: list<{channel, answered, unanswered}>|null, minGroup: int}`; en borrador, `reach` es `null`.
101. `app/Modules/Engagement/Support/Participation.php` (Modificar). Lo mismo con `engagement_invitations` (`answered`) y fuente `engagement_survey`, en la llave `reach` de lo que devuelve `of()`.
102. `resources/js/types/messaging.ts` (Crear): `export type ReachChannel = 'mail' | 'whatsapp'`; `export type ChannelTally = { channel: ReachChannel; total: number | null; delivered: number | null; undelivered: number | null; undeliveredBelowMinimum: boolean }`; `export type ChannelAnswers = { channel: ReachChannel; answered: number; unanswered: number }`; `export type ChannelReach = { channels: ChannelTally[]; answers: ChannelAnswers[] | null; minGroup: number }`. `resources/js/types/campaigns.ts` y `resources/js/types/engagement-panel.ts` (Modificar: prop `reach`). `resources/js/lib/channel-reach.ts` (Crear): `channelLabel(channel)` («Correo», «WhatsApp»), `tallyText(tally, minGroup)` («Menos de {m} mensajes» con total nulo; «{total} mensajes · menos de {m} sin entregar»; «{total} mensajes · {delivered} entregados · {undelivered} sin entregar») `answersText(row)` («{answered} contestaron · {unanswered} no») y `hiddenAnswersText(minGroup)` («Se muestra cuando cada grupo tiene al menos {m} personas», para cuando `answers` es nulo) y `tests/js/lib/channel-reach.test.ts` (Crear).
103. `resources/js/components/humetria/channel-reach-card.tsx` (Crear, props `{ reach: ChannelReach }`). Tarjeta del design system (`docs/design-system/` y las tarjetas de avance existentes) con un `progress-bar.tsx` por canal, total, entregados, no entregados o «menos de 5», y la tabla de contestadas por canal con los textos de `answersText`. Solo usa `ui`, `hooks` y `lib`. `resources/js/components/campaigns/campaign-follow-up.tsx` y `resources/js/components/engagement/participation-card.tsx` (Modificar) la muestran con su `reach`.
104. `tests/Feature/Campaigns/DeliveryGaugesTest.php` y `tests/Feature/Engagement/DeliveryGaugesTest.php` (Crear). CUJ 6: conteos 4, 5 y 6 en total y en no entregados; un recordatorio cuenta como otro mensaje; un canal apagado no aparece; la empresa A no ve los mensajes de B (consulta y URL de otra empresa da 404); contestadas por canal con 4/5/6 en cada celda (con una sola celda chica de cualquier canal, `answers` es nulo y no se muestra ningún canal; con todas en 5 o más se muestran ambos).
105. `tests/Feature/Messaging/AnonymityTest.php` (Crear). Ninguna columna de `anonymous_responses`, `engagement_responses`, `engagement_comments` ni `engagement_comment_summaries` se llama como `/channel|invited_via|wamid|provider_message|token|phone|mobile|recipient|delivery/`; no hay FK entre esas tablas y `messaging_*` en ningún sentido (`information_schema`); las props de `campaigns/show` y de `engagement/show` (las páginas de RH) no contienen `invited_via`, `recipient_hash`, `provider_message_id` ni `occurred_at`; las páginas de resultados devuelven las mismas props con y sin `?channel=whatsapp`.
106. `tests/Feature/Campaigns/CampaignPagesTest.php` y `tests/Feature/Engagement/SurveyPagesTest.php` (Modificar). Sus `expectsDatabaseQueryCount` de páginas que no son borrador suben en las consultas de los dos agregados nuevos (se esperan 2; el número se mide al escribir el commit). Cambian por los indicadores por canal de «Decisiones tomadas»; Victor lo confirma al revisar (CLAUDE.md, regla 2).

Pruebas acotadas: `php artisan test --compact tests/Feature/Campaigns/DeliveryGaugesTest.php tests/Feature/Engagement/DeliveryGaugesTest.php tests/Feature/Messaging/AnonymityTest.php tests/Feature/Campaigns/CampaignPagesTest.php tests/Feature/Engagement/SurveyPagesTest.php tests/Unit/SmallCellsTest.php` y `npx vitest run tests/js/lib/channel-reach.test.ts`.

#### Commit 12 (sesión WhatsApp): ajuste «Canales de comunicación»

107. `routes/company.php` (Modificar; `routes/tenancy.php` es la consola de la plataforma y no se toca). Dentro del grupo existente: `GET admin/{tenant:slug}/channels` → `CompanyChannelsController@edit`, nombre `tenant.channels.edit`; `PUT admin/{tenant:slug}/channels` → `CompanyChannelsController@update`, nombre `tenant.channels.update`; ambas con `->where('tenant', $notReserved)`.
108. `app/Modules/Tenancy/Http/Controllers/CompanyChannelsController.php` (Crear). `edit`: `Gate::authorize('manage-members')` (solo el dueño, como `PlanController`) e `Inertia::render('tenancy/channels', …)` con props `channels` (`list<{channel, enabled, preference, monthlyCap, consentVersion}>`), `whatsAppAvailable` (`whatsapp.enabled`), `hasPlan`, `consent` (`{version, text}`), `costNotice` (texto con el precio vigente), `whatsAppQuota` (`{used, quota}` del mes). `update`: arma `ChannelSettingsData` y llama a `UpdateCompanyChannels`.
109. `app/Modules/Tenancy/Http/Requests/UpdateCompanyChannelsRequest.php` (Crear). `authorize()`: `Gate::allows('manage-members')`. Reglas: `mail_enabled` y `whatsapp_enabled` `required|boolean`; `whatsapp_enabled` solo puede ser verdadero si `whatsapp.enabled`; `preferred` `required|in:mail,whatsapp` y debe ser un canal activo; `whatsapp_monthly_cap` `nullable|integer|min:1|max:100000`; en `after()`: al menos un canal activo («Deja al menos un canal activo.») y `consent_accepted` aceptado cuando WhatsApp pasa de apagado a activo o la versión aceptada no es la vigente. `lang/es/validation.php` (Modificar, `attributes`): `mail_enabled` «correo», `whatsapp_enabled` «WhatsApp», `preferred` «canal preferido», `whatsapp_monthly_cap` «tope mensual de WhatsApp», `consent_accepted` «aceptación del aviso».
110. `app/Modules/Tenancy/Data/ChannelSettingsData.php` y `app/Modules/Tenancy/Actions/UpdateCompanyChannels.php` (Crear). `handle(string $tenantId, ChannelSettingsData $data, int $userId): void`. En una transacción escribe cada canal (`upsert` por `tenant_id` y `channel`) si ya tiene fila o si lo enviado difiere de su valor por omisión; solo se omite un canal sin fila que queda en su valor por omisión (así un correo apagado se puede volver a encender); al activar WhatsApp guarda `enabled_by`, `enabled_at` y `consent_wording_version`, y escribe `RecordAudit::handle('tenancy.channel_enabled', $row, ['channel' => 'whatsapp', 'wording_version' => …])`; al apagarlo, `tenancy.channel_disabled`. Escribe `tenancy.channels_updated`.
111. `app/Http/Middleware/ForbidWhileImpersonating.php` (Modificar). `tenant.channels.update` en `protectedRoutes()`: aceptar el texto del opt-in es del dueño.
112. `resources/js/pages/tenancy/channels.tsx` (Crear), `resources/js/components/tenancy/company-channels-form.tsx` (Crear, con `setting-row.tsx` y `notice.tsx` del design system; parte de `company-modules.tsx` y de la página «Plan y uso»), `resources/js/types/plan.ts` (Modificar: tipos de la página) y un enlace «Canales de comunicación» en `resources/js/pages/tenancy/plan.tsx` (Modificar). La pantalla muestra el interruptor de cada canal, el canal preferido, el tope mensual, el aviso de costo adicional y el texto del opt-in con su casilla; sin plan, WhatsApp aparece apagado con el aviso «Activa un plan para usar WhatsApp»; con la función apagada en el despliegue, WhatsApp no aparece.
113. Pruebas. Crear `tests/Feature/Tenancy/CompanyChannelsTest.php` (CUJ 1: el dueño activa con el texto aceptado, queda la fila y la bitácora; hr y viewer reciben 403; otra empresa 404; sin aceptar el texto no activa; no se pueden apagar los dos; con la función apagada no se puede activar; volver a encender un correo apagado lo enciende; durante una suplantación responde 404, como todo `ForbidWhileImpersonating`) y `tests/Browser/CompanyChannelsTest.php` (`visitPage()`: componente, sin errores de JS ni de CSP, axe, en claro, oscuro y a 375 px). Modificar `tests/Feature/FormRequestAuthorizationTest.php` (fila `'change the channels' => ['put', 'tenant.channels.update', []]`).

Pruebas acotadas: `php artisan test --compact tests/Feature/Tenancy/CompanyChannelsTest.php tests/Feature/FormRequestAuthorizationTest.php tests/Feature/RouteSecurityTest.php` y, después de `npm run build`, `composer test:browser -- --filter=CompanyChannels`.

#### Commit 13 (sesión WhatsApp): costo visible y conciliación mensual

114. `app/Modules/Billing/Actions/BuildStatement.php` (Modificar). Si la empresa tiene usos `whatsapp` en el mes, agrega `StatementLineData('whatsapp', 'Mensajes de WhatsApp', round(suma de unit_price_micros / 10_000), "{n} mensajes")` antes del IVA. Si `cost_currency` no es la moneda del estado de cuenta, lanza `InvalidStatement`.
115. `app/Modules/Billing/Contracts/PlatformCost.php`, `app/Modules/Billing/Data/MessagingMargin.php` y `app/Modules/Billing/Support/LedgerPlatformCost.php` (Crear; enlace en `BillingServiceProvider`). Suma del mes de `unit_cost_micros` y `unit_price_micros` de `whatsapp`, en centavos.
116. `app/Modules/Tenancy/Support/PlatformOverview.php` (Modificar). Prop `whatsApp` = `{messages, costCents, chargedCents, marginCents}` del mes en curso. `resources/js/pages/tenancy/overview.tsx`, `resources/js/components/tenancy/overview-card.tsx` y `resources/js/types/overview.ts` (Modificar) muestran una tarjeta «WhatsApp del mes».
117. `app/Modules/Messaging/Contracts/DeliveryOutcomes.php` y `app/Modules/Messaging/Support/DeliveryLedger.php` (Crear). `undelivered()` devuelve los ids cuyo estado no es `delivered` ni `read`.
118. `routes/console.php` (Modificar). Comando manual `billing:whatsapp-reconcile {month}` (sin horario) con `app/Modules/Billing/Actions/ReconcileWhatsApp.php` (Crear): por empresa imprime mensajes cobrados, cobrados no entregados (`DeliveryOutcomes`), crédito sugerido en centavos y el costo total de la plataforma del mes para compararlo con la factura de Meta. El crédito lo aplica el operador con el ajuste que ya existe (`AdjustStatement`, en la consola de Billing) con la razón «Crédito WhatsApp {mes}». Lo que Meta cobre de más lo absorbe Humetria y no requiere acción (regla 11).
119. Pruebas: `tests/Feature/Billing/StatementGenerationTest.php` (Modificar: caso nuevo con línea de WhatsApp; los existentes no cambian), `tests/Feature/Tenancy/PlatformOverviewTest.php` (Modificar: caso nuevo), `tests/Feature/Billing/WhatsAppReconciliationTest.php` (Crear).

Pruebas acotadas: `php artisan test --compact tests/Feature/Billing/StatementGenerationTest.php tests/Feature/Tenancy/PlatformOverviewTest.php tests/Feature/Billing/WhatsAppReconciliationTest.php`.

#### Commit 14 (sesión WhatsApp): aviso de privacidad y privacidad de las páginas de RH

120. `resources/js/components/landing/privacy-notice-content.ts` (Modificar). Nombra a Meta (WhatsApp) y a Resend como encargados, qué reciben (teléfono y plantilla; correo y mensaje), la transferencia a Estados Unidos y la baja con STOP. La página es indexable (`App\Http\IndexablePages`), así que el cambio es público.
121. `tests/Feature/PrivacyNoticeMatchesCodeTest.php` (Modificar: caso nuevo). El aviso contiene «WhatsApp», «Meta», «Resend» y «STOP», y `TemplateRegistry` solo tiene plantillas `UTILITY`.
122. `tests/Feature/HrPagesPrivacyTest.php` (Modificar). Agrega filas al dataset para `campaigns/show` y `engagement/show`, y una aserción nueva: ninguna prop trae `invited_via` por persona ni llaves de `messaging_*` (`recipient_hash`, `provider_message_id`, `occurred_at`).

Pruebas acotadas: `php artisan test --compact tests/Feature/PrivacyNoticeMatchesCodeTest.php tests/Feature/PrivacyNoticeTest.php tests/Feature/HrPagesPrivacyTest.php`.

#### Commit 15 (sesión WhatsApp): operación

123. `app/Modules/Messaging/Actions/PurgeMessaging.php` (Crear, `handle(): array{deliveries: int, webhook_events: int}`) y `messaging:purge` en `routes/console.php` (Modificar), `->dailyAt('03:25')` en `app.display_timezone`, `onOneServer()->withoutOverlapping()`. En una transacción con `SET LOCAL messaging.purge = 'on'`: borra `messaging_deliveries` con `created_at` anterior a `messaging.retention_months` (sus eventos caen por la FK) y `messaging_webhook_events` anteriores a `messaging.webhook_event_days`. No toca `messaging_opt_outs` (regla 14). Escribe `messaging.purged` con los conteos.
124. `app/Modules/Messaging/Actions/ForgetRecipient.php` (Crear, `handle(string $e164): int`) y `messaging:forget` en `routes/console.php` (Modificar), sin horario. El comando pide el teléfono con `secret()` (no queda en el historial de la terminal) y lo normaliza con `PhoneNumber::e164()`; la Action calcula `RecipientHash::all()` y borra sus mensajes con el mismo `SET LOCAL`. No borra la baja. Escribe `messaging.recipient_forgotten` con el conteo (derechos ARCO). Las filas de correo no guardan dirección ni hash, así que no ligan a nadie con un correo y quedan fuera de este borrado; se van con la retención.
125. `app/Modules/Messaging/Actions/WatchFailures.php` (Crear, `handle(): bool`, si pausó) y `messaging:watch-failures` `->everyFiveMinutes()->onOneServer()->withoutOverlapping()`. En la ventana `auto_pause.window_minutes`: si hubo al menos `minimum_messages` registros `sent` de WhatsApp y los `failed` con causa `spam_limited` o `ecosystem_blocked` llegan a `failure_rate_percent`, llama `WhatsAppSwitch::pause('failure_rate')`.
126. `messaging:resume` en `routes/console.php` (Modificar), sin horario: cierra la pausa abierta (`ended_at = now()`) y escribe `whatsapp.resumed`. No reenvía nada (regla 9).
127. `tests/Feature/ScheduleTest.php` (Modificar) con `messaging:purge` y `messaging:watch-failures`; `tests/Feature/Messaging/RetentionTest.php`, `tests/Feature/Messaging/ForgetRecipientTest.php` y `tests/Feature/Messaging/AutoPauseTest.php` (Crear: umbral exacto en 49/50 mensajes y en 4.9/5 %, una sola pausa abierta, `messaging:resume`).
128. `docs/operations/whatsapp.md` (Crear). Alta en Meta y secretos en Dokploy; `WHATSAPP_ENABLED`, `WHATSAPP_PAUSED`, la pausa automática y `messaging:resume`; rotación del token de acceso, del secreto de la app (dos a la vez) y de `MESSAGING_RECIPIENT_KEYS` (agregar una versión, nunca quitar una con bajas); qué hacer ante una baja de calidad del número; retención y `messaging:forget`; conciliación mensual; envío de plantillas a Meta; orden de las fases.
129. `docs/operations/observability.md` (Modificar). Señales y alertas: `whatsapp.paused_automatically` y `whatsapp.quality_changed` (alerta inmediata a Victor), `whatsapp.daily_cap_reached`, `whatsapp.webhook_malformed`, `whatsapp.orphans_discarded` y «webhook de Meta sin eventos en 24 horas con mensajes aceptados» (alerta de ausencia).
130. `tests/Unit/OperationsDocsTest.php` (Modificar: caso nuevo). `docs/operations/whatsapp.md` y `observability.md` nombran cada una de esas señales tal como aparecen en `app/Modules/Messaging` y `routes/console.php`.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/RetentionTest.php tests/Feature/Messaging/ForgetRecipientTest.php tests/Feature/Messaging/AutoPauseTest.php tests/Feature/ScheduleTest.php tests/Unit/OperationsDocsTest.php`.

#### Commit 16 (sesión WhatsApp, después de integrar la rama Resend): recorrido del correo que rebota y señales de Resend

131. `tests/Feature/Messaging/MailBounceJourneyTest.php` (Crear). CUJ 5: una invitación sale por correo, Resend avisa un rebote definitivo con firma válida, el mensaje queda `bounced`, el indicador de correo lo cuenta como no entregado, no se crea ningún mensaje de WhatsApp y `ResendInvitations` vuelve a mandar. Debe pasar sin código nuevo; si falla, se corrige el código del commit 9b o del 11.
132. `docs/operations/observability.md` (Modificar) agrega `mail.webhook_malformed`, y el caso de `tests/Unit/OperationsDocsTest.php` del commit 15 (Modificar) lo incluye y lee también los archivos de la rama Resend. Va aquí porque esa señal solo existe después de integrar el commit 9b.

Pruebas acotadas: `php artisan test --compact tests/Feature/Messaging/MailBounceJourneyTest.php tests/Unit/OperationsDocsTest.php`.

#### Fuera de este PR

Recordatorios, enlaces del portal, avisos de asistencia y de vacaciones por WhatsApp: fases posteriores, un módulo por commit, cada una con su `via()` y su prueba.

### Fase 2: herramientas de Meta, un paso a la vez con confirmación de Victor

Requisito: el PR está en verde con `WHATSAPP_ENABLED=false` y las plantillas fueron enviadas a Meta después del commit 4.

*   **(a) Número de prueba de la app** (hasta 5 destinatarios). Sale si: una plantilla de prueba llega al teléfono de Victor; el webhook recibe `sent`, `delivered` y `read` con firma válida; el mensaje queda `read` en el historial; un STOP desde ese teléfono crea la baja; el `wa_id` entrante, leído con `PhoneForMeta::fromWaId()`, da el mismo hash que el `mobile` guardado (confirma el supuesto `+52`/`521`).
*   **(b) Ping del webhook** desde el panel de Meta (campo `messages`) y un mensaje entrante real. Sale si: el GET de verificación responde el `hub_challenge`; el ping responde 200 y no escribe nada; un texto que no es STOP no deja rastro.
*   **(c) Número real +52 81 8687 5767** con las dos plantillas UTILITY aprobadas. Sale si: el primer envío llega solo a Victor; Meta mantiene la categoría UTILITY; la tarifa cobrada coincide con `billing.whatsapp.costs` o se corrige ahí.

### Fase 3: producción

Requisito: las tres salidas de la Fase 2 y los supuestos de la tabla confirmados o corregidos. Los secretos se cargan en Dokploy, después se suscribe el callback del webhook en Meta y al final se publica la app de Meta. `WHATSAPP_ENABLED` se enciende al último. Sale si: un envío real de una empresa de prueba de Humetria aparece en su indicador y en `PlatformOverview`.

### Plan de verificación

*   **Pruebas mientras se trabaja:** solo las de cada commit, con los comandos «Pruebas acotadas» de arriba; `composer test:fast` para lo que el cambio afecta. Nunca la suite completa; la corre el CI (`.github/workflows/tests.yml`).
*   **Navegador:** `npm run build` y luego `composer test:browser -- --filter=…` de las páginas tocadas (commit 10: `CampaignsTest`; commit 12: `CompanyChannelsTest`).
*   **Compilación:** `sail npm run build` para la UI nueva; Wayfinder regenera las rutas con el plugin de Vite.
*   **Análisis estático y estilo:** las reglas vigentes de CLAUDE.md (regla 3): Larastan `level: max` sin supresiones, TypeScript estricto sin `as`, `!` ni `any`; Pint; `vp fmt`, `vp lint`; `npm run check` y `types:check`. Los hooks de Claude los corren al editar.
*   **Cobertura:** la que exige el job `coverage` de `.github/workflows/tests.yml` (90 % en Unit/Feature y en Vitest, según CLAUDE.md, «Definición de terminado»); no se baja el umbral.
*   **Mutación:** no corre en el PR. Corre cada semana en `.github/workflows/mutation.yml` (`--min=95`) para las clases cuyo test declara `mutates(Clase::class)`; aquí lo declaran `RecordDeliveryEventTest`, `ChannelPickerTest`, `MetaSignatureTest`, `ResendSignatureTest` y `WhatsAppMeterTest`.
*   **Integración real con Meta y Resend:** Fase 2, a mano y con Victor.

### Supuestos de diseño del plan (confirmados por Victor el 2026-11-18)

Victor aceptó el 2026-11-18 estos supuestos y los de «Valores por omisión» como valores de arranque, con la corrección del 8 de abajo. El precio, el margen, los topes y los límites numéricos siguen siendo provisionales: se ajustan con su tarifa real de Meta antes de la Fase 3, y los textos del opt-in, el aviso de costo y las plantillas los revisa él antes de la Fase 2.

1.  `Delivery`, `DeliveryEvent` y `OptOut` no usan `BelongsToCurrentTenant` (se leen entre empresas, como `UsageCounter`) y filtran por empresa de forma explícita; `WebhookEvent` y `Pause` son datos de plataforma.
2.  El trigger del historial permite DELETE solo para la empresa demo (como las demás tablas de solo inserción) y con `SET LOCAL messaging.purge = 'on'` (retención y ARCO). Las FK a `tenants` de las tablas de mensajes son `restrictOnDelete`, como el libro de uso.
3.  El respaldo a correo vive en Campaigns y Engagement (listener del evento público `WhatsAppMessageFailed`), no en `Messaging`; la rotación usa una columna nueva `token_version`.
4.  El job lleva solo el id del mensaje; el token y el teléfono se obtienen al enviar por el contrato etiquetado `InvitationLinks`.
5.  El id del mensaje viaja en `biz_opaque_callback_data` para correlacionar estados y resolver `unknown`, porque Meta no permite consultar el estado de un mensaje. A las 24 horas sin estado se registra `failed` con causa `unresolved`, se liquida como usado y no hay respaldo.
6.  `tries=3` se implementa como `maxExceptions = 3` y `retryUntil` de 3 días, para que el limitador y el tope diario no gasten intentos. Al llegar al tope diario, el envío espera al día siguiente.
7.  Los recordatorios no salen por WhatsApp en la v1; un recordatorio a quien solo tiene WhatsApp no se manda y su estado sigue `sent`. Las invitaciones de NOM-035, Guía I por evento traumático y Ley Silla sí salen por WhatsApp.
8.  Una baja de WhatsApp posterior al lanzamiento hace fallar el mensaje con causa `opted_out` y sí dispara el respaldo a correo, porque la baja es del canal WhatsApp y no de la invitación (corregido por indicación de Victor, 2026-11-18).
9.  La pausa automática y el tope global de la plataforma usan la tabla `messaging_pauses`; solo `messaging:resume` las quita.
10. Sin plan, el cupo de WhatsApp es 0. El tope del dueño se aplica como el menor entre el cupo del plan y su tope.
11. `UsageQuota` conserva la rama del correo por asiento y `whatsapp` usa la rama general del catálogo.
12. Precio por mensaje = costo × (1 + margen), guardado en micras por línea; el costo de Meta se registra en MXN; el cobro aparece como línea del estado de cuenta; el crédito de la conciliación lo aplica el operador con `AdjustStatement` a partir del comando que lo sugiere.
13. Los indicadores cuentan mensajes, no personas: un recordatorio, un reenvío o un respaldo suman un mensaje cada uno.
14. El texto «sin correo» pasa a «sin contacto», y la tabla de personas dice «Con contacto» o «Sin contacto», nunca el canal de la persona.
15. `RedactSensitiveData` no agrega `to` ni `from`; el código de `Messaging` nunca registra payloads.
16. Rutas `webhooks/whatsapp` y `webhooks/resend` sin sesión ni cookies (patrón de `routes/workers.php`); 6000 peticiones por minuto por dirección; 256 KiB por cuerpo; firma inválida 403, JSON inválido 400.
17. El ajuste de canales es solo del dueño (`manage-members`) y durante una suplantación su guardado responde 404.
18. `billing:release-mail-holds` pasa a `billing:release-holds` y libera también WhatsApp.
19. Los envíos normales de Engagement siguen sin reservar cupo de correo (como hoy) y sí reservan el de WhatsApp; el respaldo a correo reserva 1 en los dos módulos (regla 9).
20. La correlación de Resend usa un valor propio en el mensaje SMTP, por confirmar en el paso previo del commit 9b.
21. Los topes diario y mensual cuentan filas de `messaging_deliveries` con `accepted_at` (columna nueva), en el día o mes de `app.display_timezone`.
22. Un `unknown` o un mensaje sin id que el webhook resuelve dispara `WhatsAppMessageAccepted` (Billing liquida) o `WhatsAppMessageFailed` (con respaldo si la causa lo permite).
23. `PhoneForMeta::toMeta()` devuelve nulo para un número que no es celular de México; esa persona no recibe WhatsApp.
24. `SurveyInvitation` recibe la referencia como último parámetro opcional, y un `InvitationComposer` por módulo arma la notificación para el envío normal y para el respaldo; las pruebas que la construyen hoy no cambian.
25. `OpenMailDelivery` no sigue el correo de la empresa demo.
26. La prueba de «abrir el enlace no lo consume» corre con sesiones en base de datos y acepta solo la escritura de `sessions`.

--------------------------------------------------------------------------------

<!-- Provenance: Indicates initial structural drafting via EGM. -->

*Drafted with EGM*
