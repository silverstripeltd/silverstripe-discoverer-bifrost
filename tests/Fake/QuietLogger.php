<?php

namespace SilverStripe\DiscovererBifrost\Tests\Fake;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * A Logger that does nothing
 *
 * Used to silence logging in tests that log messages (otherwise we get a tonne of useless noise in the test report)
 */
class QuietLogger implements LoggerInterface
{

    public function emergency(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

    /**
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        // Specifically do nothing please
    }

}
