<?php
/*
Plugin Name: AI AutoPost by Kacper
Description: This plugin is designed for the automatic posting of AI-generated content. It operates in a straightforward and user-friendly manner: after selecting the number of posts, text fields will appear where you can input questions to ChatGPT about the content you wish to generate. The generated content will then be displayed in the fields, allowing you to assign a topic to each post individually. Additionally, the plugin lets you set the publication date of the first post and the interval at which subsequent posts should be published.
Version: 2.2
Author: Kacper Kulig
License: All rights reserved by the author Kacper Kulig (GitHub: Kacper20001)
*/



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class ChatGPT_Scheduled_Post_Generator
 *
 * This class handles the generation of scheduled posts using AI and adds an admin interface in WordPress.
 */
class ChatGPT_Scheduled_Post_Generator {
    /**
     * @var string $api_key The API key used for communicating with the AI service.
     */
    //important
   /* Useful links for change set and use API KEY:
    https://platform.openai.com/docs/concepts
    https://openai.com/api/pricing/
    https://platform.openai.com/api-keys
    https://platform.openai.com/settings/organization/billing/overview
    https://platform.openai.com/settings/organization/limits
   */
    private $api_key = 'sk-proj-mHLkY6LrlQ7mEwZHctxIT3BlbkFJxDzsR5p3sq5jDOVQZpvx'; //set API key

    /**
     * Constructor method.
     * Initializes the necessary WordPress actions and filters for the plugin.
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'create_admin_page')); // Adds a menu item to the admin dashboard.
        add_filter('cron_schedules', array($this, 'custom_cron_schedule')); // Adds custom intervals to the cron schedules.
        add_action('scheduled_posts_cron', array($this, 'check_scheduled_posts')); // Sets up a cron job to check scheduled posts.
    }

    /**
     * Removes numbering from the start of the title.
     *
     * @param string $title The title from which the numbering will be removed.
     * @return string The title without the leading numbering.
     */
    private function remove_numbering($title) {
        return preg_replace('/^\d+\.\s*/', '', $title); // Removes leading numbers and periods from the title.
    }

    /**
     * Creates the admin page in the WordPress admin panel.
     */
    public function create_admin_page() {
        add_menu_page(
            'AI AutoPost by Kacper', // The page title in the admin dashboard.
            'AI AutoPost', // The text displayed in the menu.
            'manage_options', // The capability required to access this menu.
            'ai-autopost-by-kacper', // The slug name for the menu.
            array($this, 'admin_page_content'), // The function that displays the page content.
            'dashicons-admin-post', // The menu icon.
            20 // The position in the menu order.
        );
    }

