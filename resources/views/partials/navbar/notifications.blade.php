{{-- Dashboard Notification Bell (AdminLTE Navbar) --}}
<li class="nav-item dropdown">
    <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false" id="notification-bell">
        <i class="far fa-bell"></i>
        <span class="badge badge-danger navbar-badge" id="notification-badge" style="display: none;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="max-height: 400px; overflow-y: auto;">
        <span class="dropdown-item dropdown-header" id="notification-header">0 Notifications</span>
        <div class="dropdown-divider"></div>

        {{-- Notification items will be injected here by JS --}}
        <div id="notification-list">
            <a href="#" class="dropdown-item text-center text-muted">
                <small>Loading...</small>
            </a>
        </div>

        <div class="dropdown-divider"></div>
        <a href="#" class="dropdown-item dropdown-footer text-primary" id="mark-all-read">
            <i class="fas fa-check-double mr-1"></i> Mark All as Read
        </a>
    </div>
</li>

<style>
    .notification-item {
        white-space: normal !important;
        padding: 8px 12px;
        border-bottom: 1px solid #f4f4f4;
    }
    .notification-item:last-child {
        border-bottom: none;
    }
    .notification-unread {
        background-color: #f0f7ff;
    }
    .notification-read {
        background-color: #ffffff;
    }
    .notification-dot {
        display: inline-block;
        width: 8px;
        height: 8px;
        background-color: #007bff;
        border-radius: 50%;
        margin-left: 4px;
        flex-shrink: 0;
    }
    .notification-title {
        font-size: 13px;
    }
    .notification-message {
        font-size: 12px;
        line-height: 1.4;
    }
</style>

{{-- Load the polling script --}}
<script src="{{ asset('js/dashboard-notifications.js') }}"></script>
