/**
 * Browser Notifications for ECAB Taxi Booking Manager
 */
(function($) {
    'use strict';
    
    // Notification Manager
    const MPTBMNotifications = {
        // Initialize
        init: function() {
            this.setupVariables();
            this.bindEvents();
            this.checkPermission();
            this.createNotificationBadge();
            this.createNotificationCenter();
            this.loadUserNotifications();
            this.setupPolling();
        },
        
        // Setup variables
        setupVariables: function() {
            this.permissionRequested = localStorage.getItem('mptbm_notification_permission_requested') === 'yes';
            this.permissionGranted = localStorage.getItem('mptbm_notification_permission_granted') === 'yes';
            this.notificationCount = 0;
            this.notifications = [];
            this.pollingInterval = null;
        },
        
        // Bind events
        bindEvents: function() {
            // Permission request
            $(document).on('click', '.mptbm-notification-permission-allow', this.requestPermission.bind(this));
            $(document).on('click', '.mptbm-notification-permission-close', this.closePermissionRequest.bind(this));
            
            // Toast notification
            $(document).on('click', '.mptbm-toast-notification', this.handleToastClick.bind(this));
            $(document).on('click', '.mptbm-toast-notification-close', this.closeToast.bind(this));
            
            // Notification center
            $(document).on('click', '.mptbm-notification-badge', this.toggleNotificationCenter.bind(this));
            $(document).on('click', '.mptbm-notification-center-close', this.closeNotificationCenter.bind(this));
            $(document).on('click', '.mptbm-notification-center-overlay', this.closeNotificationCenter.bind(this));
            $(document).on('click', '.mptbm-notification-center-item', this.handleNotificationClick.bind(this));
            
            // Test notification
            $(document).on('click', '.mptbm-test-notification-button', this.sendTestNotification.bind(this));
        },
        
        // Check notification permission
        checkPermission: function() {
            if (!('Notification' in window)) {
                console.log('This browser does not support desktop notifications');
                return;
            }
            
            if (Notification.permission === 'granted') {
                this.permissionGranted = true;
                localStorage.setItem('mptbm_notification_permission_granted', 'yes');
            } else if (Notification.permission === 'denied') {
                this.permissionGranted = false;
                localStorage.setItem('mptbm_notification_permission_granted', 'no');
            } else if (!this.permissionRequested && mptbm_notifications.enabled === 'yes') {
                // Show permission request
                setTimeout(() => {
                    $('.mptbm-notification-permission').css('display', 'block').addClass('show');
                }, 3000);
            }
        },
        
        // Request notification permission
        requestPermission: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!('Notification' in window)) {
                return;
            }
            
            Notification.requestPermission().then((permission) => {
                if (permission === 'granted') {
                    this.permissionGranted = true;
                    localStorage.setItem('mptbm_notification_permission_granted', 'yes');
                    
                    // Save subscription
                    this.saveSubscription();
                    
                    // Show success message
                    this.showToast({
                        title: 'Notifications Enabled',
                        body: 'You will now receive notifications about your bookings',
                        icon: mptbm_notifications.icon
                    });
                }
                
                this.permissionRequested = true;
                localStorage.setItem('mptbm_notification_permission_requested', 'yes');
                
                // Close permission request
                $('.mptbm-notification-permission').removeClass('show');
                setTimeout(() => {
                    $('.mptbm-notification-permission').css('display', 'none');
                }, 300);
            });
        },
        
        // Close permission request
        closePermissionRequest: function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            this.permissionRequested = true;
            localStorage.setItem('mptbm_notification_permission_requested', 'yes');
            
            $('.mptbm-notification-permission').removeClass('show');
            setTimeout(() => {
                $('.mptbm-notification-permission').css('display', 'none');
            }, 300);
        },
        
        // Save subscription
        saveSubscription: function() {
            if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                return;
            }
            
            // Check if user is logged in
            if (!this.isUserLoggedIn()) {
                return;
            }
            
            // Register service worker
            navigator.serviceWorker.register(mptbm_notifications.service_worker_url)
                .then((registration) => {
                    console.log('Service Worker registered');
                    
                    // Subscribe to push notifications
                    return registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.urlBase64ToUint8Array(mptbm_notifications.public_key)
                    });
                })
                .then((subscription) => {
                    // Send subscription to server
                    return $.ajax({
                        url: mptbm_notifications.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'mptbm_save_notification_settings',
                            nonce: mptbm_notifications.nonce,
                            enabled: 'yes',
                            endpoint: subscription.endpoint,
                            keys: {
                                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('p256dh')))),
                                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(subscription.getKey('auth'))))
                            }
                        }
                    });
                })
                .catch((error) => {
                    console.error('Error saving subscription:', error);
                });
        },
        
        // Convert base64 to Uint8Array
        urlBase64ToUint8Array: function(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            
            return outputArray;
        },
        
        // Show toast notification
        showToast: function(data) {
            // Remove existing toast
            $('.mptbm-toast-notification').remove();
            
            // Create toast element
            const toast = $(`
                <div class="mptbm-toast-notification">
                    <div class="mptbm-toast-notification-content">
                        <div class="mptbm-toast-notification-icon">
                            <img src="${data.icon}" alt="Notification">
                        </div>
                        <div class="mptbm-toast-notification-text">
                            <h4 class="mptbm-toast-notification-title">${data.title}</h4>
                            <p class="mptbm-toast-notification-body">${data.body}</p>
                        </div>
                    </div>
                    <button class="mptbm-toast-notification-close">&times;</button>
                    <div class="mptbm-toast-notification-progress"></div>
                </div>
            `);
            
            // Add data attributes
            if (data.url) {
                toast.attr('data-url', data.url);
            }
            
            // Add to body
            $('body').append(toast);
            
            // Show toast
            setTimeout(() => {
                toast.addClass('show');
            }, 100);
            
            // Auto close after 5 seconds
            setTimeout(() => {
                this.closeToast(null, toast);
            }, 5000);
        },
        
        // Close toast notification
        closeToast: function(e, toastElement) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            const toast = toastElement || $(e.target).closest('.mptbm-toast-notification');
            
            toast.removeClass('show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        },
        
        // Handle toast click
        handleToastClick: function(e) {
            if ($(e.target).hasClass('mptbm-toast-notification-close')) {
                return;
            }
            
            const toast = $(e.currentTarget);
            const url = toast.attr('data-url');
            
            if (url) {
                window.location.href = url;
            }
        },
        
        // Create notification badge
        createNotificationBadge: function() {
            // Only create if user is logged in
            if (!this.isUserLoggedIn()) {
                return;
            }
            
            // Create badge element
            const badge = $(`
                <div class="mptbm-notification-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span class="mptbm-notification-badge-count" style="display: none;">0</span>
                </div>
            `);
            
            // Add to body
            $('body').append(badge);
        },
        
        // Update notification badge count
        updateBadgeCount: function() {
            const badge = $('.mptbm-notification-badge-count');
            
            if (this.notificationCount > 0) {
                badge.text(this.notificationCount > 99 ? '99+' : this.notificationCount);
                badge.css('display', 'flex');
            } else {
                badge.css('display', 'none');
            }
        },
        
        // Create notification center
        createNotificationCenter: function() {
            // Only create if user is logged in
            if (!this.isUserLoggedIn()) {
                return;
            }
            
            // Create overlay
            const overlay = $('<div class="mptbm-notification-center-overlay"></div>');
            
            // Create notification center
            const center = $(`
                <div class="mptbm-notification-center">
                    <div class="mptbm-notification-center-header">
                        <h3 class="mptbm-notification-center-title">Notifications</h3>
                        <button class="mptbm-notification-center-close">&times;</button>
                    </div>
                    <div class="mptbm-notification-center-list"></div>
                </div>
            `);
            
            // Add to body
            $('body').append(overlay);
            $('body').append(center);
        },
        
        // Toggle notification center
        toggleNotificationCenter: function(e) {
            e.preventDefault();
            
            const center = $('.mptbm-notification-center');
            const overlay = $('.mptbm-notification-center-overlay');
            
            if (center.hasClass('show')) {
                this.closeNotificationCenter();
            } else {
                center.addClass('show');
                overlay.addClass('show');
                
                // Reset notification count
                this.notificationCount = 0;
                this.updateBadgeCount();
            }
        },
        
        // Close notification center
        closeNotificationCenter: function(e) {
            if (e) {
                e.preventDefault();
            }
            
            const center = $('.mptbm-notification-center');
            const overlay = $('.mptbm-notification-center-overlay');
            
            center.removeClass('show');
            overlay.removeClass('show');
        },
        
        // Load user notifications
        loadUserNotifications: function() {
            // Only load if user is logged in
            if (!this.isUserLoggedIn()) {
                return;
            }
            
            // Get notifications from server
            $.ajax({
                url: mptbm_notifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'mptbm_get_notifications',
                    nonce: mptbm_notifications.nonce
                },
                success: (response) => {
                    if (response.success && response.data.notifications) {
                        this.notifications = response.data.notifications;
                        this.renderNotifications();
                        
                        // Update notification count
                        this.notificationCount = response.data.unread_count || 0;
                        this.updateBadgeCount();
                    }
                }
            });
        },
        
        // Render notifications in notification center
        renderNotifications: function() {
            const list = $('.mptbm-notification-center-list');
            list.empty();
            
            if (this.notifications.length === 0) {
                list.html(`
                    <div class="mptbm-notification-center-empty">
                        <div class="mptbm-notification-center-empty-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </div>
                        <div class="mptbm-notification-center-empty-text">No notifications yet</div>
                    </div>
                `);
                return;
            }
            
            // Add notifications to list
            this.notifications.forEach((notification) => {
                const item = $(`
                    <div class="mptbm-notification-center-item" data-id="${notification.id}">
                        <h4 class="mptbm-notification-center-item-title">${notification.title}</h4>
                        <p class="mptbm-notification-center-item-body">${notification.body}</p>
                        <div class="mptbm-notification-center-item-time">${this.formatTimestamp(notification.timestamp)}</div>
                    </div>
                `);
                
                // Add data attributes
                if (notification.url) {
                    item.attr('data-url', notification.url);
                }
                
                // Add to list
                list.append(item);
            });
        },
        
        // Format timestamp
        formatTimestamp: function(timestamp) {
            const date = new Date(timestamp * 1000);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) {
                return 'Just now';
            } else if (diff < 3600) {
                const minutes = Math.floor(diff / 60);
                return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
            } else if (diff < 86400) {
                const hours = Math.floor(diff / 3600);
                return `${hours} hour${hours > 1 ? 's' : ''} ago`;
            } else if (diff < 604800) {
                const days = Math.floor(diff / 86400);
                return `${days} day${days > 1 ? 's' : ''} ago`;
            } else {
                return date.toLocaleDateString();
            }
        },
        
        // Handle notification click
        handleNotificationClick: function(e) {
            const item = $(e.currentTarget);
            const url = item.attr('data-url');
            
            if (url) {
                window.location.href = url;
            }
        },
        
        // Setup polling for new notifications
        setupPolling: function() {
            // Only setup if user is logged in
            if (!this.isUserLoggedIn()) {
                return;
            }
            
            // Check for new notifications every 30 seconds
            this.pollingInterval = setInterval(() => {
                this.checkForNewNotifications();
            }, 30000);
        },
        
        // Check for new notifications
        checkForNewNotifications: function() {
            $.ajax({
                url: mptbm_notifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'mptbm_check_new_notifications',
                    nonce: mptbm_notifications.nonce,
                    last_timestamp: this.notifications.length > 0 ? this.notifications[0].timestamp : 0
                },
                success: (response) => {
                    if (response.success && response.data.new_notifications) {
                        // Add new notifications to the beginning of the array
                        this.notifications = [...response.data.new_notifications, ...this.notifications];
                        
                        // Update notification count
                        this.notificationCount += response.data.new_notifications.length;
                        this.updateBadgeCount();
                        
                        // Render notifications
                        this.renderNotifications();
                        
                        // Show toast for the newest notification
                        if (response.data.new_notifications.length > 0 && this.permissionGranted) {
                            const newest = response.data.new_notifications[0];
                            this.showBrowserNotification(newest);
                        }
                    }
                }
            });
        },
        
        // Show browser notification
        showBrowserNotification: function(data) {
            if (!('Notification' in window) || Notification.permission !== 'granted') {
                return;
            }
            
            const notification = new Notification(data.title, {
                body: data.body,
                icon: data.icon || mptbm_notifications.icon
            });
            
            notification.onclick = () => {
                if (data.url) {
                    window.open(data.url);
                }
            };
            
            // Also show toast
            this.showToast(data);
        },
        
        // Send test notification
        sendTestNotification: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: mptbm_notifications.ajax_url,
                type: 'POST',
                data: {
                    action: 'mptbm_send_test_notification',
                    nonce: mptbm_notifications.nonce
                },
                success: (response) => {
                    if (response.success) {
                        alert('Test notification sent successfully!');
                    } else {
                        alert('Failed to send test notification: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('An error occurred while sending the test notification.');
                }
            });
        },
        
        // Check if user is logged in
        isUserLoggedIn: function() {
            return typeof mptbm_notifications.user_id !== 'undefined' && mptbm_notifications.user_id > 0;
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        MPTBMNotifications.init();
    });
    
})(jQuery);