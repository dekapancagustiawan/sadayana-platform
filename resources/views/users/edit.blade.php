@extends('adminlte::page')

@section('title', 'Edit User')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-user-edit mr-2 text-warning"></i>
            Edit User
        </h1>
        <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Users
        </a>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-user mr-1"></i>
                        Editing: <strong>{{ $user->name }}</strong>
                        <span class="text-muted ml-2" style="font-size:0.85rem;">#{{ $user->id }}</span>
                    </h3>
                </div>

                <form id="edit-user-form" action="{{ route('users.update', $user) }}" method="POST" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="card-body">

                        {{-- Name --}}
                        <div class="form-group">
                            <label for="name">Full Name <span class="text-danger">*</span></label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                class="form-control @error('name') is-invalid @enderror"
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
                                value="{{ old('email', $user->email) }}"
                                class="form-control @error('email') is-invalid @enderror"
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
                                value="{{ old('phone', $user->phone) }}"
                                class="form-control @error('phone') is-invalid @enderror"
                                placeholder="e.g. +1234567890"
                            >
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password (optional on edit) --}}
                        <div class="form-group">
                            <label for="password">
                                New Password
                                <small class="text-muted">(leave blank to keep current)</small>
                            </label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                placeholder="Minimum 8 characters"
                                autocomplete="new-password"
                            >
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Confirm Password --}}
                        <div class="form-group">
                            <label for="password_confirmation">Confirm New Password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                class="form-control"
                                placeholder="Re-enter new password"
                                autocomplete="new-password"
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
                                                {{ (is_array(old('roles')) && in_array($role->name, old('roles'))) || (!is_array(old('roles')) && $user->roles->contains('name', $role->name)) ? 'checked' : '' }}
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
                                <option value="active"    {{ old('status', $user->status->value) === 'active'    ? 'selected' : '' }}>Active</option>
                                <option value="inactive"  {{ old('status', $user->status->value) === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ old('status', $user->status->value) === 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button id="btn-update-user" type="submit" class="btn btn-warning">
                            <i class="fas fa-save mr-1"></i> Save Changes
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@stop
