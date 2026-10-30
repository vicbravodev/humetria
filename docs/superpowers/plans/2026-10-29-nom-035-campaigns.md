# NOM-035 instrument, campaigns and employee survey — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A company runs a NOM-035 evaluation of a work center end to end: RH creates and launches a campaign, each
person answers from their phone with a one-time link, and the answers are stored. Guía I is identified; Guías II/III
are anonymous. Every answer is scored exactly as the DOF says.

**Architecture:** Three modules are added or extended.
- `Instruments`: the NOM-035 definitions and scoring, in code, with no database.
- `Campaigns`: generic campaigns, participants and links, plus the public survey page.
- `Responses`: anonymous answers and Guía I screenings, insert-only.

Modules talk only through `Contracts`, `Events` and `Data`. `Organization` gains a roster contract and employee
events; `Tenancy` gains a way to enter a company by id and a `manage-campaigns` permission.

**Tech Stack:** Laravel 13, PHP 8.5, Postgres, Pest 4 (+ Browser), Larastan max, Inertia v3 + React + TypeScript,
Wayfinder, Tailwind v4, Vitest (`vp test`).

**Spec:** `docs/superpowers/specs/2026-10-29-nom-035-campaigns-design.md`.
**Prevailing criteria:** `docs/normas/nom-035/criterios.md` (C-01…C-26).
**Literal norm text:** `docs/normas/nom-035/texto-de-referencia.md`.

## Global Constraints

- TDD: test first, watch it fail for the right reason, then minimal code. Never weaken, skip or delete a test.
- Larastan `level: max` with no suppressions. TypeScript strictest, with no `as`, `!`, `any` or `@ts-ignore`.
- No new dependencies.
- Identifiers, tables, routes and enums in English; UI text in es-MX. The panel says *tú*; the survey says *usted*.
- Concrete classes are `final`. Actions have `handle()` and no HTTP. Controllers are thin (authorize → action →
  `Inertia::render`). Other modules are used only through `Contracts` / `Events` / `Data`.
- Every business table has `tenant_id` and the `BelongsToCurrentTenant` scope. Another company's resource by URL → 404.
- Dates and times are `timestampTz`, stored in UTC and shown in `config('app.display_timezone')`.
- Logs use fixed `module.action` past-tense names, with ids in the context. Never log answers, scores, emails or names.
- Sensitive actions also go to `RecordAudit`: `campaign.launched`, `campaign.closed`.
- Anonymous answers (`anonymous_responses`) have no column, key or index toward an employee, participant or token,
  and no timestamps. Response ids are UUID v4.
- Every new route has a name and requires auth, except the two survey routes, which go in `RouteSecurityTest`'s
  public list with their reason.
- Every new Inertia page has a `visitPage()` browser test.
- Frontend limits: 300 lines per file, 100 per function, complexity 10, kebab-case, one component per file, `@/`
  imports.
- Instrument text is exactly the text of `texto-de-referencia.md` (C-21).
- Mutation: tests of logic declare `mutates(...)`. Mutation is not run locally unless the user asks.
- Commits are atomic, one behavior each, and each leaves the suite green. Commits go in the user's name with no
  Claude attribution. Never push without asking.

## Review Focus

1. **Boundary scores.** A score exactly at a cut-off (Guía II Cfinal = 20, 45, 70, 90) must get the higher level
   (C-13). This is pinned in Task 4 with a dataset generated from every range row.
2. **Two submissions of the same guide at the same time.** Only one may be stored. Pinned in Task 11 with a
   lock-then-recheck test.
3. **A person moved, deactivated or added mid-campaign.** Their link and their pending guides must follow C-24.
   Pinned in Task 13.
4. **Responses that could be linked to a person through timing or logs.** No `created_at`, and no response id in
   any log. Pinned in Task 11 with schema and log tests.
5. **A sample that no longer matches the roster at launch.** The launch must refuse (C-10). Pinned in Task 9.

## File structure

```
app/Modules/Instruments/
  Contracts/Instruments.php            definition(), validate(), scoreRisk(), screenTrauma()
  Contracts/Nom035Sample.php           size(), allocate()
  Data/InstrumentDefinition.php  SectionDefinition.php  ItemDefinition.php  GateDefinition.php
  Data/RiskLevel.php (enum)  GroupScore.php  RiskScore.php  TraumaOutcome.php
  Exceptions/InvalidAnswers.php        (internal; the contract documents it, callers catch \InvalidArgumentException)
  Support/Nom035/GuideOne.php  GuideTwo.php  GuideThree.php   definitions + Tables 2/3/5/6 + ranges
  Support/Nom035/Likert.php            option values and 0–4 / 4–0 scale
  Support/AnswerValidator.php  RiskScorer.php  TraumaScreener.php  InstrumentCatalog.php  Nom035SampleRule.php
  Providers/InstrumentsServiceProvider.php (binds the new contracts)
app/Modules/Organization/
  Contracts/Roster.php  Data/RosterEntry.php
  Events/EmployeeDeactivated.php  EmployeeReactivated.php  EmployeeMoved.php
  Support/CompanyRoster.php
app/Modules/Tenancy/
  Contracts/Companies.php  Data/CompanySummary.php  Support/CompanyDirectory.php
  (ArchiveWorkCenter asks Campaigns\Contracts\OpenCampaigns)
app/Modules/Campaigns/
  Contracts/OpenCampaigns.php
  Enums/CampaignKind.php  CampaignStatus.php  Population.php  InstrumentStatus.php
  Models/Campaign.php  Participant.php  ParticipantInstrument.php
  Actions/CreateEvaluationDraft.php  DrawSample.php  RedrawSample.php  DeleteDraft.php  LaunchCampaign.php
          LaunchTraumaCampaign.php  ExtendCampaign.php  CloseCampaign.php  CloseExpiredCampaigns.php
          InviteMissing.php  ResendInvitations.php  DrawReplacements.php  OpenSurvey.php  SubmitSurvey.php
          CancelParticipation.php  SendSurveyInvitation.php  ListCampaigns.php  CampaignProgress.php
  Exceptions/DraftOutdated.php  SurveyUnavailable.php  CampaignConflict.php
  Notifications/SurveyInvitation.php
  Listeners/CancelOnDeactivation.php  CancelOnMove.php
  Http/Controllers/CampaignController.php  CampaignActionController.php  TraumaCampaignController.php  SurveyController.php
  Http/Requests/CreateEvaluationRequest.php  LaunchCampaignRequest.php  ExtendCampaignRequest.php
                TraumaCampaignRequest.php  SubmitSurveyRequest.php  ReplacementsRequest.php
  Providers/CampaignsServiceProvider.php  Support/CampaignClock.php (closes_on → instant)
app/Modules/Responses/
  Contracts/ResponseRecorder.php  Data/AnonymousAnswers.php  TraumaAnswers.php
  Models/AnonymousResponse.php  TraumaScreening.php  Support/DatabaseResponseRecorder.php  Providers/ResponsesServiceProvider.php
database/migrations/2026_10_31_100000_add_manage_campaigns_permission.php
                    2026_10_31_200000_create_campaigns_tables.php
                    2026_10_31_300000_create_responses_tables.php
database/factories/Campaigns/{Campaign,Participant,ParticipantInstrument}Factory.php
database/factories/Responses/{AnonymousResponse,TraumaScreening}Factory.php
routes/campaigns.php (company group)  routes/survey.php (public)  routes/console.php (schedule)
resources/js/pages/campaigns/{index,show,trauma,survey}.tsx
resources/js/components/campaigns/*.tsx   resources/js/components/campaigns/survey/*.tsx
resources/js/lib/survey.ts  resources/js/lib/campaigns.ts  resources/js/types/campaigns.ts
tests/Unit/Instruments/*  tests/Feature/{Organization,Tenancy,Campaigns,Responses}/*  tests/Browser/{Campaigns,Survey}Test.php
tests/js/lib/{survey,campaigns}.test.ts
```

