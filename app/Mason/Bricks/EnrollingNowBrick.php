<?php

namespace App\Mason\Bricks;

use App\Mason\Support\Fields;
use App\Mason\Support\LinkPickerField;
use App\Support\Enrollments\EnrollingNow;
use Awcodes\Mason\Brick;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * "Právě přihlašujeme": course categories that are currently enrolling, pulled
 * live from the Kurzy cluster (see App\Support\Enrollments\EnrollingNow). Only
 * the framing text and the bottom button are authored here — the category cards
 * and their course rows come from real open runs and auto-link to the
 * category-scoped archive and each course's detail page.
 */
class EnrollingNowBrick extends Brick
{
    public static function getId(): string
    {
        return 'enrolling-now';
    }

    public static function getLabel(): string
    {
        return 'Právě přihlašujeme';
    }

    public static function getIcon(): string|Heroicon|Htmlable|null
    {
        return Heroicon::OutlinedSparkles;
    }

    public static function toHtml(array $config, ?array $data = null): ?string
    {
        $categories = EnrollingNow::cached();

        // With no open runs the section would be an empty shell, so it is
        // hidden from the public site entirely. The editor preview keeps
        // rendering it so admins can still see the brick on the page.
        if ($categories === [] && ! request()->routeIs('mason.preview', 'mason.entry')) {
            return '';
        }

        return view('bricks.enrolling-now', [
            'config' => $config,
            'categories' => $categories,
        ])->render();
    }

    public static function configureBrickAction(Action $action): Action
    {
        return $action
            ->slideOver()
            ->schema([
                ...Fields::heading(),
                LinkPickerField::make('', 'Tlačítko pod sekcí', withText: true, withStyle: true, withColor: true, withIcon: true),
            ]);
    }
}
