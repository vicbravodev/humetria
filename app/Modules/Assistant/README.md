# Humi, el asistente de IA de Humetría

Módulo `Assistant`. Un asistente conversacional en el panel de RH (`/admin/{empresa}`) que responde, por streaming, sobre el
cumplimiento **de la empresa en la que está la persona**: campañas y avance, resultados por nivel de riesgo, pendientes y
plazos, y explica la normativa. Solo lee y explica: no cambia datos. Diseño: `docs/superpowers/specs/2026-11-02-humi-assistant-design.md`.

## Cómo está hecho

| Pieza                             | Dónde                                                                                                                                                                                                |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Agente (SDK `laravel/ai`)         | `Agents/HumiAssistant.php` + `resources/ai/humi.system.md` (instrucciones versionadas)                                                                                                               |
| Herramientas (solo lectura)       | `Tools/`: `GetComplianceOverview`, `GetCampaignProgress`, `GetRiskResults`, `ListPendingActions`, `SearchRegulations`                                                                                |
| Quién es la persona en la empresa | `Data/AssistantScope` (empresa, participante, permisos), armado en el servidor por `Support/AssistantScopeFactory`                                                                                   |
| Conversaciones                    | `Support/Conversations` decide de quién es una conversación; el SDK **no** verifica propiedad                                                                                                        |
| Límites antes del proveedor       | `Actions/CheckAllowance` (apagada por empresa, presupuesto mensual, frecuencia por persona y por empresa)                                                                                            |
| Cuota del plan (libro de Billing) | `Support/HumiTokens` reserva 9,000 tokens (`humi.reservation_tokens`) por el contrato `UsageMeter` antes de llamar, liquida con el conteo real del SDK y libera si falla; sin cuota, 403 sin llamada |
| Datos personales                  | `Support/PersonalDataRedactor` (correo, CURP, RFC, NSS, teléfono) antes de enviar y de guardar                                                                                                       |
| Rutas                             | `routes/assistant.php` bajo `admin/{empresa}/assistant/*` (nombre `tenant.assistant.*`); consola del superadmin en `routes/assistant_console.php`                                                    |
| Front                             | `resources/js/components/assistant/*`, `hooks/use-humi-chat.ts`, `lib/assistant/*`, `pages/assistant/index.tsx`                                                                                      |

Humi lee otros módulos **solo por contratos públicos**: `Campaigns\Contracts\{CampaignRecords,EvaluationSchedule}`,
`Results\Contracts\RiskResults`, `Telework\Contracts\TeleworkStanding`, `Tenancy\Contracts\{Companies,Members,WorkCenters}`.

## Reglas que no se rompen

- **Empresa primero.** El `AssistantScope` viene del servidor y entra por constructor a cada herramienta; ningún esquema de
  herramienta tiene empresa, tenant ni persona (`ToolRegistryTest` lo recorre). Un id de otra empresa se comporta exactamente
  como uno que no existe. `ScopeGuard` impide que una herramienta lea para una empresa distinta a la de la petición.
- **La conversación es de una persona en una empresa** (`ConversationParticipant`, único por empresa y usuario). El mismo
  usuario en dos empresas tiene dos historias. Lo ajeno, lo de otra empresa y lo inexistente dan el mismo 404.
- **Anonimato.** Los resultados de riesgo pasan por `Results` (`RiskResults` → `RiskDistribution`): personas por nivel,
  nunca promedios, solo con la evaluación cerrada (C-27) y con el mínimo por grupo `results.min_group` aplicado después de
  filtrar (C-23). Bajo el mínimo no sale ni cuántas respondieron. Humi nunca ve la Guía I ni respuestas individuales.
- **Historia autoritativa en el servidor.** El navegador manda el último mensaje, la conversación que continúa y la página
  abierta; todo lo demás (`messages`, roles) se ignora. La página se valida contra la empresa.
- **Texto que viene de datos no es una orden.** Nombres de centros y áreas salen en una línea, sin marcas, y las
  instrucciones dicen que nunca se obedecen; la pantalla abierta va en su propio bloque `<contexto_pantalla>`.
- **Sin contenido en logs.** El log lleva ids y cifras (`assistant.message_sent`, `assistant.limit_reached`…); las llaves
  `prompt`, `user_message` y `assistant_message` se redactan. El uso (`ai_usage`) guarda tokens, herramientas y tiempo, no texto.
- **Bitácora.** Ver resultados por Humi (`assistant.results_viewed`) y cambiar su configuración
  (`assistant.settings_changed`) quedan en `audit_entries`, sin contenido.

