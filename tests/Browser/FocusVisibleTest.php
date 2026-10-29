<?php

declare(strict_types=1);

it('shows a solid outline on the control focused by keyboard', function (string $mode): void {
    $focused = 'document.activeElement.dataset.slot + ":" + getComputedStyle(document.activeElement).outlineStyle';

    visit('/admin/login')->{$mode}()
        ->keys('#email', 'a')
        ->assertScript($focused, 'input:solid')
        // The focused field also turns its border to the focus color.
        ->assertScript('() => { const probe = document.createElement("span"); probe.style.color = "var(--ring)"; document.body.append(probe); const ring = getComputedStyle(probe).color; probe.remove(); return getComputedStyle(document.activeElement).borderColor === ring; }')
        // The password and its show/hide button are one field: the outline goes around both.
        ->keys('#password', 'a')
        ->assertScript('() => getComputedStyle(document.activeElement.closest(\'[data-slot="input-group"]\')).outlineStyle', 'solid')
        ->keys('#remember', 'Tab')
        ->assertScript($focused, 'button:solid')
        ->keys('button[type="submit"]', 'Shift+Tab')
        ->assertScript($focused, 'checkbox:solid')
        ->assertScript(cspViolations(), '[]');
})->with(['inLightMode', 'inDarkMode']);
