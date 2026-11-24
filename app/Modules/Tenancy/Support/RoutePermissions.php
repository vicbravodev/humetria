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
 * The account pages (settings.*, logout) and the company switcher live outside the company group; inside it, only the
 * routes of EXEMPT ask for no permission, each for its reason.
 */
final class RoutePermissions
{
    /** @var array<string, string> route name pattern => permission */
    private const array PERMISSIONS = [
        // What a company has whatever its modules (the panel itself is in EXEMPT).
        'tenant.work-centers.index' => 'view-work-centers',
        'tenant.work-centers.options' => 'view-work-centers',
        // The route of one standard in one center sends to its page; the NOM-035 one is the route permission's.
        'tenant.work-centers.route' => 'view-nom035-route',
        // «¿Te aplica?» and the first questions decide NOM-037 and Ley Silla: their controllers ask for both managers.
        'tenant.work-centers.applies' => 'manage-seating',
        'tenant.work-centers.onboarding' => 'manage-telework',
        'tenant.work-centers.onboarding.update' => 'manage-telework',
        'tenant.work-centers.applicability.update' => 'manage-telework',
        'tenant.work-centers.*' => 'manage-organization',
        'tenant.nom035.*' => 'view-nom035-route',
        'tenant.employees.*' => 'manage-organization',
        // The option lists of areas and job grades: every member could search them, like the list of centers.
        'tenant.areas.*' => 'view-work-centers',
        'tenant.job-grades.*' => 'view-work-centers',
        // The owner's: people and roles, the plan, the privacy minimum, single sign-on and the guided tour.
        'tenant.members.*' => 'manage-members',
        'tenant.roles.*' => 'manage-members',
        'tenant.plan' => 'manage-members',
        'tenant.privacy.*' => 'manage-members',
        'tenant.sso.*' => 'manage-members',
        'tenant.first-steps.*' => 'manage-members',
        'tenant.assistant.*' => 'use-assistant',
        'tenant.worker-access.index' => 'view-worker-access',
        'tenant.worker-access.*' => 'manage-worker-access',

        // NOM-035. The evaluations and their aggregated results are seen by whoever sees the NOM-035 route (no Gate
        // refused them); the Guía I list is the trauma permission's, and recording its channeling is part of seeing it.
        'tenant.campaigns.index' => 'view-nom035-route',
        'tenant.campaigns.show' => 'view-nom035-route',
        'tenant.campaigns.results' => 'view-nom035-route',
        'tenant.campaigns.report' => 'view-nom035-route',
        'tenant.campaigns.screenings' => 'view-trauma-screenings',
        'tenant.campaigns.screenings.update' => 'view-trauma-screenings',
        'tenant.campaigns.program' => 'view-interventions',
        'tenant.campaigns.program.*' => 'manage-interventions',
        'tenant.campaigns.*' => 'manage-campaigns',
        'tenant.interventions.index' => 'view-interventions',
        'tenant.interventions.*' => 'manage-interventions',
        'tenant.disclosure.index' => 'view-disclosure',
        'tenant.disclosure.policy' => 'view-disclosure',
        'tenant.disclosure.policy.version' => 'view-disclosure',
        'tenant.disclosure.*' => 'manage-disclosure',

        // NOM-037. Whoever sees it reads the documents, the photos and the visit papers (ReviewAccess::reader() then asks
        // for RH or the Comisión of the center); the Comisión's review of workplaces, notices and checklist is its own.
        'tenant.telework.index' => 'view-telework',
        'tenant.telework.center' => 'view-telework',
        'tenant.telework.route' => 'view-telework',
        'tenant.telework.inspection' => 'view-telework',
        'tenant.telework.certificate' => 'view-telework',
        'tenant.telework.dossier' => 'view-telework',
        'tenant.telework.supporting.index' => 'view-telework',
        'tenant.telework.work-centers.options' => 'view-telework',
        'tenant.telework.committee.show' => 'view-telework',
        'tenant.telework.committee.act' => 'view-telework',
        'tenant.telework.documents.index' => 'view-telework',
        'tenant.telework.documents.file' => 'view-telework',
        'tenant.telework.documents.acknowledge' => 'view-telework',
        'tenant.telework.documents.acknowledgements.index' => 'view-telework',
        'tenant.telework.checklist.show' => 'view-telework',
        'tenant.telework.photos.show' => 'view-telework',
        'tenant.telework.evidence-photos.show' => 'view-telework',
        'tenant.telework.visit-authorizations.document.*' => 'view-telework',
        'tenant.telework.visit-authorizations.cancel' => 'view-telework',
        'tenant.telework.visit-authorizations.store' => 'review-workplaces',
        'tenant.telework.checklist.*' => 'review-workplaces',
        'tenant.telework.reviews.*' => 'review-workplaces',
        'tenant.telework.notices.*' => 'review-workplaces',
        'tenant.telework.evidence.store' => 'review-workplaces',
        'tenant.telework.workplaces.remote-check.store' => 'review-workplaces',
        'tenant.telework.workplaces.visit.store' => 'review-workplaces',
        'tenant.telework.*' => 'manage-telework',

        // Ley Silla. The assessment of risk (the Comisión's and RH's) opens its own pages and the files of the evidence.
        'tenant.seating.index' => 'view-seating',
        'tenant.seating.center' => 'view-seating',
        'tenant.seating.route' => 'view-seating',
        'tenant.seating.inspection' => 'view-seating',
        'tenant.seating.certificate' => 'view-seating',
        'tenant.seating.seats.index' => 'view-seating',
        'tenant.seating.positions.show' => 'view-seating',
        'tenant.seating.assessments.*' => 'assess-seating',
        'tenant.seating.documents.index' => 'assess-seating',
        'tenant.seating.documents.options' => 'assess-seating',
        'tenant.seating.documents.file' => 'assess-seating',
        'tenant.seating.dossier' => 'assess-seating',
        'tenant.seating.plan' => 'assess-seating',
        'tenant.seating.reports.index' => 'assess-seating',
        'tenant.seating.regulation.show' => 'assess-seating',
        'tenant.seating.regulation.file' => 'assess-seating',
        'tenant.seating.measures.propose' => 'assess-seating',
        'tenant.seating.measures.evidence' => 'assess-seating',
        'tenant.seating.pause-agreements.evidence' => 'assess-seating',
        'tenant.seating.seats.photo' => 'assess-seating',
        'tenant.seating.referrals.store' => 'assess-seating',
        'tenant.seating.*' => 'manage-seating',

        // The complaints channel: the cases are the handlers'; how the channel is set up, its manager's.
        'tenant.complaints.index' => 'handle-complaints',
        'tenant.complaints.cases.*' => 'handle-complaints',
        'tenant.complaints.centers.options' => 'handle-complaints',
        'tenant.complaints.procedure' => 'handle-complaints',
        'tenant.complaints.*' => 'manage-complaint-channel',

        'tenant.attendance.index' => 'view-attendance',
        'tenant.attendance.center' => 'view-attendance',
        'tenant.attendance.setup' => 'view-attendance',
        'tenant.attendance.corrections.index' => 'view-attendance',
        'tenant.attendance.policy.show' => 'view-attendance',
        'tenant.attendance.schedules.index' => 'view-attendance',
        'tenant.attendance.*' => 'manage-attendance',

        'tenant.vacations.index' => 'view-vacations',
        'tenant.vacations.calendar' => 'view-vacations',
        'tenant.vacations.balances.index' => 'view-vacations',
        'tenant.vacations.employees.show' => 'view-vacations',
        'tenant.vacations.statements.show' => 'view-vacations',
        'tenant.vacations.policies.index' => 'view-vacations',
        'tenant.vacations.policies.edit' => 'view-vacations',
        'tenant.vacations.requests.index' => 'view-vacations',
        'tenant.vacations.settings.show' => 'view-vacations',
        'tenant.vacations.*' => 'manage-vacations',

        'tenant.org-chart.index' => 'view-org-chart',
        'tenant.org-chart.people.search' => 'view-org-chart',
        'tenant.org-chart.quality' => 'view-org-chart',
        'tenant.org-chart.quality.export' => 'view-org-chart',
        'tenant.org-chart.reports' => 'view-org-chart',
        'tenant.org-chart.*' => 'manage-org-chart',

        // Climate and pulse: SurveyPolicy asks for view-* to look at a survey and manage-* to change it.
        'tenant.climate.index' => 'view-climate',
        'tenant.climate.show' => 'view-climate',
        'tenant.climate.results' => 'view-climate',
        'tenant.climate.results.items' => 'view-climate',
        'tenant.climate.export' => 'view-climate',
        'tenant.climate.comments' => 'view-climate',
        'tenant.climate.managers' => 'view-climate',
        'tenant.climate.managers.show' => 'view-climate',
        'tenant.climate.commitments.index' => 'view-climate',
        'tenant.climate.*' => 'manage-climate',
        'tenant.pulse.index' => 'view-pulse',
        'tenant.pulse.show' => 'view-pulse',
        'tenant.pulse.results' => 'view-pulse',
        'tenant.pulse.results.items' => 'view-pulse',
        'tenant.pulse.export' => 'view-pulse',
        'tenant.pulse.managers' => 'view-pulse',
        'tenant.pulse.managers.show' => 'view-pulse',
        'tenant.pulse.commitments.index' => 'view-pulse',
        'tenant.pulse.trend' => 'view-pulse',
        'tenant.pulse.*' => 'manage-pulse',

        'tenant.compensation.index' => 'view-compensation',
        'tenant.compensation.benefits.index' => 'view-compensation',
        'tenant.compensation.benefits.grades' => 'view-compensation',
        'tenant.compensation.overrides.index' => 'view-compensation',
        'tenant.compensation.overrides.template' => 'view-compensation',
        'tenant.compensation.tabulator.index' => 'view-compensation',
        'tenant.compensation.tabulator.template' => 'view-compensation',
        'tenant.compensation.scenarios.index' => 'view-compensation',
        'tenant.compensation.scenarios.edit' => 'view-compensation',
        'tenant.compensation.scenarios.compare' => 'view-compensation',
        'tenant.compensation.scenarios.benefits' => 'view-compensation',
        'tenant.compensation.scenarios.report' => 'view-compensation',
        'tenant.compensation.*' => 'manage-compensation',
    ];

