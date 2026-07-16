@php
    use App\Enums\ContactInquiryStatus;
    use App\Filament\Resources\ContactInquiries\ContactInquiryResource;
    use App\Models\ContactInquiry;

    $canViewInquiries = ContactInquiryResource::canViewAny();

    $newInquiriesCount = $canViewInquiries
        ? ContactInquiry::query()->where('status', ContactInquiryStatus::New)->count()
        : 0;
@endphp

@if ($canViewInquiries)
    <x-filament::icon-button
        :href="ContactInquiryResource::getUrl()"
        tag="a"
        icon="heroicon-o-envelope"
        color="gray"
        label="Zprávy z webu"
        tooltip="Zprávy z webu"
        :badge="$newInquiriesCount > 0 ? $newInquiriesCount : null"
        badge-color="warning"
    />
@endif
