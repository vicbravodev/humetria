<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Models\Complaint;
use Illuminate\Validation\ValidationException;

/**
 * Finds the case that a reference and a key open, inside the company of the channel. A reference that does not exist and a
 * wrong key give the same answer, and the same work is done in both (the key is hashed and compared either way), so neither
 * the words nor the time tell which one failed.
 */
final readonly class OpenFollowUp
{
    private const string DENIED = 'La referencia o la clave no coinciden.';

    /**
     * @throws ValidationException
     */
    public function handle(string $reference, string $key): Complaint
    {
        $complaint = Complaint::query()->where('reference', self::plain($reference))->first();
        $expected = $complaint === null ? hash('sha256', 'no-complaint') : $complaint->access_key_hash;

        if (! hash_equals($expected, hash('sha256', self::plain($key))) || $complaint === null) {
            throw ValidationException::withMessages(['reference' => self::DENIED]);
        }

        return $complaint;
    }

    /** The code as the person may have typed it: any case, with spaces or dashes between the groups. */
    private static function plain(string $text): string
    {
        return strtoupper((string) preg_replace('/[\s-]+/', '', $text));
    }
}
