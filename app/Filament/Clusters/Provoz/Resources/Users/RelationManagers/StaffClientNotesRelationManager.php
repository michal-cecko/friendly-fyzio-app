<?php

namespace App\Filament\Clusters\Provoz\Resources\Users\RelationManagers;

use App\Filament\Clusters\Provoz\Resources\Clients\RelationManagers\NotesRelationManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff are treated at the practice too, and their therapy notes hang off their
 * own account. The Klienti resource only lists customers, so the same notes
 * table is offered here — but only once such notes exist, to keep the detail
 * page of ordinary staff uncluttered.
 */
class StaffClientNotesRelationManager extends NotesRelationManager
{
    protected static ?string $title = 'Poznámky z terapií (jako klient)';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof User && $ownerRecord->clientNotes()->exists();
    }
}
