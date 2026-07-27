<?php

namespace Tests\Feature;

use App\Enums\CourseSeriesStatus;
use App\Enums\CourseSeriesVisibility;
use Tests\TestCase;

/**
 * Stav and Viditelnost are explained inline in the série form, so the
 * descriptions map has to stay complete as cases come and go.
 */
class CourseSeriesStateDescriptionsTest extends TestCase
{
    public function test_status_descriptions_map_covers_every_case(): void
    {
        $descriptions = CourseSeriesStatus::descriptions();

        $this->assertCount(count(CourseSeriesStatus::cases()), $descriptions);

        foreach (CourseSeriesStatus::cases() as $status) {
            $this->assertSame($status->description(), $descriptions[$status->value]);
            $this->assertNotEmpty($status->description());
        }
    }

    public function test_visibility_descriptions_map_covers_every_case(): void
    {
        $descriptions = CourseSeriesVisibility::descriptions();

        $this->assertCount(count(CourseSeriesVisibility::cases()), $descriptions);

        foreach (CourseSeriesVisibility::cases() as $visibility) {
            $this->assertSame($visibility->description(), $descriptions[$visibility->value]);
            $this->assertNotEmpty($visibility->description());
        }
    }
}
