<?php

namespace App\Domain\Media;

final class ImagePlacementPlan
{
    /** @param list<array<string,mixed>> $placements */
    public function __construct(private readonly array $placements) {}

    /** @return list<array<string,mixed>> */
    public function placements(): array
    {
        return $this->placements;
    }

    public function count(): int
    {
        return count($this->placements);
    }

    /** @return list<int> */
    public function imageIds(): array
    {
        return array_values(array_map(static fn (array $placement): int => (int) $placement['image_id'], $this->placements));
    }

    /** @param list<array<string,mixed>> $placements */
    public static function fromArray(array $placements): self
    {
        return new self($placements);
    }
}
