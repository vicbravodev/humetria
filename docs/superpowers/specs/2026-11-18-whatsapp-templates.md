# Plantillas de WhatsApp: catálogo completo para revisión

*   **Autor:** Victor Jesus Bravo de la Peña
*   **Estado:** Borrador para iterar, versión 4. De uso interno: nada se manda a Meta hasta que validen por completo la identidad del negocio y se termine el flujo completo de WhatsApp (decisión de Victor, 2026-11-18)
*   **Fecha:** 2026-11-18
*   **Relación:** complementa `2026-11-18-whatsapp-channel.md` (el canal y su plan). Aquí solo viven los textos y la forma de cada plantilla.

## Cómo leer este documento

Cada plantilla se define una vez y de ahí salen dos cosas: el mensaje que Humetria manda y lo que se manda a Meta para su aprobación (con ejemplos). Por eso el texto de aquí es el texto definitivo: lo que apruebes se escribe tal cual en el código y en la solicitud a Meta. **Nada se manda a Meta por ahora**: este catálogo es de uso interno (textos, diseño y clases en código) y se enviará a validar cuando la identidad del negocio esté validada por completo y el flujo de WhatsApp esté terminado; en ese momento Victor lo confirma plantilla por plantilla.

Todas tratan de «usted» con un tono cálido y de México, igual que los correos y la encuesta que ya existen (decisión de Victor, 2026-11-18: lo que recibe la persona trabajadora tiene una sola voz; solo el panel tutea). Son categoría UTILITY, idioma `es_MX`, salvo una que es de autenticación. Los nombres llevan versión (`_v1`): una plantilla aprobada no se edita, se crea la `_v2`.

## Decisiones tomadas en esta revisión

*   Saludo: «Hola, buen día. Le escribimos de parte de {{empresa}}.» (aprobado).
*   Se queda el botón «Aviso de privacidad» en las invitaciones.
*   Texto de la baja: «Dejar de recibir».
*   Clima y pulso se define como utility y, cuando llegue el momento de enviarla a Meta, se manda así. Si Meta la reclasifica como marketing, se acepta.
*   Nada de esto se manda a validar a Meta todavía: es de uso interno hasta que se valide la identidad del negocio y se termine el flujo completo de WhatsApp.
*   El catálogo incluye todas las notificaciones que hoy reciben las personas trabajadoras por correo, no solo las invitaciones: no se omite nada.

## Lo que Meta permite en una plantilla (documentación consultada el 2026-11-18)

Fuente: la documentación vigente de Meta (ver «Fuentes»). Lo que no pude leer se marca.

| Componente | Reglas de Meta | Uso |
| --- | --- | --- |
| Encabezado de texto | Opcional, 1; hasta 60 caracteres; 1 variable. | Solo en las invitaciones. |
| Encabezado multimedia (imagen, video, PDF) | Opcional; el archivo se sube antes por la API de carga. | Un PDF solo en el acuse de políticas (ver su fila). |
| Cuerpo | Obligatorio; hasta 1024 caracteres; varias variables, con nombre o por posición. | Todas. |
| Pie | Opcional; hasta 60 caracteres. | `Humetria` en todas. |
| Botón URL | Hasta 2 por plantilla; texto hasta 25; **una sola variable, al final de la URL**. | El principal de casi todas. |
| Botón de respuesta rápida | Hasta 10; texto hasta 25; van juntos y después de los demás tipos. | «Dejar de recibir» en las invitaciones y avisos. |
| Botón de teléfono | 1; texto hasta 25. | «Llamar a RH» en vacaciones. |
| Copiar código | 1. | Solo el código de verificación. |
| Flows, carrusel, oferta por tiempo limitado, producto | Carrusel, oferta y producto son de marketing; no pude leer Flows. | No se usan (ver abajo). |

Reglas que importan para redactar:

*   **Variables con nombre** (`{{empresa}}`), cada una con un ejemplo al crear la plantilla. Los proveedores advierten que el cuerpo no debe empezar ni terminar con una variable ni tener dos juntas; no lo encontré en la página oficial, así que lo respetamos.
*   **Botones:** una plantilla con respuesta rápida y otro tipo de botón no se ve en WhatsApp de escritorio, solo en el celular. No queda claro si el texto de un botón acepta acentos («Dejar de recibir» no los tiene, pero «Aviso de privacidad» tampoco): se confirma con la primera plantilla enviada.
*   **Aprobación:** automática, hasta 24 horas. Solo se envía una plantilla `APPROVED`.
*   **Calidad:** Meta pausa una plantilla (3 horas, luego 6) con calidad baja, y a la tercera la desactiva. «Dejar de recibir» reduce bloqueos.
*   **Autenticación:** el código de verificación solo puede mandarse con una plantilla de autenticación de la biblioteca de Meta (texto fijo, botón de copiar código, sin enlaces, medios ni emojis, parámetros de hasta 15 caracteres).
*   **Utility:** debe ser específica de algo que la persona tiene en curso y sin intención promocional. Meta dice que una encuesta genérica no es utility y reclasifica sola las plantillas que lo parecen. Clima y pulso es la más expuesta a eso.