    /**
     * Displays the content of the admin page.
     */
    public function admin_page_content() {
        ?>
<!--        Code responsible for displaying the content of the admin page.-->
        <div class="wrap">
            <h1>AI AutoPost Generator by Kacper</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Enter number of topics</th>
                        <td><input type="number" name="num_topics" value="1" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enter category</th>
                        <td><input type="text" name="category" value="" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enter keywords (comma separated)</th>
                        <td><input type="text" name="keywords" value="" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Enter your own titles (comma separated)</th>
                        <td><input type="text" name="custom_titles" value="" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button('Proceed'); ?>
            </form>
            <?php
            // Processing form data and generating topics/content
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // (Omitted for brevity: Processing and sanitizing POST data)
                // (Omitted for brevity: Generating topics/content, displaying generated content, and managing posts)

                if (!empty($_POST['num_topics']) && !empty($_POST['category'])) { // Checks if 'num_topics' and 'category' are provided.
                    $num_topics = intval($_POST['num_topics']); // Converts 'num_topics' to an integer.
                    $category = sanitize_text_field($_POST['category']); // Sanitizes the 'category' input.
                    $keywords = sanitize_text_field($_POST['keywords']); // Sanitizes the 'keywords' input.
                    $custom_titles = isset($_POST['custom_titles']) ? array_map('sanitize_text_field', explode(',', $_POST['custom_titles'])) : []; // Sanitizes and splits custom titles.
                    $existing_posts = $this->get_existing_post_titles(); // Retrieves existing post titles.
                    $topics = []; // Initializes an empty array for topics.

                    if (!empty($_POST['selected_topics'])) { // Checks if there are selected topics.
                        $selected_topics = json_decode(stripslashes($_POST['selected_topics']), true); // Decodes the JSON string into an array.
                        $topics = array_map('sanitize_text_field', $selected_topics); // Sanitizes each selected topic.
                    }

                    if (!empty($custom_titles)) { // Checks if custom titles are provided.
                        foreach ($custom_titles as $title) { // Iterates over each custom title.
                            if (!empty($title) && !in_array($title, $topics) && !in_array($title, $existing_posts)) { // Checks if the title is unique and not empty.
                                $topics[] = $title; // Adds the unique title to the topics array.
                            }
                        }
                    }
                    if (isset($_POST['regenerate'])) { // Checks if the regenerate button was pressed.
                        $topics = $this->chatgpt_generate_unique_topics($category, $keywords, $num_topics, $existing_posts); // Generates new unique topics.
                        $topics = array_map(array($this, 'remove_numbering'), $topics); // Removes numbering from the generated topics.
                    }

                    $more_topics = !empty($_POST['more_topics']) ? intval($_POST['more_topics']) : 0; // Gets the number of additional topics to generate.
                    $num_topics_to_generate = $num_topics + $more_topics - count($topics); // Calculates the number of topics to generate.
                    if ($num_topics_to_generate > 0) { // Checks if there are more topics to generate.
                        $new_topics = $this->chatgpt_generate_unique_topics($category, $keywords, $num_topics_to_generate, array_merge($existing_posts, $topics)); // Generates additional unique topics.
                        $new_topics = array_map(array($this, 'remove_numbering'), $new_topics); // Removes numbering from the new topics.
                        $topics = array_merge($topics, $new_topics); // Merges new topics with the existing ones.
                    }
                    ?>
                    <!-- Form for generating new posts -->
                    <form method="post" action="">
                        <input type="hidden" name="num_topics" value="<?php echo $num_topics; ?>" />
                        <input type="hidden" name="category" value="<?php echo $category; ?>" />
                        <input type="hidden" name="keywords" value="<?php echo $keywords; ?>" />
                        <input type="hidden" name="selected_topics" value="<?php echo htmlspecialchars(json_encode($topics), ENT_QUOTES, 'UTF-8'); ?>" />
                        <div style="display: flex;">
                            <div style="flex: 1; padding-right: 20px;">
                                <h2>New topics</h2>
                                <?php foreach ($topics as $topic) { ?>
                                    <label>
                                        <input type="checkbox" name="selected_topics[]" value="<?php echo esc_attr($topic); ?>" checked />
                                        <?php echo esc_html($this->sanitize_topic($topic)); ?>
                                    </label><br>
                                <?php } ?>
                            </div>
                            <div style="flex: 1; padding-left: 20px; border-left: 1px solid #ddd;">
                                <h2>Existing topics</h2>
                                <ul>
                                    <?php foreach ($existing_posts as $post) { ?>
                                        <li><?php echo esc_html($post); ?></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                        <?php submit_button('Generate Posts'); ?>
                    </form>
                    <!-- Form for regenerating new posts-->
                    <form method="post" action="">
                        <input type="hidden" name="num_topics" value="<?php echo $num_topics; ?>" />
                        <input type="hidden" name="category" value="<?php echo $category; ?>" />
                        <input type="hidden" name="keywords" value="<?php echo $keywords; ?>" />
                        <input type="hidden" name="selected_topics" value="<?php echo htmlspecialchars(json_encode($topics), ENT_QUOTES, 'UTF-8'); ?>" />
                        <?php submit_button('Regenerate', 'primary', 'regenerate'); ?>
                    </form>
                    <!-- Form for adding new topics -->
                    <form method="post" action="">
                        <input type="hidden" name="num_topics" value="<?php echo $num_topics; ?>" />
                        <input type="hidden" name="category" value="<?php echo $category; ?>" />
                        <input type="hidden" name="keywords" value="<?php echo $keywords; ?>" />
                        <input type="hidden" name="selected_topics" value="<?php echo htmlspecialchars(json_encode($topics), ENT_QUOTES, 'UTF-8'); ?>" />
                        <p><label for="more_topics">Enter number of additional topics: </label>
                            <input type="number" name="more_topics" value="1" class="small-text" /></p>
                        <?php submit_button('Generate More'); ?>
                    </form>
                    <?php
                }

                if (!empty($_POST['selected_topics'])) { // Checks if any topics have been selected.
                    $selected_topics = array_map('sanitize_text_field', $_POST['selected_topics']); // Sanitizes each selected topic.
                    $category = sanitize_text_field($_POST['category']); // Sanitizes the category input.
                    $keywords = sanitize_text_field($_POST['keywords']); // Sanitizes the keywords input.
                    $category_id = $this->get_or_create_category($category); // Gets the ID of the category, or creates it if it doesn't exist.
                    $responses = array(); // Initializes an empty array to store the responses.
                    foreach ($selected_topics as $topic) { // Loops through each selected topic.
                        $response = $this->chatgpt_generate_content_with_keywords($topic, $keywords); // Generates content for the topic using the keywords.
                        $tags = $this->generate_tags($topic); // Generates tags for the topic.
                        if ($response) { // Checks if a response was successfully generated.
                            $response = $this->add_internal_links($response); // Adds internal links to the generated content.
                            $responses[] = array('title' => $this->sanitize_topic($topic), 'content' => $response, 'tags' => $tags); // Stores the sanitized title, content, and tags in the responses array.
                        } else { // If there was an error generating the response.
                            echo '<h2>Error</h2>'; // Displays an error heading.
                            echo '<p>Unable to get response from ChatGPT API for one of the posts.</p>'; // Displays an error message.
                        }
                    }
                    if (!empty($responses)) {
                        ?>
                        <form method="post" action="">
                            <!-- Form for editing generated posts and setting publication intervals -->
                            <h2>Edit Generated Posts and Set Publication Intervals</h2>
                            <table class="form-table">
                                <tr valign="top">
                                    <!-- Dropdown to choose the publication interval -->
                                    <th scope="row">Choose Interval</th>
                                    <td>
                                        <select name="post_interval">
                                            <option value="immediately">Immediately</option>
                                            <option value="1 minute">1 Minute</option>
                                            <option value="2 minutes">2 Minutes</option>
                                            <option value="5 minutes">5 Minutes</option>
                                            <option value="1 hour">1 Hour</option>
                                            <option value="1 day">1 Day</option>
                                            <option value="2 days">2 Days</option>
                                            <option value="4 days">4 Days</option>
                                            <option value="1 week">1 Week</option>
                                            <option value="2 weeks">2 Weeks</option>
                                            <option value="1 month">1 Month</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <!-- Input to set the start date and time for publication -->
                                    <th scope="row">Set Start Date and Time</th>
                                    <td><input type="datetime-local" name="start_date" required></td>
                                </tr>
                            </table>
                            <!-- Loop through the generated responses/posts -->
                            <?php foreach ($responses as $index => $response) { ?>
                                <h3>Post <?php echo ($index + 1); ?></h3>
                                <table class="form-table">
                                    <tr valign="top">
                                        <!-- Input field for entering the post title -->
                                        <th scope="row">Enter post title</th>
                                        <td><input type="text" name="post_titles[]" value="<?php echo esc_attr($response['title']); ?>" class="regular-text" /></td>
                                    </tr>
                                    <tr valign="top">
                                        <!-- Textarea for post content -->
                                        <th scope="row">Post Content</th>
                                        <td><textarea name="post_contents[]" rows="10" cols="50" class="large-text"><?php echo esc_textarea($response['content']); ?></textarea></td>
                                    </tr>
                                    <tr valign="top">
                                        <!-- Dropdown for selecting the post category -->
                                        <th scope="row">Category</th>
                                        <td>
                                            <?php
                                            wp_dropdown_categories(array(
                                                'show_option_all' => 'Select Category',
                                                'name' => 'post_categories[]',
                                                'taxonomy' => 'category',
                                                'hide_empty' => 0,
                                                'selected' => $category_id,
                                            ));
                                            ?>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <!-- Input field for entering post tags -->
                                        <th scope="row">Tags</th>
                                        <td><input type="text" name="post_tags[]" value="<?php echo esc_attr(implode(', ', $response['tags'])); ?>" class="regular-text" /></td>
                                    </tr>
                                </table>
                            <?php } ?>
                            <!-- Submit button for the form -->
                            <?php submit_button('Submit Posts'); ?>
                        </form>
                        <?php
                    }
                }

                if (!empty($_POST['post_titles']) && !empty($_POST['post_contents'])) { // Checks if post titles and contents are provided.
                    $post_titles = array_map('sanitize_text_field', $_POST['post_titles']); // Sanitizes each post title.
                    $post_contents = array_map('wp_kses_post', $_POST['post_contents']); // Sanitizes each post content.
                    $post_categories = array_map('intval', $_POST['post_categories']); // Converts each category ID to an integer.
                    $post_tags = array_map('sanitize_text_field', $_POST['post_tags']); // Sanitizes each set of tags.
                    $interval = sanitize_text_field($_POST['post_interval']); // Sanitizes the interval input.
                    $start_time = sanitize_text_field($_POST['start_date']); // Sanitizes the start date input.

                    foreach ($post_titles as $index => $title) { // Iterates over each post title.
                        $content = wp_kses_post($post_contents[$index]); // Sanitizes the content for the current post.
                        $category = $post_categories[$index]; // Gets the category ID for the current post.
                        $tags = explode(',', $post_tags[$index]); // Splits the tags into an array.
                        $date = $this->calculate_future_date($start_time, $interval, $index); // Calculates the scheduled date for the post.
                        $this->schedule_post($title, $content, $date, $category, $tags); // Schedules the post for publication.
                        echo '<p>Post ' . ($index + 1) . ' created successfully and scheduled for ' . $date . '.</p>'; // Displays a success message.
                    }
                }
            }
            ?>
        </div>
        <?php
    }


