<?php

namespace Tests\Unit;

use App\Domain\Content\ContentStateTransition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ContentStateTransitionTest extends TestCase
{
    public function test_the_content_pipeline_transitions_in_order(): void
    {
        $states = [
            'locked',
            'generating',
            'awaiting_review',
            'approved',
            'wp_draft_written',
        ];

        foreach ($states as $index => $from) {
            if (! isset($states[$index + 1])) {
                break;
            }

            ContentStateTransition::assertAllowed($from, $states[$index + 1]);
            $this->addToAssertionCount(1);
        }
    }

    public function test_it_rejects_a_transition_that_skips_the_pipeline(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('candidate');
        $this->expectExceptionMessage('wp_draft_written');

        ContentStateTransition::assertAllowed('candidate', 'wp_draft_written');
    }

    public function test_it_rejects_regressing_a_written_draft_to_generation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wp_draft_written');
        $this->expectExceptionMessage('generating');

        ContentStateTransition::assertAllowed('wp_draft_written', 'generating');
    }
}