Shared vocabulary, used by every task:

- **Instrument keys:** `nom035.guide-i`, `nom035.guide-ii`, `nom035.guide-iii`. Version: `dof-2018-10-23`.
- **Answers:** `array<string, string>`, keyed by item number as a string (`'1'`…`'72'`).
  - Likert values: `always`, `almost_always`, `sometimes`, `almost_never`, `never`.
  - Guía I values: `yes`, `no`.
  - Gates: keys `serves_clients` and `supervises`, with values `yes` or `no`.
- **RiskLevel** (backed by string): `null`, `low`, `medium`, `high`, `very_high`. Labels: "Nulo o despreciable",
  "Bajo", "Medio", "Alto", "Muy alto".
- **Category keys:** `work_environment`, `activity_factors`, `work_time`, `leadership_relations`,
  `organizational_environment` (Guía III only).
- **Domain keys:** `environment_conditions`, `workload`, `lack_of_control`, `work_day`, `work_family`, `leadership`,
  `relations`, `violence`; Guía III only: `performance_recognition`, `belonging_instability`.
- Labels are the annex's Spanish names, with "Escasa" fixed (C-21).

---

### Task 1: The three NOM-035 definitions, literal from the annex

**Files:**
- Create: `app/Modules/Instruments/Data/{InstrumentDefinition,SectionDefinition,ItemDefinition,GateDefinition}.php`
- Create: `app/Modules/Instruments/Support/Nom035/{Likert,GuideOne,GuideTwo,GuideThree}.php`
- Create: `app/Modules/Instruments/Contracts/Instruments.php` (method `definition` only, for now)
- Create: `app/Modules/Instruments/Support/InstrumentCatalog.php`
- Modify: `app/Modules/Instruments/Providers/InstrumentsServiceProvider.php` (bind `Instruments` → `InstrumentCatalog`)
- Test: `tests/Unit/Instruments/Nom035DefinitionsTest.php`

**Interfaces:**
- Produces:
  - `Instruments::definition(string $key): InstrumentDefinition`. It throws `\InvalidArgumentException` for an
    unknown key.
  - `InstrumentDefinition` (readonly):
    - `string $key`, `string $version`, `string $title`, `bool $identified`;
    - `list<array{value: string, label: string}> $options`;
    - `list<SectionDefinition> $sections`;
    - `itemNumbers(): list<int>`.
  - `SectionDefinition` (readonly): `string $title`, `string $instruction`, `list<ItemDefinition> $items`,
    `?GateDefinition $gate`. When `$gate` is set, the section's items are asked only on "yes".
  - `ItemDefinition` (readonly): `int $number`, `string $text`.
  - `GateDefinition` (readonly):
    - `string $key` (`serves_clients` or `supervises`);
    - `string $question` (literal: «En mi trabajo debo brindar servicio a clientes o usuarios:» or «Soy jefe de otros
      trabajadores:»);
    - `string $instruction` (the literal «Si su respuesta fue…» text).
- Titles:
  - Guía I: «Acontecimientos traumáticos severos».
  - Guía II: «Factores de riesgo psicosocial».
  - Guía III: «Factores de riesgo psicosocial y entorno organizacional».
- Guía I sections: «I.- Acontecimiento traumático severo» (with the shared stem «¿Ha presenciado o sufrido alguna
  vez, durante o con motivo del trabajo un acontecimiento como los siguientes:» as instruction), II, III and IV with
  their literal headings. Options are `yes` «Sí» and `no` «No».

- [ ] **Step 1: Write the failing test.**
  - The test parses `docs/normas/nom-035/texto-de-referencia.md`: numbered lines under each `## Guía de referencia`
    heading give `[number => text]`.
  - Datasets: the three keys.
  - `it('has exactly the items of the DOF, literal and in order')`: `definition($key)->itemNumbers()` equals
    `range(1, 20|46|72)`, and each item's text equals the annex text.
  - `it('asks Guía II and III with the five frequency options in the norm\'s order')`.
  - `it('puts each section under the instruction the norm gives it')`: section instructions equal the annex's bold
    lines.
  - `it('asks the clients and supervision blocks behind their yes/no questions')`:
    - Guía II: gate `serves_clients` → items 41–43; gate `supervises` → items 44–46.
    - Guía III: 65–68 and 69–72.
  - `it('marks Guía I identified and Guías II and III anonymous')`.
  - `it('rejects an unknown instrument')`.
  - `mutates(InstrumentCatalog::class)`.
- [ ] **Step 2:** Run `php artisan test --compact tests/Unit/Instruments/Nom035DefinitionsTest.php`. Expected: FAIL
  (`Instruments` not bound).
