<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

/**
 * Vimeo
 *
 * A video-hosting and social media platform.
 *
 * Supported URL patterns:
 *
 * - Playback URL:
 *   - Public URL: `https://vimeo.com/{asset_id}`
 *   - Unlisted URL: `https://vimeo.com/{asset_id}/{privacy_key}`
 *   - Permalink URL: `https://vimeo.com/{customer_handle}/{asset_slug}`
 * - Embed URL: `https://player.vimeo.com/video/{asset_id}?h={privacy_key}`
 * - Stream URL (MP4): `https://player.vimeo.com/progressive_redirect/playback/{asset_id}/rendition/1080p/file.mp4?…`
 *
 * URL pattern variables:
 *
 * - `asset_id` represents the video ID.
 * - `asset_slug` represents the video's {@link https://help.vimeo.com/hc/articles/12426180984465 custom URL}.
 *   The video ID is expected be recoverable from the oEmbed's `<iframe>`.
 * - `customer_handle` represents the customer account handle
 *   present when using a custom URL.
 * - `privacy_key` represents the token to view the unlisted video.
 *
 * Notes:
 *
 * - The `canonical_url` represents the video's URL on the platform.
 * - The `stream_url` is unavailable because it requires access to an API.
 */
class Vimeo extends AbstractResolver
{
    public const REGEXP_URL_PATTERN = '~^(?:https?://)?(?:.+\.)?vimeo\.com/(?:(?:(?:(?:progressive_redirect/playback|video)/)?(?<asset_id>\d+)(?:(?:/|(?:\?|.+\&)h=)(?!rendition)(?<privacy_key>\w+))?)|(?<customer_handle>\w+)/(?<asset_slug>\w+))~i';

    /** @var array{asset_id: string, asset_slug: ?string, customer_handle: ?string, privacy_key: ?string} */
    private array $urlPatternVars;

    /**
     * @throws InvalidArgumentException
     * @return static
     */
    public static function from(Extractor $info)
    {
        if (!preg_match(
            static::REGEXP_URL_PATTERN,
            $info->url,
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            throw new InvalidArgumentException(sprintf(
                'Expected a video URL format, received %s',
                $info->url
            ));
        }

        $resolver = new static();
        $resolver->urlPatternVars = $resolver->filterNamedPregMatches($matches);

        return $resolver;
    }

    public function format(array $data = []): array
    {
        /**
         * If using the custom URL the asset ID can be retrieved from
         * the video's `<iframe>` URL provided by Vimeo's oEmbed endpoint.
         */
        if ($this->urlPatternVars['asset_slug'] && preg_match(
            static::REGEXP_URL_PATTERN,
            $data['src'],
            $matches,
            PREG_UNMATCHED_AS_NULL
        )) {
            $this->urlPatternVars['asset_id'] = $matches['asset_id'];
        }

        $data = array_replace($data, $this->urlPatternVars);

        $data['type'] = 'video';
        $data['id']   = $data['asset_id'];

        if ($this->urlPatternVars['asset_slug']) {
            $data['canonical_url'] = $this->getCustomUrl($data['asset_slug'], $data['customer_handle']);
        } else {
            $data['canonical_url'] = $this->getCanonicalUrl($data['asset_id'], $data['privacy_key']);
        }

        $data['embed_url']  = $this->getEmbedUrl($data['asset_id'], $data['privacy_key']);
        $data['stream_url'] = null;

        return $data;
    }

    public function getCanonicalUrl(string $assetId, ?string $privacyKey = null): string
    {
        $url = "https://vimeo.com/{$assetId}";

        return $privacyKey
            ? "{$url}/{$privacyKey}"
            : $url;
    }

    public function getCustomUrl(string $assetSlug, string $customerHandle): string
    {
        return "https://vimeo.com/{$customerHandle}/{$assetSlug}";
    }

    public function getEmbedUrl(string $assetId, ?string $privacyKey = null): string
    {
        $url = "https://player.vimeo.com/video/{$assetId}";

        return $privacyKey
            ? "{$url}?h={$privacyKey}"
            : $url;
    }
}
