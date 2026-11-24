<?php

declare(strict_types=1);

use App\Modules\Tenancy\Http\Middleware\RequireRoutePermission;
use App\Modules\Tenancy\Support\RoutePermissions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

mutates(RoutePermissions::class);

/**
 * When every company route started asking for its permission, the template roles kept exactly what they opened before.
 * The lists below were written from the code that decided it then: LimitRestrictedRoles (with TenantRole::allowedRoutes():
 * the Comisión limited to tenant.telework.*, tenant.seating.* and tenant.complaints.*, the handlers of complaints to
 * tenant.complaints.*, both sent from the panel to their tool) and the Gate of each controller and FormRequest. Each list is
 * what the role passes at the route; a controller may still refuse further down (a Comisión of another center, a case not
 * assigned to the person).
 *
 * @return list<string>
 */
function routesOpenedBy(string $template): array
{
    $permissions = Role::query()->whereNull('tenant_id')->where('name', $template)->sole()->permissions->pluck('name')->all();

    return array_values(array_map(
        fn (LaravelRoute $route): string => (string) $route->getName(),
        array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route): bool => in_array(RequireRoutePermission::class, $route->gatherMiddleware(), true)
                && in_array(RoutePermissions::for((string) $route->getName()), $permissions, true),
        ),
    ));
}

it('opens to each template role the routes it opened before', function (string $template, array $before): void {
    expect(routesOpenedBy($template))->toEqualCanonicalizing($before);
})->with([
    // Viewer: every page its view-* permissions opened, the panel, Humi and the option lists every member could search.
    'viewer' => ['viewer', [
        'tenant.areas.options', 'tenant.job-grades.options',
        'tenant.assistant.chat', 'tenant.assistant.chat.stop', 'tenant.assistant.conversations.destroy',
        'tenant.assistant.conversations.index', 'tenant.assistant.conversations.show', 'tenant.assistant.facts',
        'tenant.assistant.feedback', 'tenant.assistant.index', 'tenant.attendance.center',
        'tenant.attendance.corrections.index', 'tenant.attendance.index', 'tenant.attendance.policy.show',
        'tenant.attendance.schedules.index', 'tenant.attendance.setup', 'tenant.campaigns.index',
        'tenant.campaigns.program', 'tenant.campaigns.report', 'tenant.campaigns.results', 'tenant.campaigns.show',
        'tenant.climate.comments', 'tenant.climate.commitments.index', 'tenant.climate.export', 'tenant.climate.index',
        'tenant.climate.managers', 'tenant.climate.managers.show', 'tenant.climate.results',
        'tenant.climate.results.items', 'tenant.climate.show', 'tenant.dashboard', 'tenant.disclosure.index',
        'tenant.disclosure.policy', 'tenant.disclosure.policy.version', 'tenant.interventions.index',
        'tenant.nom035.dossier', 'tenant.nom035.inspection', 'tenant.nom035.route', 'tenant.org-chart.index',
        'tenant.org-chart.people.search', 'tenant.org-chart.quality', 'tenant.org-chart.quality.export',
        'tenant.org-chart.reports', 'tenant.pulse.commitments.index', 'tenant.pulse.export', 'tenant.pulse.index',
        'tenant.pulse.managers', 'tenant.pulse.managers.show', 'tenant.pulse.results', 'tenant.pulse.results.items',
        'tenant.pulse.show', 'tenant.pulse.trend', 'tenant.seating.center', 'tenant.seating.certificate',
        'tenant.seating.index', 'tenant.seating.inspection', 'tenant.seating.positions.show', 'tenant.seating.route',
        'tenant.seating.seats.index', 'tenant.telework.center', 'tenant.telework.certificate',
        'tenant.telework.checklist.show', 'tenant.telework.committee.act', 'tenant.telework.committee.show',
        'tenant.telework.documents.acknowledge', 'tenant.telework.documents.acknowledgements.index',
        'tenant.telework.documents.file', 'tenant.telework.documents.index', 'tenant.telework.dossier',
        'tenant.telework.evidence-photos.show', 'tenant.telework.index', 'tenant.telework.inspection',
        'tenant.telework.photos.show', 'tenant.telework.route', 'tenant.telework.supporting.index',
        'tenant.telework.visit-authorizations.cancel', 'tenant.telework.visit-authorizations.document.show',
        'tenant.telework.visit-authorizations.document.store', 'tenant.telework.work-centers.options',
        'tenant.vacations.balances.index', 'tenant.vacations.calendar', 'tenant.vacations.employees.show',
        'tenant.vacations.index', 'tenant.vacations.policies.edit', 'tenant.vacations.policies.index',
        'tenant.vacations.requests.index', 'tenant.vacations.settings.show', 'tenant.vacations.statements.show',
        'tenant.work-centers.index', 'tenant.work-centers.options', 'tenant.work-centers.route',
        'tenant.worker-access.index',
    ]],
    // Comisión: inside its three tools, what its Gates let it do; the panel only to be sent on to NOM-037.
    'committee' => ['committee', [
        'tenant.complaints.cases.acknowledge', 'tenant.complaints.cases.close', 'tenant.complaints.cases.export',
        'tenant.complaints.cases.hold', 'tenant.complaints.cases.messages.store', 'tenant.complaints.cases.notes.store',
        'tenant.complaints.cases.recuse', 'tenant.complaints.cases.reopen', 'tenant.complaints.cases.review',
        'tenant.complaints.cases.share', 'tenant.complaints.cases.show', 'tenant.complaints.centers.options',
        'tenant.complaints.index', 'tenant.complaints.procedure', 'tenant.dashboard',
        'tenant.seating.assessments.index', 'tenant.seating.assessments.minutes', 'tenant.seating.assessments.show',
        'tenant.seating.assessments.sign', 'tenant.seating.assessments.store', 'tenant.seating.assessments.update',
        'tenant.seating.center', 'tenant.seating.certificate', 'tenant.seating.documents.file',
        'tenant.seating.documents.index', 'tenant.seating.documents.options', 'tenant.seating.dossier',
        'tenant.seating.index', 'tenant.seating.inspection', 'tenant.seating.measures.evidence',
        'tenant.seating.measures.propose', 'tenant.seating.pause-agreements.evidence', 'tenant.seating.plan',
        'tenant.seating.positions.show', 'tenant.seating.referrals.store', 'tenant.seating.regulation.file',
        'tenant.seating.regulation.show', 'tenant.seating.reports.index', 'tenant.seating.route',
        'tenant.seating.seats.index', 'tenant.seating.seats.photo', 'tenant.telework.center',
        'tenant.telework.certificate', 'tenant.telework.checklist.draft', 'tenant.telework.checklist.publish',
        'tenant.telework.checklist.questions.destroy', 'tenant.telework.checklist.questions.move',
        'tenant.telework.checklist.questions.store', 'tenant.telework.checklist.questions.update',
        'tenant.telework.checklist.show', 'tenant.telework.committee.act', 'tenant.telework.committee.show',
        'tenant.telework.documents.acknowledge', 'tenant.telework.documents.acknowledgements.index',
        'tenant.telework.documents.file', 'tenant.telework.documents.index', 'tenant.telework.dossier',
        'tenant.telework.evidence-photos.show', 'tenant.telework.evidence.store', 'tenant.telework.index',
        'tenant.telework.inspection', 'tenant.telework.notices.close', 'tenant.telework.notices.evidence',
        'tenant.telework.notices.index', 'tenant.telework.notices.place-review', 'tenant.telework.notices.review',
        'tenant.telework.notices.show', 'tenant.telework.photos.show', 'tenant.telework.reviews.index',
        'tenant.telework.reviews.show', 'tenant.telework.reviews.store', 'tenant.telework.route',
        'tenant.telework.supporting.index', 'tenant.telework.visit-authorizations.cancel',
        'tenant.telework.visit-authorizations.document.show', 'tenant.telework.visit-authorizations.document.store',
        'tenant.telework.visit-authorizations.store', 'tenant.telework.work-centers.options',
        'tenant.telework.workplaces.remote-check.store', 'tenant.telework.workplaces.visit.store',
    ]],
    // Handlers of complaints: the cases assigned to them; the panel only to be sent on to the channel.
    'complaints' => ['complaints', [
        'tenant.complaints.cases.acknowledge', 'tenant.complaints.cases.close', 'tenant.complaints.cases.export',
        'tenant.complaints.cases.hold', 'tenant.complaints.cases.messages.store', 'tenant.complaints.cases.notes.store',
        'tenant.complaints.cases.recuse', 'tenant.complaints.cases.reopen', 'tenant.complaints.cases.review',
        'tenant.complaints.cases.share', 'tenant.complaints.cases.show', 'tenant.complaints.centers.options',
        'tenant.complaints.index', 'tenant.complaints.procedure', 'tenant.dashboard',
    ]],
]);

