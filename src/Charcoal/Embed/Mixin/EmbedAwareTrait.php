<?php

namespace Charcoal\Embed\Mixin;

use Charcoal\Embed\Contract\EmbedRepositoryInterface;
use Charcoal\Translator\Translation;
use DOMDocument;
use DOMElement;
use Embed\Embed;
use Exception;

/**
 * Provides support for the use of embedded ressource.
 */
trait EmbedAwareTrait
{
    /**
     * Format the embed link.
     *
     * @param  mixed  $value  The link to the embedable resource.
     * @param  string $format The format in which to return the embed.
     * @return mixed
     */
    public function formatEmbed(
        $value,
        string $format = EmbedRepositoryInterface::FORMAT_HTML
    ) {
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
                    $value[$k] = $this->resolveEmbedFormat($v, $format);
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
     * @param  string $url    The URL to the embedable resource.
     * @param  string $format The format in which to return the embed. Defaults to 'iframe'.
     * @return array<string, mixed>|string|null Returns a string of HTML or the URL of the source object.
     */
    protected function resolveEmbedFormat(
        string $url,
        string $format = EmbedRepositoryInterface::FORMAT_HTML
    ) {
        $url = trim($url);

        if (!$url) {
            return null;
        }

        $embed = (new Embed())->get($url);

        if (empty($embed->code->html)) {
            return null;
        }

        // Strip width/height from iframe.
        $iframe = preg_replace('~\s*(width|height)=["\'][^"\']+["\']~', '', $embed->code->html);

        // Fix unencoded ampersands
        $iframe = preg_replace('~&(?!amp;)~i', '&amp;', $iframe);

        $src = $url;

        if (strpos($iframe, '<iframe') !== false) {
            // Extract the `src` attribute from embedable iframe.
            $doc = new DOMDocument();
            if ($doc->loadHTML($iframe)) {
                $elems = $doc->getElementsByTagName('iframe');
                if ($elems->length > 0) {
                    $elem = $elems->item(0);
                    if (
                        $elem &&
                        ($elem instanceof DOMElement) &&
                        $elem->hasAttribute('src')
                    ) {
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
            $image    = $embed->image;
            $id       = null;

            switch ($provider) {
                case 'youtube': {
                    $regExp = '!^(?:https?://)?(?:.+\.)?(?:youtube(?:-nocookie)?\.com\/(?:(?:v|e(?:mbed)?)/|watch/|[^/\s]+\/.+/|.*[?&]v=)|youtu\.be/)(?<playback_id>[\w\-]{11})!i';
                    if (preg_match($regExp, $url, $match)) {
                        $id = $match['playback_id'];
                    }
                    break;
                }

                case 'vimeo': {
                    $regExp = '!^(?:https?://)?(?:.+\.)?vimeo\.com/(?:(?:progressive_redirect/playback|video)/)?(?<playback_id>\d+)!i';
                    if (preg_match($regExp, $url, $match)) {
                        $id = $match['playback_id'];
                    }
                    break;
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
