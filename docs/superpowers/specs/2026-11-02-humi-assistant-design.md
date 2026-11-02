# Humi, el asistente de IA de Humetría: diseño

Fecha: 2026-11-02. Origen: paquete `humi-handoff` (prompt, 4 documentos, maquetas y componentes). Este documento lo
adapta a las reglas del repo; donde el handoff y el repo difieren, **manda el repo** (se anota en «Divergencias»).
Los documentos del handoff (`docs/design-system/humi/0*.md`), las maquetas (`docs/design-system/humi/maquetas/`) y los SVG del personaje (`docs/design-system/humi/svg/`) viven en el repo; las maquetas mandan sobre el texto de la UI.

## Objetivo

Un asistente conversacional dentro del panel de RH que responde, en lenguaje claro y por streaming, sobre el
cumplimiento **de la empresa en la que se está**: campañas y su avance, resultados agrupados, pendientes y plazos, y
explica la normativa (NOM-035, NOM-037; Ley Silla cuando llegue a `main`). Propone siguientes pasos y enlaza a las
pantallas donde se ejecutan. **No ejecuta cambios** ni da asesoría legal vinculante.

Está aislado por empresa de punta a punta: es un requisito de seguridad. El anonimato de las respuestas de Guía II/III
y de la encuesta de Ley Silla es un invariante: Humi solo ve agregados que ya pasaron por el mínimo de respondentes.

## Decisiones tomadas (aprobadas 2026-11-02)

1. Módulo nuevo `app/Modules/Assistant` (identificadores en inglés). «Humi» es el nombre de producto en la UI.
2. Participante de la conversación: modelo propio `ConversationParticipant` (tenant + usuario), con morph map. La
   conversación pertenece a ese participante, no al usuario: un consultor en dos empresas tiene dos historias.
3. Ayuda en la encuesta del trabajador (Fase 4 del handoff): **fuera de este trabajo**. Necesita decisiones externas
   (white label, texto de apoyo clínico, revisión de un especialista) y toca la ruta anónima. Queda su propio trabajo.
4. Presupuesto: no hay planes en el repo. Un tope mensual de tokens por defecto en `config/ai.php`, con interruptor y
   tope propio por empresa que edita el superadmin en la consola de empresas.
5. Panel acoplado desde 1280 px de ancho; hoja flotante (`shadow-float`) por debajo y en móvil.
6. Sin Humi mientras se impersona. Una empresa suspendida queda bloqueada (`BlockSuspendedTenant`).
7. Humi usa el mínimo existente `results.min_group` (piso 5), no una config nueva.
8. Sin Humi para el rol `committee` (queda limitado a Telework por `LimitCommitteeToTelework`).

## Divergencias del handoff

| Handoff | Aquí |
|---|---|
| `app/Ai/{Agents,Tools,Middleware}` | `app/Modules/Assistant/{Agents,Tools,Middleware,…}`; solo `Contracts/Events/Data` son públicos |
| `TenantContext` propio | Ya existe `Tenancy\Support\TenantContext` (`CurrentTenant`). El de Humi es el DTO `Data\AssistantScope` |
| `humetria.min_group_size`, `ResultsGate` | `results.min_group` y el mínimo que ya impone `Results` (`RiskDistribution`) |
| Participante = membresía | `TenantMembership` es interno de `Tenancy`: no se importa. Modelo propio (decisión 2) |
| Rutas `/humi/*` | `admin/{tenant:slug}/assistant/*`, nombres `tenant.assistant.*`, tras `ResolveTenant` |
| `RedactSensitiveData` (IA) | Nombre distinto (`RedactPersonalData`): `RedactSensitiveData` ya es el de los logs |
| Leer modelos de otros módulos | Solo por contratos públicos (nuevos de solo lectura donde falten) |
| `Humi.tsx` tal cual | Se parte en `components/humetria/humi*.tsx` con tokens (hex crudos y límites de tamaño lo rechazan) |
| Mockup: «turno nocturno» | No existe dimensión de turno en resultados (anónimos, sin llaves). Solo centro, área y pregunta |
| Mockup: «Plan de acción» | No hay módulo de planes. `ListPendingActions` cubre lo que existe hoy |

