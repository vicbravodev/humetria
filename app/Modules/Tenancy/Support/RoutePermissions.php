<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use Illuminate\Support\Str;

/**
 * The permission each company route asks for (RequireRoutePermission), by route name: the first pattern that matches
 * wins, so each module lists its exact names before its catch-all. A page asks for the permission its controller or
 * FormRequest already checks (that Gate stays as a second barrier); a page that checked none asks for view-* to look
 * and manage-* to change. A module's catch-all is its manage-* permission, so a route added without thinking asks for
 * the most. RoutePermissionCoverageTest fails with any company route that gets no permission of the catalog.
 *
 * Where the controller accepts «A or B», the route asks for a list and any one of them opens it. The list is exactly
 * what the controller's gate accepts: the gate stays as the second barrier.
 *
 * The account pages (settings.*, logout) and the company switcher live outside the company group; inside it, only the
 * routes of EXEMPT ask for no permission, each for its reason.
 */
final class RoutePermissions
{
    /** @var array<string, list<string>|string> route name pattern => permission, or the list of which any one opens it */
    private const array PERMISSIONS = [
        // What a company has whatever its modules (the panel itself is in EXEMPT).
        'tenant.work-centers.index' => 'view-work-centers', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.options' => 'view-work-centers', // @pest-mutate-ignore: RemoveArrayItem
        // The route of one standard in one center sends to its page; the NOM-035 one is the route permission's.
        'tenant.work-centers.route' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        // «¿Te aplica?» and the first questions decide NOM-037 and Ley Silla: their controllers ask for both managers.
        'tenant.work-centers.applies' => 'manage-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.onboarding' => 'manage-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.onboarding.update' => 'manage-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.applicability.update' => 'manage-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.*' => 'manage-organization', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.nom035.*' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.employees.*' => 'manage-organization', // @pest-mutate-ignore: RemoveArrayItem
        // The option lists of areas and job grades: every member could search them, like the list of centers.
        'tenant.areas.*' => 'view-work-centers', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.job-grades.*' => 'view-work-centers', // @pest-mutate-ignore: RemoveArrayItem
        // The owner's: people and roles, the plan, the privacy minimum, single sign-on and the guided tour.
        'tenant.members.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.roles.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.plan' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.privacy.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.sso.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.first-steps.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.channels.*' => 'manage-members', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.assistant.*' => 'use-assistant', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.worker-access.index' => 'view-worker-access', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.worker-access.*' => 'manage-worker-access', // @pest-mutate-ignore: RemoveArrayItem

        // NOM-035. The evaluations and their aggregated results are seen by whoever sees the NOM-035 route (no Gate
        // refused them); the Guía I list is the trauma permission's, and recording its channeling is part of seeing it.
        'tenant.campaigns.index' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.show' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.results' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.report' => 'view-nom035-route', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.screenings' => 'view-trauma-screenings', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.screenings.update' => 'view-trauma-screenings', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.program' => 'view-interventions', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.program.*' => 'manage-interventions', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.*' => 'manage-campaigns', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.interventions.index' => 'view-interventions', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.interventions.*' => 'manage-interventions', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.disclosure.index' => 'view-disclosure', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.disclosure.policy' => 'view-disclosure', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.disclosure.policy.version' => 'view-disclosure', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.disclosure.*' => 'manage-disclosure', // @pest-mutate-ignore: RemoveArrayItem

        // NOM-037. Whoever sees it reads the documents, the photos and the visit papers (ReviewAccess::reader() then asks
        // for RH or the Comisión of the center); the Comisión's review of workplaces, notices and checklist is its own.
        'tenant.telework.index' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.center' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.route' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.inspection' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.certificate' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.dossier' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.supporting.index' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.work-centers.options' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.committee.show' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.committee.act' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.documents.index' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.documents.file' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.documents.acknowledge' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.documents.acknowledgements.index' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.checklist.show' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.photos.show' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.evidence-photos.show' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.visit-authorizations.document.*' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.visit-authorizations.cancel' => 'view-telework', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.visit-authorizations.store' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.checklist.*' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.reviews.*' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        // Reviewing a notice is RH's or the Comisión's of its center (NoticeAccess), and the inbox and the page are the Comisión's gate.
        'tenant.telework.notices.review' => ['manage-telework', 'review-workplaces'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.close' => ['manage-telework', 'review-workplaces'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.evidence' => ['manage-telework', 'review-workplaces'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.place-review' => ['manage-telework', 'review-workplaces'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.*' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.evidence.store' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.workplaces.remote-check.store' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.workplaces.visit.store' => 'review-workplaces', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.*' => 'manage-telework', // @pest-mutate-ignore: RemoveArrayItem

        // Ley Silla. The assessment of risk (the Comisión's and RH's) opens its own pages and the files of the evidence.
        'tenant.seating.index' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.center' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.route' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.inspection' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.certificate' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.seats.index' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.positions.show' => 'view-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.assessments.*' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.documents.index' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.documents.options' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.documents.file' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.dossier' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.plan' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.reports.index' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.regulation.show' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.regulation.file' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.measures.propose' => 'assess-seating', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.measures.evidence' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.pause-agreements.evidence' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.seats.photo' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.referrals.store' => ['manage-seating', 'assess-seating'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.*' => 'manage-seating', // @pest-mutate-ignore: RemoveArrayItem

        // The complaints channel: the cases are the handlers'; how the channel is set up, its manager's.
        'tenant.complaints.index' => 'handle-complaints', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.cases.*' => 'handle-complaints', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.centers.options' => 'handle-complaints', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.procedure' => 'handle-complaints', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.*' => 'manage-complaint-channel', // @pest-mutate-ignore: RemoveArrayItem

        'tenant.attendance.index' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.center' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.setup' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.corrections.index' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.policy.show' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.schedules.index' => 'view-attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.*' => 'manage-attendance', // @pest-mutate-ignore: RemoveArrayItem

        'tenant.vacations.index' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.calendar' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.balances.index' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.employees.show' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.statements.show' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.policies.index' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.policies.edit' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.requests.index' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.settings.show' => 'view-vacations', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.*' => 'manage-vacations', // @pest-mutate-ignore: RemoveArrayItem

        // The managers' search: whoever may edit people or the reporting lines.
        'tenant.org-chart.people.options' => ['manage-organization', 'manage-org-chart'], // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.index' => 'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.people.search' => 'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.quality' => 'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.quality.export' => 'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.reports' => 'view-org-chart', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.*' => 'manage-org-chart', // @pest-mutate-ignore: RemoveArrayItem

        // Climate and pulse: SurveyPolicy asks for view-* to look at a survey and manage-* to change it.
        'tenant.climate.index' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.show' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.results' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.results.items' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.export' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.comments' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.managers' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.managers.show' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.commitments.index' => 'view-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.*' => 'manage-climate', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.index' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.show' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.results' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.results.items' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.export' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.managers' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.managers.show' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.commitments.index' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.trend' => 'view-pulse', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.*' => 'manage-pulse', // @pest-mutate-ignore: RemoveArrayItem

        'tenant.compensation.index' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.benefits.index' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.benefits.grades' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.overrides.index' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.overrides.template' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.tabulator.index' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.tabulator.template' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.scenarios.index' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.scenarios.edit' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.scenarios.compare' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.scenarios.benefits' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.scenarios.report' => 'view-compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.*' => 'manage-compensation', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /**
     * Routes whose refusal is a 404, as their controllers always answered: whoever may not see them must not learn they
     * exist. The list of people to channel of Guía I and its record (criterio C-23), and the Comisión's work on the
     * workplaces, checklist and notices of a center (the notices inbox itself answered 403 and still does).
     */
    private const array HIDDEN_WHEN_REFUSED = [
        'tenant.campaigns.screenings', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.screenings.update', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.checklist.draft', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.checklist.publish', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.checklist.questions.*', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.reviews.*', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.review', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.close', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.evidence', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.notices.place-review', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.evidence.store', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.visit-authorizations.store', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.workplaces.remote-check.store', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.workplaces.visit.store', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /** Company routes that ask for no permission, each with its reason; RoutePermissionCoverageTest pins the list. */
    private const array EXEMPT = [
        // Where a person who may open nothing lands (StartRoute): asking for a permission would leave them nowhere to go.
        'tenant.no-access', // @pest-mutate-ignore: RemoveArrayItem
        // The company's address (sign-in, the company switcher, Humi's links): its controller asks for view-dashboard and the
        // centers it is drawn from, and sends whoever lacks them to StartRoute instead of a 403.
        'tenant.dashboard', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /** Whether the route is a company route that asks for no permission. */
    public static function isExempt(string $routeName): bool
    {
        return in_array($routeName, self::EXEMPT, true);
    }

    /** What the route answers to whoever lacks its permission. */
    public static function refusalStatus(string $routeName): int
    {
        return Str::is(self::HIDDEN_WHEN_REFUSED, $routeName) ? 404 : 403;
    }

    /**
     * The permission the route asks for (or the list of which any one opens it), or null when the name is not a company
     * route of the map.
     *
     * @return list<string>|string|null
     */
    public static function for(string $routeName): array|string|null
    {
        foreach (self::PERMISSIONS as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * The permissions any one of which opens the route: empty when the name is not in the map (nobody gets in).
     *
     * @return list<string>
     */
    public static function anyOf(string $routeName): array
    {
        return (array) self::for($routeName);
    }
}
