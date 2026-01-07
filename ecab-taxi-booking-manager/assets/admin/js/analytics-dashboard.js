/**
 * E-Cab Taxi Booking Manager - Analytics Dashboard
 */
 
(function($) {
    'use strict';

    // Chart instances
    let bookingsChart = null;
    let revenueChart = null;
    let popularRoutesChart = null;
    let bookingStatusChart = null;

    // Date range - include tomorrow to ensure today's orders are included
    let startDate = moment().subtract(30, 'days').format('YYYY-MM-DD');
    let endDate = moment().add(1, 'days').format('YYYY-MM-DD');

    /**
     * Initialize the dashboard
     */
    function initDashboard() {
        initDateRangePicker();
        initCharts();
        loadAnalyticsData();

        // Refresh button click handler
        $('.mptbm-refresh-data').on('click', function() {
            loadAnalyticsData();
        });
    }

    /**
     * Initialize date range picker
     */
    function initDateRangePicker() {
        // Set default date range to include today
        let defaultStartDate = moment().subtract(30, 'days');
        let defaultEndDate = moment().add(1, 'days'); // Include today and tomorrow to ensure today's orders are included


        $('#mptbm-date-range').daterangepicker({
            startDate: defaultStartDate,
            endDate: defaultEndDate,
            ranges: {
                'Today': [moment(), moment().add(1, 'days')],
                'Yesterday': [moment().subtract(1, 'days'), moment()],
                'Last 7 Days': [moment().subtract(6, 'days'), moment().add(1, 'days')],
                'Last 30 Days': [moment().subtract(29, 'days'), moment().add(1, 'days')],
                'This Month': [moment().startOf('month'), moment().endOf('month').add(1, 'days')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month').add(1, 'days')],
                'This Year': [moment().startOf('year'), moment().endOf('year').add(1, 'days')]
            },
            locale: {
                format: 'YYYY-MM-DD'
            }
        }, function(start, end) {
            startDate = start.format('YYYY-MM-DD');
            endDate = end.format('YYYY-MM-DD');
            loadAnalyticsData();
        });

        // Set initial values
        startDate = defaultStartDate.format('YYYY-MM-DD');
        endDate = defaultEndDate.format('YYYY-MM-DD');
    }

    /**
     * Initialize chart instances
     */
    function initCharts() {
        // Set Chart.js defaults
        Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
        Chart.defaults.font.size = 12;
        Chart.defaults.color = '#666';
        
        // Bookings Chart
        const bookingsCtx = document.getElementById('mptbm-bookings-chart').getContext('2d');
        bookingsChart = new Chart(bookingsCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: mptbm_analytics.labels.bookings,
                    data: [],
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    borderColor: '#2196F3',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 3,
                    pointBackgroundColor: '#2196F3',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1,
                    pointHoverRadius: 5,
                    pointHoverBackgroundColor: '#2196F3',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Revenue Chart
        const revenueCtx = document.getElementById('mptbm-revenue-chart').getContext('2d');
        revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: mptbm_analytics.labels.revenue,
                    data: [],
                    backgroundColor: 'rgba(76, 175, 80, 0.7)',
                    borderColor: '#4CAF50',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return mptbm_analytics.currency_symbol + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return mptbm_analytics.currency_symbol + value;
                            }
                        }
                    }
                }
            }
        });

        // Popular Routes Chart
        const routesCtx = document.getElementById('mptbm-popular-routes-chart').getContext('2d');
        popularRoutesChart = new Chart(routesCtx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#2196F3',
                        '#4CAF50',
                        '#FF9800',
                        '#9C27B0',
                        '#F44336'
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true
                    }
                },
                cutout: '60%'
            }
        });

        // Booking Status Chart
        const statusCtx = document.getElementById('mptbm-booking-status-chart').getContext('2d');
        bookingStatusChart = new Chart(statusCtx, {
            type: 'pie',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#4CAF50', // completed
                        '#2196F3', // processing
                        '#FF9800', // on-hold
                        '#9E9E9E', // pending
                        '#F44336', // cancelled
                        '#00BCD4', // refunded
                        '#E91E63'  // failed
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(0, 0, 0, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true
                    }
                }
            }
        });
    }

    /**
     * Load analytics data via AJAX
     */
    function loadAnalyticsData() {
        // Show loading indicators
        showLoadingIndicators();

        // Make AJAX request
        $.ajax({
            url: mptbm_analytics.ajax_url,
            type: 'POST',
            data: {
                action: 'mptbm_get_analytics_data',
                nonce: mptbm_analytics.nonce,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {

                if (response.success && response.data) {
                    updateDashboard(response.data);
                } else {
                    hideLoadingIndicators();

                    // Show more helpful error message
                    let errorMsg = 'Error loading analytics data. ';
                    if (response.data && response.data.message) {
                        errorMsg += response.data.message;
                    } else {
                        errorMsg += 'Please try again or check the browser console for more details.';
                    }
                    alert(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                hideLoadingIndicators();
                alert('Error loading analytics data. Please check the browser console for more details.');
            }
        });
    }

    /**
     * Show loading indicators
     */
    function showLoadingIndicators() {
        $('#mptbm-bookings-loading, #mptbm-revenue-loading, #mptbm-popular-routes-loading, #mptbm-booking-status-loading').show();
        $('#mptbm-recent-bookings-table').html('<tr><td colspan="7" class="mptbm-loading-row"><span class="spinner is-active"></span><p>' + mptbm_analytics.labels.loading + '</p></td></tr>');
    }

    /**
     * Hide loading indicators
     */
    function hideLoadingIndicators() {
        $('#mptbm-bookings-loading, #mptbm-revenue-loading, #mptbm-popular-routes-loading, #mptbm-booking-status-loading').hide();
    }

    /**
     * Update dashboard with new data
     */
    function updateDashboard(data) {
        // Update summary cards
        updateSummaryCards(data);
        
        // Update charts
        updateCharts(data);
        
        // Update recent bookings table
        updateRecentBookings(data.recent_bookings);
        
        // Hide loading indicators
        hideLoadingIndicators();
    }

    /**
     * Update summary cards
     */
    function updateSummaryCards(data) {
        // Format numbers
        const formattedRevenue = mptbm_analytics.currency_symbol + data.total_revenue.toFixed(2);
        const formattedAvgValue = mptbm_analytics.currency_symbol + data.avg_booking_value.toFixed(2);
        const formattedLostRevenue = mptbm_analytics.currency_symbol + data.lost_revenue.toFixed(2);

        // Update main card values
        $('#mptbm-total-bookings').text(data.total_bookings);
        $('#mptbm-total-revenue').text(formattedRevenue);
        $('#mptbm-avg-booking-value').text(formattedAvgValue);
        $('#mptbm-completion-rate').text(data.completion_rate + '%');

        // Update cancellation and lost revenue cards
        $('#mptbm-cancelled-bookings').text(data.cancelled_bookings);
        $('#mptbm-failed-bookings').text(data.failed_bookings);
        $('#mptbm-lost-revenue').text(formattedLostRevenue);

        // Calculate and display cancellation rate
        const totalProblematicBookings = data.cancelled_bookings + data.failed_bookings;
        const cancellationRate = data.total_bookings > 0 ? Math.round((totalProblematicBookings / data.total_bookings) * 100) : 0;
        $('#mptbm-cancellation-rate').text(cancellationRate + '%');
    }

    /**
     * Update all charts
     */
    function updateCharts(data) {

        // Check if we have any bookings data
        if (data.total_bookings === 0) {
            // Display "No data available" message in each chart
            displayNoDataMessage();
            return;
        }

        // Update Bookings Chart
        bookingsChart.data.labels = data.bookings_data.labels;
        bookingsChart.data.datasets[0].data = data.bookings_data.values;
        bookingsChart.update();

        // Update Revenue Chart
        revenueChart.data.labels = data.revenue_data.labels;
        revenueChart.data.datasets[0].data = data.revenue_data.values;
        revenueChart.update();

        // Update Popular Routes Chart
        popularRoutesChart.data.labels = data.popular_routes.labels;
        popularRoutesChart.data.datasets[0].data = data.popular_routes.values;
        popularRoutesChart.update();

        // Update Booking Status Chart
        bookingStatusChart.data.labels = data.booking_status.labels;
        bookingStatusChart.data.datasets[0].data = data.booking_status.values;
        bookingStatusChart.update();
    }

    /**
     * Display "No data available" message in charts
     */
    function displayNoDataMessage() {
        // Hide loading indicators
        hideLoadingIndicators();

        // Add "No data" message to each chart container
        $('.mptbm-chart-wrapper').each(function() {
            if (!$(this).find('.mptbm-no-data-message').length) {
                $(this).append('<div class="mptbm-no-data-message">No booking data available for the selected date range.</div>');
            }
        });
    }

    /**
     * Update recent bookings table
     */
    function updateRecentBookings(bookings) {
        
        if (!bookings || bookings.length === 0) {
            $('#mptbm-recent-bookings-table').html('<tr><td colspan="7" style="text-align: center;">No bookings found in the selected date range.</td></tr>');
            return;
        }
        if(bookings && bookings == 'pro_not_active'){
            $('#mptbm-recent-bookings-table').html('<tr><td colspan="7" style="text-align: center;">Please activate the pro version to view the bookings.</td></tr>');
            return;
        }
        
        
        
        let html = '';
        bookings.forEach(function(booking) {
            html += '<tr>';
            html += '<td>#' + booking.order_id + '</td>';
            html += '<td>' + booking.customer + '</td>';
            html += '<td>' + booking.route + '</td>';
            html += '<td>' + booking.date + '</td>';
            html += '<td>' + booking.amount + '</td>';
            html += '<td><span class="mptbm-status-badge mptbm-status-' + booking.status + '">' + booking.status + '</span></td>';
            html += '<td><a href="' + booking.view_url + '" class="mptbm-action-btn mptbm-action-view">View</a></td>';
            html += '</tr>';
        });
        
        $('#mptbm-recent-bookings-table').html(html);
    }

    // Initialize on document ready
    $(document).ready(function() {
        initDashboard();
    });

})(jQuery);