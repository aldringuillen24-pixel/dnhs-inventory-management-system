@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    <x-profile-page
        :user="auth()->user()"
        role-label="School Head"
        :form-action="route('schoolHead.profile.update')"
        form-method="PATCH"
    />
@endsection
