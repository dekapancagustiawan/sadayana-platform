@extends('adminlte::page')

@section('title', 'Create Role')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-shield-alt mr-2 text-primary"></i>
            Create Role
        </h1>
        <a href="{{ route('roles.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> Back to Roles
        </a>
    </div>
@stop

@section('content')

    <form id="create-role-form" action="{{ route('roles.store') }}" method="POST" novalidate>
        @csrf

        <div class="row">
            {{-- Role Basic Details --}}
            <div class="col-md-4">
                <div class="card card-outline card-primary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-info-circle mr-1"></i> Role Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label for="name">Role Name <span class="text-danger">*</span></label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                class="form-control @error('name') is-invalid @enderror"
                                placeholder="e.g. billing-manager"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

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
                    <div class="card-footer">
                        <button id="btn-save-role" type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save mr-1"></i> Create Role
                        </button>
                    </div>
                </div>
            </div>

            {{-- Permissions Checklist Grouped by Namespace --}}
            <div class="col-md-8">
                <div class="card card-outline card-primary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="fas fa-key mr-1"></i> Assign Permissions</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-xs btn-outline-secondary mr-1" id="select-all-permissions">Select All</button>
                            <button type="button" class="btn btn-xs btn-outline-secondary" id="deselect-all-permissions">Deselect All</button>
                        </div>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        @foreach($permissionGroups as $group)
                            <div class="card card-secondary card-outline mb-3">
                                <div class="card-header py-2">
                                    <h4 class="card-title" style="font-size: 1rem;">
                                        <i class="fas fa-folder-open mr-1"></i> {{ $group['heading'] }}
                                    </h4>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-xs btn-link toggle-group-perms" data-group="{{ $group['key'] }}">Toggle All</button>
                                    </div>
                                </div>
                                <div class="card-body py-2">
                                    <div class="row">
                                        @foreach($group['permissions'] as $perm)
                                            <div class="col-sm-6 col-md-4 mb-2">
                                                <div class="custom-control custom-checkbox">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $perm->name }}"
                                                        class="custom-control-input perm-checkbox group-{{ $group['key'] }}"
                                                        id="perm-{{ $perm->id }}"
                                                        {{ is_array(old('permissions')) && in_array($perm->name, old('permissions')) ? 'checked' : '' }}
                                                    >
                                                    <label class="custom-control-label font-weight-normal text-muted" for="perm-{{ $perm->id }}" style="font-size: 0.9rem;">
                                                        {{ $presenter->label($perm->name) }}
                                                        @if($technical = $presenter->technicalName($perm->name))
                                                            <br><code style="font-size: 0.75rem;">{{ $technical }}</code>
                                                        @endif
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </form>

@stop

@section('js')
    <script>
        $(document).ready(function() {
            // Select All
            $('#select-all-permissions').click(function() {
                $('.perm-checkbox').prop('checked', true);
            });

            // Deselect All
            $('#deselect-all-permissions').click(function() {
                $('.perm-checkbox').prop('checked', false);
            });

            // Toggle Group All
            $('.toggle-group-perms').click(function(e) {
                e.preventDefault();
                var group = $(this).data('group');
                var checkboxes = $('.group-' + group);
                var allChecked = true;
                checkboxes.each(function() {
                    if (!$(this).prop('checked')) {
                        allChecked = false;
                        return false;
                    }
                });
                checkboxes.prop('checked', !allChecked);
            });
        });
    </script>
@stop
