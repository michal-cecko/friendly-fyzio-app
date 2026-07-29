<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Provoz\Resources\Users\UserResource;
use App\Models\StaffProfile;
use App\Support\Media;
use Illuminate\Contracts\View\View;

class TherapistController extends Controller
{
    public function show(StaffProfile $therapist): View
    {
        $preview = ! $therapist->isPublished();

        // An unpublished profile is invisible to the public; staff get a preview.
        abort_if($preview && ! $this->canPreview(), 404);

        $therapist->load([
            'user',
            'specializations' => fn ($query) => $query->orderBy('display_order'),
            // The service each specialization stands for: it is what the card on
            // the profile books, and a specialization without one is not shown.
            'specializations.specialization.service',
        ]);

        return view('therapists.show', [
            'therapist' => $therapist,
            'isPreview' => $preview,
            'adminEditUrl' => $therapist->user !== null
                ? $this->adminEditUrl($therapist->user, UserResource::class)
                : null,
            'seo' => [
                'title' => $therapist->user?->name,
                'description' => $therapist->title,
                'image' => Media::url($therapist->photo, '800'),
            ],
        ]);
    }
}