- [ ] **Step 3:** Implement the definitions. Write the item texts from the annex; no rewording (C-21).
- [ ] **Step 4:** Run the same test. Expected: PASS. Also run `vendor/bin/phpstan analyse` and
  `php artisan test --compact tests/Unit/ArchitectureTest.php`.
  - If the arch rule "data objects are readonly" rejects the `RiskLevel` enum in `Data` (Task 4), move the rule's
    target to `->classes()->not->toBeEnums()`. Do not move the enum.
- [ ] **Step 5:** Commit: "Define NOM-035 Guías I, II and III literal from the DOF".

### Task 2: Validate a questionnaire against its definition

**Files:**
- Create: `app/Modules/Instruments/Support/AnswerValidator.php`, `app/Modules/Instruments/Exceptions/InvalidAnswers.php`
  (`extends \InvalidArgumentException`)
- Modify: `Contracts/Instruments.php` (+ `validate`), `Support/InstrumentCatalog.php`
- Test: `tests/Unit/Instruments/AnswerValidationTest.php`

**Interfaces:**
- Produces: `Instruments::validate(string $key, array $answers): void`. It throws `\InvalidArgumentException`
  carrying a Spanish message for the respondent.

The rules come from C-06, C-12 and C-18:
- Keys are exactly the expected set: no missing key, no extra key.
- Each value is one of the item's options.
- A gate is required and must be `yes` or `no`. On `no`, its items must be absent. On `yes`, all of them must be
  present.
- Guía I: if section I is all `no`, items 7–20 must be absent. Otherwise all 20 must be present.

- [ ] **Step 1:** Write the failing tests:
  - `accepts a complete Guía III with both gates yes`;
  - `accepts both gates no without their items`;
  - `rejects a missing item`;
  - `rejects an item it does not ask`;
  - `rejects a value outside the options`;
  - `rejects gated items after a no`;
  - `rejects a yes without all its items`;
  - `rejects a missing gate`;
  - `Guía I: ends after an all-no section I`;
  - `Guía I: rejects sections II–IV after an all-no section I`;
  - `Guía I: requires all twenty after any yes`.

  Add helpers in the test file: `completeGuide(string $key, string $value = 'sometimes', bool $clients = true,
  bool $supervises = true): array`. `mutates(AnswerValidator::class)`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement it, driven only by the definition. No guide-specific branches except Guía I's
  section-I rule, which lives in `GuideOne`.
- [ ] **Step 4:** Run it. Expected: PASS.
- [ ] **Step 5:** Commit: "Accept only complete questionnaires whose yes/no gates match their answers".

### Task 3: Guía I clinical assessment rule

**Files:**
- Create: `Data/TraumaOutcome.php`, `Support/TraumaScreener.php`
- Modify: `Contracts/Instruments.php` (+ `screenTrauma`), `InstrumentCatalog.php`
- Test: `tests/Unit/Instruments/TraumaScreeningTest.php`

**Interfaces:**
- Produces: `Instruments::screenTrauma(array $answers): TraumaOutcome`, which validates first.
- `TraumaOutcome` (readonly): `bool $experiencedEvent`, `bool $requiresClinicalAssessment`. The rule is: any yes in
  section I, then section II ≥ 1 or section III ≥ 3 or section IV ≥ 2 (C-06).

- [ ] **Step 1:** Write a dataset test, `it('decides clinical assessment exactly as GR.I says')`:
  - all no → `[false, false]`;
  - one I yes and nothing else → `[true, false]`;
  - II = 1 → `[true, true]`;
  - III = 2 → `[true, false]`;
  - III = 3 → `[true, true]`;
  - IV = 1 → `[true, false]`;
  - IV = 2 → `[true, true]`;
  - only section II yes with section I all no → invalid.

  `mutates(TraumaScreener::class)`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement it.
- [ ] **Step 4:** Run it. Expected: PASS.
- [ ] **Step 5:** Commit: "Decide Guía I clinical assessment by rule GR.I".

### Task 4: Score Guías II and III, exact at every boundary

**Files:**
- Create: `Data/RiskLevel.php` (enum with `label()`), `Data/GroupScore.php`, `Data/RiskScore.php`
- Modify: `Support/Nom035/GuideTwo.php`, `GuideThree.php`: add Table 2/5 (reversed items), Table 3/6 (dimension →
  items; domain → dimensions; category → domains, with keys and labels) and the range rows
  `[key => [low, medium, high, veryHigh]]` (each value is the inclusive lower cut-off).
- Create: `Support/RiskScorer.php`
- Modify: `Contracts/Instruments.php` (+ `scoreRisk`), `InstrumentCatalog.php`
- Test: `tests/Unit/Instruments/RiskScoringTest.php`

**Interfaces:**
- Produces: `Instruments::scoreRisk(string $key, array $answers): RiskScore`, which validates first.
- `RiskScore` (readonly):
  - `int $total`, `RiskLevel $level`;
  - `list<GroupScore> $categories`, `list<GroupScore> $domains`;
  - `list<array{key: string, label: string, score: int}> $dimensions`.
- `GroupScore` (readonly): `string $key`, `string $label`, `int $score`, `RiskLevel $level`.
- `RiskLevel::fromCutoffs(int $score, array{0:int,1:int,2:int,3:int} $cutoffs): self`, where each lower bound is
  inclusive (C-13).

- [ ] **Step 1:** Write the failing tests:
  - `scores every item on its own scale`:
    - for each Guía, all `always` → each reversed item 0 and each other item 4;
    - Guía II all-`always` Cfinal = 120: items 18–33 score 0 and the other 30 score 4.
  - `gives 0 to the items of a gate answered no`.
  - `sums each domain and category from Tables 3 and 6`: use one answer set with a known value per item (e.g.
    items cycling through the five options). Assert three hand-computed domain sums and one category.
  - `places a score at each cut-off in the higher level`: a dataset generated from the range rows of the annex. For
    each row and each cut-off `c`: `fromCutoffs(c - 1)` is the lower level, and `fromCutoffs(c)` and
    `fromCutoffs(c + 1)` are the higher level. This covers 2 Cfinal rows plus 12 (Guía II) + 15 (Guía III) group rows. The rows are typed
    in the test from the annex, not read from production code.
  - `reaches the extremes`: minimum 0 → `null`; Guía II maximum 184 and Guía III maximum 288 → `very_high`.
  - `keeps item 33 (II) and 57 (III), positive, inside Violencia`.
  - `mutates(RiskScorer::class, RiskLevel::class)`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement it.
