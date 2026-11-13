@extends('adminlte::page')

@section('title', 'Create Permission')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-key mr-2 text-primary"></i>
            Create Permission
        </h1>
        <a href="{{ route('permissions.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Permissions
        </a>
    </div>
@stop

@section('content')

    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Permission Details</h3>
                </div>

                <form id="create-permission-form" action="{{ route('permissions.store') }}" method="POST" novalidate>
                    @csrf

                    <div class="card-body">

                        {{-- Name --}}
                        <div class="form-group">
                            <label for="name">Permission Name <span class="text-danger">*</span></label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. users.view"
                                required
                            >
                            <small class="text-muted mt-1 d-block">Recommended format: <code>module_name.action</code> (e.g. <code>customers.create</code>).</small>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Guard --}}
                        <div class="form-group">
                            <label for="guard_name">Guard Name <span class="text-danger">*</span></label>
                            <input
                                id="guard_name"
                                type="text"
                                name="guard_name"
                                value="{{ old('guard_name', 'web') }}"
                                class="form-control @error('guard_name') is-invalid @enderror"
                                required
                            >
                            @error('guard_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>

                    <div class="card-footer d-flex justify-content-end gap-2">
                        <a href="{{ route('permissions.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                        <button id="btn-save-permission" type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-1"></i> Create Permission
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>

@stop
