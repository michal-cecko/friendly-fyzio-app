<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\OneOffEvent;
use Database\Seeders\Concerns\ImportsMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;

/**
 * Gives every demo course/workshop a featured photo so archive cards and the
 * detail heroes match the designs. Doubly idempotent: records that already
 * have a photo (incl. admin-picked ones) are skipped, and the media import
 * dedupes by the demo-{slug} caption; failed downloads leave the image null
 * and self-heal on the next run. One-time lessons reuse their course's photo.
 */
class OfferImagesSeeder extends Seeder
{
    use ImportsMedia;

    private const COURSE_PHOTOS = [
        'hormonalni-joga' => 'photo-1518459031867-a89b944bffe4',
        'somaticka-joga' => 'photo-1758599879462-23a9d4a4bf2c',
        'sm-system' => 'photo-1717500252297-b09508db7ceb',
        'jin-joga' => 'photo-1506126613408-eca07ce68773',
        'zdrava-zada' => 'photo-1544367567-0f2fcb009e0b',
        'pilates-pro-zacatecniky' => 'photo-1518611012118-696072aa579a',
        'joga-pro-pokrocile' => 'photo-1545205597-3d9d02c29597',
        'cviceni-v-tehotenstvi' => 'photo-1506629082955-511b1aa562c8',
    ];

    private const WORKSHOP_PHOTOS = [
        'baby-massage-workshop' => 'photo-1719942274381-c4c05b0dcf68',
        'workshop-zdravych-zad' => 'photo-1599901860904-17e6ed7083a0',
        'dychaci-techniky' => 'photo-1447452001602-7090c7ab2db3',
        'cviceni-s-overballem' => 'photo-1571019613454-1cb2f99b2d8b',
        'mobilita-kycli' => 'photo-1571902943202-507ec2618e8f',
        'panevni-dno' => 'photo-1588286840104-8957b019727f',
    ];

    public function run(): void
    {
        foreach (self::COURSE_PHOTOS as $slug => $photo) {
            $this->backfill(Course::query(), $slug, $photo);
        }

        foreach (self::WORKSHOP_PHOTOS as $slug => $photo) {
            $this->backfill(OneOffEvent::query(), $slug, $photo);
        }
    }

    protected function backfill(Builder $query, string $slug, string $photo): void
    {
        $record = $query->where('slug', $slug)->whereNull('featured_image')->first();

        $record?->update(['featured_image' => $this->media(
            "https://images.unsplash.com/{$photo}?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1080",
            'demo-'.$slug,
        )]);
    }
}
