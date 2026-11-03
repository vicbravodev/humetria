<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Exceptions;

use RuntimeException;

/**
 * The conversation is not the person's in this company: it belongs to someone else, to another company, or does not
 * exist. All three are the same, so an id reveals nothing.
 */
final class ConversationNotFound extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Conversation not found.');
    }
}
