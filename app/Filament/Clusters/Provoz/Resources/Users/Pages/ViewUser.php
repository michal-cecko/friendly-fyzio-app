<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Pages;

use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\DeactivateUserAction;
use App\Filament\Support\Actions\ReactivateUserAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Filament\Support\Actions\SendEmailAction;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        /** @var User $record */
        $record = $this->getRecord();

        return 'Uživatel '.$record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Filament does not authorize action buttons against the resource on
            // its own — only the pages they lead to — so the read-only rule is
            // spelled out here as well.
            EditAction::make()
                ->visible(fn (): bool => UserResource::canManageStaff()),
            ActionGroup::make([
                Impersonate::make()->record($this->getRecord()),
                SendEmailAction::make(),
                ResetPasswordAction::make(),
                DeactivateUserAction::make(),
                ReactivateUserAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => UserResource::canDeleteUser($record)),
                ActivityLogAction::make(),
            ])
                ->label('Akce')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button()
                // Every action in the group acts on the account. Non-admin staff
                // read a colleague's page and nothing more, so the whole button goes.
                ->visible(fn (): bool => UserResource::canManageStaff()),
        ];
    }
}
