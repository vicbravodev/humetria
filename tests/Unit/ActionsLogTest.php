<?php

declare(strict_types=1);

/**
 * Actions that may skip their own log line, each with its reason. Anything else that lives in an Actions namespace
 * changes state and must write a `module.action_past` log line.
 */
const ACTIONS_WITHOUT_OWN_LOG = [
    // Read-only: they change nothing.
    'app/Modules/Demos/Actions/ListDemoRequests.php' => 'reads a paginated list',
    'app/Modules/Organization/Actions/CountEmployees.php' => 'counts',
    'app/Modules/Organization/Actions/ListEmployees.php' => 'reads a paginated list',
    'app/Modules/Organization/Actions/PlanEmployeeImport.php' => 'checks the rows without writing; ImportEmployees logs the import',
    'app/Modules/Tenancy/Actions/ListTenants.php' => 'reads a paginated list',
    'app/Modules/Tenancy/Actions/ListUserTenants.php' => 'reads a list',
    'app/Modules/Tenancy/Actions/ListWorkCenters.php' => 'reads a paginated list',
    'app/Modules/Campaigns/Actions/OpenSurvey.php' => 'reads the link; SubmitSurvey logs what changes',
    'app/Modules/Telework/Actions/OpenPortal.php' => 'reads the portal link; each portal action logs what changes',
    // Steps of a logged action: the action that calls them writes the line, with the counts.
    'app/Modules/Campaigns/Actions/CloseExpiredCampaigns.php' => 'each close is logged by CloseCampaign',
    'app/Modules/Campaigns/Actions/QueueSurveyInvitations.php' => 'its callers (launch, resend, invite missing, replacements) log with the counts',
    'app/Modules/Tenancy/Actions/SendInvitationEmail.php' => 'only queues the email; InviteMember, InviteOwner and ResendInvitation log it',
    // The audit trail itself: every caller logs the same event beside it.
    'app/Actions/RecordAudit.php' => 'it is the audit trail; its callers log',
];

/** @return list<string> paths relative to the project root */
function actionFiles(): array
{
    $root = dirname(__DIR__, 2).'/';
    $files = [
        ...glob($root.'app/Modules/*/Actions/*.php') ?: [],
        ...glob($root.'app/Actions/*.php') ?: [],
        ...glob($root.'app/Actions/*/*.php') ?: [],
    ];

    return array_values(array_map(fn (string $file): string => substr($file, strlen($root)), $files));
}

it('has every allowed action still in place', function (): void {
    expect(actionFiles())->toContain(...array_keys(ACTIONS_WITHOUT_OWN_LOG));
});

it('makes every action that changes state write a log line', function (): void {
    $silent = array_values(array_filter(
        array_diff(actionFiles(), array_keys(ACTIONS_WITHOUT_OWN_LOG)),
        fn (string $file): bool => ! str_contains((string) file_get_contents(dirname(__DIR__, 2).'/'.$file), 'Log::'),
    ));

    expect($silent)->toBe([]);
});
