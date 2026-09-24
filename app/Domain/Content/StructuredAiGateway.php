<?php

namespace App\Domain\Content;

interface StructuredAiGateway
{
    /** @return array<string, mixed> */
    public function generate(string $prompt, array $schema): array;

    /** @return array<string, mixed> */
    public function review(string $prompt, array $schema): array;
}
