<?php
/**
 * PageSpeed Ninja
 * https://pagespeed.ninja/
 *
 * @version    1.4.5
 * @license    GNU/GPL v2 - http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright  (C) 2016-2024 PageSpeed Ninja Team
 * @date       September 2024
 */

class PagespeedNinja_Cache_Hooks
{
    /** @var string $plugin_slug Official slug for this plugin on wordpress.org. */
    protected $plugin_slug;

    /** @var string $plugin_name The string used to uniquely identify this plugin. */
    protected $plugin_name;

    /** @var string $version The current version of the plugin. */
    protected $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_slug The slug of this plugin.
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_slug, $plugin_name, $version)
    {
        $this->plugin_slug = $plugin_slug;
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /** @return void */
    public function define_cache_hooks()
    {
        add_action('psn_cache_disable', array($this, 'disable_cache'));
        add_action('psn_cache_reset', array($this, 'reset_cache'));
        add_action('psn_cache_tags_update', array($this, 'pagecache_tags_update'));
        add_action('psn_cache_tags_use', array($this, 'pagecache_tags_use'));
        $reset_cache_actions = array(
            'clean_attachment_cache'/*($att_id)*/, // after the given attachment's cache is cleaned
//          'clean_comment_cache'/*($comment_id)*/, // immediately after a comment has been removed from the object cache
            'clean_page_cache'/*($post_id)*/, // immediately after the given page's cache is cleaned
            'clean_post_cache'/*($post_id,$post)*/, // immediately after the given post's cache is cleaned
            'customize_save_after'/*($WP_Customize_Manager)*/, // after Customize settings have been saved
            'post_stuck'/*($post_id)*/, // once a post has been added to the sticky list
            'post_unstuck'/*($post_id)*/, // once a post has been removed from the sticky list
            'switch_theme'/*($new_name,$new_WP_Theme,$old_WP_Theme)*/, // after the theme is switched
            // Global Reading settings:
            'update_option_show_on_front',
            'update_option_page_on_front',
            'update_option_page_for_posts',
            'update_option_posts_per_page',
        );
        foreach ($reset_cache_actions as $action) {
            add_action($action, array($this, 'reset_cache'));
        }

        add_action('the_post', array($this, 'the_post'));

        add_action('save_post', array($this, 'save_post'), 10, 3);
        add_action('post_updated', array($this, 'post_updated'), 10, 3);

        add_action('pre_get_posts', array($this, 'pre_get_posts'));
    }

    /**
     * @param mixed $dummy (unused)
     * @return void
     */
    public function disable_cache($dummy = null)
    {
        global $pagespeedninja_cache;
        if (isset($pagespeedninja_cache)) {
            $pagespeedninja_cache->caching = false;
        }
    }

    /**
     * @param mixed $dummy (unused)
     * @return void
     */
    public function reset_cache($dummy = null)
    {
        // don't rely on global $pagespeedninja_cache as it may be unavailable
        $cache_dir = WP_CONTENT_DIR . '/uploads/psn-pagespeed-ninja/pagecache';
        @touch($cache_dir . '/tags/GLOBAL');
    }

    /**
     * @param string[] $tags
     * @return void
     */
    public function pagecache_tags_update($tags)
    {
        global $pagespeedninja_cache;
        if (isset($pagespeedninja_cache)) {
            $cache = $pagespeedninja_cache;
            foreach ($tags as $tag) {
                $cache->updateTag($tag);
            }
        }
    }

    /**
     * @param string[] $tags
     * @return void
     */
    public function pagecache_tags_use($tags)
    {
        global $pagespeedninja_cache;
        if (isset($pagespeedninja_cache) && $pagespeedninja_cache->caching) {
            $pagespeedninja_cache->addTagDependence($tags);
        }
    }

    /**
     * @param WP_Post $post
     * @return void
     */
    public function the_post($post)
    {
        $this->pagecache_tags_use(array('post-' . $post->ID));
    }

    /**
     * @param int $post_id
     * @param WP_Post $post_after
     * @param ?WP_Post $post_before
     * @return void
     */
    public function post_updated($post_id, $post_after, $post_before)
    {
        if ($post_before) {
            $this->pagecache_tags_update($this->getPostTags($post_before));
        }
        $this->pagecache_tags_update($this->getPostTags($post_after));
        // if sticky changed -> invalidate categories (covered by above calls)
    }

    /**
     * @param int $post_id
     * @param WP_Post $post
     * @param bool $update
     * @return void
     */
    public function save_post($post_id, $post, $update)
    {
        if (!$update) {
            $this->pagecache_tags_update($this->getPostTags($post));
        }
    }

    /**
     * @param WP_Query $wp_query
     * @return void
     */
    public function pre_get_posts($wp_query)
    {
        if (!$wp_query->is_main_query()) {
            return;
        }

        if ($wp_query->is_feed() || $wp_query->is_preview()) {
            $this->disable_cache();
            return;
        }

        $q = $wp_query->query;
        $qv = $wp_query->query_vars;

        if ($wp_query->is_home() && $qv['p'] === '') {
            $this->pagecache_tags_use(array('ANY'));
            return;
        }

        if ($qv['s'] !== '') {
            $options = get_option('pagespeedninja_config');
            if ($options['pagecache_search']) {
                $this->pagecache_tags_use(array('ANY'));
            } else {
                $this->disable_cache();
            }
            return;
        }

        $tags = array();

        if ($qv['m']) {
            $year = substr($qv['m'], 0, 4);
            if (strlen($year) === 4) {
                $month = substr($qv['m'], 4, 2);
                $day = substr($qv['m'], 6, 2);
                if ($day) {
                    $tags[] = 'date-' . $year . $month . $day;
                } elseif ($month) {
                    $tags[] = 'date-' . $year . $month;
                } else {
                    $tags[] = 'date-' . $year;
                }
                unset($q['m']);
            }
        }

        if ($qv['year']) {
            if ($qv['monthnum']) {
                if ($qv['day']) {
                    $tags[] = 'date-' . $qv['year'] . $qv['monthnum'] . $qv['day'];
                } else {
                    $tags[] = 'date-' . $qv['year'] . $qv['monthnum'];
                }
            } else {
                $tags[] = 'date-' . $qv['year'];
            }
            unset($q['year'], $q['monthnum'], $q['day']);
        }

        if ($qv['p']) {
            $tags[] = 'post-' . $qv['p'];
        }
        unset($q['p'], $q['page_id']);

        if ($qv['author']) {
            $tags[] = 'author-' . $qv['author'];
        }
        unset($q['author'], $q['author_name']);

        if (!empty($qv['page'])) {
            $tags[] = 'post-' . $qv['page'];
        }
        unset($q['page']);

        if ($qv['name'] !== '') {
            $tags[] = 'postname-' . $qv['name'];
        }
        unset($q['name']);

        if ($qv['tag_id']) {
            $tags[] = 'tag-' . $qv['tag_id'];
        }
        unset($q['tag'], $q['tag_id']);

        if ($qv['cat']) {
            foreach (explode(',', $qv['cat']) as $cat) {
                $tags[] = 'cat-' . trim($cat);
            }
            unset($q['category_name'], $q['cat']);
        }

        unset($q['paged'], $q['order'], $q['posts_per_page']);

        if (count($q)) {
            $tags = array('ANY');
        }

        if (count($tags)) {
            $this->pagecache_tags_use($tags);
        }
    }

    /**
     * @param WP_Post $post
     * @return string[]
     */
    private function getPostTags($post)
    {
        $year = substr($post->post_date, 0, 4);
        $month = substr($post->post_date, 5, 2);
        $day = substr($post->post_date, 8, 2);
        $tags = array(
            'post-' . $post->ID,
            'postname-' . $post->post_name,
            'author-' . $post->post_author,
            'date-' . $year,
            'date-' . $year . $month,
            'date-' . $year . $month . $day,
        );
        if ($post->post_parent) {
            $tags[] = 'post-' . $post->post_parent;
        }

        foreach ($post->post_category as $cat_id) {
            $tags[] = 'cat-' . $cat_id;
        }

        if (is_object_in_taxonomy($post->post_type, 'post_tag')) {
            $terms = get_the_terms($post, 'post_tag');
            if (!empty($terms)) {
                $tag_ids = wp_list_pluck($terms, 'term_id');
                foreach ($tag_ids as $tag_id) {
                    $tags[] = 'tag-' . $tag_id;
                }
            }
        }

        return $tags;
    }
}
