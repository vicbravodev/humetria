<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/** What the room shares of the demo company in a live session: links the story knows how to find. */
interface DemoShowcase
{
    /** The public address of the demo's complaint channel; null when there is no demo. */
    public function complaintChannelUrl(): ?string;

    /**
     * A new link for the time-clock screen of each center of the demo, by the center's name: the one before dies and the
     * screen waits, not activated, until the link is opened. A screen that already shows the code is left out; empty when
     * there is no demo or no screen to link.
     *
     * @return list<array{center: string, url: string}>
     */
    public function kioskLinks(): array;

    /**
     * A new one-use portal link of `$employeeId`, issued as the demo's head of HR and sent nowhere, for the presenter to open on
     * their own computer; it revokes the person's links before. Null, issuing nothing, unless a session is under way and the
     * person is active in its center.
     */
    public function portalLinkFor(string $employeeId): ?string;
}
