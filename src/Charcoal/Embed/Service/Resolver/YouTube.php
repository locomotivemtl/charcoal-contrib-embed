<?php

namespace Charcoal\Embed\Service\Resolver;

use Embed\Extractor;
use InvalidArgumentException;

/**
 * YouTube
 *
 * A video-sharing social media platform.
 *
 * Supported URL patterns:
 *
 * - Playback URL:
 *   - `https://www.youtube.com/watch?v={asset_id}`
 *   - `https://youtu.be/{asset_id}`
 * - Embed URL:
 *   - `https://www.youtube.com/embed/{asset_id}`
 *   - `https://www.youtube-nocookie.com/embed/{asset_id}`
 *
 * URL pattern variables:
 *
 * - `asset_id` represents the video ID.
 *
 * Notes:
 *
 * - The `canonical_url` represents the video's URL on the platform.
 * - The `stream_url` is unavailable because it requires access to an API.
 */
class YouTube extends AbstractResolver
{
    public const REGEXP_URL_PATTERN = '~^(?:https?://)?(?:.+\.)?(?:youtube(?:-nocookie)?\.com\/(?:(?:v|e(?:mbed)?)/|watch/|[^/\s]+\/.+/|.*[?&]v=)|youtu\.be/)(?<asset_id>[\w\-]{11})~i';

    /** @var array{asset_id: string} */
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
                'Expected a YouTube video URL format, received %s',
                $info->url
            ));
        }

        $resolver = new static();
        $resolver->urlPatternVars = $resolver->filterNamedPregMatches($matches);

        return $resolver;
    }

    public function format(array $data = []): array
    {
        $data = array_replace($data, $this->urlPatternVars);

        $data['id']            = $data['asset_id'];
        $data['image']         = $this->getThumbnailUrl($data['asset_id']);
        $data['canonical_url'] = $this->getCanonicalUrl($data['asset_id']);
        $data['embed_url']     = $this->getEmbedUrl($data['asset_id']);
        $data['stream_url']    = null;

        return $data;
    }

    public function getCanonicalUrl(string $assetId): string
    {
        return "https://www.youtube.com/watch?v={$assetId}";
    }

    public function getEmbedUrl(string $assetId): string
    {
        return "https://www.youtube-nocookie.com/embed/{$assetId}";
    }

    /**
     * Ensures we are using a higher quality thumbnail.
     */
    public function getThumbnailUrl(string $assetId): string
    {
        return "https://img.youtube.com/vi/{$assetId}/maxresdefault.jpg";
    }
}
