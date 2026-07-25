<?php

namespace App\Providers;

use App\Enums\NavigationLocation;
use App\Listeners\LogSentEmail;
use App\Listeners\SuppressNonAdminMail;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseSeries;
use App\Models\EventCategory;
use App\Models\Navigation;
use App\Models\OneOffEvent;
use App\Models\OneOffEventBooking;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Auth\FilamentResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailChangeNotification;
use App\Observers\MediaLibraryItemObserver;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Auth\Notifications\VerifyEmailChange as FilamentVerifyEmailChange;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RalphJSmit\Filament\Explore\Filament\Forms\Components\FilePicker;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Render Filament's own notifications from the dashboard-editable CMS
        // templates: the admin panel's profile dispatches the e-mail-change
        // verification and its "forgot password" page dispatches the reset
        // notification directly, bypassing the User model hooks. Registration and
        // e-mail verification run through the public auth pages and dispatch
        // App\Notifications\Auth\* directly.
        $this->app->bind(FilamentVerifyEmailChange::class, VerifyEmailChangeNotification::class);
        $this->app->bind(FilamentResetPassword::class, FilamentResetPasswordNotification::class);
    }

    public function boot(): void
    {
        // A media library item that is still referenced anywhere (brick images,
        // image columns, WYSIWYG content) must not be deleted — the vendor model
        // can't carry an #[ObservedBy] attribute, so the observer registers here.
        MediaLibraryItem::observe(MediaLibraryItemObserver::class);

        // The Admin capability grants access to everything that isn't gated by a
        // different capability. Filament resource/policy checks are all Gates, so
        // an all-abilities bypass here is the cleanest expression of that — it
        // extends Shield's own super_admin bypass down to plain admins. The
        // therapist/lecturer-specific behaviours (being bookable, being an
        // assignable instructor) are capability predicates, not Gates, so they
        // are unaffected; and the super-admin-only rules (managing admins,
        // granting admin/super-admin) are explicit isSuperAdmin() checks, also
        // not Gates. Returning null (not false) lets non-admins fall through to
        // the normal checks.
        Gate::before(fn ($user): ?bool => $user instanceof User && $user->isAdmin() ? true : null);

        // Pre-launch guard: hold back e-mail to anyone but administrators while
        // the database carries real client records (MAIL_SUPPRESS_NON_ADMIN).
        // Registered before the logger so suppressed mail is never logged as sent.
        Event::listen(NotificationSending::class, SuppressNonAdminMail::class);

        // Record every outgoing e-mail (manual or automatic) in the activity log.
        Event::listen(NotificationSent::class, LogSentEmail::class);

        Relation::morphMap([
            'service' => Service::class,
            'course_series' => CourseSeries::class,
            'course' => Course::class,
            'event_category' => EventCategory::class,
            'one_off_event' => OneOffEvent::class,
            'reservation' => Reservation::class,
            'course_enrollment' => CourseEnrollment::class,
            'one_off_event_booking' => OneOffEventBooking::class,
        ]);

        FilamentIcon::register([
            ActionsIconAlias::DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION => Heroicon::OutlinedTrash,
            ActionsIconAlias::FORCE_DELETE_ACTION_GROUPED => Heroicon::OutlinedTrash,
            ActionsIconAlias::EDIT_ACTION => Heroicon::OutlinedPencilSquare,
            ActionsIconAlias::EDIT_ACTION_GROUPED => Heroicon::OutlinedPencilSquare,
        ]);

        // The icon aliases above only cover table/grouped contexts; set the
        // base button icon so header and standalone delete/edit buttons match too.
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));
        ForceDeleteAction::configureUsing(fn (ForceDeleteAction $action) => $action->icon(Heroicon::OutlinedTrash));
        EditAction::configureUsing(fn (EditAction $action) => $action->icon(Heroicon::OutlinedPencilSquare));

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
        // The closure parameter MUST be named $action — Filament injects the built
        // submit button by that name; any other name falls back to type-based
        // injection, which hands over the parent Create/Edit action instead.
        foreach ([CreateAction::class, EditAction::class] as $formActionClass) {
            $formActionClass::configureUsing(
                fn (CreateAction|EditAction $formAction) => $formAction->modalSubmitAction(
                    fn (Action $action) => $action->icon('lucide-save'),
                ),
            );
        }

        // Extend every RichEditor globally: expose the brand "Accent" text colors
        // (pink + dark) via the textColor tool, and trim the toolbar to our set
        // (Filament's default minus blockquote & code-block). Plugin buttons (e.g.
        // the media library button) are merged in separately, so they're unaffected.
        // Direct file uploads (drag & drop, paste, the attach button) are disabled
        // everywhere — images enter rich content only through the media library
        // plugin, whose id-based rendering is not affected by this switch.
        RichEditor::configureUsing(fn (RichEditor $editor) => $editor
            ->textColors([
                'accent' => TextColor::make('Akcent (růžová)', '#ed86a3'),
                'dark' => TextColor::make('Tmavá', '#171717'),
            ])
            ->fileAttachments(false)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'subscript', 'superscript', 'link'],
                ['h2', 'h3'],
                ['alignStart', 'alignCenter', 'alignEnd'],
                ['bulletList', 'orderedList'],
                ['textColor'],
                ['table'],
                ['undo', 'redo'],
            ]));

        // The file/media picker's "clear" button gets its label from the action
        // name, so it stays English no matter the locale. Configuring the parent
        // FilePicker covers the MediaPicker subclass used across the app.
        FilePicker::configureUsing(fn (FilePicker $picker) => $picker->modifyClearActionUsing(
            fn (Action $action) => $action->label(__('filament-explore::filament/forms/components/file-picker.clear_action.label')),
        ));

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
