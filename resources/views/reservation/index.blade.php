@extends('layouts.public')

@section('content')
    <livewire:reservation-wizard :preset="$preset ?? null" />
@endsection
