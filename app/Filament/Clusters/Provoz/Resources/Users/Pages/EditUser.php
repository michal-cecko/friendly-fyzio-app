<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\Pages;

use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Filament\Resources\Pages\BaseEditRecord;
use App\Filament\Support\Actions\ActivityLogAction;
use App\Filament\Support\Actions\DeactivateUserAction;
use App\Filament\Support\Actions\ReactivateUserAction;
use App\Filament\Support\Actions\ResetPasswordAction;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use STS\FilamentImpersonate\Actions\Impersonate;

class EditUser extends BaseEditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        /** @var User $record */
        $record = $this->getRecord();

        return 'Upravit uživatele '.$record->name;
    }

    /** @var list<string> */
    protected array $selectedCapabilities = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();
        /** @var User $actor */
        $actor = auth()->user();

        // Only prefill capabilities the actor can actually manage; ones they
        // can't (e.g. Admin for a plain admin) aren't options and are preserved
        // untouched on save by applyCapabilitySelection().
        $assignable = collect($actor->assignableCapabilities());
        $data['capabilities'] = $record->capabilities()
            ->filter(fn ($c) => $assignable->contains($c))
            ->map(fn ($c) => $c->value)
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedCapabilities = $data['capabilities'] ?? [];
        unset($data['capabilities']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $record */
        $record = $this->getRecord();
        /** @var User $actor */
        $actor = auth()->user();
        $record->applyCapabilitySelection($this->selectedCapabilities, $actor);
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Impersonate::make()->record($this->getRecord()),
                ResetPasswordAction::make(),
                DeactivateUserAction::make(),
                ReactivateUserAction::make(),
                DeleteAction::make()
                    ->visible(fn (User $record): bool => UserResource::canDeleteUser($record)),
                ForceDeleteAction::make(),
                RestoreAction::make(),
                ActivityLogAction::make(),
            ])
                ->label('Akce')
                ->icon(Heroicon::OutlinedEllipsisVertical)
                ->color('gray')
                ->button(),
            // Rightmost, primary — the main call to action.
            $this->getSaveHeaderAction(),
        ];
    }
}