## Arquitectura

```
app/Modules/Assistant/
  Contracts/   ComplianceReader (una norma o módulo), RegulationCorpus
  Data/        AssistantScope, PageContext, ToolCard (render + link), ComplianceStatus
  Events/      (ninguno público en v1)
  Agents/      HumiAssistant
  Tools/       GetComplianceOverview, GetCampaignProgress, GetRiskResults, ListPendingActions, SearchRegulations
  Middleware/  EnforceTenantBudget, RateLimitByMember, RedactPersonalData, AuditAiUsage
  Actions/     StartOrContinueConversation, RecordUsage, StopGeneration, RateMessage, DeleteConversation, SetTenantAiSettings
  Models/      ConversationParticipant, TenantAiSettings, AiUsage, AiFeedback
  Support/     PageContextResolver, ToolRegistry, ComplianceReaders (una por norma), RegulationIndex
  Http/        Controllers (Chat, Conversations, Feedback, Page, TenantSettings), Requests
  Providers/   AssistantServiceProvider
resources/ai/humi.system.md           plantilla versionada del sistema
resources/ai/regulations/*.md         corpus normativo global, de solo lectura
routes/assistant.php                  incluido desde routes/company.php (+ la ruta del superadmin en routes/console de empresas)
```

**Lo que Humi lee de otros módulos** (siempre el contrato, nunca el modelo):

| Necesidad | Contrato | Estado |
|---|---|---|
| Campañas, invitados y respondentes por guía | `Campaigns\Contracts\CampaignRecords` (`find`, `current`, `cycles`) | existe |
| Fechas de la próxima evaluación NOM-035 | `Campaigns\Contracts\EvaluationSchedule` | existe |
| Nombres de centros | `Tenancy\Contracts\WorkCenters` | existe |
| Resultados por dominio, categoría y área, con mínimo | `Results\Contracts\RiskResults` | **nuevo** (envuelve `RiskDistribution`, que es interno) |
| Pendientes y estado de Telework (sin nombres ni salud) | `Telework\Contracts\TeleworkStanding` | **nuevo** |
| Quitar un miembro borra sus conversaciones | `Tenancy\Events\MemberRemoved` | **nuevo** (hoy `RemoveMember` no emite evento) |

**Registro de herramientas.** `ToolRegistry` lista las clases de herramienta; `ComplianceReaders` lista un lector por
norma. Un módulo nuevo (violencia laboral, Ley Silla) se suma creando su lector o herramienta y registrándola ahí.

## Seguridad y multitenancy

- El `AssistantScope` (empresa, participante, permisos) se arma en el servidor desde `ResolveTenant` y `CurrentTenant`,
  es inmutable y entra por constructor al agente y a cada herramienta. Ningún esquema de herramienta tiene
  tenant, empresa ni usuario; los ids que pasa el modelo (campaña, centro) se resuelven por los contratos, que ya
  acotan a la empresa: un id ajeno es **idéntico** a uno inexistente.
- Toda tabla propia lleva `tenant_id`, `BelongsToCurrentTenant`, llaves foráneas y `unique`. Las tablas del SDK
  (`agent_conversations`, `agent_conversation_messages`) ganan `tenant_id` por migración nuestra; el listado, el detalle,
  el borrado y la continuación se resuelven por participante **y** empresa, con 404 (no 403) ante lo ajeno.
  El SDK no verifica propiedad: la verificación vive en `StartOrContinueConversation`, un solo punto.
- El cliente manda `message` y `conversationId`. Se ignora todo lo demás (`messages`, roles). La historia sale de la base.
- Lo que Humi no ve **nunca**: Guía I (identificada: canalización, nombres con valoración clínica), respuestas
  individuales, comentarios abiertos, salud de nadie. Lo que ve cada miembro se limita a lo que su rol puede ver
  (Gate de Spatie); `viewer` no recibe lo reservado a `view-trauma-screenings`.
