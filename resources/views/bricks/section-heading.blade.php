@php($config ??= [])

<section class="py-12 lg:py-16">
    <div class="ff-container">
        @include('bricks.partials.heading', ['config' => $config])
    </div>
</section>
