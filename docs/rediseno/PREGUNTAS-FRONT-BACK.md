# Preguntas de front y back

Fase 0 hecha el 4 nov 2026 contra el código de `main` (`2e1bb189`). Decisiones tomadas por Claude el 4 nov 2026 a pedido de Victor, en la columna "Decisión"
(Sí, No o Después).

**Un PR por módulo** (decisión del 4 nov). La columna "PR" dice en cuál cae cada cosa:

| PR | Módulo | Fases del prompt |
|---|---|---|
| 1 | Fundaciones, menú por rol, panel, "¿te aplica?" y primera vez | 1 y 2 |
| 2 | NOM-037 | 3 |
| 3 | Ley Silla | 4 |
| 4 | NOM-035 (canalización, difusión, compartir, carteles de la página) | 5 |
| 5 | Quejas y denuncias | 6 |
| 6 | Compensación y sindicato | 7 |
| 7 | Roles, consultor, correos y estados | 8 |

La fase 9 (calidad) no es un PR: cada PR se cierra con su recorrido en claro, oscuro y 375 px (`CLAUDE.md`).

**Lo que ya está:** los tokens, `decisiones.md` y `design-system.md` del paquete son idénticos a `docs/design-system/`;
las sombras `shadow-raised` y `shadow-float` ya existen en `resources/css/app.css`. El sidebar ya cambia por rol
(`lib/app-navigation.ts`, `lib/tool-navigation.ts`): el board 40 es reordenarlo, no crearlo.

## A. El diseño pide algo que el back no tiene (o no completo)

