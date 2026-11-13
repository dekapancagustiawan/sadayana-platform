@extends('adminlte::page')

@section('title', 'Create User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-user-plus mr-2 text-primary"></i>
            Create User
        </h1>
        <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Users
        </a>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user mr-1"></i> New User Details</h3>
                </div>

                <form id="create-user-form" action="{{ route('users.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="card-body">

                        {{-- Name --}}
                        <div class="form-group">
                            <label for="name">Full Name <span class="text-danger">*</span></label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. John Smith"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="form-group">
                            <label for="email">Email Address <span class="text-danger">*</span></label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="form-control @error('email') is-invalid @enderror"
                                placeholder="user@example.com"
                                required
                            >
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input
                                id="phone"
                                type="text"
                                name="phone"
                                value="{{ old('phone') }}"
                                class="form-control @error('phone') is-invalid @enderror"
                                placeholder="e.g. +1234567890"
                            >
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="form-group">
                            <label for="password">Password <span class="text-danger">*</span></label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Minimum 8 characters"
                                required
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div class="form-group">
                            <label for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                placeholder="Re-enter password"
                                required
                            >
                        </div>

                        {{-- Roles Checklist --}}
                        <div class="form-group">
                            <label>Roles <span class="text-danger">*</span></label>
                            <div class="row">
                                @foreach($roles as $role)
                                    <div class="col-md-6 mb-2">
                                        <div class="custom-control custom-checkbox">
                                            <input
                                                type="checkbox"
                                                name="roles[]"
                                                value="{{ $role->name }}"
                                                class="custom-control-input"
                                                id="role-{{ $role->id }}"
                                                {{ is_array(old('roles')) && in_array($role->name, old('roles')) ? 'checked' : '' }}
                                            >
                                            <label class="custom-control-label font-weight-normal" for="role-{{ $role->id }}">
                                                {{ ucfirst($role->name) }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('roles')
                                <div class="text-danger font-weight-normal" style="font-size: 80%">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="form-group">
                            <label for="status">Account Status <span class="text-danger">*</span></label>
                            <select
                                id="status"
                                name="status"
                                class="form-control @error('status') is-invalid @enderror"
                                required
                            >
                                <option value="active"    {{ old('status', 'active') === 'active'    ? 'selected' : '' }}>Active</option>
                                <option value="inactive"  {{ old('status') === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button id="btn-save-user" type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Create User
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@stop
