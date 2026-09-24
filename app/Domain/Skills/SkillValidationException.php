<?php

namespace App\Domain\Skills;

use InvalidArgumentException;

final class SkillValidationException extends InvalidArgumentException
{
    /** @param array<string,list<string>> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Skill validation failed: '.json_encode($errors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
