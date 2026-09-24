@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Profile" />

    <x-profile-page
        :user="auth()->user()"
        role-label="Property Custodian"
        :form-action="route('propertyCustodian.profile.update')"
        form-method="PATCH"
    />
@endsection
