# 01 · Arquitectura del backend (Laravel AI SDK)

Basado en la documentación oficial de `laravel/ai` consultada el 2 nov 2026 (la página del SDK mostraba la versión 1.3.0). Laravel 13 exige PHP 8.3 o superior. Lo que sigue son las piezas; los nombres exactos de clases y métodos se confirman contra `vendor/laravel/ai`.

## Instalación

```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate   # crea agent_conversations y agent_conversation_messages
```

Variables: `ANTHROPIC_API_KEY` (y las de un proveedor de respaldo si se usa failover). Modelo y proveedor se leen de configuración, por ejemplo `HUMI_PROVIDER`, `HUMI_MODEL`, `HUMI_SURVEY_MODEL`.

## Piezas

```
app/Ai/
  Agents/
    HumiAssistant.php        # panel de RH (con memoria y herramientas)
    SurveyHelper.php         # encuesta (sin estado; ver docs/04)
  Tools/
    GetComplianceOverview.php
    GetCampaignProgress.php
    GetRiskResults.php
    ListPendingActions.php
    SearchRegulations.php    # base de conocimiento normativa (global, solo lectura)
  Middleware/
    EnforceTenantBudget.php
    RateLimitByMembership.php
    RedactSensitiveData.php
    AuditAiUsage.php
  Support/
    TenantContext.php        # tenant + membresía + permisos, inmutable, inyectado
    PageContext.php          # entidad que el usuario está viendo, ya validada
```

### HumiAssistant

- Implementa `Agent`, `Conversational`, `HasTools` y `HasMiddleware`; usa `Promptable` y `RemembersConversations`. No define `messages()`.
- Constructor: `TenantContext` y, opcionalmente, `PageContext`. Se crea con `HumiAssistant::make(context: $ctx)`.
- `instructions()` se arma desde una plantilla versionada en `resources/ai/humi.system.md`. Debe cubrir: quién es Humi, voz (tú, sin exclamaciones ni emoji), alcance (todo el cumplimiento laboral del tenant), qué no hace (no ejecuta cambios, no da asesoría legal vinculante, no muestra datos individuales), cómo cita (enlaces de las herramientas) y cómo maneja lo que no sabe (lo dice y propone dónde verlo).
- Los datos de la pantalla actual entran como bloque delimitado y marcado como dato (`<contexto_pantalla>…</contexto_pantalla>`), nunca mezclados con las instrucciones.
- Atributos: `#[MaxSteps]` acotado (por ejemplo 6), `#[MaxTokens]`, `#[Timeout]`; proveedor y modelo desde configuración.

### Herramientas (MVP, todas de solo lectura)

| Herramienta | Devuelve | Notas |
|---|---|---|
| `GetComplianceOverview` | Estado por norma o módulo: al día, en curso, pendiente, con fechas límite | Recorre el registro de módulos; un módulo nuevo se añade ahí |
| `GetCampaignProgress` | Avance y tasa de respuesta por campaña, centro o área | Sin nombres de personas |
| `GetRiskResults` | Resultados agrupados por categoría y dominio, con nivel (nulo, bajo, medio, alto, muy alto) | Aplica `humetria.min_group_size`; bajo el umbral responde "sin suficientes respuestas" |
| `ListPendingActions` | Acciones y planes de acción abiertos, responsables y vencimientos | Incluye enlace a la pantalla |
| `SearchRegulations` | Fragmentos de la normativa con su fuente | Corpus global (no es de ningún tenant); búsqueda por similitud con pgvector si está disponible |

Reglas de toda herramienta:

- Reciben el `TenantContext` por constructor. El esquema que ve el modelo **no incluye** tenant, empresa ni usuario.
- Los argumentos del modelo (id de campaña, centro, periodo) se validan con `$request->validate()` y se resuelven con consultas ya acotadas al tenant. Un id de otro tenant debe comportarse exactamente como un id inexistente.
- Devuelven JSON compacto y estable, con un campo `link` a la pantalla correspondiente y, cuando aplique, `render` (por ejemplo `risk_meter`, `progress`, `table`) para que el front pinte una tarjeta rica en lugar de prosa.
- Máximo de filas y de caracteres por respuesta; truncan con aviso explícito.
- Texto proveniente de datos (nombres, comentarios) se devuelve como dato y se marca como tal.

### Middleware (se ejecuta por paso)

1. `EnforceTenantBudget`: si el tenant agotó su presupuesto mensual o tiene la IA apagada, detiene con `Stop` y un mensaje claro para la UI.
2. `RateLimitByMembership`: límites por minuto y por día, por membresía y por tenant.
3. `RedactSensitiveData`: reemplaza correos, teléfonos, CURP, RFC y NSS por marcadores antes de enviar al proveedor.
4. `AuditAiUsage`: registra tenant, membresía, agente, herramientas usadas, tokens y latencia. No registra el contenido.

Además, `Stop::when(...)` con una bandera en caché por conversación para el botón "Detener".

### Tablas propias (además de las del SDK)

- `ai_usage`: `tenant_id`, `membership_id`, `agent`, `conversation_id`, `input_tokens`, `output_tokens`, `cost_estimate`, `tools` (json), `created_at`.
- `ai_feedback`: `tenant_id`, `membership_id`, `conversation_id`, `message_id`, `rating`, `reason` (opcional, sin texto libre del usuario sobre terceros).
- `tenant_ai_settings`: `tenant_id`, `enabled`, `monthly_token_budget`, `survey_helper_enabled`, `survey_helper_branding`.
- Migración sobre `agent_conversations`: columna `tenant_id` indexada, llenada al crear (ver `docs/02`). Revisa primero el esquema real que crea el SDK.

## Streaming

Ruta `POST /humi/chat` (autenticada, con el middleware de tenant del repo):

1. Valida la entrada: `message` (texto, longitud máxima), `conversationId` opcional, `pageContext` opcional.
2. Resuelve `TenantContext` y la membresía.
3. Si hay `conversationId`, **autoriza** que pertenece a esa membresía y a ese tenant (ver `docs/02`). Si no, `continueOrStart`.
4. Valida `pageContext` contra el tenant (el id de la campaña que dice ver existe y es suyo) y lo convierte en `PageContext`.
5. `HumiAssistant::make(...)->continueOrStart($id, as: $membership)->stream($message)->usingVercelDataProtocol($messageId)->then(fn ($r) => registrarUso($r))`.
6. Cabeceras para proxies: `X-Accel-Buffering: no`, `Cache-Control: no-cache`. Verifica que el servidor (FPM, Octane) y el CDN no acumulen la respuesta.

Rutas auxiliares: `GET /humi/conversations` (paginada, solo de la membresía), `GET /humi/conversations/{id}` (mensajes, autorizada), `DELETE` (borrado lógico), `POST /humi/chat/{id}/stop`, `POST /humi/feedback`.

El cliente envía solo el último mensaje y el `conversationId`. La historia que usa el modelo sale de la base, nunca del navegador.

## Pruebas

`HumiAssistant::fake([...])`, `assertPrompted`, `preventStrayPrompts()`. Las pruebas de herramientas se hacen sin modelo: se instancia la herramienta con un `TenantContext` y se verifica qué devuelve. Lo obligatorio está en `docs/02`.
