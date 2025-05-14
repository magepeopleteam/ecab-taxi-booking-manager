/**
 * E-Cab AI Chatbot Analytics Dashboard JavaScript
 */
jQuery(document).ready(function($) {
    // Charts objects
    let timeSeriesChart = null;
    let intentChart = null;
    let sentimentChart = null;
    
    // Initialize the dashboard
    initDashboard();
    
    /**
     * Initialize dashboard
     */
    function initDashboard() {
        // Set up form submission
        $('#mptbm-analytics-form').on('submit', function(e) {
            e.preventDefault();
            loadAnalyticsData();
        });
        
        // Set up quick range buttons
        $('.quick-range').on('click', function() {
            const days = $(this).data('days');
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(startDate.getDate() - days);
            
            $('#end_date').val(formatDate(endDate));
            $('#start_date').val(formatDate(startDate));
            
            loadAnalyticsData();
        });
        
        // Initial data load
        loadAnalyticsData();
    }
    
    /**
     * Load analytics data from server
     */
    function loadAnalyticsData() {
        const startDate = $('#start_date').val();
        const endDate = $('#end_date').val();
        
        $('.mptbm-loading-overlay').show();
        
        $.ajax({
            url: mptbm_analytics_data.ajaxurl,
            type: 'POST',
            data: {
                action: 'mptbm_chatbot_analytics',
                nonce: mptbm_analytics_data.nonce,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                $('.mptbm-loading-overlay').hide();
                
                if (response && response.success && response.data) {
                    if (isEmpty(response.data)) {
                        showNoDataMessage();
                    } else {
                        updateDashboard(response.data);
                    }
                } else {
                    showError(response.data && response.data.message ? response.data.message : 'Error loading analytics data');
                }
            },
            error: function(xhr, status, error) {
                $('.mptbm-loading-overlay').hide();
                showError('Error connecting to server: ' + (error || status));
                console.error('AJAX Error:', status, error);
            }
        });
    }
    
    /**
     * Check if data object is empty or has no meaningful values
     */
    function isEmpty(data) {
        if (!data) return true;
        
        // Check if total messages is 0
        if (data.total_messages === 0) return true;
        
        // Check if time series data is empty or only has zeros
        if (!data.time_series_data || data.time_series_data.length === 0) return true;
        
        const hasValues = data.time_series_data.some(item => item.count > 0);
        if (!hasValues) return true;
        
        return false;
    }
    
    /**
     * Show message when no data is available
     */
    function showNoDataMessage() {
        // Clear existing charts
        if (timeSeriesChart) timeSeriesChart.destroy();
        if (intentChart) intentChart.destroy();
        if (sentimentChart) sentimentChart.destroy();
        
        // Reset summary cards
        $('#total-conversations').text('0');
        $('#total-messages').text('0');
        $('#unique-users').text('0');
        $('#booking-rate').text('0%');
        
        // Reset response metrics
        $('#avg-message-length').text('0 characters');
        $('#avg-response-length').text('0 characters');
        $('#avg-response-time').text('0 s');
        
        // Show no data message on charts
        const noDataMsg = mptbm_analytics_data.labels.no_data || 'No data available for the selected period.';
        
        // Add a message to each chart area
        $('.chart-container').each(function() {
            $(this).html('<div class="no-data-message"><p>' + noDataMsg + '</p></div>');
        });
        
        // Clear common phrases
        $('#common-phrases').html('<p class="no-data-message">' + noDataMsg + '</p>');
        
        // Clear training opportunities
        $('#training-list').html('<p class="no-data-message">' + noDataMsg + '</p>');
    }
    
    /**
     * Update dashboard with data
     */
    function updateDashboard(data) {
        // Use safe data with defaults for missing values
        const safeData = {
            total_conversations: data.total_conversations || 0,
            total_messages: data.total_messages || 0,
            unique_users: data.unique_users || 0,
            booking_reference_rate: data.booking_reference_rate || 0,
            avg_message_length: data.avg_message_length || 0,
            avg_response_length: data.avg_response_length || 0,
            avg_response_time: data.avg_response_time || 0,
            time_series_data: data.time_series_data || [{date: formatDate(new Date()), count: 0}],
            intent_data: data.intent_data || [{intent: 'no data', count: 0}],
            sentiment_data: data.sentiment_data || [{sentiment: 'no data', count: 0}],
            common_phrases: data.common_phrases || {
                words: [{text: 'No data available', count: 0}],
                phrases: [{text: 'No data available', count: 0}]
            }
        };
        
        // Update summary cards
        $('#total-conversations').text(safeData.total_conversations);
        $('#total-messages').text(safeData.total_messages);
        $('#unique-users').text(safeData.unique_users);
        $('#booking-rate').text(safeData.booking_reference_rate + '%');
        
        // Update response metrics
        $('#avg-message-length').text(safeData.avg_message_length + ' characters');
        $('#avg-response-length').text(safeData.avg_response_length + ' characters');
        $('#avg-response-time').text(safeData.avg_response_time + ' s');
        
        // Update charts
        updateTimeSeriesChart(safeData.time_series_data);
        updateIntentChart(safeData.intent_data);
        updateSentimentChart(safeData.sentiment_data);
        
        // Update common phrases
        updateCommonPhrases(safeData.common_phrases);
        
        // Update training opportunities
        updateTrainingOpportunities(safeData);
    }
    
    /**
     * Update time series chart
     */
    function updateTimeSeriesChart(data) {
        const ctx = document.getElementById('time-series-chart').getContext('2d');
        
        // Prepare data
        const labels = data.map(item => item.date);
        const values = data.map(item => item.count);
        
        // Destroy previous chart if exists
        if (timeSeriesChart) {
            timeSeriesChart.destroy();
        }
        
        // Create new chart
        timeSeriesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Messages',
                    data: values,
                    borderColor: '#2271b1',
                    backgroundColor: 'rgba(34, 113, 177, 0.1)',
                    tension: 0.3,
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
                        callbacks: {
                            title: function(tooltipItems) {
                                // Format the date for display
                                if (!tooltipItems || !tooltipItems[0]) return '';
                                const date = new Date(tooltipItems[0].label);
                                return date.toLocaleDateString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update intent chart
     */
    function updateIntentChart(data) {
        const ctx = document.getElementById('intent-chart').getContext('2d');
        
        // Prepare data
        const labels = data.map(item => capitalizeFirst(item.intent || 'unknown'));
        const values = data.map(item => item.count || 0);
        
        // Colors for different intents
        const colors = [
            '#2271b1', // Blue
            '#1abc9c', // Teal
            '#3498db', // Light Blue
            '#9b59b6', // Purple
            '#f1c40f', // Yellow
            '#e67e22', // Orange
            '#e74c3c', // Red
            '#34495e'  // Dark Blue
        ];
        
        // Get colors array matching the number of labels
        const backgroundColors = [];
        for (let i = 0; i < labels.length; i++) {
            backgroundColors.push(colors[i % colors.length]);
        }
        
        // Destroy previous chart if exists
        if (intentChart) {
            intentChart.destroy();
        }
        
        // Create new chart
        intentChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update sentiment chart
     */
    function updateSentimentChart(data) {
        const ctx = document.getElementById('sentiment-chart').getContext('2d');
        
        // Prepare data
        const labels = data.map(item => capitalizeFirst(item.sentiment || 'unknown'));
        const values = data.map(item => item.count || 0);
        
        // Colors for sentiment
        const sentimentColors = {
            'Positive': '#2ecc71', // Green
            'Neutral': '#3498db',  // Blue
            'Negative': '#e74c3c'  // Red
        };
        
        // Map colors to labels
        const backgroundColors = labels.map(label => sentimentColors[label] || '#95a5a6');
        
        // Destroy previous chart if exists
        if (sentimentChart) {
            sentimentChart.destroy();
        }
        
        // Create new chart
        sentimentChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: backgroundColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Update common phrases section
     */
    function updateCommonPhrases(data) {
        const container = $('#common-phrases');
        container.empty();
        
        if (!data || (!data.words || !data.words.length) && (!data.phrases || !data.phrases.length)) {
            container.html('<p class="no-data">No data available for the selected period.</p>');
            return;
        }
        
        // Add phrases
        if (data.phrases && data.phrases.length) {
            const phrasesList = $('<ul class="common-phrases-list"></ul>');
            
            data.phrases.forEach(function(phrase) {
                if (phrase && phrase.text) {
                    phrasesList.append(
                        '<li><span class="phrase-text">' + escapeHtml(phrase.text) + '</span> ' +
                        '<span class="phrase-count">(' + (phrase.count || 0) + ')</span></li>'
                    );
                }
            });
            
            container.append(phrasesList);
        } else {
            container.html('<p class="no-data">No common phrases detected yet.</p>');
        }
    }
    
    /**
     * Update training opportunities section
     */
    function updateTrainingOpportunities(data) {
        const container = $('#training-opportunities');
        const list = $('#training-list');
        list.empty();
        
        // Look for potential training opportunities based on intent frequency
        let trainingNeeded = false;
        
        if (data.intent_data && data.intent_data.length) {
            // Find the most common intents
            const sortedIntents = [...data.intent_data].sort((a, b) => (b.count || 0) - (a.count || 0));
            
            // If there are common intents, suggest training for them
            if (sortedIntents.length > 0 && sortedIntents[0].count > 0) {
                const topIntents = sortedIntents.slice(0, Math.min(3, sortedIntents.length));
                
                list.append('<p><strong>Top Intents to Improve:</strong></p>');
                
                const intentList = $('<ul></ul>');
                topIntents.forEach(function(intent) {
                    if (intent && intent.intent) {
                        intentList.append(
                            '<li>' + capitalizeFirst(intent.intent) + ' (' + (intent.count || 0) + ' queries)</li>'
                        );
                    }
                });
                
                list.append(intentList);
                trainingNeeded = true;
            }
        }
        
        // Add training suggestion based on sentiment
        if (data.sentiment_data && data.sentiment_data.length) {
            const negativeData = data.sentiment_data.find(item => item.sentiment === 'negative');
            
            if (negativeData && negativeData.count > 0) {
                list.append('<p><strong>Sentiment Training Needed:</strong></p>');
                list.append('<p>Users have expressed negative sentiment in their conversations. Consider reviewing these interactions to improve responses.</p>');
                trainingNeeded = true;
            }
        }
        
        if (!trainingNeeded) {
            list.append('<p>No specific training opportunities identified for the current period.</p>');
        }
    }
    
    /**
     * Helper: Format date for input fields
     */
    function formatDate(date) {
        const d = new Date(date);
        let month = '' + (d.getMonth() + 1);
        let day = '' + d.getDate();
        const year = d.getFullYear();
        
        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;
        
        return [year, month, day].join('-');
    }
    
    /**
     * Helper: Capitalize first letter
     */
    function capitalizeFirst(string) {
        if (!string) return 'Unknown';
        return string.charAt(0).toUpperCase() + string.slice(1);
    }
    
    /**
     * Helper: Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
    
    /**
     * Show error message
     */
    function showError(message) {
        const errorHtml = '<div class="notice notice-error"><p>' + escapeHtml(message) + '</p></div>';
        $('.mptbm-analytics-content').prepend(errorHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $('.notice-error').fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
}); 