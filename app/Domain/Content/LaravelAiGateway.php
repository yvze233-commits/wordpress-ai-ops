<?php

namespace App\Domain\Content;

use Illuminate\JsonSchema\JsonSchema;
use RuntimeException;

use function Laravel\Ai\agent;

final class LaravelAiGateway implements StructuredAiGateway
{
    public function generate(string $prompt, array $schema): array
    {
        return $this->prompt($prompt, $schema);
    }

    public function review(string $prompt, array $schema): array
    {
        return $this->prompt($prompt, $schema);
    }

    /** @return array<string, mixed> */
    private function prompt(string $prompt, array $schema): array
    {
        $response = agent(
            instructions: 'Return only the requested structured JSON. Do not invent evidence or source links.',
            schema: fn (JsonSchema $jsonSchema): array => [
                'result' => $jsonSchema::fromArray($schema),
            ],
        )->prompt($prompt, model: config('content-ops.ai_default_model'));

        $structured = $response->structured ?? null;
        if (isset($structured['result']) && is_array($structured['result'])) {
            return $structured['result'];
        }
        if (is_array($structured)) {
            return $structured;
        }

        throw new RuntimeException('AI provider returned no structured output.');
    }
}
