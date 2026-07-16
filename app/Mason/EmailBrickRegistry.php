<?php

namespace App\Mason;

use App\Mason\Bricks\Email\EmailButtonsBrick;
use App\Mason\Bricks\Email\EmailCalloutBrick;
use App\Mason\Bricks\Email\EmailChecklistBrick;
use App\Mason\Bricks\Email\EmailGreetingBrick;
use App\Mason\Bricks\Email\EmailInvoiceItemsBrick;
use App\Mason\Bricks\Email\EmailNoteBrick;
use App\Mason\Bricks\Email\EmailParagraphBrick;
use App\Mason\Bricks\Email\EmailPaymentBrick;
use App\Mason\Bricks\Email\EmailReservationDetailsBrick;
use Awcodes\Mason\Brick;
use Awcodes\Mason\BrickGroup;

/**
 * Bricks available when authoring transactional email bodies. Kept separate from
 * the website BrickRegistry: email bricks render inline-styled, table-based HTML
 * for mail clients, and the two palettes must not mix.
 */
class EmailBrickRegistry
{
    /**
     * @return array<int, BrickGroup>
     */
    public static function all(): array
    {
        return [
            BrickGroup::make('Obsah')->bricks([
                EmailGreetingBrick::class,
                EmailParagraphBrick::class,
                EmailCalloutBrick::class,
                EmailNoteBrick::class,
            ]),
            BrickGroup::make('Rezervace')->bricks([
                EmailReservationDetailsBrick::class,
                EmailChecklistBrick::class,
            ]),
            BrickGroup::make('Platby')->bricks([
                EmailPaymentBrick::class,
                EmailInvoiceItemsBrick::class,
            ]),
            BrickGroup::make('Akce')->bricks([
                EmailButtonsBrick::class,
            ]),
        ];
    }

    /**
     * Flat list of every registered email brick class (groups unwrapped).
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
