jQuery(document).ready(function($) {
    var offset = 0;
    var processed = 0;
    var total = wc_reviews_remover_params.total;
    var batchSize = wc_reviews_remover_params.batch_size;
    
    function updateProgress(progress, message) {
        $('.progress').css('width', progress + '%');
        $('.processed').text(processed);
        $('.status').text(message);
    }
    
    function removeReviews() {
        $.ajax({
            url: wc_reviews_remover_params.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_remove_reviews',
                nonce: wc_reviews_remover_params.nonce,
                offset: offset,
                processed: processed,
                total: total
            },
            success: function(response) {
                if (response.success) {
                    processed = response.data.processed;
                    updateProgress(response.data.progress, response.data.message);
                    
                    if (!response.data.complete) {
                        offset = response.data.offset;
                        removeReviews(); // Continue with next batch
                    } else {
                        $('.status').text(response.data.message);
                    }
                } else {
                    $('.status').text('Error: ' + response.data);
                }
            },
            error: function() {
                $('.status').text('Error occurred while processing the request.');
            }
        });
    }
    
    // Start the removal process
    removeReviews();
});