## Qué sale al proveedor y qué se guarda

- **Sale:** el texto que escribe la persona (ya con correo, teléfono, CURP, RFC y NSS enmascarados), las instrucciones, el
  nombre de la página abierta y lo que devuelven las herramientas (agregados por nivel, nombres de centros y áreas, avance de
  campañas, estado de NOM-037 por centro, extractos de los criterios). **Nunca** respuestas individuales ni la Guía I.
- **Se guarda:** las conversaciones (tablas del SDK, de la persona; ella puede borrarlas), el uso por mensaje y el pulgar
  (arriba o abajo y un motivo de lista cerrada, sin texto libre).
- **Lo valida el usuario, no el código:** que el contrato con el proveedor excluya entrenar con estos datos y su retención;
  el aviso de privacidad y la base de tratamiento de datos sensibles (riesgo psicosocial) con quien lleve lo legal.

## Variables de entorno

`HUMI_PROVIDER` (por defecto `anthropic`), `HUMI_MODEL`, `ANTHROPIC_API_KEY`, `HUMI_MONTHLY_TOKEN_BUDGET`,
`HUMI_RATE_PER_MINUTE`, `HUMI_RATE_PER_DAY`, `HUMI_TENANT_RATE_PER_MINUTE`. Se leen en `config/humi.php` y `config/ai.php`;
el proveedor y el modelo se piden al agente (`provider()` y `model()`), nunca están en el código.

## Cómo apagar la IA

- **De una empresa:** el superadmin, en la consola de empresas (`…` de la fila → «Asistente Humi»): interruptor y tope de
  tokens al mes (vacío: el de la plataforma). Apagada o sin presupuesto, Humi responde con su mensaje y **no llama al proveedor**.
- **De todas:** sin `ANTHROPIC_API_KEY` el proveedor falla y el panel muestra un mensaje de error calmado; o quitar el
  permiso `use-assistant` a los roles (migración `add_use_assistant_permission`).

## Cómo sumar una herramienta o una norma

1. **Una herramienta nueva** (p. ej. violencia laboral): crea `Tools/Xyz.php` (`implements Laravel\Ai\Contracts\Tool`, recibe
   `AssistantScope` y `ScopeGuard` por constructor, llama `$this->guard->check($this->scope)` y lee **solo por contratos**) y
   agrégala a la lista de `ToolRegistry` en `AssistantServiceProvider`. Si el módulo no tiene contrato de lectura, créalo en
   su módulo (como `RiskResults`). Su prueba: otra empresa = id inexistente, esquema sin empresa ni persona, mínimo por grupo.
2. **Una norma en el resumen de cumplimiento:** un `ComplianceReader` en `Support/Readers/` y su alta en `ComplianceReaders`
   (mismo provider). `GetComplianceOverview` no cambia.
3. **Una norma en la búsqueda normativa:** su `criterios.md` (con encabezados `### C-NN. Título · Fuente`) en
   `config/humi.php` → `regulations`.

## Pruebas

`tests/Feature/Assistant/*` (aislamiento, herramientas, límites, uso, chat, conversaciones, consola), `tests/Unit/PersonalDataRedactorTest.php`,
`tests/js/lib/assistant/*` (lógica del cliente) y `tests/Browser/AssistantTest.php` (panel, estados, página, consola).
Ninguna llama a un proveedor real (`HumiAssistant::fake()` y `Http::preventStrayRequests()`).

**Evaluación adversarial** (con el modelo real, solo en staging; no corre en producción ni en CI):

```bash
php artisan humi:evaluate {id-de-la-empresa} --as=persona@empresa.mx --other={id-de-otra-empresa}
```

Intenta leer otra empresa, hacerse pasar por el administrador global, esconder órdenes en el nombre de un centro, pedir lo que
contestó una persona, ver su propio prompt, salirse de alcance (médico y legal), mandar datos personales y cambiarle las
reglas. Termina con error si algún intento pasa; no guarda conversaciones ni uso. Guarda el resultado en el PR.

## Pendiente o fuera de alcance

- **Ayuda en la encuesta del trabajador:** aparte (necesita decisiones externas y toca la ruta anónima; ver el spec).
- **Aviso proactivo** (punto en el lanzador), **búsqueda por similitud** (pgvector) y **tablas** en el markdown: no están.
- **Verificar a mano:** el streaming token a token detrás de un proxy real (el servidor de las pruebas de navegador entrega
  un cuerpo en streaming vacío; el stream se prueba contra el servidor en `ChatTest` y el panel dibuja un stream de muestra).