- [ ] **Step 4:** Run it. Expected: PASS.
- [ ] **Step 5:** Commit: "Score Guías II and III by domain, category and total, with inclusive lower cut-offs".

### Task 5: Guía III sample size and allocation by gender

**Files:**
- Create: `Contracts/Nom035Sample.php`, `Support/Nom035SampleRule.php`; bind it in the provider
- Test: `tests/Unit/Instruments/Nom035SampleTest.php`

**Interfaces:**
- `Nom035Sample::size(int $workers): int` = `intdiv(9604 * N + d - 1, d)` with `d = 25 * (N - 1) + 9604` (ceil in
  integers, C-09). It is only meaningful for N > 50, and throws for N ≤ 50.
- `Nom035Sample::allocate(int $size, array<string, int> $strata): array<string, int>`. It uses the largest remainder
  method; ties go to the larger stratum, then to key order. The result sums exactly to `$size`, and no quota exceeds
  its stratum.

- [ ] **Step 1:** Write the failing tests:
  - `size` dataset: 51 → 46 (45.13), 52 → 46 (45.91), 100 → 80 (79.51), 500 → 218 (217.49), 1000 → 278 (277.74),
    10000 → 370 (369.98).
  - `never exceeds N` (property over 51..2000).
  - `rejects 50 or fewer`.
  - `allocate`:
    - `[female 56, male 42, other 2]` of 100 with n = 80 → `[45, 34, 1]`. Exact values: 44.8 / 33.6 / 1.6; floors
      44 / 33 / 1 = 78; the 2 seats left go to the largest remainders: female (.8), then the .6 tie between male
      and other goes to the larger stratum, male. Result 45 / 34 / 1 = 80.
    - A single stratum gets everything.
    - A stratum of 1 never gets 2.
    - The sum equals n across a randomized property of 200 cases.
  - `mutates(Nom035SampleRule::class)`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement it.
- [ ] **Step 4:** Run it. Expected: PASS.
- [ ] **Step 5:** Commit: "Size the Guía III sample rounding up and split it by gender".

### Task 6: Organization roster contract and employee events

**Files:**
- Create: `app/Modules/Organization/Contracts/Roster.php`, `Data/RosterEntry.php`, `Support/CompanyRoster.php`,
  `Events/{EmployeeDeactivated,EmployeeReactivated,EmployeeMoved}.php`
- Modify: `Actions/DeactivateEmployee.php`, `ReactivateEmployee.php`, `UpdateEmployee.php` (dispatch after commit),
  `Providers/OrganizationServiceProvider.php`
- Test: `tests/Feature/Organization/RosterTest.php`, `tests/Feature/Organization/EmployeeEventsTest.php`

**Interfaces:**
- `Roster::activeIn(string $workCenterId): list<RosterEntry>`, ordered by name. Deactivated employees are excluded.
- `Roster::find(string $employeeId): ?RosterEntry`. It includes deactivated employees. It is scoped to the current
  company.
- `Roster::findMany(list<string> $ids): array<string, RosterEntry>`.
- `RosterEntry` (readonly): `string $id`, `string $name`, `?string $email`, `string $gender` (`female`, `male` or
  `other`), `string $areaId`, `string $areaName`, `string $workCenterId`, `bool $active`.
- Events (readonly, public properties):
  - `EmployeeDeactivated(string $tenantId, string $employeeId)`;
  - `EmployeeReactivated(string $tenantId, string $employeeId)`;
  - `EmployeeMoved(string $tenantId, string $employeeId, string $fromWorkCenterId, string $toWorkCenterId)`, fired
    only when the work center changes.

- [ ] **Step 1:** Write the failing tests:
  - `lists the active people of a work center with their area`;
  - `leaves out deactivated people and other work centers`;
  - `never sees another company's people`;
  - `finds a deactivated person by id`;
  - `announces a deactivation, a reactivation and a move` (`Event::fake`);
  - `does not announce a move when only the area changes`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them. Events dispatch with `DB::afterCommit`.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: "Expose a company's roster and announce employee deactivation, reactivation and moves".

### Task 7: Tenancy company lookup and the manage-campaigns permission

**Files:**
- Create: `app/Modules/Tenancy/Contracts/Companies.php`, `Data/CompanySummary.php`, `Support/CompanyDirectory.php`
- Create: `database/migrations/2026_10_31_100000_add_manage_campaigns_permission.php`, modelled on
  `2026_10_29_300000_keep_company_roles_in_spatie_teams.php`: grant to owner and hr.
- Modify: `Http/Middleware/ResolveTenant.php` (share `can.manage_campaigns`), `resources/js/types/tenancy.ts`
- Test: `tests/Feature/Tenancy/CompaniesTest.php`, `tests/Feature/Tenancy/RoleStorageTest.php` (extend)

**Interfaces:**
- `Companies::enter(string $tenantId): CompanySummary`. It sets the company context the way `ResolveTenant` does
  (via `TenantContext::set`) and throws `\InvalidArgumentException` when the id does not exist.
- `CompanySummary` (readonly): `string $id`, `string $tradeName`, `bool $blocked`.
- Permission `manage-campaigns`: owner ✓, hr ✓, viewer ✗.

- [ ] **Step 1:** Write the failing tests:
  - `enters a company by id so its data is readable`;
  - `reports a suspended company as blocked`;
  - `rejects an unknown id`;
  - `grants manage-campaigns to owner and hr only`;
  - `shares can.manage_campaigns with the pages`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: "Let other modules enter a company by id, and give owner and hr the manage-campaigns permission".

### Task 8: Campaigns and Responses schema, with isolation

**Files:**
- Create the campaigns and responses migrations, the models, the enums and the factories listed in "File
  structure".
- Test: `tests/Feature/Campaigns/CampaignStorageTest.php`, `tests/Feature/Responses/ResponseStorageTest.php`

