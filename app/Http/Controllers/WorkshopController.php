<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\Workshopy\Resources\Workshops\WorkshopResource;
use App\Models\Workshop;
use Illuminate\Contracts\View\View;

class WorkshopController extends Controller
{
    /**
     * Public workshop detail: hero card with date/place/capacity/price, the
     * description, registration section (all states) and reviews. Unpublished
     * workshops are visible only to staff as a preview; past ones stay
     * reachable as muted information (spec §3.6).
     */
    public function show(Workshop $workshop): View
    {
        $workshop->load(['room.building', 'instructor.therapistProfile'])->loadCount('activeTakers');

        $user = auth()->user();
        $isCustomer = $user !== null && ! $user->isStaff();
        $hasToken = filled($workshop->presale_token) && request()->query('predprodej') === $workshop->presale_token;
        $unlocked = $hasToken || ($isCustomer && $workshop->isPrivate());

        $isPreview = ! $workshop->isPublished();

        abort_if($isPreview && ! $hasToken && ! $this->canPreview(), 404);
        abort_if($workshop->isPrivate() && ! $unlocked && ! $this->canPreview(), 404);

        return view('workshops.show', [
            'workshop' => $workshop,
            'presale' => $unlocked,
            'reviews' => $workshop->reviews()->where('visible', true)->latest()->take(6)->get(),
            'isPreview' => $isPreview,
            'adminEditUrl' => $this->adminEditUrl($workshop, WorkshopResource::class),
        ]);
    }
}
