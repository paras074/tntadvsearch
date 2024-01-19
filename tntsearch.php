<?php
/**
 * Plugin Name: TNTSearch
 * Description: A custom search plugin using TNTSearch library.
 * Version: 1.0
 * Author: PWS
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TNTSearchPlugin {

    private $tnt;

    public function __construct() {
        // Include TNTSearch Library
        require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

        // Initialize TNTSearch instance
        $this->initializeTNTSearch();

        // Enqueue assets
        add_action('wp_enqueue_scripts', [$this, 'tnt_enqueueAssets']);

        // Add admin menu
        add_action('admin_menu', [$this, 'tnt_addAdminMenu']);

        // Shortcode for search form
        add_shortcode('tnt_search_form', [$this, 'tntSearchFormShortcode']);

        // Ajax actions
        add_action('wp_ajax_tnt_search_action', [$this, 'tntSearchActionCallback']);
        add_action('wp_ajax_nopriv_tnt_search_action', [$this, 'tntSearchActionCallback']);
    }

    private function initializeTNTSearch() {
        $config = [
            'driver'    => 'mysql',
            'host'      => DB_HOST,
            'database'  => DB_NAME,
            'username'  => DB_USER,
            'password'  => DB_PASSWORD,
            'storage'   => __DIR__,
            'stemmer'   => \TeamTNT\TNTSearch\Stemmer\PorterStemmer::class,
        ];

        $this->tnt = new \TeamTNT\TNTSearch\TNTSearch;
        $this->tnt->loadConfig($config);
    }

    public function tnt_enqueueAssets() {
        wp_enqueue_style('tnt-search-styles', plugins_url('assets/css/style.css', __FILE__));
        wp_enqueue_script('jquery'); 
        wp_enqueue_script('tnt-search-scripts', plugins_url('assets/js/script.js', __FILE__), ['jquery'], null, true);
        wp_localize_script('tnt-search-scripts', 'tnt_ajax_object', ['ajaxurl' => admin_url('admin-ajax.php')]);
    }

    public function tnt_addAdminMenu() {
        add_menu_page('TNT Search', 'TNT Search', 'manage_options','tnt-search', [$this, 'displaySettingPage'], 'dashicons-admin-generic', 20);
    }

    public function displaySettingPage() { ?>
        <div class="short-code-parent wrap">
            <h2>TNT Search Setting</h2>
            <hr>
            <div class="tnt-data shortcode-data">
                <p>We utilize a shortcode on a page to display a list of posts based on user search criteria </p>
                <div class="">
                    <span class='TNTSearch-code'>ShortCode : </span>
                    <input style="text-align:center;" type="text" id="tnt-shortcode" name="tnt_search_form" value="[tnt_search_form]">
                </div>
            </div>
       
        </div>
        <?php
    }

    public function tntSearchFormShortcode() {
        ob_start();  ?>
        <form id="tnt-search-form">
            <div class="tnt-form">
                <label for="tnt-search-input">Custom Search: </label> 
                <input type="text" id="tnt-search-input" name="tnt_search_input" placeholder="car">
            </div>
        </form>
        <div id="tnt-search-results">
            <!-- Append here -->
        </div>
        <?php
        return ob_get_clean();
    }

    public function tntSearchActionCallback() {
        try {
            // Create index and perform search
            $this->createIndexAndSearch();
        } catch (Exception $e) {
            //echo 'Caught exception: ', $e->getMessage(), "\n";
            wp_die();
        }
    }

    private function createIndexAndSearch() {
        $indexer = $this->tnt->createIndex('name.index');
        $indexer->disableOutput = true;

        global $wpdb;
        $searchTerm = sanitize_text_field($_POST['search_term']);
        $tableName = $wpdb->prefix . 'posts';
        $indexer->query('SELECT id, post_title FROM '.$tableName.' WHERE post_type = "post" and post_title like "%'.$searchTerm.'%"');
        $indexer->run();

        // Select the index before searching
        $this->tnt->selectIndex('name.index');

        // Add wildcard characters to the search term for partial matching
        $searchTerm = $searchTerm;
        $this->tnt->asYouType(true);
        $results = $this->tnt->search($searchTerm);

        // Display search results
        $this->displaySearchResults($results, $searchTerm);
    }

    private function displaySearchResults($results, $searchTerm) {
        echo "<ul>";
        if (count($results['ids']) > 0) {
            foreach ($results['ids'] as $id) {
                $post_title = get_the_title($id);
                $highlighted_title = $this->highlightSearchTerm($post_title, $searchTerm);
                $post_content = wp_trim_words(get_post_field('post_content', $id),28,'...');
                echo "<li class='post-data-list'>
                    <div class='post-data'>
                        <div class='post-img-parent'>
                            <img class='post-img'  src='".plugin_dir_url(__FILE__)."assets/img/noimg.jpg' alt='img' width='500' height='600'>
                        </div>
                        <div class='post-grid'>
                            <a class='post-title'  href='" . get_permalink($id) . "'>" . $highlighted_title . "</a>
                            <p class='post-desc' >" . $post_content . "</p>
                        </div>
                    </div>
                </li>";
            }
        } else {
            echo "<li class='no-post'>No result found</li>";
        }
        echo "</ul";
        wp_die();
    }

    private function highlightSearchTerm($text, $term) {
        $pos = stripos($text, $term);
        
        if ($pos !== false) {
            $highlighted_text = substr_replace($text, '</span>', $pos + strlen($term), 0);
            $highlighted_text = substr_replace($highlighted_text, '<span class="highlighted">', $pos, 0);
            return $highlighted_text;
        } else {
            return $text;
        }
    }
}

// Instantiate the class
new TNTSearchPlugin();
