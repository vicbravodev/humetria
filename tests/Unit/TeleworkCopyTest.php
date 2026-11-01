<?php

declare(strict_types=1);

/*
 * The portal says what is recorded when a person confirms something: date, time and IP address (Acknowledge and
 * ConfirmVisitAuthorization store nothing else). It must not promise a device.
 */
it('does not promise the portal records the device', function (): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../resources/js/components/telework', FilesystemIterator::SKIP_DOTS));
    $promising = [];

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'tsx' && str_contains((string) file_get_contents($file->getPathname()), 'dispositivo')) {
            $promising[] = $file->getFilename();
        }
    }

    expect($promising)->toBe([]);
});
