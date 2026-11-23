# WhatsApp: validación contra el sandbox de Meta

Resultado de la Fase 2 del canal (`docs/superpowers/specs/2026-11-18-whatsapp-channel.md`), hecha el 2026-11-18 con el
**número de prueba** de la app y un solo destinatario registrado. No se usó el número real, no se publicó la app y no se
mandó nada a validar en la cuenta de negocio real. Todo salió de respuestas reales de Meta; aquí van recortadas, sin
teléfonos, tokens ni ids.

## 1. Qué quedó validado

Leyenda: **Verificado** = Meta se comportó como el código lo espera. **Distinto** = Meta difería y se corrigió.
**No verificable** = el sandbox no lo permite o falta algo.

| # | Punto | Estado | Evidencia |
|---|-------|--------|-----------|
| 1 | Versión, URL y autenticación | Verificado | `POST https://graph.facebook.com/v23.0/{phone_number_id}/messages` con `Authorization: Bearer`: HTTP 200 (y `v25.0` también). La página de Meta ya muestra `v25.0`; `whatsapp.api_version` sigue en `v23.0`. |
| 2 | Envío de una plantilla | Verificado (`hello_world`); invitaciones: pendiente | `hello_world`/`en_US` sin parámetros: `{"messages":[{"id":"wamid.…","message_status":"accepted"}]}`. Las plantillas propias siguen en revisión de Meta (ver §3). |
| 3 | Número mexicano | Verificado | Se manda `52` + 10 dígitos. Meta responde `contacts[0].wa_id` = `521` + 10 dígitos, y lo mismo en `recipient_id` y `from` de los webhooks. `PhoneForMeta::fromWaId()` quita el 1: el hash de un STOP coincidió con el del teléfono guardado (`+52…`). **No hace falta ningún `1` al enviar.** |
| 4 | Webhook y firma | Verificado | El GET de verificación de Meta (`facebookplatform/1.0`) respondió el `hub.challenge`; token malo: 403. Los POST traen `X-Hub-Signature-256` y se aceptaron (200) con el secreto real de la app. |
| 5 | Estados | Verificado | `sent`, `delivered` y `read` llegan en `entry[].changes[].value.statuses[]`: `id`, `status`, `timestamp`, `recipient_id`, `biz_opaque_callback_data`, `pricing`. **`biz_opaque_callback_data` regresa tal como se mandó** en los tres estados (sin eso no se resuelven los `unknown`). Se guardan en `messaging_webhook_events` solo con estado, id de mensaje y `callback_id`: ni teléfono ni payload crudo. `read` solo llega con las confirmaciones de lectura activas en el teléfono. |
| 6 | Entrante | Verificado (texto); botón: pendiente | Un texto «STOP» llega como `messages[0]` con `type: text`, `text.body`, `from` (`521…`); creó una baja con el hash correcto. «hola» no dejó rastro. El botón de respuesta rápida «Dejar de recibir» no se probó: ninguna plantilla propia estaba aprobada. |
| 6b | Alta y respuestas (rama `whatsapp-start-reply`) | Verificado | ALTA/START quita la baja; la confirmación de STOP y la de ALTA salen como texto libre (`type: text`) dentro de la ventana de 24 h, sin plantilla, a `52` + 10 dígitos; Meta las entregó (`sent`, `delivered`, `read`, sin `failed`) y no llevan `biz_opaque_callback_data`. |
| 7 | Errores | Parcial | Se provocó el 131030 (destinatario fuera de la lista del sandbox): HTTP 400, `error.code` entero, `error.message`, `error.error_data.details`. Se lee bien y queda como `unknown_error` (definitivo, sin reintento ni correo); no ocurre fuera del sandbox. Los demás códigos de `WhatsAppErrorMap` (190, 131047, 132001…) no se pudieron provocar sin dañar el token ni escribir a terceros: siguen como supuesto de la documentación. |
| 8 | JSON de plantillas | Distinto en 2 de 6 y 1 no verificable | Ver §3. |
| 9 | Límites | Parcial | El número de prueba responde `throughput.level: STANDARD` y `messaging_limit_tier: TIER_250`, calidad `GREEN`. `whatsapp.max_per_second` (20) queda por debajo del rendimiento estándar. No se midió una ráfaga real. |

## 2. Hallazgos que no estaban en el diseño

- **La app debe estar suscrita al WABA, no solo tener el webhook configurado.** Con la URL y el campo `messages` puestos en la
  app, el WABA de prueba seguía suscrito solo a una app interna de Meta y no llegó ningún POST. `GET /{waba}/subscribed_apps`
  lo muestra; `POST /{waba}/subscribed_apps` suscribe la app. Pasará lo mismo con el WABA real.
- **El `wamid` lleva el teléfono** codificado en base64. Se guarda como `provider_message_id`; no debe ir a logs, a PostHog ni a
  documentos.
- **Campos nuevos que el código ignora:** `user_id` / `recipient_user_id` / `from_user_id` (identificadores `MX.…`) y
  `contacts[0].profile.name` (nombre de perfil). No se guardan ni se deben guardar.
