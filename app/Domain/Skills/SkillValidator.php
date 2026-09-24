<?php

namespace App\Domain\Skills;

use Illuminate\Http\UploadedFile;

final class SkillValidator
{
    private const DANGEROUS_EXTENSIONS = ['php', 'phar', 'exe', 'bat', 'cmd', 'ps1', 'sh', 'js'];

    /** @return array{valid:bool,errors:array<string,list<string>>,warnings:list<string>,fields:array<string,mixed>} */
    public function validate(string $rawText, string $kind, array $fields = []): array
    {
        $errors = [];
        $warnings = [];
        $rawText = trim($rawText);
        $maxBytes = app()->bound('config')
            ? (int) config('content-ops.skill_max_bytes', 256 * 1024)
            : 256 * 1024;

        if (! in_array($kind, ['writing', 'review'], true)) {
            $errors['kind'][] = 'Skill kind must be writing or review.';
        }
        if ($rawText === '') {
            $errors['raw_text'][] = 'Skill content is required.';
        }
        if (strlen($rawText) > $maxBytes) {
            $errors['raw_text'][] = 'Skill content exceeds the configured size limit.';
        }
        if (preg_match('/(?:ignore|disregard|override)\s+(?:all|previous|system)\s+instructions|忽略(?:所有|之前|系统)?(?:系统)?指令|执行(?:代码|命令)/iu', $rawText) === 1) {
            $errors['raw_text'][] = 'Instruction override or executable directives are not allowed.';
        }

        $hasExplicitSchema = isset($fields['output_schema'])
            || preg_match('/^#{1,6}\s+output[ _-]+schema\s*$/imu', $rawText) === 1;
        $parsed = app()->bound(SkillParser::class)
            ? app(SkillParser::class)->parse($rawText, $kind, $fields)
            : (new SkillParser)->parse($rawText, $kind, $fields);
        $required = $parsed['output_schema']['required'] ?? [];
        if (! $hasExplicitSchema || ! is_array($required) || $required === []) {
            $errors['output_schema'][] = 'Output schema must define required fields.';
        }
        if ($kind === 'review' && ($parsed['pass_threshold'] === null || $parsed['pass_threshold'] < 1 || $parsed['pass_threshold'] > 100)) {
            $errors['pass_threshold'][] = 'Review Skills require a threshold between 1 and 100.';
        }
        if ($kind === 'writing' && $parsed['pass_threshold'] !== null) {
            $warnings[] = 'Writing Skill pass threshold is ignored.';
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'fields' => $parsed,
        ];
    }

    public function assertValid(string $rawText, string $kind, array $fields = []): array
    {
        $report = $this->validate($rawText, $kind, $fields);
        if (! $report['valid']) {
            throw new SkillValidationException($report['errors']);
        }

        return $report;
    }

    public function validateFile(UploadedFile $file, string $kind, array $fields = []): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true)) {
            return [
                'valid' => false,
                'errors' => ['file' => ['Executable Skill files are not allowed.']],
                'warnings' => [],
                'fields' => [],
            ];
        }
        if (! in_array($extension, ['txt', 'md', 'markdown', 'docx'], true)) {
            return [
                'valid' => false,
                'errors' => ['file' => ['Only TXT, Markdown, or DOCX-extracted text is supported.']],
                'warnings' => [],
                'fields' => [],
            ];
        }

        return $this->validate((string) file_get_contents($file->getRealPath()), $kind, $fields);
    }
}
