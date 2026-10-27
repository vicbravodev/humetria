<?php

declare(strict_types=1);

it('loads Figtree and no longer Instrument Sans', function (): void {
    $this->get(route('login'))->assertOk()
        ->assertSee('figtree-400-normal', false)
        ->assertDontSee('instrument-sans', false);
});
