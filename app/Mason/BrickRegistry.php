<?php

namespace App\Mason;

use App\Mason\Bricks\CardsBrick;
use App\Mason\Bricks\CtaBannerBrick;
use App\Mason\Bricks\FeatureCardsBrick;
use App\Mason\Bricks\HeroBrick;
use App\Mason\Bricks\InstagramBrick;
use App\Mason\Bricks\NewsletterBrick;
use App\Mason\Bricks\RichTextBrick;
use App\Mason\Bricks\SectionHeadingBrick;
use App\Mason\Bricks\StatsBrick;
use App\Mason\Bricks\TestimonialsBrick;
use Awcodes\Mason\Brick;

/**
 * Single source of truth for the bricks available to the page builder.
 * Used by both the Filament Mason field and the frontend MasonRenderer.
 */
class BrickRegistry
{
    /**
     * @return list<class-string<Brick>>
     */
    public static function all(): array
    {
        return [
            HeroBrick::class,
            SectionHeadingBrick::class,
            RichTextBrick::class,
            FeatureCardsBrick::class,
            CardsBrick::class,
            StatsBrick::class,
            TestimonialsBrick::class,
            CtaBannerBrick::class,
            InstagramBrick::class,
            NewsletterBrick::class,
        ];
    }
}
