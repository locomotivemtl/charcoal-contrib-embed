<?php

namespace Charcoal\Embed\Service;

use Charcoal\Embed\Service\Resolver\ResolverInterface;
use DOMDocument;
use DOMElement;
use Embed\Embed;
use Embed\Extractor;
use Exception;

/**
 * Decorator for {@see \Embed}
 */
class EmbedResolver
{
    /**
     * Fetches a formatted dataset from URL's embed information.
     *
     * @return ?array<string, mixed>
     */
    public function fetchData(string $url): ?array
    {
        $info = $this->fetch($url);
        if (!$info) {
            return null;
        }

        return $this->formatData($info);
    }

    /**
     * Fetches the URL's embed information.
     */
    public function fetch(string $url): ?Extractor
    {
        try {
            /**
             * Convert any warnings and notices into exceptions.
             *
             * @todo Remove when migrating to PHP 8.
             *
             * Based on {@link https://www.php.net/manual/en/class.errorexception.php ErrorException} documentation.
             */
            set_error_handler(fn(int $errno, string $errstr, string $errfile, int $errline) => $this->handleEmbedError($errno, $errstr, $errfile, $errline));

            /** @todo Log extraction for debugging. */
            $info = (new Embed())->get($url);

            restore_error_handler();

            return $info;
        } catch (Exception $e) {
            /** @todo Log exception. */
            error_log('Error resolving embed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatData(Extractor $info): array
    {
        $html = $this->sanitizeIframe($info->code->html);
        $src  = $this->extractSrcFromIframe($html) ?: (string) $info->url;

        $data = [
            'iframe'   => $html,
            'src'      => $src,
            'image'    => (string) $info->image,
            'provider' => strtolower($info->providerName),
            'type'     => $info->getOEmbed()->str('type'),
            'id'       => null,
        ];

        /** @var class-string<ResolverInterface> $customResolverClass */
        foreach ($this->getCustomResolvers() as $customResolverClass) {
            if (!$this->isValidCustomResolverClass($customResolverClass)) {
                continue;
            }

            /** @var ?ResolverInterface */
            $customResolver = $customResolverClass::tryFrom($info);
            if (!$customResolver) {
                continue;
            }

            return $customResolver->format($data);
        }

        return $data;
    }

    /**
     * @return class-string<ResolverInterface>[]
     */
    public function getCustomResolvers(): array
    {
        return [
            Resolver\BunnyStream::class,
            Resolver\CloudflareStream::class,
            Resolver\Mux::class,
            Resolver\Vimeo::class,
            Resolver\YouTube::class,
        ];
    }

    /**
     * Extracts the URL from the `src` attribute of an `<iframe>`.
     */
    protected function extractSrcFromIframe(string $iframe): ?string
    {
        return $this->extractValueFromHtmlAttribute($iframe, 'iframe', 'src');
    }

    /**
     * Extracts the value from an attribute of an HTML element.
     *
     * @param  string $html The HTML snippet to parse.
     * @param  string $tag  The HTML element to lookup.
     * @param  string $attr The HTML attribute to extract.
     */
    protected function extractValueFromHtmlAttribute(string $html, string $tag, string $attr): ?string
    {
        $doc = new DOMDocument();
        $libXmlState = libxml_use_internal_errors(true);
        libxml_use_internal_errors($libXmlState);

        if (!$doc->loadHTML($html)) {
            /** @todo Log errors from {@see libxml_get_errors()}. */
            libxml_clear_errors();
            return null;
        }

        $elems = $doc->getElementsByTagName($tag);
        if ($elems->length === 0) {
            return null;
        }

        $elem = $elems->item(0);
        if (
            $elem instanceof DOMElement &&
            $elem->hasAttribute($attr)
        ) {
            return $elem->getAttribute($attr);
        }

        return null;
    }

    protected function isValidCustomResolverClass(string $class): bool
    {
        return (
            class_exists($class) &&
            class_implements($class, ResolverInterface::class)
        );
    }

    /**
     * Sanitizes an HTML `<iframe>` element.
     *
     * @param  string $iframe
     * @return string
     */
    protected function sanitizeIframe(string $iframe): ?string
    {
        $replacements = [
            /** Strip width/height from iframe. */
            '~\s*(width|height)=["\'][^"\']+["\']~' => '',
            /** Fix unencoded ampersands */
            '~&(?!amp;)~i'                          => '&amp;',
        ];

        return preg_replace(
            array_keys($replacements),
            array_values($replacements),
            $iframe
        );
    }

    /**
     * @throws \ErrorException
     */
    private function handleEmbedError(int $errno, string $errstr, string $errfile, int $errline) : void
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting.
            return;
        }

        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            // Do not throw an Exception for deprecation warnings as new or unexpected
            // deprecations would break the application.
            return;
        }

        /**
         * The {@see \mb_encoding_aliases()} function is called from the HTTP Content-Type
         * in {@see \Embed\Document}.
         *
         * If the encoding is unsupported, a warning is logged in PHP 7 and
         * a {@see \ValueError} is thrown in PHP 8.
         */
        if (strpos($errstr, 'mb_encoding_aliases') !== false) {
            return;
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
}
