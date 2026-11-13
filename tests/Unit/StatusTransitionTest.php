<?php

namespace Tests\Unit;

use App\Enums\Concerns\HasTransitions;
use App\Exceptions\InvalidStatusTransition;
use PHPUnit\Framework\TestCase;

enum ExampleStatus: string
{
    use HasTransitions;

    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Issued, self::Cancelled],
            self::Issued => [self::Cancelled],
            self::Cancelled => [],
        };
    }
}

class StatusTransitionTest extends TestCase
{
    public function test_allowed_transition_passes(): void
    {
        ExampleStatus::Draft->assertCanTransitionTo(ExampleStatus::Issued);

        $this->assertTrue(ExampleStatus::Issued->canTransitionTo(ExampleStatus::Cancelled));
    }

    public function test_disallowed_transition_throws_runtime_exception(): void
    {
        $this->expectException(InvalidStatusTransition::class);
        $this->expectExceptionMessage('"issued" ke "draft"');

        ExampleStatus::Issued->assertCanTransitionTo(ExampleStatus::Draft);
    }

    public function test_final_status(): void
    {
        $this->assertTrue(ExampleStatus::Cancelled->isFinal());
        $this->assertFalse(ExampleStatus::Draft->isFinal());
    }
}
