@php($config ??= [])

<section class="py-12 lg:py-16">
    <div class="mx-auto max-w-7xl px-6 lg:px-8">
        @include('bricks.partials.heading', ['config' => $config])
    </div>
</section>