    /**
     * Routes whose refusal is a 404, as their controllers always answered: whoever may not see them must not learn they
     * exist. The list of people to channel of Guía I and its record (criterio C-23), and the Comisión's work on the
     * workplaces, checklist and notices of a center (the notices inbox itself answered 403 and still does).
     */
    private const array HIDDEN_WHEN_REFUSED = [
        'tenant.campaigns.screenings',
        'tenant.campaigns.screenings.update',
        'tenant.telework.checklist.draft',
        'tenant.telework.checklist.publish',
        'tenant.telework.checklist.questions.*',
        'tenant.telework.reviews.*',
        'tenant.telework.notices.review',
        'tenant.telework.notices.close',
        'tenant.telework.notices.evidence',
        'tenant.telework.notices.place-review',
        'tenant.telework.evidence.store',
        'tenant.telework.visit-authorizations.store',
        'tenant.telework.workplaces.remote-check.store',
        'tenant.telework.workplaces.visit.store',
    ];

    /** Company routes that ask for no permission, each with its reason; RoutePermissionCoverageTest pins the list. */
    private const array EXEMPT = [
        // Where a person who may open nothing lands (StartRoute): asking for a permission would leave them nowhere to go.
        'tenant.no-access',
        // The company's address (sign-in, the company switcher, Humi's links): its controller asks for view-dashboard and the
        // centers it is drawn from, and sends whoever lacks them to StartRoute instead of a 403.
        'tenant.dashboard',
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

    /** The permission the route asks for, or null when the name is not a company route of the map. */
    public static function for(string $routeName): ?string
    {
        foreach (self::PERMISSIONS as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $permission;
            }
        }

        return null;
    }
}