## Qué no usaremos, y por qué

*   **WhatsApp Flows y respuestas dentro de WhatsApp:** cada respuesta llegaría con el número de quien contestó y rompería el anonimato de las encuestas. La encuesta se contesta en la web.
*   **Imagen o video en el encabezado:** el nombre y el logo ya se ven en el perfil del número, y los medios aumentan el riesgo de que Meta lo lea como marketing.
*   **Carrusel, producto, oferta, llamada de voz:** no aplican.

## Criterios de redacción

1.  **Español de México, «usted» y cálido:** «Hola, buen día. Le escribimos de parte de…», «tiene», «celular», «contestar», «unos minutos».
2.  **Neutral en la pantalla bloqueada.** El cuerpo se ve en la vista previa del celular: ninguna plantilla dice de qué trata algo sensible. Por eso los avisos de vacaciones, de apoyo y de accidente no dicen el resultado ni el motivo; todo se ve dentro del portal, con la sesión de la persona.
3.  **Específico**, porque lo exige Meta para utility: cada texto nombra lo concreto (la evaluación, el trámite) y a la empresa.
4.  **No prometer lo que no es cierto.** Solo clima y pulso y la de seguimiento de quejas dicen «anónima»; NOM-035 dice «confidencial» porque incluye la Guía I, que es identificada.
5.  **Una acción** por mensaje, con su botón.
6.  **Una salida clara:** «Dejar de recibir» en cada plantilla que no sea un código o un aviso de seguridad.
7.  **Parámetros permitidos:** nombre comercial de la empresa, una fecha en palabras, minutos de vigencia y el sufijo de un enlace. Ni el nombre de la persona ni su centro de trabajo ni el motivo de nada.

## Catálogo

Variables y sus ejemplos para Meta: `empresa` = «Industrias Ejemplo»; `fecha_limite` = «viernes 20 de noviembre»; `fecha` = «lunes 16 de noviembre»; `minutos` = «15». En la columna «Botones», «URL» lleva el sufijo variable al final; «QR» es de respuesta rápida.

### A. Invitaciones a encuestas

#### 1. `survey_invite_v1`: NOM-035 y Guía I por evento traumático

| Componente | Contenido |
| --- | --- |
| Encabezado | `Su evaluación de la NOM-035` |
| Cuerpo | `Hola, buen día. Le escribimos de parte de {{empresa}}. Como parte de la NOM-035, tiene pendiente contestar una evaluación sobre su trabajo. Es confidencial y puede hacerlo hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.` |
| Pie | `Humetria` |
| Botones | URL `Contestar ahora` → `https://{dominio}/survey/{{token}}`; URL `Aviso de privacidad` (fija); QR `Dejar de recibir` |

#### 2. `seating_invite_v1`: Ley Silla

| Componente | Contenido |
| --- | --- |
| Encabezado | `Encuesta sobre su puesto de trabajo` |
| Cuerpo | `Hola, buen día. Le escribimos de parte de {{empresa}}. Como parte de la Ley Silla, queremos saber cómo son los asientos y las pausas en su trabajo. Son unos minutos y puede contestar hasta el {{fecha_limite}}. Su enlace es personal y solo sirve una vez.` |
| Pie | `Humetria` |
| Botones | URL `Contestar ahora` → `https://{dominio}/survey/{{token}}`; URL `Aviso de privacidad` (fija); QR `Dejar de recibir` |

#### 3. `engagement_invite_v1`: clima y pulso (se manda como utility; puede reclasificarse)

| Componente | Contenido |
| --- | --- |
| Encabezado | `Encuesta anónima de clima` (fijo, sin variable: la empresa va solo en el cuerpo) |
| Cuerpo | `Hola, buen día. Le escribimos de parte de {{empresa}}. Queremos saber cómo le va en su trabajo con esta encuesta anónima: nadie verá sus respuestas individuales. Tiene hasta el {{fecha_limite}} para contestarla y su enlace es personal.` |
| Pie | `Humetria` |
| Botones | URL `Contestar ahora` → `https://{dominio}/engagement/survey/{{token}}`; URL `Aviso de privacidad` (fija); QR `Dejar de recibir` |

