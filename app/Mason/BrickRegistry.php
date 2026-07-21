<?php

namespace App\Mason;

use App\Mason\Bricks\CalloutBrick;
use App\Mason\Bricks\CardsBrick;
use App\Mason\Bricks\CategoryCardsBrick;
use App\Mason\Bricks\ContactBrick;
use App\Mason\Bricks\CourseArchiveBrick;
use App\Mason\Bricks\CtaBannerBrick;
use App\Mason\Bricks\EnrollingNowBrick;
use App\Mason\Bricks\FeatureCardsBrick;
use App\Mason\Bricks\HeroBrick;
use App\Mason\Bricks\HtmlBlockBrick;
use App\Mason\Bricks\InstagramBrick;
use App\Mason\Bricks\LastMinuteBrick;
use App\Mason\Bricks\NewsletterBrick;
use App\Mason\Bricks\OneOffEventArchiveBrick;
use App\Mason\Bricks\PageIntroBrick;
use App\Mason\Bricks\PhotoTextBrick;
use App\Mason\Bricks\PriceListBrick;
use App\Mason\Bricks\PricingBrick;
use App\Mason\Bricks\QuoteBannerBrick;
use App\Mason\Bricks\ReviewsBrick;
use App\Mason\Bricks\RichTextBrick;
use App\Mason\Bricks\SectionHeadingBrick;
use App\Mason\Bricks\ServiceCardsBrick;
use App\Mason\Bricks\StatsBrick;
use App\Mason\Bricks\StepsBrick;
use App\Mason\Bricks\TeamBrick;
use App\Mason\Bricks\TestimonialsBrick;
use App\Mason\Bricks\TextListBrick;
use Awcodes\Mason\Brick;
use Awcodes\Mason\BrickGroup;

/**
 * Single source of truth for the bricks available to the page builder, grouped
 * into categories for the Mason picker. Used by both the Filament Mason field
 * (grouped) and the frontend MasonRenderer (which flattens groups automatically).
 */
class BrickRegistry
{
    /**
     * @return array<int, BrickGroup>
     */
    public static function all(): array
    {
        return [
            BrickGroup::make('Hero')->bricks([
                HeroBrick::class,
            ]),
            BrickGroup::make('Text')->bricks([
                PageIntroBrick::class,
                SectionHeadingBrick::class,
                RichTextBrick::class,
                PhotoTextBrick::class,
                TextListBrick::class,
                HtmlBlockBrick::class,
            ]),
            BrickGroup::make('Karty')->bricks([
                CardsBrick::class,
                FeatureCardsBrick::class,
                CategoryCardsBrick::class,
                ServiceCardsBrick::class,
                TeamBrick::class,
            ]),
            BrickGroup::make('Sekce')->bricks([
                StatsBrick::class,
                TestimonialsBrick::class,
                ReviewsBrick::class,
                CtaBannerBrick::class,
                QuoteBannerBrick::class,
                CalloutBrick::class,
                StepsBrick::class,
                PricingBrick::class,
                PriceListBrick::class,
                LastMinuteBrick::class,
                ContactBrick::class,
            ]),
            BrickGroup::make('Kurzy a workshopy')->bricks([
                EnrollingNowBrick::class,
                CourseArchiveBrick::class,
                OneOffEventArchiveBrick::class,
            ]),
            BrickGroup::make('Sociální sítě')->bricks([
                InstagramBrick::class,
                NewsletterBrick::class,
            ]),
        ];
    }

    /**
     * Flat list of every registered brick class (groups unwrapped).
     *
     * @return list<class-string<Brick>>
     */
    public static function flat(): array
    {
        return collect(self::all())
            ->flatMap(fn (BrickGroup $group): array => $group->getBricks())
            ->all();
    }
}
