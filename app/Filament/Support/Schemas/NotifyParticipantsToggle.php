<?php

namespace App\Filament\Support\Schemas;

use App\Filament\Support\Concerns\NotifiesScheduleChange;
use Filament\Forms\Components\Toggle;

/**
 * Edit-only, non-persisted toggle that lets staff choose whether saving a
 * schedule change e-mails the enrolled participants (and the instructor). Read
 * back in {@see NotifiesScheduleChange}. Shared by
 * the course-lesson, one-time-lesson and workshop forms so the control is
 * identical everywhere.
 */
class NotifyParticipantsToggle
{
    public static function make(): Toggle
    {
        return Toggle::make('notify_participants')
            ->label('Upozornit účastníky na změnu termínu')
            ->helperText('Při změně data, času nebo místnosti odešle přihlášeným účastníkům a lektorovi e-mail.')
            ->default(true)
            ->dehydrated(false)
            ->visibleOn('edit')
            ->columnSpanFull();
    }
}