#### 4. `survey_reminder_v1`: recordatorio de cualquier evaluación o encuesta pendiente

| Componente | Contenido |
| --- | --- |
| Cuerpo | `Hola, buen día. Le recordamos que su evaluación de {{empresa}} sigue pendiente. Puede contestarla hasta el {{fecha_limite}}. Su enlace sigue siendo personal.` (Meta rechaza un cuerpo que termina en variable: confirmado con el WABA de prueba.) |
| Pie | `Humetria` |
| Botones | URL `Continuar` → `https://{dominio}/survey/{{token}}`; QR `Dejar de recibir` |

*   Una variante para clima y pulso (`engagement_reminder_v1`) con su propio cuerpo, `Hola, buen día. Le recordamos que su encuesta anónima de {{empresa}} sigue pendiente. Puede contestarla hasta el {{fecha_limite}}. Su enlace sigue siendo personal.`, y la ruta `engagement/survey`.

### B. Avisos al portal de la persona trabajadora

Todas llevan el pie `Humetria` y un botón URL que abre el portal; la persona entra ahí con su propia sesión (los avisos nunca llevan una credencial, salvo los dos enlaces de acceso).

| Nombre | Origen (correo que ya existe) | Cuerpo | Botones |
| --- | --- | --- | --- |
| `leave_decision_v1` | `Leave\RequestDecided`: respuesta a su solicitud | `Hola, buen día. {{empresa}} ya respondió su solicitud del {{fecha}}. Puede verla en su portal.` | URL `Ver respuesta`; teléfono `Llamar a RH`; QR `Dejar de recibir` |
| `leave_to_decide_v1` | `Leave\RequestToDecide`: a quien debe responder | `Hola, buen día. Tiene una solicitud de su equipo en {{empresa}} que le toca responder. Puede hacerlo en su portal.` | URL `Responder`; QR `Dejar de recibir` |
| `policy_to_confirm_v1` | `Disclosure\PolicyToConfirm`: política que requiere acuse | `Hola, buen día. {{empresa}} publicó su política de prevención y necesita que confirme que la leyó. Puede leerla y dar su acuse en su página.` | Encabezado PDF opcional con la política; URL `Leer y confirmar`; QR `Dejar de recibir` |
| `engagement_feedback_v1` | `Engagement\FeedbackPublished`: lo que hará la empresa con la encuesta | `Hola, buen día. {{empresa}} publicó lo que hará a partir de la encuesta de clima. Puede leerlo en su página.` | URL `Leer`; QR `Dejar de recibir` |
| `seating_risks_v1` | `Seating\RisksToConfirm`: resultado de su evaluación de puesto | `Hola, buen día. {{empresa}} ya evaluó su puesto de trabajo. Puede leer el resultado y confirmar que se lo explicaron en su página.` | URL `Ver resultado`; QR `Dejar de recibir` |
| `seating_questions_v1` | `Seating\StandingQuestions`: dos preguntas sobre su trabajo de pie | `Hola, buen día. {{empresa}} quiere hacerle dos preguntas sobre su trabajo de pie. Contestar es voluntario y toma un minuto.` | URL `Contestar`; QR `Dejar de recibir` |
| `telework_invite_v1` | `Telework\PortalInvitation`: lista de verificación de teletrabajo | `Hola, buen día. {{empresa}} le pide completar su lista de verificación de teletrabajo. Puede hacerlo en su portal.` | URL `Completar lista`; QR `Dejar de recibir` |
| `telework_reminder_v1` | `Telework\PortalReminder`: todo lo que espera en su portal | `Hola, buen día. Tiene pendientes en su portal de teletrabajo de {{empresa}}. Puede revisarlos cuando guste.` | URL `Ver pendientes`; QR `Dejar de recibir` |
| `telework_answered_v1` | `Telework\NoticeAnswered`: la empresa respondió su aviso de accidente | `Hola, buen día. {{empresa}} respondió el aviso que usted envió. El detalle solo se ve en su portal.` | URL `Ver respuesta` (con su enlace personal) |
| `support_waiting_v1` | `Results\SupportWaiting`: mensaje confidencial en su portal | `Hola, buen día. {{empresa}} le dejó un aviso en su portal personal. Solo usted puede verlo.` | URL `Ver aviso` |
| `attendance_correction_v1` | Módulo de asistencia: respuesta a una corrección de asistencia | `Hola, buen día. {{empresa}} respondió su solicitud de corrección de asistencia. Puede verla en su portal.` | URL `Ver respuesta`; QR `Dejar de recibir` |

*   Asistencia no manda ningún correo hoy; esta es la única plantilla que corresponde a algo que el módulo ya tiene (las correcciones). Si quieres otros avisos de asistencia (por ejemplo, una salida sin registrar), son un producto nuevo y se definen cuando se decida.

