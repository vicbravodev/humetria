<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Exceptions;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/** The push service could not be reached. The library catches this one and reports a rejected notice; its message never names the address. */
final class PushNetworkFailed extends RuntimeException implements NetworkExceptionInterface
{
    public function __construct(private readonly RequestInterface $request)
    {
        parent::__construct('The push service could not be reached.');
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
