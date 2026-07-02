@php($svcIcon = $service->icon ?: $category?->icon)
<label wire:key="svc-{{ $service->slug }}" class="cursor-pointer">
    <input type="radio" wire:model="serviceSlug" value="{{ $service->slug }}" class="peer sr-only">
    <div class="flex h-full items-center gap-3 rounded-xl border-2 border-line bg-white p-4 transition hover:border-primary peer-checked:border-primary peer-checked:bg-primary-light peer-checked:[&_.svc-icon]:bg-primary peer-checked:[&_.svc-icon]:text-white peer-checked:[&_.svc-radio]:border-primary peer-checked:[&_.svc-radio]:bg-primary peer-checked:[&_.svc-dot]:opacity-100">
        <span class="svc-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-surface-alt text-primary transition">
            @if ($svcIcon){!! \App\Support\Icon::render($svcIcon, 'h-[22px] w-[22px]') !!}@endif
        </span>
        <span class="min-w-0 flex-1">
            <span class="block font-heading text-[15px] font-semibold text-neutral-900">{{ $service->name }}</span>
            <span class="mt-0.5 block text-[13px] text-[#666666]">{{ $service->duration_minutes }} min · {{ number_format($service->price, 0, ',', ' ') }} Kč</span>
        </span>
        <span class="svc-radio flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-full border-2 border-line transition">
            <span class="svc-dot h-2 w-2 rounded-full bg-white opacity-0 transition"></span>
        </span>
    </div>
</label>