### C. Acceso y seguridad de la persona trabajadora

| Nombre | Categoría | Origen | Cuerpo | Botones |
| --- | --- | --- | --- | --- |
| `worker_invite_v1` | UTILITY | `WorkerAccess\WorkerInvitation`: invitación a su portal | `Hola, buen día. {{empresa}} le invita a crear su acceso personal al portal. El enlace es personal y solo sirve una vez.` | URL `Crear mi acceso` → enlace con el token |
| `access_link_v1` | UTILITY | `WorkerAccess\AccessLinkRequested`: el enlace que pidió | `Hola, buen día. Este es el enlace que pidió para entrar a su portal de {{empresa}}. Solo sirve una vez y vence en {{minutos}} minutos.` | URL `Entrar al portal` → enlace con el token |
| `key_registered_v1` | UTILITY (aviso de seguridad) | `WorkerAccess\KeyRegistered`: se registró una llave | `Hola, buen día. Se registró una llave de acceso nueva en su portal de {{empresa}}. Si no fue usted, avísenos.` | URL `No fui yo` → dirección firmada que la revoca |
| `proof_code_v1` | AUTENTICACIÓN | `WorkerAccess\ProofCode`: código para confirmar que es usted | Texto fijo de la biblioteca de autenticación de Meta, con el código | Copiar código |

*   `proof_code_v1` no usa nuestro texto: Meta solo permite mandar códigos con sus plantillas de autenticación, con texto fijo en español y un botón de copiar código, sin enlaces. No lleva «Dejar de recibir».
*   Los tres primeros llevan credencial o dirección firmada, así que la revisión de seguridad del plan (un enlace al portador por un canal de terceros y los números reciclados) aplica antes de activarlos, aunque la plantilla quede aprobada.

### D. Lo que no se hace plantilla, y por qué

*   **Los avisos al panel** (resúmenes diarios, el cierre de una evaluación, vacaciones escaladas a RH, accidentes, cambios de centro, salida de módulos): llegan a quienes usan el panel con una cuenta (dueño, RH, Comisión), no a personas trabajadoras por su celular, y tutean. El canal de WhatsApp envía al `Employee.mobile`, así que no aplican.
*   **El seguimiento de quejas** (`Complaints\NewsOnYourCase`): la persona deja un correo, no un celular, y el canal de denuncias se diseñó para no guardar nada que la identifique. No se hace plantilla.
*   **Los correos de facturación y de demos** son para clientes y prospectos, no para el personal evaluado.

## Notas técnicas para quien lo implemente

*   **La variable de una URL va al final.** Los enlaces del portal con la sección en medio (`/me/{slug}/telework`) no caben: hace falta una ruta que termine en la variable (por ejemplo `/me/{slug}` o una ruta corta que redirija). Lo resuelve la sesión que implemente cada plantilla.
*   **Variables con nombre** en el gateway y en la solicitud a Meta, en lugar de posiciones (hoy el código usa posiciones y empieza con una variable).
*   **`TemplateDefinition`** describe categoría, encabezado, cuerpo, pie y botones, y de ahí salen el mensaje y la solicitud a Meta. Las plantillas de autenticación se crean desde la biblioteca de Meta, no con texto propio.
*   **El webhook** entiende el botón «Dejar de recibir» como una baja igual que STOP.
*   **Fases del plan:** definir y registrar todas las plantillas es lo que se hace ahora. Conectar cada correo del catálogo al envío por WhatsApp sigue las fases del plan (un módulo por commit) y no cambia con este documento.

## Preguntas para Victor

1.  **Cuándo y cómo se mandan a Meta (para más adelante, no ahora).** Son veinte plantillas. Cuando llegue el momento, ¿las mandamos todas juntas o primero las invitaciones y el recordatorio y luego el resto? No hace falta decidirlo hoy.
2.  Dijiste que las fases posteriores van en este trabajo también. ¿Entiendo bien que significa tener definidas y registradas todas las plantillas ahora, y que conectar cada correo al envío por WhatsApp sigue las fases del plan? Si quieres conectarlos todos ahora, se amplía bastante el alcance.
3.  Los avisos de asistencia no existen hoy. ¿Dejamos solo la corrección, o quieres definir más (por ejemplo, una salida sin registrar)?

## Fuentes

*   Meta: [Template fundamentals](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview)
*   Meta: [Template components](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/components)
*   Meta: [Utility templates](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/utility-templates/utility-templates)
*   Meta: [Template categorization](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-categorization)
*   Meta: [Template library](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-library)
*   Meta: [Template pausing](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/template-pausing)
