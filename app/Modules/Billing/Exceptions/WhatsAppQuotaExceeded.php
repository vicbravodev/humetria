<?php

declare(strict_types=1);

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

/** A launch does not fit in the WhatsApp messages the company's plan and cap leave this month. Its message is for the person. */
final class WhatsAppQuotaExceeded extends RuntimeException {}
