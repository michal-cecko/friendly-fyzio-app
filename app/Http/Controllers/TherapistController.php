<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Provoz\Resources\StaffProfiles\StaffProfileResource;
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
        ]);

        return view('therapists.show', [
            'therapist' => $therapist,
            'isPreview' => $preview,
            'adminEditUrl' => $this->adminEditUrl($therapist, StaffProfileResource::class),
            'seo' => [
                'title' => $therapist->user?->name,
                'description' => $therapist->title,
                'image' => Media::url($therapist->photo, '800'),
            ],
        ]);
    }
}
