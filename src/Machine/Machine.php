<?php

declare(strict_types=1);

namespace Reprostar\MpclWordpress\Machine;

use Reprostar\MpclWordpress\Support\Cast;

defined('ABSPATH') || exit;

final class Machine
{
    public int $id = 0;
    public ?int $uid = null;
    public ?int $created = null;
    public ?int $modified = null;
    public ?string $slug = null;
    public bool $isVisible = false;
    public ?string $description = null;
    public ?int $physicalState = null;
    public ?string $customName = null;
    public ?string $name = null;
    public ?string $manufacturer = null;
    public ?int $manufacturerId = null;
    public ?string $yearOfProduction = null;
    public ?string $typeName = null;
    public ?int $typeId = null;
    public ?string $serialNumber = null;
    public ?string $price = null;

    /** @var list<Photo> */
    public array $photos = [];

    public bool $isExtension = false;
    public bool $isStandalone = false;
    public ?int $synchronized = null;

    /**
     * @param array<mixed> $data one item of an API v1 machines list
     */
    public static function fromApi(array $data): self
    {
        $machine = self::fromScalars($data);

        if (isset($data['photos']) && is_array($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                $machine->photos[] = Photo::fromArray($photo);
            }
        }

        return $machine;
    }

    /**
     * @param array<mixed> $row one row of the machines table
     */
    public static function fromRow(array $row): self
    {
        $machine = self::fromScalars($row);
        $machine->synchronized = Cast::nullableInt($row['synchronized'] ?? null);

        $photos = is_string($row['photos'] ?? null) ? json_decode($row['photos'], true) : null;
        if (is_array($photos)) {
            foreach ($photos as $photo) {
                $machine->photos[] = Photo::fromArray($photo);
            }
        }

        return $machine;
    }

    /**
     * @return array<string, int|string|null> column values without `synchronized`
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'created' => $this->created,
            'modified' => $this->modified,
            'slug' => $this->slug,
            'is_visible' => (int) $this->isVisible,
            'description' => $this->description,
            'physical_state' => $this->physicalState,
            'custom_name' => $this->customName,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'manufacturer_id' => $this->manufacturerId,
            'year_of_production' => $this->yearOfProduction,
            'type_name' => $this->typeName,
            'type_id' => $this->typeId,
            'serial_number' => $this->serialNumber,
            'price' => $this->price,
            'photos' => (string) wp_json_encode(array_map(
                static function (Photo $photo): array {
                    return $photo->toArray();
                },
                $this->photos
            )),
            'is_extension' => (int) $this->isExtension,
            'is_standalone' => (int) $this->isStandalone,
        ];
    }

    public function getFirstPhoto(): ?Photo
    {
        return $this->photos[0] ?? null;
    }

    /**
     * @param array<mixed> $data
     */
    private static function fromScalars(array $data): self
    {
        $machine = new self();
        $machine->id = Cast::int($data['id'] ?? null);
        $machine->uid = Cast::nullableInt($data['uid'] ?? null);
        $machine->created = Cast::nullableInt($data['created'] ?? null);
        $machine->modified = Cast::nullableInt($data['modified'] ?? null);
        $machine->slug = Cast::nullableString($data['slug'] ?? null);
        $machine->isVisible = Cast::bool($data['is_visible'] ?? null);
        $machine->description = Cast::nullableString($data['description'] ?? null);
        $machine->physicalState = Cast::nullableInt($data['physical_state'] ?? null);
        $machine->customName = Cast::nullableString($data['custom_name'] ?? null);
        $machine->name = Cast::nullableString($data['name'] ?? null);
        $machine->manufacturer = Cast::nullableString($data['manufacturer'] ?? null);
        $machine->manufacturerId = Cast::nullableInt($data['manufacturer_id'] ?? null);
        $machine->yearOfProduction = Cast::nullableString($data['year_of_production'] ?? null);
        $machine->typeName = Cast::nullableString($data['type_name'] ?? null);
        $machine->typeId = Cast::nullableInt($data['type_id'] ?? null);
        $machine->serialNumber = Cast::nullableString($data['serial_number'] ?? null);
        $machine->price = Cast::nullableString($data['price'] ?? null);
        $machine->isExtension = Cast::bool($data['is_extension'] ?? null);
        $machine->isStandalone = Cast::bool($data['is_standalone'] ?? null);

        return $machine;
    }
}
