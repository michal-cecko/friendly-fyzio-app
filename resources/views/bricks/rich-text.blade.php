@php($config ??= [])

<section class="py-12 lg:py-16">
    <div class="ff-prose mx-auto max-w-3xl px-6 text-neutral-700 lg:px-8">
        {!! $config['content'] ?? '' !!}
    </div>
</section>