    /**
     * Generates unique topics using the ChatGPT API.
     *
     * @param string $category The category for the topics.
     * @param string $keywords The keywords to use for topic generation.
     * @param int $num_topics The number of topics to generate.
     * @param array $existing_posts A list of existing post titles to avoid duplicates.
     * @return array The list of unique topics.
     */
    private function chatgpt_generate_unique_topics($category, $keywords, $num_topics, $existing_posts) {
        $topics = []; // Initializes an empty array to store generated topics.
        while (count($topics) < $num_topics) { // Continues generating topics until the desired number is reached.
            //important
            $question = "Generate " . ($num_topics - count($topics)) . " blog post topics about $category using keywords: $keywords"; // Constructs the prompt for ChatGPT.
            $response = $this->chatgpt_generate_content($question); // Calls the method to generate content based on the prompt.
            if ($response) { // Checks if a response was received.
                $new_topics = array_filter(explode("\n", $response), function($topic) use ($existing_posts) { // Splits the response into topics and filters them.
                    $sanitized_topic = $this->remove_numbering(trim($topic)); // Removes numbering from the topic and trims whitespace.
                    return !in_array($sanitized_topic, $existing_posts) && !in_array($sanitized_topic, $topics); // Ensures the topic is unique.
                });
                $topics = array_merge($topics, $new_topics); // Merges the new unique topics into the existing topics array.
            }
        }
        return $topics; // Returns the final array of unique topics.
    }