it('keeps the Comisión and the handlers of complaints inside the tools they were limited to', function (string $template, array $allowed): void {
    expect(array_values(array_filter(routesOpenedBy($template), fn (string $name): bool => ! Str::is($allowed, $name))))->toBe([]);
})->with([
    'committee' => ['committee', ['tenant.telework.*', 'tenant.seating.*', 'tenant.complaints.*', 'tenant.dashboard']],
    'complaints' => ['complaints', ['tenant.complaints.*', 'tenant.dashboard']],
]);

it('lets the owner open every company route and hr all but the owner\'s', function (): void {
    $all = array_map(fn (LaravelRoute $route): string => (string) $route->getName(), array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        // The page without access asks for no permission (RoutePermissions::EXEMPT): it is everyone's, not the owner's.
        fn (LaravelRoute $route): bool => in_array(RequireRoutePermission::class, $route->gatherMiddleware(), true)
            && ! RoutePermissions::isExempt((string) $route->getName()),
    )));
    $ownerOnly = [
        'tenant.first-steps.hide', 'tenant.first-steps.show', 'tenant.first-steps.welcome', 'tenant.members.destroy',
        'tenant.members.index', 'tenant.members.invitations.cancel', 'tenant.members.invitations.create',
        'tenant.members.invitations.resend', 'tenant.members.invitations.store', 'tenant.members.update', 'tenant.plan',
        'tenant.privacy.edit', 'tenant.privacy.update', 'tenant.sso.domains.verify', 'tenant.sso.show',
        'tenant.sso.state', 'tenant.sso.update',
    ];

    expect(routesOpenedBy('owner'))->toEqualCanonicalizing($all)
        ->and(routesOpenedBy('hr'))->toEqualCanonicalizing(array_values(array_diff($all, $ownerOnly)));
});
