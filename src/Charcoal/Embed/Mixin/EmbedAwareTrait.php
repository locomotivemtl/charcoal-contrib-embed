<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Translator\Translation;
use Embed\Embed;
use Exception;

/**
 * Provides support for the use of embedded ressource.
 */
trait EmbedAwareTrait
{
    /**
     * Whether the embed is loaded.
     *
     * @var boolean
     */
    protected $isEmbedLoaded = false;

    /**
     * Format the embed link.
     *
     * @param  mixed       $value  The link to the embedable resource.
     * @param  string|null $format The format in which to return the embed.
     * @return Translation|array|string|null
     */
    public function formatEmbed($value, $format = null)
    {
        if (empty($value) && !is_numeric($value)) {
            return $value;
        }

        if ($value instanceof Translation) {
            foreach ($value->data() as $lang => $trans) {
                try {
                    $value[$lang] = (string)$this->resolveEmbedFormat($trans, $format);
                } catch (Exception $e) {
                    $value[$lang] = null;
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                try {
                    $value[$k] = $this->resolveEmbedFormat($value[$k], $format);
                } catch (Exception $e) {
                    $value[$k] = null;
                }
            }
        } else {
            try {
                $value = $this->resolveEmbedFormat($value, $format);
            } catch (Exception $e) {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * Resolve the format of an embedable resource.
     *
     * @param  string      $url    The URL to the embedable resource.
     * @param  string|null $format The format in which to return the embed. Defaults to 'iframe'.
     * @return array|string|null Returns a string of HTML or the URL of the source object.
     */
    private function resolveEmbedFormat($url, $format = null)
    {
        if (is_string($url)) {
            $url = trim($url);
        }

        if (!$url) {
            return null;
        }

        $embed = Embed::create($url);

        if (empty($embed->code)) {
            return null;
        }

        // Strip width/height from iframe.
        $iframe = preg_replace('~\s*(width|height)=["\'][^"\']+["\']~', '', $embed->code);

        // Fix unencoded ampersands
        $iframe = preg_replace('~&(?!amp;)~i', '&amp;', $iframe);

        $src = $url;

        if (strpos($iframe, 'iframe') !== false) {
            // Extract the `src` attribute from embedable iframe.
            $doc = new \DOMDocument();
            if ($doc->loadHTML($iframe)) {
                $elems = $doc->getElementsByTagName('iframe');
                if ($elems->length > 0) {
                    $elem = $elems->item(0);
                    if ($elem->hasAttribute('src')) {
                        $src = $elem->getAttribute('src');
                    }
                }
            }
        }

        if ($format === EmbedRepositoryInterface::FORMAT_SRC) {
            return $src;
        }

        if ($format === EmbedRepositoryInterface::FORMAT_ARRAY) {
            $provider = strtolower($embed->providerName);

            // Extract an image preview from embedable iframe.
            // Defaults to the image extracted by the Embed object.
            $images = $embed->images;
            $image  = $embed->image;

            if (count($images) > 1) {
                if ($provider === 'youtube') {
                    // The largest image available for YouTube will be near the end of the images array.
                    // However, the last image image is some kind of tracking pixel.
                    $images = array_slice($images, 0, -1);
                    $image  = array_pop($images)['url'];
                } elseif ($provider === 'vimeo') {
                    // Vimeo sticks an overlay on their best quality image. Find the width, and replace it on the
                    // smaller image (without an overlay).
                    $smallImage = $images[0];
                    $largeImage = array_pop($images);

                    if ($largeImage) {
                        $image = preg_replace(
                            '/_(\d+)?(x)?(\d+)?\.[\w-]+$/',
                            sprintf(
                                '_%sx%s.jpg',
                                $largeImage['width'],
                                $largeImage['height']
                            ),
                            $smallImage['url']
                        );
                    }
                }
            }

            $id = null;
            if ($provider === 'youtube') {
                $regExp = '/^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#\&\?]*).*/';
                if (preg_match($regExp, $url, $match) && isset($match[7]) && strlen($match[7]) === 11) {
                    $id = $match[7];
                }
            } else {
                $regExp = '/^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/))?([0-9]+)/';
                if (preg_match($regExp, $url, $match) && isset($match[5])) {
                    $id = $match[5];
                }
            }

            return [
                'iframe'   => $iframe,
                'src'      => $src,
                'image'    => $image,
                'provider' => $provider,
                'id'       => $id,
            ];
        }

        return $iframe;
    }
}
