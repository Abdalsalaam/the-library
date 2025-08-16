/**
 * Admin JavaScript for The Library
 */

(function($) {
    'use strict';
    
    var WPRL_Admin = {
        
        init: function() {
            this.initFileUpload();
            this.initDownloadRequestsPage();
            this.bindEvents();
        },
        
        bindEvents: function() {
            // File upload button
            $(document).on('click', '#wprl_upload_file_button', function(e) {
                e.preventDefault();
                WPRL_Admin.openMediaUploader();
            });
            
            // Remove file button
            $(document).on('click', '#wprl_remove_file_button', function(e) {
                e.preventDefault();
                WPRL_Admin.removeFile();
            });
            
            // Select all checkbox
            $(document).on('change', '#cb-select-all-1', function() {
                $('input[name="request_ids[]"]').prop('checked', this.checked);
            });
            
            // Individual delete buttons
            $(document).on('click', '.wprl-delete-request', function(e) {
                e.preventDefault();
                WPRL_Admin.deleteRequest($(this));
            });
            
            // Bulk actions form submission
            $(document).on('submit', '.wprl-bulk-actions-form', function(e) {
                var action = $(this).find('select[name="action"]').val();
                var checkedItems = $('input[name="request_ids[]"]:checked');
                
                if (action === 'bulk_delete' && checkedItems.length > 0) {
                    if (!confirm('Are you sure you want to delete the selected requests?')) {
                        e.preventDefault();
                    }
                }
            });
        },
        
        initFileUpload: function() {
            // Initialize media uploader
            if (typeof wp !== 'undefined' && wp.media) {
                WPRL_Admin.mediaUploader = wp.media({
                    title: 'Select File',
                    button: {
                        text: 'Use this file'
                    },
                    multiple: false
                });
                
                WPRL_Admin.mediaUploader.on('select', function() {
                    var attachment = WPRL_Admin.mediaUploader.state().get('selection').first().toJSON();
                    WPRL_Admin.setSelectedFile(attachment);
                });
            }
        },
        
        openMediaUploader: function() {
            if (WPRL_Admin.mediaUploader) {
                WPRL_Admin.mediaUploader.open();
            }
        },
        
        setSelectedFile: function(attachment) {
            $('#wprl_file_id').val(attachment.id);
            $('#wprl_file_url').val(attachment.url);
            $('#wprl_remove_file_button').show();
            
            // Update preview if exists
            WPRL_Admin.updateFilePreview(attachment);
        },
        
        updateFilePreview: function(attachment) {
            var $preview = $('.wprl-file-upload-preview');
            
            if ($preview.length === 0) {
                // Create preview element
                $preview = $('<div class="wprl-file-upload-preview"></div>');
                $('#wprl_file_url').after($preview);
            }
            
            var fileExtension = attachment.filename.split('.').pop().toUpperCase();
            var fileSize = attachment.filesizeHumanReadable || '';
            
            var previewHtml = '<div class="wprl-file-icon">' + fileExtension + '</div>' +
                             '<div class="wprl-file-details">' +
                             '<h4>' + attachment.filename + '</h4>' +
                             '<p>Size: ' + fileSize + '</p>' +
                             '<p>Type: ' + attachment.mime + '</p>' +
                             '</div>' +
                             '<button type="button" class="wprl-remove-file">Remove</button>';
            
            $preview.html(previewHtml).removeClass('hidden');
        },
        
        removeFile: function() {
            if (confirm('Are you sure you want to remove this file?')) {
                $('#wprl_file_id').val('');
                $('#wprl_file_url').val('');
                $('#wprl_remove_file_button').hide();
                $('.wprl-file-upload-preview').addClass('hidden');
            }
        },
        
        initDownloadRequestsPage: function() {
            // Initialize DataTables if available
            if ($.fn.DataTable && $('.wprl-requests-table').length) {
                $('.wprl-requests-table').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[5, 'desc']], // Sort by date column
                    columnDefs: [
                        { orderable: false, targets: [0, 7] } // Disable sorting for checkbox and actions columns
                    ]
                });
            }
            
            // Initialize date pickers if available
            if ($.fn.datepicker) {
                $('.wprl-date-picker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    changeMonth: true,
                    changeYear: true
                });
            }
        },
        
        deleteRequest: function($button) {
            if (!confirm('Are you sure you want to delete this request?')) {
                return;
            }
            
            var requestId = $button.data('id');
            var $row = $button.closest('tr');
            
            // Show loading state
            $button.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wprl_delete_download_request',
                    request_id: requestId,
                    nonce: (window.wprl_admin_nonces && window.wprl_admin_nonces.wprl_delete_request) || ''
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                            WPRL_Admin.updateRowCount();
                        });
                        WPRL_Admin.showNotice('Request deleted successfully.', 'success');
                    } else {
                        WPRL_Admin.showNotice('Error deleting request.', 'error');
                        $button.prop('disabled', false).text('Delete');
                    }
                },
                error: function() {
                    WPRL_Admin.showNotice('Error deleting request.', 'error');
                    $button.prop('disabled', false).text('Delete');
                }
            });
        },
        
        updateRowCount: function() {
            var $table = $('.wprl-requests-table tbody');
            var rowCount = $table.find('tr').length;
            
            if (rowCount === 0) {
                $table.html('<tr><td colspan="8" class="wprl-no-items">No download requests found.</td></tr>');
            }
            
            // Update pagination info if exists
            var $paginationInfo = $('.wprl-pagination-info');
            if ($paginationInfo.length) {
                // Update the count display
                // This would need to be implemented based on your pagination structure
            }
        },
        
        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
        },
        
        getNonce: function(action) {
            // This would typically be localized from PHP
            // For now, we'll assume nonces are available globally
            if (typeof wprl_admin_nonces !== 'undefined' && wprl_admin_nonces[action]) {
                return wprl_admin_nonces[action];
            }
            return '';
        },
        
        // Export functionality
        exportRequests: function(format, filters) {
            var exportUrl = ajaxurl + '?action=wprl_export_requests&format=' + format;
            
            if (filters) {
                for (var key in filters) {
                    if (filters[key]) {
                        exportUrl += '&' + key + '=' + encodeURIComponent(filters[key]);
                    }
                }
            }
            
            // Add nonce
            exportUrl += '&nonce=' + WPRL_Admin.getNonce('wprl_export_requests');
            
            // Trigger download
            window.location.href = exportUrl;
        },
        
        // Statistics functionality
        loadStatistics: function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wprl_get_statistics',
                    nonce: WPRL_Admin.getNonce('wprl_get_statistics')
                },
                success: function(response) {
                    if (response.success) {
                        WPRL_Admin.updateStatisticsDisplay(response.data);
                    }
                },
                error: function() {
                    console.log('Error loading statistics');
                }
            });
        },
        
        updateStatisticsDisplay: function(stats) {
            // Update statistics cards
            $('.wprl-stat-total-downloads').text(stats.total_downloads || 0);
            $('.wprl-stat-downloads-today').text(stats.downloads_today || 0);
            $('.wprl-stat-downloads-week').text(stats.downloads_this_week || 0);
            $('.wprl-stat-downloads-month').text(stats.downloads_this_month || 0);
            
            // Update charts if available
            if (typeof Chart !== 'undefined' && stats.downloads_by_date) {
                WPRL_Admin.updateDownloadsChart(stats.downloads_by_date);
            }
        },
        
        updateDownloadsChart: function(data) {
            var ctx = document.getElementById('wprl-downloads-chart');
            if (!ctx) return;
            
            var labels = data.map(function(item) { return item.date; });
            var values = data.map(function(item) { return item.count; });
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Downloads',
                        data: values,
                        borderColor: '#007cba',
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        
        // Utility functions
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
        
        formatDate: function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        WPRL_Admin.init();
        
        // Load statistics on dashboard
        if ($('.wprl-stats-overview').length) {
            WPRL_Admin.loadStatistics();
        }
    });
    
    // Make WPRL_Admin globally accessible
    window.WPRL_Admin = WPRL_Admin;
    
})(jQuery);