**Schema:**
- `campaigns`:
  - columns: `id uuid`, `tenant_id`, `work_center_id`, `kind`, `status`, `workers int null`, `guides jsonb`,
    `population null`, `sample_size int null`, `quotas jsonb null`, `closes_on date null`,
    `launched_at timestamptz null`, `closed_at timestamptz null`, `created_by bigint`, timestamps;
  - composite FK `(work_center_id, tenant_id)`;
  - checks on `kind`, `status` and `population`; `active` ⇒ `closes_on` and `launched_at` set;
  - partial unique index `(tenant_id, work_center_id) WHERE kind = 'nom035_evaluation' AND status IN
    ('draft','active')`;
  - index `(status, closes_on)`.
- `campaign_participants`:
  - columns: `id uuid`, `tenant_id`, `campaign_id`, `employee_id`, `in_sample bool default false`,
    `token_hash char(64) null unique`, `invited_at timestamptz null`, timestamps;
  - unique `(campaign_id, employee_id)`; composite FKs to campaigns and employees.
- `participant_instruments`:
  - columns: `id uuid`, `tenant_id`, `participant_id`, `instrument`, `status` (`pending`, `completed`,
    `cancelled`), `completed_at null`, `cancelled_at null`;
  - unique `(participant_id, instrument)`;
  - check: `completed` ⇔ `completed_at`.
- `anonymous_responses`:
  - columns: `id uuid`, `tenant_id`, `campaign_id`, `work_center_id`, `area_id`, `instrument`,
    `instrument_version`, `answers jsonb`;
  - **no timestamps, no employee, participant or token columns**;
  - FKs to campaigns, work_centers and areas;
  - a trigger rejects UPDATE and DELETE (like `audit_entries`).
- `trauma_screenings`:
  - columns: `id uuid`, `tenant_id`, `campaign_id`, `employee_id`, `instrument_version`, `answers jsonb`,
    `requires_clinical_assessment bool`, `answered_at timestamptz`;
  - unique `(campaign_id, employee_id)`; insert-only trigger.
- Models: `BelongsToCurrentTenant`, `HasVersion4Uuids`, and `#[UseFactory]`. `AnonymousResponse` sets
  `$timestamps = false`.

- [ ] **Step 1:** Write the failing tests:
  - `stores a campaign for a work center of its own company only` (a foreign work center → QueryException);
  - `allows one open NOM-035 evaluation per work center` (draft + active → violation; closed + new draft OK;
    trauma campaigns unlimited);
  - `keeps anonymous responses with no way back to a person`: read `information_schema.columns` and FK constraints
    of `anonymous_responses`, and assert the column list equals the exact expected set;
  - `refuses to change or delete a stored response or screening`;
  - `never reads another company's campaigns, participants or responses`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: "Store campaigns, participants and responses, anonymous ones with no link to a person".

### Task 9: RH creates an evaluation draft, draws the sample, launches it

**Files:**
- Create:
  - `Actions/CreateEvaluationDraft.php`, `DrawSample.php`, `RedrawSample.php`, `DeleteDraft.php`,
    `LaunchCampaign.php`, `SendSurveyInvitation.php`, `ListCampaigns.php`;
  - `Notifications/SurveyInvitation.php`, `Exceptions/{DraftOutdated,CampaignConflict}.php`;
  - `Http/Controllers/CampaignController.php` (index, store, show, destroy);
  - `Http/Controllers/CampaignActionController.php` (redraw, launch);
  - `Http/Requests/{CreateEvaluationRequest,LaunchCampaignRequest}.php`;
  - `routes/campaigns.php`, required from `routes/company.php`;
  - `Contracts/OpenCampaigns.php` with its implementation.
- Create: the pages `campaigns/index.tsx` and `campaigns/show.tsx` (draft view) and their components; the
  navigation item; `lib/campaigns.ts`; `types/campaigns.ts`.
- Modify: `lib/app-navigation.ts`: `complianceNavItems(tenant)` → `[{ title: 'NOM-035', href: campaigns(slug),
  icon: ClipboardCheck }]` inside a company.
- Modify: `lang/es/validation.php` (attributes `work_center_id`, `population`, `closes_on`).
- Test: `tests/Feature/Campaigns/CreateEvaluationTest.php`, `LaunchCampaignTest.php`, `CampaignPagesTest.php`;
  `tests/Browser/CampaignsTest.php`; `tests/js/lib/campaigns.test.ts`

**Interfaces:**
- Routes (names), all under `admin/{tenant:slug}/campaigns`:
  - `tenant.campaigns.index` GET; `tenant.campaigns.store` POST; `tenant.campaigns.show` GET `{campaign}`;
  - `tenant.campaigns.destroy` DELETE; `tenant.campaigns.redraw` POST; `tenant.campaigns.launch` POST.
- `CreateEvaluationDraft::handle(string $workCenterId, Population $population, int $createdBy): Campaign`.
  - It computes `workers = count(Roster::activeIn)`, `guides = Nom035Guides::for(workers)`, and for Guía III with
    `Sample`: `sample_size`, `quotas` (by gender) and the draw.
  - It throws `CampaignConflict` when there is no person, the work center is archived, or an evaluation is open.
- `DrawSample::handle(Campaign $campaign): void`. It replaces `in_sample` participants of a draft. It shuffles each
  stratum with `Random\Randomizer` and takes its quota. The draft's participants hold only the sampled people.
- `LaunchCampaign::handle(Campaign $campaign, CarbonImmutable $closesOn): void`.
  - It recomputes workers, guides and quotas. With a sample, it checks every sampled person is still active in the
    work center. On any difference it throws `DraftOutdated` (C-10).
  - It creates participants for everyone in the roster. Instruments: always `nom035.guide-i`, plus the II for
    everyone, or the III for everyone or for the sampled people only.
  - Each participant with an email gets a 64-character token (hash stored) and the email after commit.
  - It sets `active`, `launched_at` and `closes_on`.
  - It writes the log and the audit entry `campaign.launched` with `{workers, participants, guides, population,
    sample_size}`.
- `SurveyInvitation(string $tradeName, string $token, CarbonImmutable $closesAt)`:
  - subject «{Empresa} le invita a contestar una evaluación confidencial»;
  - body in *usted*: what it is, the deadline (`DisplayDate::day`), that Guías II/III are anonymous, button
    «Contestar evaluación» → `route('survey.show', $token)`.
- Page props:
  - `campaigns/index`: `{ campaigns: CampaignCard[], workCenters: {id, name, workers, guides, lastEvaluatedOn,
    dueOn, eligible}[], can: {manage} }`.
  - `campaigns/show` (draft): `{ campaign: {id, status, kind, workCenter, workers, guides, population, sampleSize,
    quotas}, sample: {name, area, gender}[] }`.
