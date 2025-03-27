jQuery(document).ready(function($) {
    if (typeof wc_reviews_remover_params !== 'undefined') {
        var params = wc_reviews_remover_params;
        var processed = 0;
        var offset = 0;
        var total = parseInt(params.total);
        
        function processBatch() {
            $.ajax({
                url: params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wc_remove_reviews',
                    nonce: params.nonce,
                    offset: offset,
                    processed: processed,
                    total: total
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        processed = data.processed;
                        offset = data.offset || offset + params.batch_size;
                        
                        // Update UI
                        $('.progress').css('width', data.progress + '%');
                        $('.processed').text(processed);
                        $('.status').text(data.message);
                        
                        if (data.complete) {
                            $('.status').html('<strong>' + data.message + '</strong>');
                        } else {
                            // Process next batch
                            setTimeout(processBatch, 300); // Small delay to prevent server overload
                        }
                    } else {
                        $('.status').html('<strong>Error: ' + response.data + '</strong>');
                    }
                },
                error: function(xhr, status, error) {
                    $('.status').html('<strong>Error: ' + error + '</strong>');
                }
            });
        }
        
        // Start the process
        processBatch();
    }
});