    /**
     * Generates content using the ChatGPT API.
     *
     * @param string $question The question or prompt to send to the API.
     * @return string The generated content.
     */
    private function chatgpt_generate_content($question) {
        $url = 'https://api.openai.com/v1/chat/completions'; // Sets the URL for the OpenAI API endpoint.
        $headers = array( // Prepares the headers for the HTTP request.
            'Content-Type: application/json', // Specifies the content type as JSON.
            'Authorization: Bearer ' . $this->api_key, // Includes the API key for authorization.
        );

        $data = array( // Prepares the data payload for the API request.
            //important
            'model' => 'gpt-3.5-turbo', // Specifies the AI model to use. You should change for better one, that is for test because it's cheaper.
            'messages' => array( // Constructs the conversation context for the model.
                array('role' => 'user', 'content' => $question), // The user message containing the prompt.
            ),
            //important
            'max_tokens' => 50, // Limits the response length to 50 tokens. better change for more
        );

        $options = array( // Configures the HTTP request options.
            'http' => array(
                'header'  => "Content-type: application/json\r\n" . // Sets the content type header.
                    "Authorization: Bearer " . $this->api_key . "\r\n", // Sets the authorization header.
                'method'  => 'POST', // Specifies the request method as POST.
                'content' => json_encode($data), // Encodes the data array as JSON.
            ),
        );

        $context  = stream_context_create($options); // Creates a stream context with the options.
        $result = file_get_contents($url, false, $context); // Sends the HTTP request and retrieves the response.

        if ($result === FALSE) { // Checks if the request failed.
            return 'Error: Unable to get response from ChatGPT API'; // Returns an error message if the request failed.
        }

        $response = json_decode($result, true); // Decodes the JSON response into an associative array.
        return $response['choices'][0]['message']['content']; // Returns the content of the response.
    }

