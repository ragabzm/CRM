<?php

declare(strict_types=1);

namespace App\Modules\Channels\Exceptions;

use RuntimeException;

/**
 * The adapter could not make a message out of what the provider sent.
 *
 * Thrown, not returned, because there is no partial answer worth having: a
 * half-read message attached to a plausible ticket is worse than one an
 * administrator can see was not handled. The spine catches it and quarantines
 * the raw payload, so nothing is ever discarded.
 */
final class UnparseableChannelPayload extends RuntimeException {}
