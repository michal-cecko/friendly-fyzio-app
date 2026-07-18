{{-- Split-screen auth shell per the pencil designs: centered white card with the
     logo on the left, studio photo on the right (hidden on small screens). --}}
<div class="grid min-h-screen grid-cols-1 lg:grid-cols-2">
    <div class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <a href="{{ url('/') }}" class="flex items-center justify-center gap-1 font-heading text-2xl">
                <span class="font-medium text-neutral-900">Friendly</span>
                <span class="font-semibold italic text-primary">Fyzio</span>
            </a>

            <div class="mt-8">
                {{ $slot }}
            </div>
        </div>
    </div>

    <div class="relative hidden overflow-hidden bg-primary-light lg:block">
        <img
            src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=1600"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            loading="lazy"
        >
    </div>
</div>
