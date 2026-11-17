<?php

declare(strict_types=1);

/*
 * The posters to print (Difusión and the complaints channel) share one sheet: these checks read it the same way on both.
 */

/**
 * The preview is the paper (sección 10 del pulido): a letter sheet, white even when the app is dark, scaled so it is seen whole
 * (on a wide screen without scrolling) and never wider than the window.
 */
function posterSheetIsPaper(): string
{
    return <<<'JS'
        () => {
            const sheet = document.querySelector('[data-slot="poster-sheet"]');
            const box = sheet.getBoundingClientRect();

            return getComputedStyle(sheet).backgroundColor === 'rgb(255, 255, 255)'
                && Math.abs(box.width / box.height - 8.5 / 11) < 0.01
                && box.left >= 0 && box.right <= window.innerWidth
                && (window.innerWidth < 1024 || box.bottom <= window.innerHeight);
        }
        JS;
}

/** The address under the QR breaks only between its parts, never in the middle of one, and fits its column. */
function posterAddressIsWhole(): string
{
    return <<<'JS'
        () => {
            const address = document.querySelector('[data-slot="poster-address"]');
            const walker = document.createTreeWalker(address, NodeFilter.SHOW_TEXT);
            const characters = [];

            for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
                for (let offset = 0; offset < node.length; offset++) {
                    const range = document.createRange();
                    range.setStart(node, offset);
                    range.setEnd(node, offset + 1);
                    characters.push({ character: node.data[offset], top: range.getBoundingClientRect().top });
                }
            }

            const breaksOnlyAfterSlash = characters.every((current, index) => index === 0
                || current.top <= characters[index - 1].top + 1
                || characters[index - 1].character === '/');

            return breaksOnlyAfterSlash && address.scrollWidth <= address.clientWidth;
        }
        JS;
}

/** On paper only the sheet is left, at its real size, white, without the controls or the stage around it. */
function posterPrintsAlone(): string
{
    return <<<'JS'
        () => {
            const sheet = document.querySelector('[data-slot="poster-sheet"]');
            const controls = document.querySelector('[data-slot="poster-controls"]');

            return getComputedStyle(controls).display === 'none'
                && getComputedStyle(sheet).zoom === '1'
                && getComputedStyle(sheet).backgroundColor === 'rgb(255, 255, 255)'
                && getComputedStyle(sheet.parentElement).backgroundColor === 'rgba(0, 0, 0, 0)';
        }
        JS;
}
