<?php
/*
Plugin Name: AI AutoPost by Kacper
Description: This plugin is designed for the automatic posting of AI-generated content. It operates in a straightforward and user-friendly manner: after selecting the number of posts, text fields will appear where you can input questions to ChatGPT about the content you wish to generate. The generated content will then be displayed in the fields, allowing you to assign a topic to each post individually. Additionally, the plugin lets you set the publication date of the first post and the interval at which subsequent posts should be published.
Version: 1.5
Author: Kacper Kulig
License: All rights reserved by the author Kacper Kulig (GitHub: Kacper20001)*/

if (!defined('ABSPATH')) {
    exit;
}

class ChatGPT_Scheduled_Post_Generator {
    private $api_key = '';

    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_page'));
        add_filter('cron_schedules', array($this, 'custom_cron_schedule'));
        add_action('scheduled_posts_cron', array($this, 'check_scheduled_posts'));
    }

    public function create_admin_page() {
        add_menu_page(
            'AI AutoPost by Kacper',
            'AI AutoPost',
            'manage_options',
            'ai-autopost-by-kacper',
            array($this, 'admin_page_content'),
            'dashicons-admin-post',
            20
        );
    }

    public function admin_page_content() {
        ?>
        <div class="wrap">
            <h1>AI AutoPost Generator by Kacper</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enter number of posts</th>
                        <td><input type="number" name="num_posts" value="1" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Proceed'); ?>
            </form>
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['num_posts']) && empty($_POST['chatgpt_questions'])) {
                $num_posts = intval($_POST['num_posts']);
                ?>
                <form method="post" action="">
                    <input type="hidden" name="num_posts" value="<?php echo $num_posts; ?>" />
                    <h2>Enter your questions</h2>
                    <?php for ($i = 0; $i < $num_posts; $i++) { ?>
                        <h3>Post <?php echo ($i + 1); ?></h3>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Enter your question</th>
                                <td><input type="text" name="chatgpt_questions[]" value="" class="regular-text" /></td>
                            </tr>
                        </table>
                    <?php } ?>
                    <?php submit_button('Generate Posts'); ?>
                </form>
                <?php
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['chatgpt_questions'])) {
                $questions = array_map('sanitize_text_field', $_POST['chatgpt_questions']);
                $responses = array();
                foreach ($questions as $question) {
                    $response = $this->chatgpt_generate_content($question);
                    if ($response) {
                        $responses[] = $response;
                    } else {
                        echo '<h2>Error</h2>';
                        echo '<p>Unable to get response from ChatGPT API for one of the posts.</p>';
                    }
                }
                if (!empty($responses)) {
                    ?>
                    <form method="post" action="">
                        <h2>Edit Generated Posts and Set Publication Intervals</h2>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Choose Interval</th>
                                <td>
                                    <select name="post_interval">
                                        <option value="5 minutes">5 Minutes</option>
                                        <option value="1 hour">1 Hour</option>
                                        <option value="1 day">1 Day</option>
                                        <option value="2 days">2 Days</option>
                                        <option value="4 days">4 Days</option>
                                        <option value="1 week">1 Week</option>
                                        <option value="1 month">1 Month</option>
                                    </select>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Set Start Date and Time</th>
                                <td><input type="datetime-local" name="start_date" required></td>
                            </tr>
                        </table>
                        <?php foreach ($responses as $index => $content) { ?>
                            <h3>Post <?php echo ($index + 1); ?></h3>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">Enter post title</th>
                                    <td><input type="text" name="post_titles[]" value="" class="regular-text" /></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">Post Content</th>
                                    <td><textarea name="post_contents[]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($content); ?></textarea></td>
                                </tr>
                            </table>
                        <?php } ?>
                        <?php submit_button('Submit Posts'); ?>
                    </form>
                    <?php
                }
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['post_titles']) && !empty($_POST['post_contents'])) {
                $post_titles = array_map('sanitize_text_field', $_POST['post_titles']);
                $post_contents = array_map('sanitize_textarea_field', $_POST['post_contents']);
                $interval = $_POST['post_interval'];
                $start_time = $_POST['start_date'];

                foreach ($post_titles as $index => $title) {
                    $content = $post_contents[$index];
                    $date = $this->calculate_future_date($start_time, $interval, $index);
                    $this->schedule_post($title, $content, $date);
                    echo '<p>Post ' . ($index + 1) . ' created successfully and scheduled for ' . $date . '.</p>';
                }
            }
            ?>
        </div>
        <?php
    }

    private function chatgpt_generate_content($question) {
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->api_key,
        );

        $data = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'user', 'content' => $question),
            ),
            'max_tokens' => 15,
        );

        $options = array(
            'http' => array(
                'header'  => "Content-type: application/json\r\n" .
                    "Authorization: Bearer " . $this->api_key . "\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
            ),
        );

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === FALSE) {
            return 'Error: Unable to get response from ChatGPT API';
        }

        $response = json_decode($result, true);
        return $response['choices'][0]['message']['content'];
    }

    private function calculate_future_date($start_time, $interval, $index) {
        $date = new DateTime($start_time);
        switch ($interval) {
            case '5 minutes':
                $date->modify('+' . (5 * $index) . ' minutes');
                break;
            case '1 hour':
                $date->modify('+' . $index . ' hour');
                break;
            case '1 day':
                $date->modify('+' . $index . ' day');
                break;
            case '2 days':
                $date->modify('+' . (2 * $index) . ' days');
                break;
            case '4 days':
                $date->modify('+' . (4 * $index) . ' days');
                break;
            case '1 week':
                $date->modify('+' . $index . ' week');
                break;
            case '1 month':
                $date->modify('+' . $index . ' month');
                break;
        }
        return $date->format('Y-m-d H:i:s');
    }

    private function schedule_post($title, $content, $date) {
        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $content,
            'post_status'   => 'future',
            'post_author'   => get_current_user_id(),
            'post_date'     => $date,
        );

        wp_insert_post($post_data);
    }

    public function custom_cron_schedule($schedules) {
        if (!isset($schedules['every_minute'])) {
            $schedules['every_minute'] = array(
                'interval' => 60,
                'display' => __('Every Minute')
            );
        }
        return $schedules;
    }

    public function check_scheduled_posts() {
        $args = array(
            'post_status' => 'future',
            'posts_per_page' => -1
        );

        $future_posts = get_posts($args);

        foreach ($future_posts as $post) {
            $post_date = strtotime($post->post_date);
            if ($post_date <= current_time('timestamp')) {
                wp_publish_post($post->ID);
            }
        }
    }
}

if (!wp_next_scheduled('scheduled_posts_cron')) {
    wp_schedule_event(time(), 'every_minute', 'scheduled_posts_cron');
}

new ChatGPT_Scheduled_Post_Generator();
?>