| # | Board | Hoy en el código | Qué falta | Tamaño | PR | Recomendación | Decisión |
|---|---|---|---|---|---|---|---|
| A1 | 8 Panel | `Tenancy/Support/DashboardPage` solo trae NOM-035 (campaña activa, siguientes pasos, ciclo por centro). Lo único que cruza módulos son `Assistant/Support/Readers/Nom035Reader` y `Nom037Reader` | Un contrato por módulo que dé urgencias y etapa (1–6) por centro; el panel los junta. Ley Silla y Quejas no tienen lector | M | 1 | Sí: es el centro del rediseño || Sí |
| A2 | 1, 14, 18 | Ley Silla tiene `seating_profiles` (declaración por centro). NOM-037 deriva "No aplica" si nadie teletrabaja. NOM-035 no declara nada. No hay constancia | Declaración "¿te aplica?" por centro y norma, con fecha y quién; constancia imprimible | M | 1 | Sí, constancia como página para imprimir (ver D6) || Sí, constancia imprimible (sin PDF) |
| A3 | 3, 7, 13, 19, 31 | `Disclosure/Support/DocumentTemplates` llena la política 5.1 y los bloques 5.7 con textos base que RH edita, con versión. Los documentos de NOM-037 (`TeleworkDocument`, 8 tipos) son archivos subidos | Asistente de preguntas que arma el texto, para política NOM-037 (5.2), los 6 documentos de apoyo y los contenidos 5.7. Todo con `TODO-LEGAL` | L | 2 y 4 | Sí, pero el texto legal lo valida un abogado antes de producción || Sí, con `TODO-LEGAL` |
| A4 | 10, 22, 23 | No hay consultor. Un usuario puede tener varias empresas (membresías, `TenantSwitcher`, `tenancy/companies`). Logo por empresa, solo desde la consola. Alta de empresas solo del superadmin | Cuenta de consultor con sus clientes, su marca (logo) y alta de clientes; barra "Estás dentro de X como Y"; bitácora a su nombre | L | 7 | Después: es una línea de producto nueva, no un rediseño || Después (boards 10, 22, 23 fuera) |
| A5 | 11 | La Comisión es un usuario con rol `committee` y `CommitteeAssignment` por centro | Entrar por enlace personal sin contraseña que vence a los 7 días | M | 2 | No por ahora (ver D4): se queda con cuenta y se rediseña su cola || No: Comisión con cuenta; el board 11 se adapta |
| A6 | 20, 40 | `TenantRole`: Owner, Hr, Viewer ("Consulta"), Committee, Complaints. Permisos de Spatie por rol, no por centro | Rol "Ayuda con las rutas" y permisos por centro | M | 7 | "Ayuda con las rutas" = `hr` sin canalización ni compensación: sí. Por centro: después || Sí a las etiquetas (owner = Administra todo, hr = Ayuda con las rutas, viewer = Solo consulta). Cambiar permisos de `hr` y por centro: después, lo decide Victor |
| A7 | 39 | `Results/Models/ScreeningReferral`: estado (canalizada/atendida), fecha e institución en texto libre | Destino (IMSS, servicio médico, apoyo externo, otra, "No quiso atenderse"), modo (en persona o aviso en el portal), nota sin detalle clínico, recordatorio a 30 días | M | 4 | Sí || Sí |
| A8 | 26, 45 | `ComplaintHandler.user_id` exige cuenta con rol `complaints`; cada quien ve solo sus casos (`ComplaintAssignment`) | Persona externa sin cuenta, con enlace personal | M | 5 | No sin un segundo factor (ver D4) || Después (solo personas con cuenta) |
| A9 | 29, 43 | Ya existe: `reference` + `access_key_hash`, seguimiento y mensajes de la persona (`routes/complaints-public.php`, `SendReporterMessage`). La clave la genera el sistema (20 caracteres) | Que la persona elija su clave | S | 5 | No: la generada es más segura (ver D3). Solo UI || No: clave generada; solo UI |
| A10 | 27, 41 | `ComplaintsDigest` sin contenido, pero **cada hora** (`routes/console.php:66`). `reporter_email` opcional recibe `NewsOnYourCase` al acusar, escribir y cerrar; nada al enviar | Resumen diario; confirmación al enviar la queja confidencial | S | 5 | Sí a la confirmación. ¿Diario o cada hora? (D5) || Sí a la confirmación; resumen cada hora (D5) |
| A11 | 32 | `RecordManualAcknowledgments` registra firmas en papel por persona, con día y nota. `DisseminationRecord` sin archivo | Subir el archivo de las firmas como evidencia | S | 4 | Sí || Sí |
| A12 | 35 | El costo se calcula en el servidor al guardar (`Compensation/Support/ReportBuilder` + `Calculation/*`). No hay vista previa | Costo en vivo mientras se mueven los valores | M | 6 | Endpoint de vista previa que reusa `ReportBuilder`: un solo motor, sin duplicar fórmulas en TS || Sí, endpoint de vista previa |
| A13 | 36, 37 | Ya hay desglose por concepto, costo por mes, por categoría (`byGrade`), proyección (3–5 años), costo por punto (`Sensitivity`) y retroactivo total. El aumento es por grupo (`group_changes`) | Aumento con monto fijo; costo por mes de retroactivo; aumento mensual por categoría en el reporte | M | 6 | Sí; es menos de lo que el paquete suponía (era L) || Sí |
| A14 | 44 | Ya existe: `Benefit` con periodicidad, `applies_to_group`, `job_grade_ids`, antigüedad mínima, tipo (incluye monto fijo) y `TenureTable` | Solo UI | S | 6 | Sí || Sí |
| A15 | 21, 41 | El layout de correo muestra el logo de la empresa (`resources/views/vendor/mail/html/message.blade.php`) | Un solo botón y el sello "hecho con humetría" en todos; logo del consultor | M | 7 | Sí, sin consultor hasta A4 || Sí, sin consultor |
| A16 | 42 | Carteles en HTML para imprimir con QR (`Complaints/Support/PosterPages`, `Disclosure/Support/PosterPages`). `shortUrl` es la URL sin `https://` | Tamaño carta; enlace corto real | S | 4 y 5 | Carta con CSS de impresión, sin PDF. Enlace corto: después || Sí, carta con CSS; enlace corto después |
| A17 | 15, 16 | `WorkerNotice` (3 tipos) se revisa una vez con nota (`ReviewNotice`). Hay expediente por centro en NOM-037, Ley Silla y difusión | Aviso de accidente: qué se hizo → avisar a la persona → cerrar con evidencia, recordatorio a 3 días. Vista "Si hoy llegara una inspección" calculada por centro | M | 2 | Sí || Sí |

## B. El back tiene algo que el diseño no muestra

| # | Qué es | Dónde vive | Propuesta | Decisión |
|---|---|---|---|---|
| B1 | Invitaciones por WhatsApp o SMS | No existen: solo correo (`Campaigns/Notifications/SurveyInvitation.php`) | Nada que diseñar todavía || Nada |
| B2 | Muestra de la Guía III | `Campaigns/Enums/Population`, `Participant.in_sample`, `DrawSample`, `DrawReplacements` | NOM-035 es el módulo maduro y su pantalla ya existe; se queda || Se queda |
| B3 | Conversaciones de Humi | `Assistant/Http/Controllers/ConversationController` devuelve JSON; no hay página | Fuera de este rediseño (Humi va en su artifact). Ver C1 || Fuera |
| B4 | Doble factor, passkeys y verificación de correo | `config/fortify.php`, `User` | Se quedan (19 cuenta). Solo el layout nuevo || Se queda |
| B5 | Detalle de revisión de teletrabajo y acta de la Comisión | `pages/telework/review.tsx` (`ReviewDetail`); el acta es un archivo subido (`CommitteeController::act`) | La revisión se queda (`N037-Revision`); el acta entra en la etapa 3 de la ruta como zona de arrastre || Sí, como se propone |
| B6 | Solicitudes de demo | Consola | Se queda || Se queda |
| B7 | Tipo de empresa `union` (sindicato) y roles `committee` y `complaints` con menú propio | `Tenancy`, `LimitRestrictedRoles` | El board 40 ya los cubre; confirmar que el menú del sindicato es el del board 34 || Sí |

