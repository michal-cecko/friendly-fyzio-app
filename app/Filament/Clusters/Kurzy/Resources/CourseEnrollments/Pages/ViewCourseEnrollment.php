<?php

namespace App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\Pages;

use App\Filament\Clusters\Finance\Resources\Invoices\Actions\GenerateInvoiceFromPayableAction;
use App\Filament\Clusters\Kurzy\Resources\CourseEnrollments\CourseEnrollmentResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\CancelSignupAction;
use App\Filament\Support\Actions\RecordPaymentAction;
use App\Filament\Support\Actions\RevertSignupAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Filament\Support\Concerns\HasCourseBreadcrumbs;
use App\Models\CourseEnrollment;
use App\Support\Enrollments\SignupStatus;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class ViewCourseEnrollment extends ViewRecord
{
    use HasCourseBreadcrumbs;

    protected static string $resource = CourseEnrollmentResource::class;

    public function getTitle(): string
    {
        /** @var CourseEnrollment $record */
        $record = $this->getRecord();

        return 'Přihláška '.($record->client?->name ?? 'bez klienta');
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            RevertSignupAction::make(),
            CancelSignupAction::make(),
            // The catch-all dropdown always sits last in the header.
            ActionGroup::make([
                SendEmailAction::make(),
                GenerateInvoiceFromPayableAction::make(),
                // Zrušit already hard-deletes active sign-ups via its toggle, so a
                // plain delete is only offered to purge already-cancelled rows.
                DeleteAction::make()
                    ->visible(fn (Model $record): bool => ! SignupStatus::isActiveSignup($record)),
                ActivityLogAction::make(),
            ])
                ->label('Další akce')
                ->icon(Heroicon::OutlinedEllipsisHorizontal)
                ->button()
                ->color('gray'),
        ];
    }
}
