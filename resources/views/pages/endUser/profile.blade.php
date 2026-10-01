@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    <x-profile-page
        :user="auth()->user()"
        role-label="End User"
        :form-action="route('endUser.profile.update')"
        form-method="PATCH"
    />
@endsection
