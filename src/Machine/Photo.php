<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Support\Cast;

final class Photo
{
    public ?int $id = null;
    public ?string $slug = null;
    public ?string $origName = null;
    public ?string $extension = null;
    public ?string $origUri = null;

    /** @var array<int, string> thumbnail URL keyed by size */
    public array $thumbnails = [];

    /**
     * Accepts both the API shape and the shape stored in the `photos` column.
     * Anything that is not an array (older API versions sent bare file names)
     * becomes an empty photo, which renders as the placeholder image.
     *
     * @param mixed $data
     */
    public static function fromArray($data): self
    {
        $photo = new self();

        if (!is_array($data)) {
            return $photo;
        }

        $photo->id = Cast::nullableInt($data['id'] ?? null);
        $photo->slug = Cast::nullableString($data['slug'] ?? null);
        $photo->origName = Cast::nullableString($data['orig_name'] ?? null);
        $photo->extension = Cast::nullableString($data['extension'] ?? null);
        $photo->origUri = Cast::nullableString($data['orig_uri'] ?? null);

        if (isset($data['thumbnails']) && is_array($data['thumbnails'])) {
            foreach ($data['thumbnails'] as $size => $uri) {
                if (is_scalar($uri)) {
                    $photo->thumbnails[(int) $size] = (string) $uri;
                }
            }
        }

        return $photo;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'orig_name' => $this->origName,
            'extension' => $this->extension,
            'orig_uri' => $this->origUri,
            'thumbnails' => $this->thumbnails,
        ];
    }

    public function getThumbnail(int $size): ?string
    {
        return $this->thumbnails[$size] ?? null;
    }
}
