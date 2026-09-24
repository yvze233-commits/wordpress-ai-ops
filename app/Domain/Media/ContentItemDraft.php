<?php

namespace App\Domain\Media;

final class ContentItemDraft
{
    /** @param list<array{heading?:string|null,content:string}> $paragraphs */
    public function __construct(
        public readonly string $title,
        public readonly array $paragraphs,
        public readonly ?string $category = null,
        public readonly ?string $scene = null,
    ) {}

    public function paragraphCount(): int
    {
        return count($this->paragraphs);
    }

    public static function fromText(string $title, string $text, ?string $category = null, ?string $scene = null): self
    {
        $paragraphs = [];
        foreach (preg_split('/\n{2,}/u', trim($text)) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $heading = null;
            if (preg_match('/^#{1,6}\s+(.+)\n?/u', $paragraph, $matches) === 1) {
                $heading = trim($matches[1]);
                $paragraph = trim((string) preg_replace('/^#{1,6}\s+.+\n?/u', '', $paragraph));
            }
            $paragraphs[] = ['heading' => $heading, 'content' => $paragraph];
        }

        return new self($title, $paragraphs, $category, $scene);
    }
}
