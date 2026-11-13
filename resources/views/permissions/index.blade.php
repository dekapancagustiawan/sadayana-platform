@extends('adminlte::page')

@section('title', __('Permission Management'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="m-0">
            <i class="fas fa-key mr-2 text-primary"></i>
            {{ __('Permission Management') }}
        </h1>
        <a href="{{ route('permissions.create') }}" class="btn btn-primary btn-sm" id="btn-create-permission">
            <i class="fas fa-plus mr-1"></i> New Permission
        </a>
    </div>
@stop

@section('content')

    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Permissions Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>
                Permissions
                <span class="badge badge-secondary ml-2">{{ $permissions->total() }}</span>
            </h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>Permission Name</th>
                        <th>Module / Group</th>
                        <th>Guard Name</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($permissions as $permission)
                        <tr>
                            <td>{{ $permission->id }}</td>
                            <td>
                                <code class="text-primary font-weight-bold">{{ $permission->name }}</code>
                            </td>
                            <td>
                                @php
                                    $parts = explode('.', $permission->name);
                                    $group = count($parts) > 1 ? $parts[0] : 'General';
                                @endphp
                                <span class="badge badge-secondary text-capitalize">{{ $group }}</span>
                            </td>
                            <td>{{ $permission->guard_name }}</td>
                            <td>
                                <a
                                    href="{{ route('permissions.edit', $permission) }}"
                                    class="btn btn-xs btn-warning"
                                    title="Edit permission"
                                    id="btn-edit-permission-{{ $permission->id }}"
                                >
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($permission->roles()->count() === 0)
                                    <form
                                        id="delete-permission-form-{{ $permission->id }}"
                                        action="{{ route('permissions.destroy', $permission) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Delete permission {{ addslashes($permission->name) }}?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-xs btn-danger"
                                            title="Delete permission"
                                            id="btn-delete-permission-{{ $permission->id }}"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="fas fa-key fa-2x mb-2 d-block"></i>
                                No permissions found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($permissions->hasPages())
            <div class="card-footer d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="text-muted small mb-2 mb-md-0">
                    Showing {{ $permissions->firstItem() ?? 0 }} to {{ $permissions->lastItem() ?? 0 }} of {{ $permissions->total() }} entries
                </div>
                <div class="pagination-wrapper">
                    {{ $permissions->withQueryString()->links('partials.pagination') }}
                </div>
            </div>
        @endif
    </div>


    <!-- Offcanvas for Detail View -->
    <style>
    .detail-offcanvas {
        position: fixed;
        top: 0;
        right: -400px;
        width: 400px;
        height: 100vh;
        background: #fff;
        box-shadow: -2px 0 10px rgba(0,0,0,0.1);
        z-index: 1050;
        transition: right 0.3s ease-in-out;
        padding: 20px;
        overflow-y: auto;
    }
    .detail-offcanvas.open {
        right: 0;
    }
    .offcanvas-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .offcanvas-title {
        font-size: 1.25rem;
        margin: 0;
    }
    .detail-row {
        margin-bottom: 15px;
        border-bottom: 1px dashed #f0f0f0;
        padding-bottom: 5px;
    }
    .detail-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .detail-value {
        color: #212529;
        font-size: 1rem;
        margin-top: 4px;
    }
    </style>

    <div class="detail-offcanvas" id="detailOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><i class="fas fa-info-circle mr-2 text-primary"></i> Data Detail</h5>
            <button type="button" class="close" id="closeOffcanvas" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="offcanvas-body" id="offcanvasBody">
            <!-- Content injected here via JS -->
        </div>
    </div>

@stop


@section('js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteForms = document.querySelectorAll('.form-delete');
        deleteForms.forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This action cannot be undone!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@stop