- `OpenCampaigns::existFor(string $workCenterId): bool`.

- [ ] **Step 1:** Write the failing feature tests:
  - `creates a draft with the guides of the work center's headcount` (dataset 15 / 16 / 50 / 51);
  - `defaults Guía III to everyone`;
  - `draws a sample split by gender in proportion` (100 people 56 / 42 / 2 → 80 sampled, 45 / 34 / 1);
  - `offers no sample for Guía II` (the request with `population=sample` and 30 people → validation error);
  - `redraws a draft`;
  - `deletes a draft but never an active campaign` (409);
  - `refuses a second open evaluation of a work center`;
  - `refuses an archived or empty work center`;
  - `launches: invites everyone to Guía I and the II, emailing those with an address` (`Notification::fake`,
    participants, instruments);
  - `launches a sample: Guía III only for the sampled people`;
  - `refuses to launch when the headcount changed the guides or n`;
  - `refuses to launch when someone sampled left or the gender split changed`;
  - `refuses a deadline today or in the past`;
  - `records campaign.launched with counts, never names or emails`;
  - `lets viewer see the list but not create, launch or delete` (403);
  - `answers 404 for another company's campaign`;
  - `never puts answers in the props` (the index and show props contain no `answers` key at any depth).
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement the backend, then the pages:
  - The index uses `CampaignCard` from the design system (title = work center, subtitle «Guía III · cierra el …»,
    big count, progress bar, footer action) and a per-work-center table «Última evaluación / Siguiente antes del».
    Dialog «Nueva evaluación»: work center (eligible only), shows workers and guides, a Todos/Muestra radio when Guía
    III applies (with n and the split preview from `lib/campaigns.ts`), and the plantilla warning.
  - The show page (draft): summary, the sample list (when there is one) with «Volver a sortear», «Eliminar
    borrador», and «Lanzar» with a DatePicker for the deadline.
- [ ] **Step 4:** Write `tests/js/lib/campaigns.test.ts`, covering the guides label, the sample split text and the
  due date. Write the Browser tests `visitPage('campaigns/index')` and `visitPage('campaigns/show')` in light/dark ×
  desktop/mobile, plus `creates and launches an evaluation from the panel`. Run `php artisan test --compact
  tests/Feature/Campaigns`, `npm run test:js` and `composer test:browser -- --filter=Campaigns`. Expected: PASS.
- [ ] **Step 5:** Commit in this order (each green):
  1. "Create a NOM-035 evaluation draft with its guides and, if chosen, a Guía III sample";
  2. "Launch an evaluation and email each person their link";
  3. "Show NOM-035 evaluations under Cumplimiento".

### Task 10: The survey link opens the survey

**Files:**
- Create: `Actions/OpenSurvey.php`, `Exceptions/SurveyUnavailable.php` (with a reason enum: `invalid`, `closed`,
  `done`, `suspended`), `Http/Controllers/SurveyController.php` (`show`), `routes/survey.php` (included from
  `routes/web.php`, `throttle:60,1`), and the page `resources/js/pages/campaigns/survey.tsx` (intro + instrument
  list + unavailable states).
- Modify: `tests/Feature/RouteSecurityTest.php`: add
  `'survey.show', // guest: survey link from the email; the token is the credential, single use per guide` and
  `'survey.submit', // guest: submits one guide; throttled, locked, single use`.
- Test: `tests/Feature/Campaigns/OpenSurveyTest.php`

**Interfaces:**
- Route `survey.show` GET `/survey/{token}`.
- `OpenSurvey::handle(string $token): array{participant: Participant, company: CompanySummary, closesOn:
  CarbonImmutable}`.
  - It finds the participant by `hash('sha256', $token)` without the tenant scope.
  - It enters its company via `Companies::enter`.
  - It throws `SurveyUnavailable` when: the token is unknown; the campaign is not active or the deadline passed;
    no instrument is pending; the company is blocked.
- `survey` page props: `{ company: {tradeName}, closesOn: string, instruments: {key, title, identified, status,
  options, sections}[] }`, or `{ unavailable: 'invalid' | 'closed' | 'done' | 'suspended' }`. For `invalid`, no
  company is shown.

- [ ] **Step 1:** Write the failing tests:
  - `opens with the company, the deadline and the pending guides`;
  - `shows a completed guide as done and offers the rest`;
  - `says the link is not valid without naming any company`;
  - `says the evaluation closed after closing or past its deadline`;
  - `says there is nothing left once every guide is answered`;
  - `says the evaluation is not available while the company is suspended`;
  - `stops a resent link's old token`;
  - `is public and throttled`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: "Open the survey from the emailed link and explain when it no longer works".

### Task 11: Submit a guide: anonymous storage, Guía I screening, single use

**Files:**
- Create:
  - `app/Modules/Responses/Contracts/ResponseRecorder.php`, `Data/{AnonymousAnswers,TraumaAnswers}.php`,
    `Support/DatabaseResponseRecorder.php`, `Providers/ResponsesServiceProvider.php` (registered in
    `bootstrap/providers.php`);
  - `app/Modules/Campaigns/Actions/SubmitSurvey.php`, `Http/Requests/SubmitSurveyRequest.php`,
    `SurveyController::submit`.
- Test: `tests/Feature/Campaigns/SubmitSurveyTest.php`, `tests/Feature/Responses/AnonymityTest.php`

**Interfaces:**
- Route `survey.submit` POST `/survey/{token}/{instrument}` (`throttle:20,1`). Body `{ answers: Record<string,
  string> }`. It redirects back to `survey.show` with a flash.
- `ResponseRecorder::recordAnonymous(AnonymousAnswers $data): void`.
  - `AnonymousAnswers`: `campaignId`, `workCenterId`, `areaId`, `instrument`, `version`, `answers`.
  - Log `response.recorded` with `{campaign_id, instrument}` only.
- `ResponseRecorder::recordTraumaScreening(TraumaAnswers $data): void`.
  - `TraumaAnswers`: `campaignId`, `employeeId`, `version`, `answers`, `requiresClinicalAssessment`.
  - Log `trauma_screening.recorded` with `{campaign_id}`.
