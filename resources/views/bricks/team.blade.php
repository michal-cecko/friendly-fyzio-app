@php
    $config ??= [];
    $therapists ??= collect();
    $cols = (int) ($config['columns'] ?? 4);
    $bg = ($config['background'] ?? 'white') === 'alt' ? 'bg-surface-alt' : 'bg-white';
    $grid = match ($cols) {
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

<section class="{{ $bg }} py-16 lg:py-24">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        @if($therapists->isNotEmpty())
            <div class="grid grid-cols-1 gap-6 {{ $grid }}">
                @foreach($therapists as $therapist)
                    @php
                        $profile = $therapist->staffProfile;
                        $img = \App\Support\Media::url($profile?->photo, '400');
                        $clickable = $profile && $profile->isPublished() && filled($profile->slug);
                        $specs = $profile ? $profile->specializations->pluck('name')->join(' • ') : null;
                        $cardClass = 'group flex h-full flex-col overflow-hidden rounded-2xl border border-line bg-white text-center transition'.($clickable ? ' hover:border-primary hover:shadow-lg hover:shadow-primary/5' : '');
                    @endphp

                    @if($clickable)
                        <a href="{{ $profile->permalink }}" class="{{ $cardClass }}">
                    @else
                        <div class="{{ $cardClass }}">
                    @endif
                        <div class="aspect-square w-full overflow-hidden bg-primary-light">
                            @if($img)
                                <img src="{{ $img }}" alt="{{ $therapist->full_name }}" class="h-full w-full object-cover transition duration-500 {{ $clickable ? 'group-hover:scale-105' : '' }}">
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col items-center gap-2 p-6">
                            <h3 class="font-heading text-base font-semibold text-neutral-900">{{ $therapist->full_name }}</h3>
                            @if($profile?->title)
                                <p class="text-sm text-neutral-600">{{ $profile->title }}</p>
                            @endif
                            @if($specs)
                                <p class="text-xs font-medium text-primary">{{ $specs }}</p>
                            @endif
                            @if($clickable)
                                <div class="mt-auto pt-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-primary px-4 py-2 font-heading text-[13px] font-semibold text-white transition group-hover:bg-primary-dark">
                                        Shlédnout profil
                                        {!! \App\Support\Icon::render('arrow-right', 'h-4 w-4') !!}
                                    </span>
                                </div>
                            @endif
                        </div>
                    @if($clickable)
                        </a>
                    @else
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
</section>
