<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/** What the room shares of the demo company in a live session: links the story knows how to find. */
interface DemoShowcase
{
    /** The public address of the demo's complaint channel; null when there is no demo. */
    public function complaintChannelUrl(): ?string;

    /**
     * A new link for the demo's time-clock screen: the one before dies and the screen waits, not activated, until the link
     * is opened. Null when the demo has no screen to link, or its screen already shows the code.
     */
    public function kioskLink(): ?string;
}
