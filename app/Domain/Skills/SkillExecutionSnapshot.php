<?php

namespace App\Domain\Skills;

use App\Models\SkillVersion;

final class SkillExecutionSnapshot implements \JsonSerializable
{
    public function __construct(
        public readonly int $skillId,
        public readonly int $version,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $rawText,
        public readonly array $parsedRules,
        public readonly array $outputSchema,
        public readonly array $prohibitedTerms,
        public readonly ?int $passThreshold,
    ) {}

    public static function fromVersion(SkillVersion $version): self
    {
        $skill = $version->skill;

        return new self(
            $skill->id,
            $version->version,
            $skill->name,
            $skill->kind,
            $version->raw_text,
            $version->parsed_rules,
            $version->output_schema,
            $version->prohibited_terms ?? [],
            $version->pass_threshold,
        );
    }

    public function toArray(): array
    {
        return [
            'skill_id' => $this->skillId, 'version' => $this->version, 'name' => $this->name,
            'kind' => $this->kind, 'raw_text' => $this->rawText, 'parsed_rules' => $this->parsedRules,
            'output_schema' => $this->outputSchema, 'prohibited_terms' => $this->prohibitedTerms,
            'pass_threshold' => $this->passThreshold,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
