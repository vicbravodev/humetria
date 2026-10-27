<?php

declare(strict_types=1);

it('shows a solid outline on the control focused by keyboard', function (string $mode): void {
    $focused = 'document.activeElement.dataset.slot + ":" + getComputedStyle(document.activeElement).outlineStyle';

    visit('/login')->{$mode}()
        ->keys('#email', 'a')
        ->assertScript($focused, 'input:solid')
        ->keys('#remember', 'Tab')
        ->assertScript($focused, 'button:solid')
        ->keys('button[type="submit"]', 'Shift+Tab')
        ->assertScript($focused, 'checkbox:solid');
})->with(['inLightMode', 'inDarkMode']);
