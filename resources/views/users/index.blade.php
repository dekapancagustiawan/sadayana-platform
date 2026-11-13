@extends('adminlte::page')

@section('title', __('User Management'))

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6 d-flex align-items-center">
            <h1 class="mr-3">
                <i class="fas fa-users mr-2 text-primary"></i>
                {{ __('User Management') }}
            </h1>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm" id="btn-create-user">
                <i class="fas fa-plus mr-1"></i> New User
            </a>
        </div>
        <div class="col-sm-6">
            <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </div>
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

    {{-- Filter Bar --}}
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter</h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form id="filter-form" method="GET" action="{{ route('users.index') }}">
                <div class="row">
                    <div class="col-md-5">
                        <div class="input-group">
                            <input
                                id="search"
                                type="text"
                                name="search"
                                value="{{ request('search') }}"
                                class="form-control"
                                placeholder="Search by name, email, or phone…"
                            >
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filter-status" name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Active</option>
                            <option value="inactive"  {{ request('status') === 'inactive'  ? 'selected' : '' }}>Inactive</option>
                            <option value="suspended" {{ request('status') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="filter-role" name="role" class="form-control" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ request('role') === $role->name ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary btn-block" title="Clear filters">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Users Table --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-list mr-1"></i>
                Users
                <span class="badge badge-secondary ml-2">{{ $users->total() }}</span>
            </h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width:40px">#</th>
                        <th>@include('partials.sortable-header', ['column' => 'name', 'label' => 'Name'])</th>
                        <th>@include('partials.sortable-header', ['column' => 'email', 'label' => 'Email'])</th>
                        <th>@include('partials.sortable-header', ['column' => 'phone', 'label' => 'Phone'])</th>
                        <th>Roles</th>
                        <th>@include('partials.sortable-header', ['column' => 'status', 'label' => 'Status'])</th>
                        <th>Last Login</th>
                        <th>@include('partials.sortable-header', ['column' => 'created_at', 'label' => 'Created'])</th>
                        <th style="width:120px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <i class="fas fa-user-circle text-muted mr-1"></i>
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span class="badge badge-light text-muted ml-1">You</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td>
                                @foreach($user->roles as $role)
                                    <span class="badge badge-primary mr-1">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if($user->status->value === 'active')
                                    <span class="badge badge-success">Active</span>
                                @elseif($user->status->value === 'inactive')
                                    <span class="badge badge-secondary">Inactive</span>
                                @else
                                    <span class="badge badge-danger">Suspended</span>
                                @endif
                            </td>
                            <td class="text-muted" style="font-size:0.85rem;">
                                {{ $user->last_login_at ? $user->last_login_at->format('d M Y H:i') : 'Never' }}
                            </td>
                            <td class="text-muted" style="font-size:0.85rem;">
                                {{ $user->created_at->format('d M Y') }}
                            </td>
                            <td>
                                <a
                                    href="{{ route('users.edit', $user) }}"
                                    class="btn btn-xs btn-warning"
                                    title="Edit user"
                                    id="btn-edit-user-{{ $user->id }}"
                                >
                                    <i class="fas fa-edit"></i>
                                </a>
                                @if($user->id !== auth()->id())
                                    <form
                                        id="delete-user-form-{{ $user->id }}"
                                        action="{{ route('users.destroy', $user) }}"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Delete user {{ addslashes($user->name) }}? This cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="btn btn-xs btn-danger"
                                            title="Delete user"
                                            id="btn-delete-user-{{ $user->id }}"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <div class="d-flex flex-wrap justify-content-between align-items-center w-100 gap-2">
                <!-- LEFT: Page Size Selector -->
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <form action="{{ request()->url() }}" method="GET" class="d-flex align-items-center mb-0">
                        @foreach(request()->except(['per_page', 'page']) as $key => $value)
                            @if(is_array($value))
                                @foreach($value as $v)
                                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                                @endforeach
                            @else
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <span class="mr-2 text-muted small">Show</span>
                        <select name="per_page" class="form-control form-control-sm d-inline-block w-auto" onchange="this.form.submit()">
                            @foreach([5, 10, 25, 50, 100] as $size)
                                <option value="{{ $size }}" {{ request('per_page', 10) == $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                        <span class="ml-2 text-muted small">entries</span>
                    </form>
                </div>

                <!-- CENTER: Record Count Display -->
                <div class="text-muted small text-center mb-2 mb-md-0">
                    Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} entries
                </div>

                <!-- RIGHT: Pagination links -->
                <div class="pagination-wrapper">
                    {{ $users->withQueryString()->links('partials.pagination') }}
                </div>
            </div>
        </div>
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
        .card-footer .pagination {
        float: right !important;
        margin: 0 !important;
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