    /**
     * Generates content with specified keywords using the ChatGPT API.
     *
     * @param string $topic The topic of the content.
     * @param string $keywords The keywords to include in the content.
     * @return string The generated content.
     */
    private function chatgpt_generate_content_with_keywords($topic, $keywords) {
        //important
        $question = "Write a blog post titled \"$topic\" using the keywords: $keywords. The content should cover the importance of the topic, specific benefits, and practical advice."; // Constructs the prompt with the specified topic and keywords.
        return $this->chatgpt_generate_content($question); // Calls the function to generate content using the constructed prompt.
    }

    /**
     * Generates tags for a given title using the ChatGPT API.
     *
     * @param string $title The title for which to generate tags.
     * @return array The list of generated tags.
     */
    private function generate_tags($title) {
        //important
        $question = "Generate tags for a blog post titled: \"$title\""; // Constructs the prompt to generate tags for the given title.
        $response = $this->chatgpt_generate_content($question); // Calls the function to generate content (tags) using the constructed prompt.
        if ($response) { // Checks if a response was received.
            $response = str_replace(['#', '-'], ',', $response); // Replaces separators '#' and '-' with commas in the response.
            $tags = array_map('trim', explode(',', $response)); // Splits the response by commas into an array and trims whitespace.
            $tags = array_filter($tags, function($tag) { // Filters out empty elements from the tags array.
                return !empty($tag);
            });
            return $tags; // Returns the array of filtered and trimmed tags.
        }
        return []; // Returns an empty array if no response was received.
    }

    /**
     * Retrieves the titles of existing posts.
     *
     * @return array The list of existing post titles.
     */
    private function get_existing_post_titles() {
        global $wpdb; // Accesses the global $wpdb object for database operations.
        $results = $wpdb->get_results("SELECT post_title FROM $wpdb->posts WHERE post_type = 'post' AND (post_status = 'publish' OR post_status = 'future')", ARRAY_A); // Retrieves the titles of posts that are either published or scheduled.
        return array_map(function($row) { // Maps over each result row.
            return $row['post_title']; // Returns the post title from each row.
        }, $results);
    }

