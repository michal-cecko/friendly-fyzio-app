<?php

namespace Tests\Feature;

use App\Filament\Clusters\Kurzy\Resources\CourseCategories\Schemas\CourseCategoryInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Schemas\CourseEnrollmentInfolist;
use App\Filament\Clusters\Kurzy\Resources\CourseLessons\Schemas\CourseLessonInfolist;
use App\Filament\Clusters\Kurzy\Resources\Courses\Schemas\CourseInfolist;
use App\Filament\Clusters\Kurzy\Resources\EventCategories\Schemas\EventCategoryInfolist;
use App\Filament\Clusters\Kurzy\Resources\LessonAttendances\Schemas\LessonAttendanceInfolist;
use App\Filament\Clusters\Kurzy\Resources\OneOffEventBookings\Schemas\OneOffEventBookingInfolist;
use App\Filament\Clusters\Provoz\Resources\Rooms\Schemas\RoomForm;
use App\Filament\Clusters\Provoz\Resources\Rooms\Schemas\RoomInfolist;
use App\Filament\Clusters\Provoz\Resources\ServiceCategories\Schemas\ServiceCategoryInfolist;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Filament's edit and view pages fall back to a two-column page schema whenever
 * the resource schema does not declare its own columns. Each schema below holds
 * a single top-level section, so without an explicit one-column root that
 * section renders at half the page width.
 */
class FullWidthSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string}>
     */
    public static function singleSectionSchemas(): array
    {
        return [
            'room form' => [RoomForm::class],
            'room infolist' => [RoomInfolist::class],
            'course infolist' => [CourseInfolist::class],
            'course category infolist' => [CourseCategoryInfolist::class],
            'course enrollment infolist' => [CourseEnrollmentInfolist::class],
            'course lesson infolist' => [CourseLessonInfolist::class],
            'event category infolist' => [EventCategoryInfolist::class],
            'lesson attendance infolist' => [LessonAttendanceInfolist::class],
            'one-off event booking infolist' => [OneOffEventBookingInfolist::class],
            'service category infolist' => [ServiceCategoryInfolist::class],
        ];
    }

    /**
     * @param  class-string  $schemaClass
     */
    #[DataProvider('singleSectionSchemas')]
    public function test_schema_spans_the_full_page_width(string $schemaClass): void
    {
        $schema = $schemaClass::configure(Schema::make());

        $this->assertTrue(
            $schema->hasCustomColumns(),
            $schemaClass.' must declare its own columns, otherwise the page defaults to two.',
        );
        $this->assertSame(1, $schema->getColumns('lg'));
    }
}
