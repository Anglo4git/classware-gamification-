<?php
/*
Plugin Name: Buzzer Quiz System
Description: Interactive quiz system with buzzer functionality
Version: 2.0
Author: Your Name
*/

global $buzzer_db_version;
$buzzer_db_version = '2.0';

// Database setup
function buzzer_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Questions table
    $questions_table = $wpdb->prefix . 'buzzer_questions';
    $sql1 = "CREATE TABLE $questions_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question text NOT NULL,
        answer1 varchar(255) NOT NULL,
        answer2 varchar(255) NOT NULL,
        correct_answer tinyint(1) NOT NULL,
        category varchar(100),
        tags varchar(255),
        explanation text,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    // Scores table
    $scores_table = $wpdb->prefix . 'buzzer_scores';
    $sql2 = "CREATE TABLE $scores_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        question_id mediumint(9) NOT NULL,
        correct tinyint(1) NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    
    add_option('buzzer_db_version', $buzzer_db_version);
}
register_activation_hook(__FILE__, 'buzzer_install');

// Admin interface
add_action('admin_menu', 'buzzer_admin_menu');
function buzzer_admin_menu() {
    add_menu_page(
        'Quiz System',
        'Quiz System',
        'manage_options',
        'buzzer-system',
        'buzzer_admin_page',
        'dashicons-games'
    );
}

function buzzer_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    ?>
    <div class="wrap">
        <h1>Quiz Control Panel</h1>
        <div class="admin-controls">
            <button id="prev-question" class="button-primary">← Previous</button>
            <button id="next-question" class="button-primary">Next →</button>
            <button id="reset-buzzers" class="button-secondary">Reset</button>
            
            <div class="csv-import">
                <h3>Import Questions</h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('buzzer_csv_import', 'buzzer_nonce'); ?>
                    <input type="file" name="buzzer_csv" accept=".csv">
                    <button type="submit" name="import_csv" class="button-primary">Import CSV</button>
                </form>
            </div>
        </div>
        <div id="current-status"></div>
    </div>
    <?php
}

// Handle CSV import
add_action('admin_init', 'handle_csv_import');
function handle_csv_import() {
    if (!isset($_POST['import_csv']) || !wp_verify_nonce($_POST['buzzer_nonce'], 'buzzer_csv_import')) {
        return;
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    
    $uploadedfile = $_FILES['buzzer_csv'];
    $upload_overrides = array('test_form' => false);
    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
    
    if ($movefile && !isset($movefile['error'])) {
        $csv = array_map('str_getcsv', file($movefile['file']));
        array_shift($csv); // Remove header
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'buzzer_questions';
        
        foreach ($csv as $row) {
            $wpdb->insert($table_name, array(
                'question' => sanitize_text_field($row[0]),
                'category' => sanitize_text_field($row[1]),
                'tags' => sanitize_text_field($row[2]),
                'explanation' => sanitize_text_field($row[3]),
                'correct_answer' => intval($row[4]),
                'answer1' => sanitize_text_field($row[5]),
                'answer2' => sanitize_text_field($row[6])
            ), array('%s', '%s', '%s', '%s', '%d', '%s', '%s'));
        }
        wp_redirect(admin_url('admin.php?page=buzzer-system&import=success'));
        exit;
    }
}

// Student interface
add_shortcode('quiz_system', 'buzzer_student_interface');
function buzzer_student_interface() {
    if (!is_user_logged_in()) {
        return wp_login_form(array('echo' => false));
    }
    
    global $wpdb;
    $current_question = $wpdb->get_row(
        "SELECT * FROM {$wpdb->prefix}buzzer_questions ORDER BY id LIMIT 1"
    );
    
    ob_start(); ?>
    <div class="buzzer-container">
        <div id="question-display"><?= esc_html($current_question->question) ?></div>
        <div class="buzzer-buttons">
            <button class="buzzer answer button-primary" data-answer="1">
                <?= esc_html($current_question->answer1) ?>
            </button>
            <button class="buzzer answer button-primary" data-answer="2">
                <?= esc_html($current_question->answer2) ?>
            </button>
        </div>
        <div id="result-feedback"></div>
        <div id="score-display"></div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX handlers
add_action('wp_ajax_update_question', 'handle_question_update');
add_action('wp_ajax_handle_answer', 'handle_answer_submission');
add_action('wp_ajax_get_score', 'handle_score_request');

function handle_question_update() {
    check_ajax_referer('buzzer_nonce', 'nonce');
    
    global $wpdb;
    $direction = sanitize_text_field($_POST['direction']);
    $current_id = intval($_POST['current_id']);
    
    if ($direction === 'next') {
        $question = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}buzzer_questions WHERE id > $current_id ORDER BY id ASC LIMIT 1"
        );
    } else {
        $question = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}buzzer_questions WHERE id < $current_id ORDER BY id DESC LIMIT 1"
        );
    }
    
    wp_send_json_success($question);
}

function handle_answer_submission() {
    check_ajax_referer('buzzer_nonce', 'nonce');
    
    global $wpdb;
    $user_id = get_current_user_id();
    $question_id = intval($_POST['question_id']);
    $answer = intval($_POST['answer']);
    
    $correct = $wpdb->get_var(
        $wpdb->prepare("SELECT correct_answer FROM {$wpdb->prefix}buzzer_questions WHERE id = %d", $question_id)
    );
    
    $result = ($answer == $correct) ? 1 : 0;
    
    $wpdb->insert(
        $wpdb->prefix . 'buzzer_scores',
        array(
            'user_id' => $user_id,
            'question_id' => $question_id,
            'correct' => $result
        ),
        array('%d', '%d', '%d')
    );
    
    wp_send_json(array(
        'correct' => $result,
        'explanation' => $wpdb->get_var(
            $wpdb->prepare("SELECT explanation FROM {$wpdb->prefix}buzzer_questions WHERE id = %d", $question_id)
        )
    ));
}

// Enqueue assets
add_action('admin_enqueue_scripts', 'buzzer_admin_scripts');
add_action('wp_enqueue_scripts', 'buzzer_student_scripts');

function buzzer_admin_scripts($hook) {
    if ($hook != 'toplevel_page_buzzer-system') return;
    
    wp_enqueue_style('buzzer-admin-style', plugins_url('admin-style.css', __FILE__));
    wp_enqueue_script('buzzer-admin-js', plugins_url('admin-script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('buzzer-admin-js', 'buzzerData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('buzzer_nonce')
    ));
}

function buzzer_student_scripts() {
    wp_enqueue_style('buzzer-style', plugins_url('style.css', __FILE__));
    wp_enqueue_script('buzzer-js', plugins_url('script.js', __FILE__), array('jquery'), null, true);
    wp_localize_script('buzzer-js', 'buzzerData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('buzzer_nonce'),
        'sounds' => array(
            'buzzer' => plugins_url('sounds/buzzer.mp3', __FILE__),
            'correct' => plugins_url('sounds/correct.mp3', __FILE__),
            'incorrect' => plugins_url('sounds/incorrect.mp3', __FILE__)
        )
    ));
}