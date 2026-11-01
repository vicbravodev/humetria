<?php

declare(strict_types=1);

it('limits uploads to 8 MB for photos and 20 MB for documents', function (): void {
    expect(config('telework.max_photo_kb'))->toBe(8192)
        ->and(config('telework.max_document_kb'))->toBe(20480);
});

it('keeps the evidence on a private disk, never at a public URL', function (): void {
    $disk = config()->string('filesystems.telework_files');

    expect($disk)->toBe('local')
        ->and(config("filesystems.disks.{$disk}.url"))->toBeNull()
        ->and(config("filesystems.disks.{$disk}.visibility"))->not->toBe('public');
});