- `SubmitSurvey::handle(string $token, string $instrument, array $answers): void`, inside `DB::transaction`:
  1. lock the participant row;
  2. re-run the `OpenSurvey` checks;
  3. require the instrument to be `pending` for that participant (otherwise `SurveyUnavailable('done')`);
  4. `Instruments::validate`;
  5. Guía I → `screenTrauma` + `recordTraumaScreening`; II/III → `recordAnonymous` with the campaign's work center
     and `Roster::find(employee)->areaId` (C-24);
  6. mark it `completed`;
  7. log `campaign_participant.completed` with `{participant_id, instrument}`.

- [ ] **Step 1:** Write the failing tests:
  - `stores a Guía III anonymously with the campaign's work center and the person's current area`;
  - `stores a Guía I screening with its clinical assessment`;
  - `accepts the guides in any order` (III before I);
  - `rejects an incomplete or incoherent guide and stores nothing`;
  - `refuses a guide twice`;
  - `lets only one of two simultaneous submissions in`: run the action twice where the second sees `completed`
    after the lock, and assert one row;
  - `refuses a guide not assigned to the person`;
  - `refuses after the deadline`;
  - `keeps no timestamp or identifier of the person on an anonymous response`;
  - `never writes the answers or a response id to the log`: capture log lines with the test log channel, submit a
    Guía III with distinctive values, and assert no line contains any answer value pattern or the stored
    response's id;
  - `marks the person as answered`.
  - `mutates(SubmitSurvey::class, DatabaseResponseRecorder::class)`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them. Expected: PASS.
- [ ] **Step 5:** Commit: "Store each answered guide once: Guía I with the person, Guías II and III anonymous".

### Task 12: The employee answers on the phone (survey UI)

**Files:**
- Create: `resources/js/lib/survey.ts` and the components in `resources/js/components/campaigns/survey/`:
  `survey-shell.tsx` (logo, «Confidencial», «hecho con humetría»), `survey-intro.tsx`, `guide-intro.tsx`,
  `survey-question.tsx` (fieldset + legend, five vertical 56 px options, keyboard arrows), `gate-question.tsx`,
  `section-progress.tsx`, `survey-navigation.tsx`, `survey-done.tsx`, `survey-unavailable.tsx`. Hook:
  `hooks/use-survey-progress.ts` (localStorage).
- Modify: `pages/campaigns/survey.tsx`
- Test: `tests/js/lib/survey.test.ts`, `tests/Browser/SurveyTest.php`

**Interfaces:**
- `lib/survey.ts`:
  - `visibleItems(definition, answers): number[]`: hides gated items after a `no`, and Guía I sections II–IV after
    an all-no section I.
  - `screens(definition, answers, perScreen: 1 | 3): Screen[]`: one question per screen on mobile, up to 3 of a
    section on desktop. Gates are their own screen.
  - `isComplete(definition, answers): boolean`.
  - `payload(definition, answers): Record<string, string>`: drops hidden items.
  - `storageKey(token, instrument): string`.
- Copy, in *usted*, as the spec says:
  - The intro states the objective, confidentiality, that there are no right or wrong answers, that every question
    must be answered, «considere las condiciones de los dos últimos meses», and the deadline.
  - Guía I intro: «Esta parte sí lleva su nombre…» (channeling, authorized people only).
  - Guías II/III intro: «Esta parte es anónima…».
  - The footer note on the device-only save: «Sus respuestas se guardan en este dispositivo hasta que las envíe.
    Nadie de su empresa puede ver lo que contesta.» For Guía I it omits the second sentence.
- The survey never auto-advances: «Siguiente» is explicit and stays disabled until the question is answered.

- [ ] **Step 1:** Write the failing Vitest tests:
  - each function on Guía II/III/I fixtures (copy small definition fixtures from the page props shape);
  - a gate `no` hides its items and drops them from the payload;
  - an all-no Guía I section I completes the guide;
  - `screens` is `1|3` per screen.
  - Coverage must stay ≥ 90%.
- [ ] **Step 2:** Run `npm run test:js`. Expected: FAIL.
- [ ] **Step 3:** Implement the lib, then the components and the page.
- [ ] **Step 4:** Write the Browser tests on mobile and desktop, light and dark:
  - `the employee answers Guía I and Guía III from the phone` (fill everything; a gate «No» skips its block; submit;
    see «Gracias»; DB has 1 screening and 1 anonymous response);
  - `an all-no section I ends Guía I`;
  - `a reload keeps what was answered`;
  - `the used link says there is nothing left`;
  - `visitPage('campaigns/survey')` for intro, unavailable and done (axe, CSP, no English).

  Run `npm run build && composer test:browser -- --filter=Survey`. Expected: PASS.
- [ ] **Step 5:** Commit:
  1. "Walk the employee through each guide one question at a time, keeping progress on the device";
  2. "Submit each guide once from the survey".

### Task 13: Progress and the actions of an active campaign; changes in the roster

**Files:**
- Create:
  - `Actions/CampaignProgress.php`, `ResendInvitations.php`, `InviteMissing.php`, `DrawReplacements.php`,
    `ExtendCampaign.php`, `CloseCampaign.php`, `CloseExpiredCampaigns.php`, `CancelParticipation.php`;
  - `Listeners/{CancelOnDeactivation,CancelOnMove}.php`, `Http/Requests/{ExtendCampaignRequest,ReplacementsRequest}.php`.
- Routes:
  - `tenant.campaigns.resend` POST (all pending);
  - `tenant.campaigns.participants.resend` POST `{participant}`;
  - `tenant.campaigns.invite-missing` POST;
  - `tenant.campaigns.replacements` POST `{count}`;
  - `tenant.campaigns.extend` POST;
  - `tenant.campaigns.close` POST.
- Modify: `routes/console.php`: `Schedule::call(CloseExpiredCampaigns)->hourly()`, or an artisan command
  `campaigns:close-expired` scheduled hourly. `Tenancy/Actions/ArchiveWorkCenter.php`: throw a 409-mapped exception
  when `OpenCampaigns::existFor`.
- Modify: `pages/campaigns/show.tsx` (active/closed view), `components/campaigns/*`.
- Test: `tests/Feature/Campaigns/CampaignProgressTest.php`, `CampaignActionsTest.php`, `RosterChangesTest.php`,
  `CloseExpiredTest.php`; `tests/Feature/Tenancy/ArchiveWorkCenterTest.php` (extend); Browser (extend
  `CampaignsTest.php`).

