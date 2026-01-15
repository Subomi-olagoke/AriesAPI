@extends('layouts.app')

@section('content')
<div id="live-classroom" 
     data-class-id="{{ $liveClass->id }}"
     data-user-id="{{ auth()->id() }}"
     data-is-teacher="{{ auth()->id() === $liveClass->teacher_id }}">
</div>
@endsection
