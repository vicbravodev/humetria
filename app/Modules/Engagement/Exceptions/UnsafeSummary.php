<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Exceptions;

use RuntimeException;

/** The summary the model gave cannot be shown: it quotes a comment, carries a figure or a quote, or is not the shape asked. */
final class UnsafeSummary extends RuntimeException {}
