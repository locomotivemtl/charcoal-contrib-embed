<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

abstract class AbstractResolver implements ResolverInterface
{
    /**
     * @return ?static
     */
    public static function tryFrom(Extractor $info)
    {
        try {
            return static::from($info);
        } catch (InvalidArgumentException $e) {
            // Do nothing.
        }

        return null;
    }

    /**
     * Filters the array of PREG matches to only keep named capture groups.
     *
     * @param  array<array-key, mixed> $matches
     * @return array<string, mixed>
     */
    protected function filterNamedPregMatches(array $matches): array
    {
        $data = [];

        foreach ($matches as $key => $match) {
            if (is_string($key)) {
                $data[$key] = $match;
            }
        }

        return $data;
    }
}
