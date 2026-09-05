<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Exceptions;

use RuntimeException;

/**
 * Somebody tried to delete an article that has been published.
 *
 * It is refused because publishing put the article in front of people. An
 * agent may have linked it into a reply; a customer may be holding the URL.
 * Deleting it turns those into nothing, silently, with no way to find out what
 * used to be there. Archiving keeps the row and takes it out of circulation,
 * which is what "delete" almost always meant anyway.
 *
 * Thrown from the domain rather than checked in the controller, so an
 * administration console, a console command and a future import all get the
 * same answer.
 */
final class CannotDeletePublishedArticle extends RuntimeException
{
    public static function make(): self
    {
        return new self('This article has been published. Archive it instead.');
    }
}
