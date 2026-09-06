<?php

declare(strict_types=1);

namespace App\Modules\Ai\Contracts;

/**
 * Text that has been through the sanitiser, and the only thing a transport
 * will accept.
 *
 * It lives in `Contracts/` because it IS the contract: the transport's
 * signature is what makes the sanitiser unbypassable, and a type that appears
 * in a signature belongs beside the interface that uses it rather than in the
 * domain that happens to build it.
 *
 * A TYPE rather than a convention. The transport's signature takes one of
 * these, so "did this go through the sanitiser?" is answered by the compiler
 * on every call rather than by whoever is reading the diff. There is no flag
 * that turns the sanitiser off and no second method that skips it, because
 * there is no other way to produce this object.
 *
 * `SanitiserIsUnbypassableTest` pins the one place it may be constructed. That
 * pin is the whole guarantee: PHP cannot make a constructor callable from one
 * class only, so the rule is held by a test that fails the build instead.
 */
final readonly class SanitisedPrompt
{
    /**
     * @param  string  $text  What will actually be sent.
     * @param  bool  $shortened  Whether the cap dropped anything. The transport
     *                           passes this on, so a model is never told the
     *                           whole story arrived when it did not.
     */
    public function __construct(
        public string $text,
        public bool $shortened = false,
    ) {}
}
