/**
 * Dashboard Notifications — Polling Client
 *
 * Polls the notification API every 60 seconds and updates the
 * AdminLTE navbar bell icon, badge, and dropdown.
 *
 * Transport-agnostic: This file only handles the UI update logic.
 * Replace fetchNotifications() with SSE/WebSocket handler in the future
 * without changing the rendering logic.
 */
(function () {
    'use strict';

    const POLL_INTERVAL = 60000; // 60 seconds
    const API_URL = '/admin/notifications';
    const UNREAD_COUNT_URL = '/admin/notifications/unread-count';
    const READ_ALL_URL = '/admin/notifications/read-all';

    let previousUnreadCount = -1;
    let pollTimer = null;

    // ─── DOM References ──────────────────────────────────────────────
    function getBadge() {
        return document.getElementById('notification-badge');
    }
    function getDropdownList() {
        return document.getElementById('notification-list');
    }
    function getDropdownHeader() {
        return document.getElementById('notification-header');
    }

    // ─── CSRF Token ──────────────────────────────────────────────────
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // ─── Render Helpers ──────────────────────────────────────────────

    function updateBadge(count) {
        const badge = getBadge();
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
        } else {
            badge.textContent = '0';
            badge.style.display = 'none';
        }
    }

    function renderNotifications(notifications) {
        const list = getDropdownList();
        if (!list) return;

        if (notifications.length === 0) {
            list.innerHTML = '<a href="#" class="dropdown-item text-center text-muted"><small>No notifications</small></a>';
            return;
        }

        let html = '';
        notifications.forEach(function (n) {
            const readClass = n.is_read ? 'notification-read' : 'notification-unread';
            const dotHtml = n.is_read ? '' : '<span class="notification-dot"></span>';
            const clickUrl = n.reference_url || '#';

            html += '<a href="' + clickUrl + '" class="dropdown-item notification-item ' + readClass + '" data-id="' + n.id + '">'
                + '<div class="d-flex align-items-start">'
                +   '<div class="mr-2 mt-1"><i class="' + (n.type_icon || 'fas fa-bell') + ' text-' + (n.type_color || 'primary') + '"></i></div>'
                +   '<div class="flex-grow-1">'
                +     '<div class="d-flex justify-content-between">'
                +       '<strong class="notification-title">' + escapeHtml(n.title) + '</strong>'
                +       dotHtml
                +     '</div>'
                +     '<small class="text-muted notification-message">' + escapeHtml(n.message) + '</small><br>'
                +     '<small class="text-muted"><i class="fas fa-clock mr-1"></i>' + (n.time_ago || '') + '</small>'
                +   '</div>'
                + '</div>'
                + '</a>';
        });

        list.innerHTML = html;

        // Attach click handlers to mark as read
        list.querySelectorAll('.notification-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                const id = item.getAttribute('data-id');
                if (id) {
                    markAsRead(id);
                }
            });
        });
    }

    function updateHeader(count) {
        const header = getDropdownHeader();
        if (header) {
            header.textContent = count + ' Notification' + (count !== 1 ? 's' : '');
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text || ''));
        return div.innerHTML;
    }

    // ─── API Calls ───────────────────────────────────────────────────

    function fetchNotifications() {
        fetch(API_URL + '?limit=10', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        .then(function (response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function (data) {
            const newCount = data.unread_count || 0;

            updateBadge(newCount);
            updateHeader(newCount);
            renderNotifications(data.notifications || []);

            // ─── Future Hook: Notification Sound ─────────────
            if (previousUnreadCount >= 0 && newCount > previousUnreadCount) {
                onNewNotification(newCount - previousUnreadCount);
            }

            previousUnreadCount = newCount;
        })
        .catch(function (err) {
            console.warn('[Notifications] Poll failed:', err.message);
        });
    }

    function markAsRead(id) {
        fetch(API_URL.replace('/notifications', '/notifications/' + id + '/read'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        }).catch(function (err) {
            console.warn('[Notifications] markAsRead failed:', err.message);
        });
    }

    function markAllAsRead() {
        fetch(READ_ALL_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
        .then(function () {
            fetchNotifications(); // Refresh UI
        })
        .catch(function (err) {
            console.warn('[Notifications] markAllAsRead failed:', err.message);
        });
    }

    // ─── Future Hooks ────────────────────────────────────────────────

    /**
     * Called when new notifications arrive. Override this in the future
     * to play sounds or trigger browser notifications.
     */
    function onNewNotification(count) {
        // Hook: playNotificationSound();
        // Hook: showBrowserNotification(count);
    }

    // ─── Initialization ──────────────────────────────────────────────

    function startPolling() {
        if (pollTimer === null && !document.hidden) {
            pollTimer = setInterval(fetchNotifications, POLL_INTERVAL);
        }
    }

    function stopPolling() {
        clearInterval(pollTimer);
        pollTimer = null;
    }

    function init() {
        // Initial fetch
        fetchNotifications();

        // Start polling — berhenti saat tab tersembunyi supaya tab admin yang
        // terbuka di latar tidak terus membebani server; refresh saat kembali.
        startPolling();
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
            } else {
                fetchNotifications();
                startPolling();
            }
        });

        // "Mark All Read" button
        const markAllBtn = document.getElementById('mark-all-read');
        if (markAllBtn) {
            markAllBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                markAllAsRead();
            });
        }
    }

    // ─── Public API (for future SSE/WS migration) ────────────────────
    window.DashboardNotifications = {
        refresh: fetchNotifications,
        markAsRead: markAsRead,
        markAllAsRead: markAllAsRead,
        stopPolling: stopPolling,
        startPolling: startPolling,
    };

    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
