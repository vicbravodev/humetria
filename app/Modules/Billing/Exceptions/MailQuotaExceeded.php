<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A bulk send does not fit in the campaign email the company's plan includes this month. Its message is for the person: how many are missing and what to do. */
final class MailQuotaExceeded extends RuntimeException {}
