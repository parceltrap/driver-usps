<?php

declare(strict_types=1);

namespace ParcelTrap\USPS\Exceptions;

use ParcelTrap\Contracts\ParcelTrapException;
use RuntimeException;

class USPSErrorResponseException extends RuntimeException implements ParcelTrapException
{
}