**Interfaces:**
- `CampaignProgress::handle(Campaign $campaign, bool $withPeople): array`, with:
  - `{instruments: [{key, invited, completed, pending, cancelled}], sample: {size, completed} | null, people:
    [{id, name, area, guides: {key: status}, reachable}] | null}`;
  - `people` is `null` for viewer (no `manage-campaigns`).
- `ResendInvitations::handle(Campaign $campaign, ?Participant $only = null): int`. It rotates the token of each
  participant with a pending instrument and an email, and returns how many were sent.
- `InviteMissing::handle(Campaign $campaign): int`.
  - It invites new active people of the work center. They get Guía I, plus the II, or the III when the population
    is `all`.
  - It restores cancelled instruments of participants who are active in the work center again (C-24).
  - It emails them.
- `DrawReplacements::handle(Campaign $campaign, int $count): int`. It adds `$count` people at random, proportional
  across strata by the remaining shortfall. Simplest correct rule: draw from each stratum with
  `allocate($count, available per stratum)`. It marks them `in_sample`, gives them Guía I if they are not
  participants yet, and gives them the III.
- `ExtendCampaign::handle(Campaign $campaign, CarbonImmutable $closesOn): void`. The new date must be later than
  the current one and later than today.
- `CloseCampaign::handle(Campaign $campaign): void`. It writes the log and audit entry `campaign.closed` with
  completed counts.
- `CloseExpiredCampaigns::handle(): int`. It runs across companies via `Companies::enter`.
- `CancelParticipation::handle(string $employeeId, ?string $onlyWorkCenterId = null): void`. It sets `cancelled` on
  pending instruments of open campaigns and clears `token_hash` when nothing is left pending.
- Show page (active):
  - the big count against the sample or the participants;
  - the warning «Faltan N para el mínimo de la muestra»;
  - a table of people with status badges and a search (not for viewer);
  - the buttons «Reenviar a pendientes», «Invitar a quien falte», «Sortear reemplazos» (sample only), «Extender
    fecha» and «Cerrar evaluación» (with a confirm that warns when the sample is not met).
  - Closed: read-only, «Cerrada el …».

- [ ] **Step 1:** Write the failing tests:
  - `counts invited, answered and pending per guide`;
  - `compares Guía III answers with the sample size`;
  - `hides the list of people from viewer`;
  - `resends to the pending people with an email and kills their old link`;
  - `resends to one person`;
  - `invites people hired after launch, with the III when everyone takes it and without it with a sample`;
  - `restores a reactivated person's guides with a new link`;
  - `draws replacements from people outside the sample, by gender`;
  - `says when nobody is left to draw`;
  - `extends the deadline but never to an earlier date`;
  - `closes an evaluation and every link stops`;
  - `warns but allows closing with the sample short`;
  - `closes expired evaluations of every company every hour`;
  - `cancels a deactivated person's pending guides`;
  - `cancels the guides of a person moved to another work center`;
  - `keeps them when only the area changes`;
  - `refuses to archive a work center with an open evaluation`;
  - `asserts the progress page's query count` (`expectsDatabaseQueryCount`);
  - each action → 403 for viewer and 404 for another company;
  - `records campaign.closed in the audit trail`.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them, plus the Browser test `follows an active evaluation and closes it` and `visitPage` of
  the active and closed show pages. Expected: PASS.
- [ ] **Step 5:** Commit:
  1. "Show an evaluation's progress per guide and against its sample";
  2. "Resend links, invite whoever is missing and draw replacements";
  3. "Extend and close evaluations, and close expired ones every hour";
  4. "Follow deactivations and moves in open evaluations, and keep their work centers from being archived".

### Task 14: Guía I-only campaign after a traumatic event

**Files:**
- Create: `Actions/LaunchTraumaCampaign.php`, `Http/Controllers/TraumaCampaignController.php` (`create`, `store`),
  `Http/Requests/TraumaCampaignRequest.php`, `pages/campaigns/trauma.tsx` + components.
- Test: `tests/Feature/Campaigns/TraumaCampaignTest.php`, Browser.

**Interfaces:**
- Routes `tenant.campaigns.trauma.create` GET (`?work_center=`) and `tenant.campaigns.trauma.store` POST
  `{work_center_id, employee_ids[], closes_on}`.
- `LaunchTraumaCampaign::handle(string $workCenterId, list<string> $employeeIds, CarbonImmutable $closesOn, int
  $createdBy): Campaign`. It creates an `active` `nom035_trauma` campaign with Guía I for exactly those people, who
  must be active people of that work center. It emails them and records `campaign.launched`.
- The page explains when to use it (C-05: also accidents on the direct commute) and offers a searchable checklist
  of the work center's active people.

- [ ] **Step 1:** Write the failing tests:
  - `launches Guía I for the chosen people only`;
  - `allows it while an evaluation is open`;
  - `rejects people of another work center, inactive ones or another company's`;
  - `requires at least one person`;
  - viewer 403.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement them.
- [ ] **Step 4:** Run them, plus the Browser test `applies Guía I to the people of an event` and `visitPage`.
  Expected: PASS.
- [ ] **Step 5:** Commit: "Apply Guía I to chosen people after a traumatic event".

### Task 15: Verify everything, screenshots, criteria check

- [ ] **Step 1:** Run `composer test` (pint, phpstan max, Unit/Feature with coverage ≥ 90%, Browser), `npm run
  types:check`, `npm run test:js` and `npm run check`. Expected: all green.
- [ ] **Step 2:** Walk C-01…C-26 in `docs/normas/nom-035/criterios.md` and name, for each criterion this PR
  implements, the test that proves it. Add to `criterios.md` a section «Cómo se prueba» listing criterion → test
  file. Update the version history only if a criterion changed.
- [ ] **Step 3:** Capture screenshots with Pest Browser `->screenshot()` (light/dark, desktop/mobile) of:
  - campaigns index;
  - new-evaluation dialog with the sample;
  - draft;
  - active progress;
  - trauma page;
  - survey intro, a question, a gate, Guía I intro, done, unavailable.

  Save them under `docs/superpowers/screenshots/2026-10-29-nom-035/` (not committed) and send them to the user.
- [ ] **Step 4:** Request a whole-branch review (superpowers:requesting-code-review), apply the findings and
  commit.
