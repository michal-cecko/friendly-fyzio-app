<?php

namespace App\Providers;

use App\Enums\NavigationLocation;
use App\Http\Responses\LoginResponse;
use App\Models\Course;
use App\Models\CourseSeries;
use App\Models\Navigation;
use App\Models\OneTimeLesson;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\Workshop;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Route users to the correct panel after the single shared login.
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'service' => Service::class,
            'course_series' => CourseSeries::class,
            'course' => Course::class,
            'workshop' => Workshop::class,
            'one_time_lesson' => OneTimeLesson::class,
            'reservation' => Reservation::class,
        ]);

        FilamentIcon::register([
            ActionsIconAlias::DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
        ]);

        // The icon aliases above only cover table/grouped contexts; set the
        // base button icon so header and standalone delete buttons match too.
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));
        ForceDeleteAction::configureUsing(fn (ForceDeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));

        // Give every save/create submit button a diskette icon app-wide.
        // Page-level buttons are matched by name; CreateAction is excluded so the
        // "New record" trigger buttons keep their own icon.
        Action::configureUsing(function (Action $action): void {
            if (
                ! $action instanceof CreateAction
                && in_array($action->getName(), ['save', 'create', 'createAnother'], true)
            ) {
                $action->icon('lucide-save');
            }
        });

        // Modal save/create submit buttons (Edit/Create modals across the app,
        // including the calendar's FullCalendarEditAction which extends EditAction).
        // Scoping to these parents avoids icon-ing delete/restore confirmation
        // buttons, whose submit action shares the "submit" name.
        foreach ([CreateAction::class, EditAction::class] as $formActionClass) {
            $formActionClass::configureUsing(
                fn (CreateAction|EditAction $action) => $action->modalSubmitAction(
                    fn (Action $submit) => $submit->icon('lucide-save'),
                ),
            );
        }

        // Extend every RichEditor globally: expose the brand "Accent" text colors
        // (pink + dark) via the textColor tool, and trim the toolbar to our set
        // (Filament's default minus blockquote & code-block). Plugin buttons (e.g.
        // the media library button) are merged in separately, so they're unaffected.
        RichEditor::configureUsing(fn (RichEditor $editor) => $editor
            ->textColors([
                'accent' => TextColor::make('Akcent (růžová)', '#ed86a3'),
                'dark' => TextColor::make('Tmavá', '#171717'),
            ])
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h2', 'h3'],
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['bulletList', 'orderedList'],
                ['textColor'],
                ['table', 'attachFiles'],
                ['undo', 'redo'],
            ]));

        // Inject the public navigation menus into the site header and footer.
        View::composer('components.site.header', fn (\Illuminate\View\View $view) => $view->with('headerNav', $this->navigation(NavigationLocation::Header)));
        View::composer('components.site.footer', fn (\Illuminate\View\View $view) => $view->with('footerNav', $this->navigation(NavigationLocation::Footer)));
    }

    private function navigation(NavigationLocation $location): ?Navigation
    {
        return Navigation::query()
            ->with('items.children')
            ->where('location', $location->value)
            ->first();
    }
}
