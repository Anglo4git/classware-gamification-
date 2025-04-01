jQuery(document).ready(function($) {
    const sounds = {
        buzzer: new Audio(buzzerData.sounds.buzzer),
        correct: new Audio(buzzerData.sounds.correct),
        incorrect: new Audio(buzzerData.sounds.incorrect)
    };
    
    let currentState = {
        active: true,
        currentQuestion: {},
        score: 0
    };
    
    function updateScore() {
        $.ajax({
            url: buzzerData.ajaxurl,
            data: {
                action: 'get_score',
                user_id: buzzerData.user_id,
                nonce: buzzerData.nonce
            },
            success: function(response) {
                $('#score-display').text(`Score: ${response.score}/${response.total}`);
            }
        });
    }
    
    $('.buzzer.answer').click(function() {
        if (!currentState.active) return;
        
        sounds.buzzer.play();
        const selectedAnswer = $(this).data('answer');
        
        $.ajax({
            url: buzzerData.ajaxurl,
            method: 'POST',
            data: {
                action: 'handle_answer',
                answer: selectedAnswer,
                question_id: currentState.currentQuestion.id,
                nonce: buzzerData.nonce
            },
            success: function(response) {
                currentState.active = false;
                const feedback = $('#result-feedback');
                
                if(response.correct) {
                    feedback.html(`
                        <div class="correct">✓ Correct!<br>
                        ${response.explanation || ''}
                        </div>
                    `).addClass('correct').removeClass('incorrect');
                    sounds.correct.play();
                } else {
                    feedback.html(`
                        <div class="incorrect">✗ Incorrect!<br>
                        ${response.explanation || ''}
                        </div>
                    `).addClass('incorrect').removeClass('correct');
                    sounds.incorrect.play();
                }
                
                updateScore();
                setTimeout(() => currentState.active = true, 3000);
            }
        });
    });
    
    // Poll for question updates
    setInterval(function() {
        $.ajax({
            url: buzzerData.ajaxurl,
            data: {
                action: 'get_current_question',
                nonce: buzzerData.nonce
            },
            success: function(response) {
                if(response.id !== currentState.currentQuestion.id) {
                    currentState.currentQuestion = response;
                    $('#question-display').text(response.question);
                    $('.buzzer.answer').eq(0).text(response.answer1);
                    $('.buzzer.answer').eq(1).text(response.answer2);
                    $('#result-feedback').removeClass('correct incorrect').empty();
                    currentState.active = true;
                }
            }
        });
    }, 2000);
    
    // Initial score load
    updateScore();
});