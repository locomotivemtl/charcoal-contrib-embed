<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

interface ResolverInterface
{
    /**
     * @throws InvalidArgumentException
     * @return static
     */
    public static function from(Extractor $info);

    /**
     * @return ?static
     */
    public static function tryFrom(Extractor $info);

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function format(array $data = []): array;
}
