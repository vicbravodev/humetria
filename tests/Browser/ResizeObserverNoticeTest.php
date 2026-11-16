<?php

declare(strict_types=1);

it('does not raise the ResizeObserver loop notice when a callback resizes what it observes', function (): void {
    // The case behind the notice, as a floating menu or a chart can under load: the callback changes the size of what it observes.
    $page = visit('/admin/login')->assertNoJavaScriptErrors();

    $raised = $page->script(<<<'JS'
            () => new Promise((done) => {
                const box = document.createElement('div');
                box.style.width = '10px';
                box.style.height = '10px';
                document.body.append(box);
                let wide = false;
                const observer = new ResizeObserver(() => {
                    wide = !wide;
                    box.style.width = wide ? '30px' : '10px';
                });
                observer.observe(box);
                setTimeout(() => {
                    observer.disconnect();
                    box.remove();
                    done(true);
                }, 400);
            })
            JS);

    expect($raised)->toBeTrue();

    $page->assertNoJavaScriptErrors();
});
