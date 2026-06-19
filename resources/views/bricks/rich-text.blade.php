@php($config ??= [])

<section class="py-12 lg:py-16">
    <div class="ff-container">
        <div class="ff-prose mx-auto max-w-3xl text-neutral-700">
            {!! $config['content'] ?? '' !!}
        </div>
    </div>
</section>
