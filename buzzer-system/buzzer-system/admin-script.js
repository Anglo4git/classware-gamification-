jQuery(document).ready(function($) {
    let currentQuestionId = 0;
    
    function updateAdminStatus(message) {
        $('#current-status').html(`<p>Current Question ID: ${currentQuestionId}</p>`);
    }
    
    $('#next-question, #prev-question').click(function() {
        const direction = $(this).attr('id').split('-')[0];
        
        $.ajax({
            url: buzzerData.ajaxurl,
            method: 'POST',
            data: {
                action: 'update_question',
                direction: direction,
                current_id: currentQuestionId,
                nonce: buzzerData.nonce
            },
            success: function(response) {
                if(response.data) {
                    currentQuestionId = response.data.id;
                    updateAdminStatus();
                }
            }
        });
    });
    
    $('#reset-buzzers').click(function() {
        $.ajax({
            url: buzzerData.ajaxurl,
            method: 'POST',
            data: {
                action: 'reset_buzzers',
                nonce: buzzerData.nonce
            }
        });
    });
    
    updateAdminStatus();
});