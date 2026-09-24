<?php

namespace App\Domain\Skills;

use Illuminate\Support\Str;
use JsonException;

final class SkillParser
{
    /** @return array{parsed_rules:array<string,mixed>,output_schema:array<string,mixed>,prohibited_terms:list<string>,pass_threshold:?int} */
    public function parse(string $rawText, string $kind, array $options = []): array
    {
        $lines = preg_split('/\R/u', trim($rawText)) ?: [];
        $sections = [];
        $section = 'instructions';
        $buffer = [];
        foreach ($lines as $line) {
            if (preg_match('/^#{1,6}\s+(.+)$/u', trim($line), $matches) === 1) {
                $sections[$section] = trim(implode("\n", $buffer));
                $section = Str::snake(trim($matches[1]));
                $buffer = [];

                continue;
            }
            $buffer[] = $line;
        }
        $sections[$section] = trim(implode("\n", $buffer));

        $schema = $options['output_schema'] ?? $this->schemaFromSections($sections['output_schema'] ?? '');
        $schema ??= $this->defaultSchema($kind);
        $prohibited = $options['prohibited_terms'] ?? $this->listFromSection($sections['prohibited_terms'] ?? '');
        $threshold = isset($options['pass_threshold']) ? (int) $options['pass_threshold'] : ($kind === 'review' ? 70 : null);

        return [
            'parsed_rules' => ['kind' => $kind, 'sections' => $sections],
            'output_schema' => $schema,
            'prohibited_terms' => array_values(array_unique(array_filter(array_map('trim', $prohibited)))),
            'pass_threshold' => $threshold,
        ];
    }

    /** @return array<string,mixed>|null */
    private function schemaFromSections(string $section): ?array
    {
        if (trim($section) === '') {
            return null;
        }
        $section = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $section));
        try {
            $decoded = json_decode($section, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /** @return list<string> */
    private function listFromSection(string $section): array
    {
        return array_values(array_filter(array_map(
            static fn (string $line): string => trim((string) preg_replace('/^[-*]\s*/', '', $line)),
            preg_split('/\R/u', $section) ?: [],
        )));
    }

    /** @return array<string,mixed> */
    private function defaultSchema(string $kind): array
    {
        if ($kind === 'writing') {
            return ['type' => 'object', 'required' => ['title', 'slug', 'excerpt', 'content_markdown', 'keywords', 'category', 'source_links', 'claims']];
        }

        return ['type' => 'object', 'required' => ['passed', 'score', 'threshold', 'criterion_scores', 'conflicts', 'missing_evidence', 'image_issues', 'revision_instructions']];
    }
}
