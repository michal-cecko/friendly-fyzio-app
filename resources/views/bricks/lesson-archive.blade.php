@php
    $config ??= [];
@endphp

<section class="bg-white py-16 lg:py-24" id="akce-archiv">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])

        <livewire:lesson-archive :config="$config" />
    </div>
</section>
