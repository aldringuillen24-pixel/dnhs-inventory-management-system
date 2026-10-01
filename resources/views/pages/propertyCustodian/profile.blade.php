@extends('layouts.app')

@section('content')
    <x-profile-page
        :user="auth()->user()"
        role-label="Property Custodian"
        :form-action="route('propertyCustodian.profile.update')"
        form-method="PATCH"
    />
@endsection