## C. Errores de las capturas actuales

| # | Error | Confirmado | Dónde se arregla | PR |
|---|---|---|---|---|
| C1 | `asistente-conversaciones` muestra JSON crudo | Sí, pero no es una página: `tenant.assistant.conversations.index/show` es la API que usa el widget de Humi (`lib/assistant/api.ts`), y la captura la abrió directo | No es una página; no se arregla en este rediseño | — |
| C2 | `elegir-empresa` muestra la lista de empresas | No es error: con 0 o más de 1 empresa, `/admin` muestra `pages/tenancy/companies.tsx` para elegir | Darle el diseño nuevo | 1 |
| C3 | 127.0.0.1:8010 en cartel, página de las personas y canal | Sale de `APP_URL` local; en producción da el dominio | Nada en código; enlace corto en A16 | — |
| C4 | Campo de archivo nativo | Sí, en 14 componentes: `organization/import-file-field`, `telework/review-attach-form`, `photo-picker`, `training-fields`, `register-committee-form`, `document-file-field`, `tenancy/tenant-logo-dialog`, `compensation/csv-import-card`, `seating/regulation-step-form`, `agreement-form`, `document-form`, `measure-follow-up-fields`, `seat-status-fields`, `interventions/progress-dialog` | Una zona de arrastre en `components/humetria` y se cambia en cada módulo | 1 (componente) y cada PR |
| C5 | "menos de 5" se rompe en tarjetas angostas | Sin verificar en el código | Container queries | 5 |
| C6 | Avisos fiscales repetidos en cada reporte | Sin verificar en el código | Un solo aviso de exactitud (board 36) | 6 |
| C7 | "Nadie coincide" sin haber buscado | Sí, en 4 de 5 listas: `disclosure/ack-list.tsx:67`, `telework/ack-people-list.tsx:61`, `campaigns/people-table.tsx:95`, `campaigns/people-checklist.tsx:95`. Solo `compensation/overrides-empty.tsx` lo hace bien | Distinguir lista vacía de búsqueda sin resultados, como `overrides-empty` | 2 y 4 |

## D. Del diseño, a decidir antes de construir

| # | Board | Qué | Propuesta | Decisión |
|---|---|---|---|---|
| D1 | 35 | La empresa ve "Sindicato · 7 %" entre sus escenarios, pero el sindicato es otra cuenta con sus propios datos | Mostrarlo solo si el sindicato compartió su propuesta por enlace; si no, quitarlo || Quitarlo |
| D2 | 28 | El caso anónimo muestra la hora exacta ("21:14"). Con centro y turno puede delatar a quien la presentó | Solo la fecha en casos anónimos || Solo la fecha |
| D3 | 29, 43 | Clave elegida por la persona: más débil que la generada, y hoy se guarda con sha256 | Dejar la generada. Si se elige, con `Hash::make` y mínimo de longitud || Clave generada |
| D4 | 11, 26, 45 | Enlaces sin contraseña para la Comisión y para la persona externa de quejas, que vería contenido confidencial | Comisión con cuenta; persona externa con código por correo en cada entrada || Comisión con cuenta; externa después |
| D5 | 27 | Resumen de quejas diario en el diseño, cada hora en el código. La hora es a propósito: agrupa para no delatar la hora de cada queja (`ComplaintsDigest`), y deja acusar a tiempo | Dejar cada hora y ajustar el texto del board; diario también protege, pero retrasa el acuse hasta un día || Cada hora |
| D6 | 2, 42 | Constancia y carteles en PDF: no hay librería de PDF instalada | Páginas para imprimir con CSS de impresión, como hoy. Una librería de PDF necesita tu aprobación || Imprimible, sin PDF |
| D7 | — | `CLAUDE.md` dice que el estándar visual es `docs/design-system/pantallas/` (29 pantallas) | Agregar `docs/rediseno/` como estándar de las pantallas que cubre, en el PR 1 || Sí, en el PR 1 |
