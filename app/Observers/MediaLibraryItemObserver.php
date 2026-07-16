<?php

namespace App\Observers;

use App\Support\MediaLibrary\MediaUsageScanner;
use Filament\Notifications\Notification;
use RalphJSmit\Filament\MediaLibrary\Models\MediaLibraryItem;

/**
 * Guards media library items against deletion while they are still referenced
 * anywhere — brick images, image columns, or WYSIWYG content. Returning false
 * from `deleting` halts the delete; the admin gets a notification listing
 * where the file is used so they can unlink it first.
 */
class MediaLibraryItemObserver
{
    public function deleting(MediaLibraryItem $item): bool
    {
        $usages = MediaUsageScanner::usagesOf((int) $item->getKey());

        if ($usages === []) {
            return true;
        }

        Notification::make()
            ->title('Soubor nelze smazat — je stále používán')
            ->body(
                'Nejdříve jej odeberte z těchto míst: '
                .implode(', ', array_slice($usages, 0, 10))
                .(count($usages) > 10 ? ' a dalších '.(count($usages) - 10).'…' : '.')
            )
            ->danger()
            ->persistent()
            ->send();

        return false;
    }
}