    /**
     * Sanitizes a topic title.
     *
     * @param string $topic The topic title to sanitize.
     * @return string The sanitized topic title.
     */
    private function sanitize_topic($topic) {
        return trim(str_replace(array('\\', '"'), '', $topic)); // Removes backslashes and double quotes from the topic, then trims any surrounding whitespace.
    }

    /**
     * Retrieves or creates a category by name.
     *
     * @param string $category_name The name of the category.
     * @return int The ID of the category.
     */
    private function get_or_create_category($category_name) {
        $category_id = get_cat_ID($category_name); // Retrieves the ID of the category by name.
        if ($category_id == 0) { // Checks if the category does not exist.
            $new_category = wp_insert_category(array( // Attempts to create a new category.
                'cat_name' => $category_name, // Sets the category name.
                'taxonomy' => 'category' // Specifies the taxonomy as 'category'.
            ));
            if (is_wp_error($new_category)) { // Checks if an error occurred during category creation.
                return 0; // Returns 0 if there was an error.
            }
            return $new_category['term_id']; // Returns the ID of the newly created category.
        }
        return $category_id; // Returns the ID of the existing category.
    }

    /**
     * Calculates the future date based on the start time and interval.
     *
     * @param string $start_time The start time in 'Y-m-d H:i:s' format.
     * @param string $interval The interval for the next post.
     * @param int $index The index of the post in the schedule.
     * @return string The calculated date and time in 'Y-m-d H:i:s' format.
     */
    private function calculate_future_date($start_time, $interval, $index) {
        $date = new DateTime($start_time); // Creates a new DateTime object with the provided start time.
        switch ($interval) { // Determines the modification based on the interval.
            case 'immediately':
                break; // No modification; publish immediately.
            case '1 minute':
                $date->modify('+' . (1 * $index) . ' minute'); // Adds the corresponding number of minutes to the start time.
                break;
            case '2 minutes':
                $date->modify('+' . (2 * $index) . ' minutes');
                break;
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
            case '2 weeks':
                $date->modify('+' . (2 * $index) . ' weeks');
                break;
            case '1 month':
                $date->modify('+' . $index . ' month');
                break;
        }
        return $date->format('Y-m-d H:i:s'); // Returns the formatted date and time as a string.
    }

    /**
     * Schedules a post for publication.
     *
     * @param string $title The title of the post.
     * @param string $content The content of the post.
     * @param string $date The scheduled date and time for the post.
     * @param int $category The category ID for the post.
     * @param array $tags The tags associated with the post.
     */
    private function schedule_post($title, $content, $date, $category, $tags) {
        $post_data = array( // Constructs the post data array.
            'post_title'    => $title, // Sets the title of the post.
            'post_content'  => $content, // Sets the content of the post.
            'post_status'   => 'future', // Sets the post status to 'future', scheduling it for later.
            'post_author'   => get_current_user_id(), // Sets the post author to the current user.
            'post_date'     => $date, // Sets the date when the post should be published.
            'post_category' => array($category), // Sets the category of the post.
            'tags_input'    => $tags, // Sets the tags associated with the post.
        );

        $post_id = wp_insert_post($post_data); // Inserts the post into the WordPress database.

        if (!is_wp_error($post_id)) { // Checks if the post insertion was successful.
            echo "Post created with ID: " . $post_id; // Outputs a success message with the post ID.
        } else { // If there was an error inserting the post.
            echo "Error: " . $post_id->get_error_message(); // Outputs the error message.
        }
    }