- Texto que viene de datos (nombres de centro, títulos) entra como dato delimitado (`<dato>…</dato>`) y las
  instrucciones del sistema avisan que no contiene órdenes. El contexto de pantalla va en `<contexto_pantalla>`.
- Herramientas de solo lectura. Consultar resultados escribe `RecordAudit` (`assistant.results_viewed`), sin contenido.
- Logs: eventos `assistant.message_sent`, `assistant.message_stopped`, `assistant.limit_reached`,
  `assistant.conversation_deleted`, solo con ids y conteos. **Nunca** el texto de la conversación. Las llaves nuevas
  (`message`, `prompt`) se agregan a `RedactSensitiveData` con su prueba.
- Al proveedor sale: el texto del miembro (ya redactado de correo, teléfono, CURP, RFC y NSS) y agregados de las
  herramientas. Nunca respuestas individuales. README del módulo lo documenta; el contrato del proveedor sin
  entrenamiento lo valida el usuario fuera del código.

## Cuotas y costo

`EnforceTenantBudget`: IA apagada o presupuesto mensual agotado → `Stop` con mensaje claro, **sin llamar al
proveedor**. `RateLimitByMember`: por minuto y por día, por participante y por empresa. `AiUsage` registra tokens,
herramientas y latencia (sin contenido) al terminar el stream (`then`), también cuando se detiene o falla.

## Streaming

`POST admin/{tenant:slug}/assistant/chat` responde con el protocolo de datos de Vercel, cabeceras
`X-Accel-Buffering: no` y `Cache-Control: no-cache`. Otras rutas: `GET assistant` (página completa, historial
paginado), `GET assistant/conversations`, `GET assistant/conversations/{id}`, `DELETE` (borrado lógico),
`POST assistant/chat/{id}/stop`, `POST assistant/feedback`. Detener usa una bandera en caché por conversación leída
por `Stop::when`. Se verifica en el SDK instalado que cada nombre exista antes de usarlo.

## UI

Según `docs/03` del handoff y las maquetas (`docs/design-system/humi/`). Páginas en `pages/assistant`, componentes en
`components/assistant` (`humi-launcher`, `humi-panel`, `humi-thread`, `humi-message`, `humi-tool-step`,
`humi-composer`, `humi-suggestions`, `humi-result-card`), la pieza base en `components/humetria/humi.tsx` y el hook del
cliente de chat en `hooks/`. Tarjetas ricas con `level-bar`, `risk-level` y `progress-bar`. Un componente por archivo,
≤ 100 líneas por función. Panel acoplado (≥ 1280 px, solo borde) u hoja flotante (`shadow-float`). Atajo `Ctrl/Cmd+J`.
Voz: tuteo, sin exclamaciones ni emoji. Revisión en claro, oscuro y 375 px contra las maquetas.

Cliente de streaming: se decide en la tarea 1 tras leer el paquete instalado. Por defecto `@ai-sdk/react` (con `ai`),
como pide el handoff; la alternativa sin dependencias es un lector del protocolo en `resources/js/lib/assistant/`.
**Cualquier paquete nuevo se aprueba antes de instalarlo.**

## Fuera de alcance

Ayuda en la encuesta; acciones que cambian datos; avisos proactivos por reglas (el punto del lanzador queda
apagado); búsqueda por similitud con pgvector (v1: búsqueda por texto sobre el corpus); llaves por empresa del
proveedor; Ley Silla (entra por registro cuando esté en `main`).

## Riesgos y cosas por verificar

- Nombres de clases y atributos del SDK (`laravel/ai` v1.3.0, PHP ^8.3, Laravel 12|13): se leen en `vendor/` al instalar.
- Forma real de las tablas del SDK y su encaje con `TenantScopedModelsTest`.
- Que `LimitCommitteeToTelework` bloquee las rutas de Humi para `committee`.
- Que el servidor de Sail y el proxy no acumulen la respuesta (prueba manual detrás del proxy).
- Retención y entrenamiento del proveedor: lo valida el usuario.