- **`pricing`** viene en cada estado (`billable`, `pricing_model: PMP`, `category`): sirve para conciliar contra la factura.
- Meta manda también `account_update` (`WEBHOOK_CONFIG_UPDATED`) al suscribir; el webhook lo ignora sin error.

## 3. Plantillas en el WABA de prueba

Se crearon solo en el WABA de prueba (las plantillas son de cada WABA: **aprobar aquí no aprueba en producción**, hay que
crearlas de nuevo en el WABA real).

| Plantilla | Resultado |
|-----------|-----------|
| `survey_invite_v1`, `seating_invite_v1` | Aceptadas (JSON válido), UTILITY, en revisión. |
| `engagement_invite_v1` | Aceptada, **reclasificada a MARKETING** mientras se revisa. Riesgo ya previsto en el diseño: si se queda así, no entra en el WhatsApp de solo UTILITY; habría que suavizar el texto («encuesta anónima… cómo le va en su trabajo») o dejarla solo por correo. |
| `survey_reminder_v1`, `engagement_reminder_v1` | **Distinto:** Meta las rechazó («Las variables no pueden estar al principio ni al final de la plantilla»); un punto después de la variable no basta. Se agregó una frase al final (commit `9c1fc137`) y una prueba que impide un cuerpo que empiece o termine con variable. Aceptadas después. |
| `proof_code_v1` (autenticación) | **No verificable:** «Esta cuenta no tiene permiso para crear una plantilla de mensaje». No es el JSON: el WABA de prueba no admite plantillas de autenticación. Probar en el WABA real. |
| Las otras 14 | No se enviaron: su botón de URL no tiene destino (`_pending`; Meta exige que la URL termine en la variable). Las resuelve la sesión del catálogo. |

Meta exige URL `https` en los botones: el export usa `APP_URL` (se probó con la del sitio).

## 4. Cómo repetir la validación

Necesitas: la app de Meta con su número de prueba (Configuración de la API de WhatsApp), un destinatario registrado ahí
(Meta le manda un código), el token temporal (24 h), el id del número de prueba, el del WABA de prueba, el secreto de la app
(Configuración de la app → Básica → «Mostrar») y un token de verificación que inventas. Ponlos en un archivo fuera del
repositorio y nunca en la salida de los comandos.

1. **Solo lectura:** `GET /{phone_number_id}?fields=display_phone_number,quality_rating,messaging_limit_tier,throughput` y
   `GET /{waba}/message_templates?fields=name,status,category,rejected_reason`; confirma que existe `hello_world`.
2. **Servidor y túnel:** un servidor local con las variables `WHATSAPP_*` y `MESSAGING_RECIPIENT_KEYS` en el entorno del
   proceso, y `ngrok http <puerto>`. Prueba tú el GET: `/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=…&hub.challenge=1`.
3. **Webhook:** en la app, «WhatsApp → Configuración → Webhook»: la URL del túnel, tu token y el campo `messages`. Después
   `POST /{waba}/subscribed_apps` (sin cuerpo) y comprueba con el `GET` que tu app aparece.
4. **Envío:** `POST /{phone_number_id}/messages` con `{"messaging_product":"whatsapp","to":"52…","type":"template","biz_opaque_callback_data":"x","template":{"name":"hello_world","language":{"code":"en_US"}}}`.
5. **Estados:** mira en `localhost:4040` (inspector de ngrok) los POST; comprueba `sent`, `delivered`, `read` (con las
   confirmaciones de lectura activas) y el `biz_opaque_callback_data`. Revisa `messaging_webhook_events` sin teléfonos.
6. **Entrantes:** desde tu teléfono manda «STOP», «ALTA» y «hola» al número de prueba; revisa `messaging_opt_outs`.
7. **Plantillas:** `sail artisan messaging:export-templates` con `APP_URL` en `https`, y `POST /{waba}/message_templates` con
   cada JSON (sin los campos `_pending`).
   El token temporal del sandbox caduca en unas dos horas (error 190, «Session has expired»): hay que generar otro y cambiarlo.
8. Al terminar: genera un token nuevo para invalidar el anterior y elimina el túnel.

## 5. Lo que falta para producción

- Estado al cierre (2026-11-18, unas seis horas después): las cinco plantillas del WABA de prueba seguían en revisión, sin rechazo; un sandbox sin negocio verificado puede no recibir revisión. Cerrada la validación sin ellas.
- Aprobación de las plantillas en el WABA real (y el resultado de `engagement_invite_v1`: ¿UTILITY o MARKETING?).
- Probar el botón de respuesta rápida «Dejar de recibir» (tipo `button` del mensaje entrante) con una plantilla aprobada.
- Probar `proof_code_v1` y las 14 plantillas con destino pendiente cuando existan sus rutas.
- Suscribir la app al WABA real, además de configurar el webhook; secretos en Dokploy; `WHATSAPP_ENABLED` al último.
- Revisar el nivel de mensajería y el rendimiento del número real, y medir `whatsapp.max_per_second` bajo carga controlada.
- Los demás códigos de error de Meta de `WhatsAppErrorMap` solo se verán con tráfico real.
- La verificación del negocio, que Victor tiene en proceso, y el costo real por mensaje frente a `billing.whatsapp`.