    /**
     * Retrieves all published posts.
     *
     * @return array The list of published posts.
     */
    private function get_all_posts() {
        global $wpdb; // Accesses the global $wpdb object for database operations.
        $results = $wpdb->get_results("SELECT ID, post_title, post_name FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'", ARRAY_A); // Retrieves the ID, title, and slug of all published posts.
        return $results; // Returns the results as an array of associative arrays.
    }

    /**
     * Adds internal links to the content by finding matching post titles.
     *
     * @param string $content The content in which to add internal links.
     * @return string The content with added internal links.
     */
    private function add_internal_links($content) {
        $all_posts = $this->get_all_posts(); // Retrieves all published posts.
        $link_count = 0; // Initializes the counter for the number of links added.
        $max_links = 3; // Sets the maximum number of internal links to add.

        foreach ($all_posts as $post) { // Iterates through each post.
            $title = $post['post_title']; // Retrieves the title of the current post.
            $url = get_permalink($post['ID']); // Retrieves the permalink URL of the current post.

            if (stripos($content, $title) !== false && $link_count < $max_links) { // Checks if the post title is found in the content and if the max links limit is not reached.
                $link = '<a href="' . esc_url($url) . '">' . esc_html($title) . '</a>'; // Creates a hyperlink for the post title.
                $content = str_replace($title, $link, $content); // Replaces the plain text title with the hyperlink in the content.
                $link_count++; // Increments the link counter.
            }
        }

        return wp_kses_post($content); // Returns the content with added internal links, ensuring it's safe for output.
    }

    /**
     * Adds custom cron schedules.
     *
     * @param array $schedules The existing cron schedules.
     * @return array The updated cron schedules.
     */
    public function custom_cron_schedule($schedules) {
        if (!isset($schedules['every_minute'])) { // Checks if the 'every_minute' schedule is not already set.
            $schedules['every_minute'] = array( // Adds a new schedule for every minute.
                'interval' => 60, // The interval in seconds (60 seconds = 1 minute).
                'display' => __('Every Minute') // The display name for the schedule.
            );
        }
        if (!isset($schedules['every_two_minutes'])) { // Checks if the 'every_two_minutes' schedule is not already set.
            $schedules['every_two_minutes'] = array( // Adds a new schedule for every two minutes.
                'interval' => 120, // The interval in seconds (120 seconds = 2 minutes).
                'display' => __('Every Two Minutes') // The display name for the schedule.
            );
        }
        if (!isset($schedules['every_two_weeks'])) { // Checks if the 'every_two_weeks' schedule is not already set.
            $schedules['every_two_weeks'] = array( // Adds a new schedule for every two weeks.
                'interval' => 1209600, // The interval in seconds (1209600 seconds = 2 weeks).
                'display' => __('Every Two Weeks') // The display name for the schedule.
            );
        }
        return $schedules; // Returns the modified schedules array.
    }

    /**
     * Checks for scheduled posts and publishes them if their scheduled time has arrived.
     */
    public function check_scheduled_posts() {
        // Define the arguments for fetching future posts
        $args = array(
            'post_status' => 'future', // Only get posts that are scheduled for future publication
            'posts_per_page' => -1 // Get all posts, no limit on number
        );

        $future_posts = get_posts($args); // Get all posts that match the criteria


        foreach ($future_posts as $post) { // Iterate through each future post
            $post_date = strtotime($post->post_date); // Convert the post's scheduled date to a timestamp
            if ($post_date <= current_time('timestamp')) { // If the post's scheduled time is in the past or present, publish it

                wp_publish_post($post->ID); // Publish the post
            }
        }
    }
}

if (!wp_next_scheduled('scheduled_posts_cron')) { // Check if the cron event 'scheduled_posts_cron' is not already scheduled
    wp_schedule_event(time(), 'every_minute', 'scheduled_posts_cron'); // Schedule the event 'scheduled_posts_cron' to run every minute

}

// Instantiate the ChatGPT_Scheduled_Post_Generator class
new ChatGPT_Scheduled_Post_Generator();
?>
