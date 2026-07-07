@extends('layouts.public')

@section('content')
    <section class="bg-surface-alt py-16 lg:py-24">
        <div class="ff-container">
            <livewire:review-form :token="$token" />
        </div>
    </section>
@endsection
