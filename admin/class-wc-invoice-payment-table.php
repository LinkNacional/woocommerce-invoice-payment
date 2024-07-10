<?php
/**
 * Class Lkn_Wcip_List_Table
 */
final class Lkn_Wcip_List_Table {
    /**
     * The current list of items.
     *
     * @since 3.1.0
     * @var array
     */
    public $items;

    /**
     * Various information about the current table.
     *
     * @since 3.1.0
     * @var array
     */
    protected $_args;

    /**
     * Various information needed for displaying the pagination.
     *
     * @since 3.1.0
     * @var array
     */
    protected $_pagination_args = array();

    /**
     * The current screen.
     *
     * @since 3.1.0
     * @var WP_Screen
     */
    protected $screen;

    /**
     * Cached bulk actions.
     *
     * @since 3.1.0
     * @var array
     */
    private $_actions;

    /**
     * Cached pagination output.
     *
     * @since 3.1.0
     * @var string
     */
    private $_pagination;

    /**
     * Nonce validate.
     *
     * @since 3.1.0
     * @var string
     */
    private $_nonce;

    /**
     * The view switcher modes.
     *
     * @since 4.1.0
     * @var array
     */
    protected $modes = array();

    /**
     * Stores the value returned by ->get_column_info().
     *
     * @since 4.1.0
     * @var array
     */
    protected $_column_headers;

    /**
     * {@internal Missing Summary}
     *
     * @var array
     */
    protected $compat_fields = array('_args', '_pagination_args', 'screen', '_actions', '_pagination');

    /**
     * {@internal Missing Summary}
     *
     * @var array
     */
    protected $compat_methods = array(
        'set_pagination_args',
        'get_views',
        'get_bulk_actions',
        'bulk_actions',
        'row_actions',
        'months_dropdown',
        'view_switcher',
        'comments_bubble',
        'get_items_per_page',
        'pagination',
        'get_sortable_columns',
        'get_column_info',
        'get_table_classes',
        'display_tablenav',
        'extra_tablenav',
        'single_row_columns',
    );

    /**
     * Constructor.
     *
     * The child class should call this constructor from its own constructor to override
     * the default $args.
     *
     * @since 3.1.0
     *
     * @param array|string $args {
     *     Array or string of arguments.
     *
     *     @type string $plural   Plural value used for labels and the objects being listed.
     *                            This affects things such as CSS class-names and nonces used
     *                            in the list table, e.g. 'posts'. Default empty.
     *     @type string $singular Singular label for an object being listed, e.g. 'post'.
     *                            Default empty
     *     @type bool   $ajax     Whether the list table supports Ajax. This includes loading
     *                            and sorting data, for example. Default false.
     *     @type string $screen   String containing the hook name used to determine the current
     *                            screen. If left null, the current screen will be automatically set.
     *                            Default null.
     * }
     */
    public function __construct($args = array()) {
        $args = wp_parse_args(
            $args,
            array(
                'plural' => '',
                'singular' => '',
                'ajax' => false,
                'screen' => null,
            )
        );

        $this->screen = convert_to_screen($args['screen']);

        add_filter("manage_{$this->screen->id}_columns", array($this, 'get_columns'), 0);

        if ( ! $args['plural']) {
            $args['plural'] = $this->screen->base;
        }

        $args['plural'] = sanitize_key($args['plural']);
        $args['singular'] = sanitize_key($args['singular']);

        $this->_args = $args;

        if (empty($this->modes)) {
            $this->modes = array(
                'list' => __('Compact view'),
                'excerpt' => __('Extended view'),
            );
        }
    }

    /**
     * Make private properties readable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to get.
     * @return mixed Property.
     */
    public function __get($name) {
        if (in_array($name, $this->compat_fields, true)) {
            return $this->$name;
        }
    }

    /**
     * Make private properties settable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name  Property to check if set.
     * @param mixed  $value Property value.
     * @return mixed Newly-set property.
     */
    public function __set($name, $value) {
        if (in_array($name, $this->compat_fields, true)) {
            return $this->$name = $value;
        }
    }

    /**
     * Make private properties checkable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to check if set.
     * @return bool Whether the property is a back-compat property and it is set.
     */
    public function __isset($name) {
        if (in_array($name, $this->compat_fields, true)) {
            return isset($this->$name);
        }

        return false;
    }

    /**
     * Make private properties un-settable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name Property to unset.
     */
    public function __unset($name): void {
        if (in_array($name, $this->compat_fields, true)) {
            unset($this->$name);
        }
    }

    /**
     * Make private/protected methods readable for backward compatibility.
     *
     * @since 4.0.0
     *
     * @param string $name      Method to call.
     * @param array  $arguments Arguments to pass when calling.
     * @return mixed|bool Return value of the callback, false otherwise.
     */
    public function __call($name, $arguments) {
        if (in_array($name, $this->compat_methods, true)) {
            return $this->$name(...$arguments);
        }

        return false;
    }

    /**
     * Checks the current user's permissions
     *
     * @since 3.1.0
     * @abstract
     */
    public function ajax_user_can(): void {
        die('function WP_List_Table::ajax_user_can() must be overridden in a subclass.');
    }

    /**
     * An internal method that sets all the necessary pagination arguments
     *
     * @since 3.1.0
     *
     * @param array|string $args Array or string of arguments with information about the pagination.
     */
    protected function set_pagination_args($args): void {
        $args = wp_parse_args(
            $args,
            array(
                'total_items' => 0,
                'total_pages' => 0,
                'per_page' => 0,
            )
        );

        if ( ! $args['total_pages'] && $args['per_page'] > 0) {
            $args['total_pages'] = ceil($args['total_items'] / $args['per_page']);
        }

        // Redirect if page number is invalid and headers are not already sent.
        if ( ! headers_sent() && ! wp_doing_ajax() && $args['total_pages'] > 0 && $this->get_pagenum() > $args['total_pages']) {
            wp_redirect(add_query_arg('paged', $args['total_pages']));
            exit;
        }

        $this->_pagination_args = $args;
    }

    /**
     * Access the pagination args.
     *
     * @since 3.1.0
     *
     * @param string $key Pagination argument to retrieve. Common values include 'total_items',
     *                    'total_pages', 'per_page', or 'infinite_scroll'.
     * @return int Number of items that correspond to the given pagination argument.
     */
    public function get_pagination_arg($key) {
        if ('page' === $key) {
            return $this->get_pagenum();
        }

        if (isset($this->_pagination_args[$key])) {
            return $this->_pagination_args[$key];
        }

        return 0;
    }

    /**
     * Whether the table has items to display or not
     *
     * @since 3.1.0
     *
     * @return bool
     */
    public function has_items() {
        return ! empty($this->items);
    }

    /**
     * Message to be displayed when there are no items
     *
     * @since 3.1.0
     */
    public function no_items(): void {
        esc_attr_e('No items found.');
    }

    /**
     * Gets the list of views available on this table.
     *
     * The format is an associative array:
     * - `'id' => 'link'`
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_views() {
        return array();
    }

    /**
     * Displays the list of views available on this table.
     *
     * @since 3.1.0
     */
    public function views(): void {
        $views = $this->get_views();
        /**
         * Filters the list of available list table views.
         *
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen.
         *
         * @since 3.1.0
         *
         * @param string[] $views An array of available list table views.
         */
        $views = apply_filters("views_{$this->screen->id}", $views);

        if (empty($views)) {
            return;
        }

        $this->screen->render_screen_reader_content('heading_views');

        echo "<ul class='subsubsub'>\n";
        foreach ($views as $class => $view) {
            $views[$class] = "\t<li class='$class'>$view";
        }
        echo implode(" |</li>\n", esc_attr($views)) . "</li>\n";
        echo '</ul>';
    }

    /**
     * Displays the bulk actions dropdown.
     *
     * @since 3.1.0
     *
     * @param string $which The location of the bulk actions: 'top' or 'bottom'.
     *                      This is designated as optional for backward compatibility.
     */
    protected function bulk_actions($which = ''): void {
        if (is_null($this->_actions)) {
            $this->_actions = $this->get_bulk_actions();

            /**
             * Filters the items in the bulk actions menu of the list table.
             *
             * The dynamic portion of the hook name, `$this->screen->id`, refers
             * to the ID of the current screen.
             *
             * @since 3.1.0
             * @since 5.6.0 A bulk action can now contain an array of options in order to create an optgroup.
             *
             * @param array $actions An array of the available bulk actions.
             */
            $this->_actions = apply_filters("bulk_actions-{$this->screen->id}", $this->_actions); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

            $two = '';
        } else {
            $two = '2';
        }

        if (empty($this->_actions)) {
            return;
        }
        $nonceAction = wp_create_nonce( 'nonce_action' );
        echo '<input type="hidden" name="nonce_action_field" value="' . esc_attr( $nonceAction ) . '" />';
        echo '<label for="bulk-action-selector-' . esc_attr($which) . '" class="screen-reader-text">' . esc_attr(__('Select bulk action')) . '</label>';
        echo '<select name="action' . esc_attr($two) . '" id="bulk-action-selector-' . esc_attr($which) . "\">\n";
        echo '<option value="-1">' . esc_attr(__('Bulk actions')) . "</option>\n";

        foreach ($this->_actions as $key => $value) {
            if (is_array($value)) {
                echo "\t" . '<optgroup label="' . esc_attr($key) . '">' . "\n";

                foreach ($value as $name => $title) {
                    $class = ('edit' === $name) ? ' class="hide-if-no-js"' : '';

                    echo "\t\t" . '<option value="' . esc_attr($name) . '"' . esc_attr($class) . '>' . esc_html($title) . "</option>\n";
                }
                echo "\t" . "</optgroup>\n";
            } else {
                $class = ('edit' === $key) ? ' class="hide-if-no-js"' : '';

                echo "\t" . '<option value="' . esc_attr($key) . '"' . esc_attr($class) . '>' . esc_html($value) . "</option>\n";
            }
        }

        echo "</select>\n";

        submit_button(__('Apply'), 'action', '', false, array('id' => 'doaction' . esc_attr($two)));
        echo "\n";
    }

    /**
     * Gets the current action selected from the bulk actions dropdown.
     *
     * @since 3.1.0
     *
     * @return string|false The action name. False if no action was selected.
     */
    public function current_action() {
        if (
            isset($_REQUEST['filter_action']) && 
            ! empty($_REQUEST['filter_action']) && 
            ! wp_verify_nonce($this->_nonce, 'validate_nonce')
        ) {
            return false;
        }

        if (isset($_REQUEST['action']) && -1 != $_REQUEST['action']) {
            return $_REQUEST['action'];
        }

        return false;
    }

    /**
     * Generates the required HTML for a list of row action links.
     *
     * @since 3.1.0
     *
     * @param string[] $actions        An array of action links.
     * @param bool     $always_visible Whether the actions should be always visible.
     * @return string The HTML for the row actions.
     */
    protected function row_actions($actions, $always_visible = false) {
        $action_count = count($actions);

        if ( ! $action_count) {
            return '';
        }

        $mode = get_user_setting('posts_list_mode', 'list');

        if ('excerpt' === $mode) {
            $always_visible = true;
        }

        $out = '<div class="' . ($always_visible ? 'row-actions visible' : 'row-actions') . '">';

        $i = 0;

        foreach ($actions as $action => $link) {
            ++$i;

            $sep = ($i < $action_count) ? ' | ' : '';

            $out .= "<span class='$action'>$link$sep</span>";
        }

        $out .= '</div>';

        $out .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __('Show more details') . '</span></button>';

        return $out;
    }

    /**
     * Displays a view switcher.
     *
     * @since 3.1.0
     *
     * @param string $current_mode
     */
    protected function view_switcher($current_mode): void {
        ?>
<input type="hidden" name="mode"
	value="<?php echo esc_attr($current_mode); ?>" />
<div class="view-switch">
	<?php
        foreach ($this->modes as $mode => $title) {
            $classes = array('view-' . $mode);
            $aria_current = '';

            if ($current_mode === $mode) {
                $classes[] = 'current';
                $aria_current = ' aria-current="page"';
            }

            printf(
                "<a href='%s' class='%s' id='view-switch-%s'%s><span class='screen-reader-text'>%s</span></a>\n",
                esc_url(remove_query_arg('attachment-filter', add_query_arg('mode', $mode))),
                esc_attr(implode(' ', $classes)),
                esc_attr($mode),
                esc_attr($aria_current),
                esc_html($title)
            );
        } ?>
</div>
<?php
    }

    /**
     * Displays a comment count bubble.
     *
     * @since 3.1.0
     *
     * @param int $post_id          The post ID.
     * @param int $pending_comments Number of pending comments.
     */
    protected function comments_bubble($post_id, $pending_comments): void {
        $approved_comments = get_comments_number();
        $approved_comments_number = number_format_i18n($approved_comments);
        $pending_comments_number = number_format_i18n($pending_comments);

        $approved_only_phrase = sprintf(
            /* translators: %s: Number of comments. */
            _n('%s comment', '%s comments', $approved_comments),
            $approved_comments_number
        );

        $approved_phrase = sprintf(
            /* translators: %s: Number of comments. */
            _n('%s approved comment', '%s approved comments', $approved_comments),
            $approved_comments_number
        );

        $pending_phrase = sprintf(
            /* translators: %s: Number of comments. */
            _n('%s pending comment', '%s pending comments', $pending_comments),
            $pending_comments_number
        );

        if ( ! $approved_comments && ! $pending_comments) {
            // No comments at all.
            printf(
                '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">%s</span>',
                esc_html__('No comments')
            );
        } elseif ($approved_comments && 'trash' === get_post_status($post_id)) {
            // Don't link the comment bubble for a trashed post.
            printf(
                '<span class="post-com-count post-com-count-approved"><span class="comment-count-approved" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
                esc_html($approved_comments_number),
                $pending_comments ? esc_html($approved_phrase) : esc_html($approved_only_phrase)
            );
        } elseif ($approved_comments) {
            // Link the comment bubble to approved comments.
            printf(
                '<a href="%s" class="post-com-count post-com-count-approved"><span class="comment-count-approved" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
                esc_url(
                    add_query_arg(
                        array(
                            'p' => $post_id,
                            'comment_status' => 'approved',
                        ),
                        admin_url('edit-comments.php')
                    )
                ),
                esc_html($approved_comments_number),
                $pending_comments ? esc_html($approved_phrase) : esc_html($approved_only_phrase)
            );
        } else {
            // Don't link the comment bubble when there are no approved comments.
            printf(
                '<span class="post-com-count post-com-count-no-comments"><span class="comment-count comment-count-no-comments" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
                esc_html($approved_comments_number),
                $pending_comments ? esc_html__('No approved comments') : esc_html__('No comments')
            );
        }

        if ($pending_comments) {
            printf(
                '<a href="%s" class="post-com-count post-com-count-pending"><span class="comment-count-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></a>',
                esc_url(
                    add_query_arg(
                        array(
                            'p' => $post_id,
                            'comment_status' => 'moderated',
                        ),
                        admin_url('edit-comments.php')
                    )
                ),
                esc_html($pending_comments_number),
                esc_html($pending_phrase)
            );
        } else {
            printf(
                '<span class="post-com-count post-com-count-pending post-com-count-no-pending"><span class="comment-count comment-count-no-pending" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
                esc_html($pending_comments_number),
                $approved_comments ? esc_html__('No pending comments') : esc_html__('No comments')
            );
        }
    }

    /**
     * Gets the current page number.
     *
     * @since 3.1.0
     *
     * @return int
     */
    public function get_pagenum() {
        if( ! wp_verify_nonce($this->_nonce, 'validate_nonce')) {
            return;
        }
    
        $pagenum = isset($_REQUEST['paged']) ? absint($_REQUEST['paged']) : 0;

        if (isset($this->_pagination_args['total_pages']) && $pagenum > $this->_pagination_args['total_pages']) {
            $pagenum = $this->_pagination_args['total_pages'];
        }

        return max(1, $pagenum);
    }

    /**
     * Gets the number of items to display on a single page.
     *
     * @since 3.1.0
     *
     * @param string $option
     * @param int    $default
     * @return int
     */
    protected function get_items_per_page($option, $default = 20) {
        $per_page = (int) get_user_option($option);
        if (empty($per_page) || $per_page < 1) {
            $per_page = $default;
        }

        /**
         * Filters the number of items to be displayed on each page of the list table.
         *
         * The dynamic hook name, `$option`, refers to the `per_page` option depending
         * on the type of list table in use. Possible filter names include:
         *
         *  - `edit_comments_per_page`
         *  - `sites_network_per_page`
         *  - `site_themes_network_per_page`
         *  - `themes_network_per_page'`
         *  - `users_network_per_page`
         *  - `edit_post_per_page`
         *  - `edit_page_per_page'`
         *  - `edit_{$post_type}_per_page`
         *  - `edit_post_tag_per_page`
         *  - `edit_category_per_page`
         *  - `edit_{$taxonomy}_per_page`
         *  - `site_users_network_per_page`
         *  - `users_per_page`
         *
         * @since 2.9.0
         *
         * @param int $per_page Number of items to be displayed. Default 20.
         */
        return (int) apply_filters("{$option}", $per_page);
    }

    /**
     * Displays the pagination.
     *
     * @since 3.1.0
     *
     * @param string $which
     */
    protected function pagination($which): void {
        if (empty($this->_pagination_args)) {
            return;
        }

        $total_items = $this->_pagination_args['total_items'];
        $total_pages = $this->_pagination_args['total_pages'];
        $infinite_scroll = false;
        if (isset($this->_pagination_args['infinite_scroll'])) {
            $infinite_scroll = $this->_pagination_args['infinite_scroll'];
        }

        if ('top' === $which && $total_pages > 1) {
            $this->screen->render_screen_reader_content('heading_pagination');
        }

        $output = '<span class="displaying-num">' . sprintf(
            /* translators: %s: Number of items. */
            _n('%s item', '%s items', $total_items),
            number_format_i18n($total_items)
        ) . '</span>';

        $current = $this->get_pagenum();
        $removable_query_args = wp_removable_query_args();

        $sanitizedUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_url = sanitize_url($sanitizedUrl);

        $page_links = array();

        $total_pages_before = '<span class="paging-input">';
        $total_pages_after = '</span></span>';

        $disable_first = false;
        $disable_last = false;
        $disable_prev = false;
        $disable_next = false;

        if (1 == $current) {
            $disable_first = true;
            $disable_prev = true;
        }
        if ($total_pages == $current) {
            $disable_last = true;
            $disable_next = true;
        }

        if ($disable_first) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(remove_query_arg('paged', $current_url)),
                __('First page'),
                '&laquo;'
            );
        }

        if ($disable_prev) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', max(1, $current - 1), $current_url)),
                __('Previous page'),
                '&lsaquo;'
            );
        }

        if ('bottom' === $which) {
            $html_current_page = $current;
            $total_pages_before = '<span class="screen-reader-text">' . __('Current Page') . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
        } else {
            $html_current_page = sprintf(
                "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
                '<label for="current-page-selector" class="screen-reader-text">' . __('Current Page') . '</label>',
                $current,
                strlen($total_pages)
            );
        }
        $html_total_pages = sprintf("<span class='total-pages'>%s</span>", number_format_i18n($total_pages));
        $page_links[] = $total_pages_before . sprintf(
            /* translators: 1: Current page, 2: Total pages. */
            _x('%1$s of %2$s', 'paging'),
            $html_current_page,
            $html_total_pages
        ) . $total_pages_after;

        if ($disable_next) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', min($total_pages, $current + 1), $current_url)),
                __('Next page'),
                '&rsaquo;'
            );
        }

        if ($disable_last) {
            $page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
        } else {
            $page_links[] = sprintf(
                "<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
                esc_url(add_query_arg('paged', $total_pages, $current_url)),
                __('Last page'),
                '&raquo;'
            );
        }

        $pagination_links_class = 'pagination-links';
        if ( ! empty($infinite_scroll)) {
            $pagination_links_class .= ' hide-if-js';
        }
        $output .= "\n<span class='$pagination_links_class'>" . implode("\n", $page_links) . '</span>';

        if ($total_pages) {
            $page_class = $total_pages < 2 ? ' one-page' : '';
        } else {
            $page_class = ' no-pages';
        }
        $this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

        echo wp_kses_post($this->_pagination);
    }

    /**
     * Gets a list of sortable columns.
     *
     * The format is:
     * - `'internal-name' => 'orderby'`
     * - `'internal-name' => array( 'orderby', 'asc' )` - The second element sets the initial sorting order.
     * - `'internal-name' => array( 'orderby', true )`  - The second element makes the initial order descending.
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_sortable_columns() {
        $sortable_columns = array(
            'lkn_wcip_id' => array('lkn_wcip_id', false),
            'lkn_wcip_client' => array('lkn_wcip_client', false), 
            'lkn_wcip_status' => array('lkn_wcip_status', false),
            'lkn_wcip_exp_date' => array('lkn_wcip_exp_date', false)
        );

        return $sortable_columns;
    }

    /**
     * Gets the name of the default primary column.
     *
     * @since 4.3.0
     *
     * @return string Name of the default primary column, in this case, an empty string.
     */
    protected function get_default_primary_column_name() {
        $columns = $this->get_columns();
        $column = '';

        if (empty($columns)) {
            return $column;
        }

        // We need a primary defined so responsive views show something,
        // so let's fall back to the first non-checkbox column.
        foreach ($columns as $col => $column_name) {
            if ('cb' === $col) {
                continue;
            }

            $column = $col;

            break;
        }

        return $column;
    }

    /**
     * Public wrapper for WP_List_Table::get_default_primary_column_name().
     *
     * @since 4.4.0
     *
     * @return string Name of the default primary column.
     */
    public function get_primary_column() {
        return $this->get_primary_column_name();
    }

    /**
     * Gets the name of the primary column.
     *
     * @since 4.3.0
     *
     * @return string The name of the primary column.
     */
    protected function get_primary_column_name() {
        $columns = get_column_headers($this->screen);
        $default = $this->get_default_primary_column_name();

        // If the primary column doesn't exist,
        // fall back to the first non-checkbox column.
        if ( ! isset($columns[$default])) {
            $default = self::get_default_primary_column_name();
        }

        /**
         * Filters the name of the primary column for the current list table.
         *
         * @since 4.3.0
         *
         * @param string $default Column name default for the specific list table, e.g. 'name'.
         * @param string $context Screen ID for specific list table, e.g. 'plugins'.
         */
        $column = apply_filters('list_table_primary_column', $default, $this->screen->id);

        if (empty($column) || ! isset($columns[$column])) {
            $column = $default;
        }

        return $column;
    }

    /**
     * Gets a list of all, hidden, and sortable columns, with filter applied.
     *
     * @since 3.1.0
     *
     * @return array
     */
    protected function get_column_info() {
        // $_column_headers is already set / cached.
        if (isset($this->_column_headers) && is_array($this->_column_headers)) {
            /*
             * Backward compatibility for `$_column_headers` format prior to WordPress 4.3.
             *
             * In WordPress 4.3 the primary column name was added as a fourth item in the
             * column headers property. This ensures the primary column name is included
             * in plugins setting the property directly in the three item format.
             */
            $column_headers = array(array(), array(), array(), $this->get_primary_column_name());
            foreach ($this->_column_headers as $key => $value) {
                $column_headers[$key] = $value;
            }

            return $column_headers;
        }

        $columns = get_column_headers($this->screen);
        $hidden = get_hidden_columns($this->screen);

        $sortable_columns = $this->get_sortable_columns();
        /**
         * Filters the list table sortable columns for a specific screen.
         *
         * The dynamic portion of the hook name, `$this->screen->id`, refers
         * to the ID of the current screen.
         *
         * @since 3.1.0
         *
         * @param array $sortable_columns An array of sortable columns.
         */
        $_sortable = apply_filters("manage_{$this->screen->id}_sortable_columns", $sortable_columns);

        $sortable = array();
        foreach ($_sortable as $id => $data) {
            if (empty($data)) {
                continue;
            }

            $data = (array) $data;
            if ( ! isset($data[1])) {
                $data[1] = false;
            }

            $sortable[$id] = $data;
        }

        $primary = $this->get_primary_column_name();
        $this->_column_headers = array($columns, $hidden, $sortable, $primary);

        return $this->_column_headers;
    }

    /**
     * Returns the number of visible columns.
     *
     * @since 3.1.0
     *
     * @return int
     */
    public function get_column_count() {
        list($columns, $hidden) = $this->get_column_info();
        $hidden = array_intersect(array_keys($columns), array_filter($hidden));

        return count($columns) - count($hidden);
    }

    /**
     * Prints column headers, accounting for hidden and sortable columns.
     *
     * @since 3.1.0
     *
     * @param bool $with_id Whether to set the ID attribute or not
     */
    public function print_column_headers($with_id = true): void {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();
        
        if( ! wp_verify_nonce($this->_nonce, 'validate_nonce')) {
            return;
        }

        $sanitizedUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_url = sanitize_url($sanitizedUrl);

        if (isset($_GET['orderby'])) {
            $current_orderby = sanitize_text_field($_GET['orderby']);
        } else {
            $current_orderby = '';
        }

        if (isset($_GET['order']) && 'desc' === sanitize_text_field($_GET['order'])) {
            $current_order = 'desc';
        } else {
            $current_order = 'asc';
        }

        if ( ! empty($columns['cb'])) {
            static $cb_counter = 1;
            $columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . esc_attr($cb_counter) . '">' . __('Select All') . '</label>'
                . '<input id="cb-select-all-' . esc_attr($cb_counter) . '" type="checkbox" />';
            $cb_counter++;
        }

        foreach ($columns as $column_key => $column_display_name) {
            $class = array('manage-column', "column-$column_key");

            if (in_array($column_key, $hidden, true)) {
                $class[] = 'hidden';
            }

            if ('cb' === $column_key) {
                $class[] = 'check-column';
            } elseif (in_array($column_key, array('posts', 'comments', 'links'), true)) {
                $class[] = 'num';
            }

            if ($column_key === $primary) {
                $class[] = 'column-primary';
            }

            if (isset($sortable[$column_key])) {
                list($orderby, $desc_first) = $sortable[$column_key];

                if ($current_orderby === $orderby) {
                    $order = 'asc' === $current_order ? 'desc' : 'asc';

                    $class[] = 'sorted';
                    $class[] = $current_order;
                } else {
                    $order = strtolower($desc_first);

                    if ( ! in_array($order, array('desc', 'asc'), true)) {
                        $order = $desc_first ? 'desc' : 'asc';
                    }

                    $class[] = 'sortable';
                    $class[] = 'desc' === $order ? 'asc' : 'desc';
                }

                $column_display_name = sprintf(
                    '<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>',
                    esc_url(add_query_arg(compact('orderby', 'order'), $current_url)),
                    esc_attr($column_display_name)
                );
            }

            $tag = ('cb' === $column_key) ? 'td' : 'th';
            $scope = ('th' === $tag) ? 'scope="col"' : '';
            $id = $with_id ? "id='" . esc_attr($column_key) . "'" : '';

            if ( ! empty($class)) {
                $class = "class='" . esc_attr(implode(' ', $class)) . "'";
            }

            // All attributes are previously escaped
            // Removendo warning do checker
            $allowed_html = array(
                'thead' => array(),
                'tr' => array(),
                'td' => array(
                    'id' => true,
                    'class' => true
                ),
                'th' => array(
                    'scope' => true,
                    'id' => true,
                    'class' => true
                ),
                'label' => array(
                    'for' => true,
                    'class' => true
                ),
                'input' => array(
                    'id' => true,
                    'type' => true
                ),
                'a' => array(
                    'href' => true
                ),
                'span' => array(
                    'class' => true
                ),
                'div' => array(
                    'class' => true
                )
            );
            echo (wp_kses("<$tag $scope $id $class>$column_display_name</$tag>", $allowed_html));
        }
    }

    /**
     * Displays the table.
     *
     * @since 3.1.0
     */
    public function display(): void {
        $singular = $this->_args['singular'];

        $this->display_tablenav('top');

        $this->screen->render_screen_reader_content('heading_list'); ?>
<table
	class="wp-list-table <?php echo esc_attr(implode(' ', $this->get_table_classes())); ?>">
	<thead>
		<tr>
			<?php $this->print_column_headers(); ?>
		</tr>
	</thead>

	<tbody id="the-list" <?php
                if ($singular) {
                    echo " data-wp-lists='list:" . esc_attr($singular) . "'";
                } ?>
		>
		<?php $this->display_rows_or_placeholder(); ?>
	</tbody>

	<tfoot>
		<tr>
			<?php $this->print_column_headers(false); ?>
		</tr>
	</tfoot>

</table>
<?php
        $this->display_tablenav('bottom');
    }

    /**
     * Gets a list of CSS classes for the WP_List_Table table tag.
     *
     * @since 3.1.0
     *
     * @return string[] Array of CSS classes for the table tag.
     */
    protected function get_table_classes() {
        $mode = get_user_setting('posts_list_mode', 'list');

        $mode_class = esc_attr('table-view-' . $mode);

        return array('widefat', 'fixed', 'striped', $mode_class, $this->_args['plural']);
    }

    /**
     * Generates the table navigation above or below the table
     *
     * @since 3.1.0
     * @param string $which
     */
    protected function display_tablenav($which): void {
        if ('top' === $which) {
            wp_nonce_field('bulk-' . $this->_args['plural']);
        } ?>
<div class="tablenav <?php echo esc_attr($which); ?>">

	<?php if ($this->has_items()) : ?>
	<div class="alignleft actions bulkactions">
		<?php $this->bulk_actions($which); ?>
	</div>
	<?php
	endif;
        $this->extra_tablenav($which);
        $this->pagination($which); ?>

	<br class="clear" />
</div>
<?php
    }

    /**
     * Extra controls to be displayed between bulk actions and pagination.
     *
     * @since 3.1.0
     *
     * @param string $which
     */
    protected function extra_tablenav($which): void {
    }

    /**
     * Generates the tbody element for the list table.
     *
     * @since 3.1.0
     */
    public function display_rows_or_placeholder(): void {
        if ($this->has_items()) {
            $this->display_rows();
        } else {
            echo '<tr class="no-items"><td class="colspanchange" colspan="' . esc_html($this->get_column_count()) . '">';
            echo esc_html($this->no_items());
            echo '</td></tr>';
        }
    }

    /**
     * Generates the table rows.
     *
     * @since 3.1.0
     */
    public function display_rows(): void {
        foreach ($this->items as $item) {
            $this->single_row($item);
        }
    }

    /**
     * Generates content for a single row of the table.
     *
     * @since 3.1.0
     *
     * @param object|array $item The current item
     */
    public function single_row($item): void {
        echo '<tr>';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * Generates the columns for a single row of the table.
     *
     * @since 3.1.0
     *
     * @param object|array $item The current item.
     */
    protected function single_row_columns($item): void {
        list($columns, $hidden, $sortable, $primary) = $this->get_column_info();

        foreach ($columns as $column_name => $column_display_name) {
            $classes = "$column_name column-$column_name";
            if ($primary === $column_name) {
                $classes .= ' has-row-actions column-primary';
            }

            if (in_array($column_name, $hidden, true)) {
                $classes .= ' hidden';
            }

            // Comments column uses HTML in the display name with screen reader text.
            // Strip tags to get closer to a user-friendly string.
            $data = 'data-colname="' . esc_attr(wp_strip_all_tags($column_display_name)) . '"';

            $attributes = "class='$classes' $data";
            if ('cb' === $column_name) {
                echo '<th scope="row" class="check-column">';
                echo wp_kses($this->column_cb($item), array(
                    'input' => array(
                        'type' => true,
                        'name' => true,
                        'class' => true,
                        'value' => true,
                    ),
                ));
                echo '</th>';
            } elseif ((method_exists($this, '_column_' . $column_name))) {
                echo esc_html(
                    call_user_func(
                        array($this, '_column_' . $column_name),
                        $item,
                        esc_attr($classes),
                        $data,
                        $primary
                    )
                );
            } elseif (method_exists($this, 'column_' . $column_name)) {
                echo "<td " . esc_attr($attributes) . ">";
                echo esc_attr(call_user_func(array($this, 'column_' . $column_name), $item));
                echo wp_kses_post($this->handle_row_actions($item, $column_name, $primary));
                echo '</td>';
            } else {
                echo "<td " . esc_attr($attributes) . ">";
                echo esc_html($this->column_default($item, $column_name));
                echo wp_kses_post($this->handle_row_actions($item, $column_name, $primary));
                echo '</td>';
            }
        }
    }

    /**
     * Handles an incoming ajax request (called from admin-ajax.php)
     *
     * @since 3.1.0
     */
    public function ajax_response(): void {
        $this->prepare_items(wp_create_nonce('validate_nonce'));

        if( ! wp_verify_nonce($this->_nonce, 'validate_nonce')) {
            return;
        }

        ob_start();
        if ( ! empty($_REQUEST['no_placeholder'])) {
            $this->display_rows();
        } else {
            $this->display_rows_or_placeholder();
        }

        $rows = ob_get_clean();

        $response = array('rows' => $rows);

        if (isset($this->_pagination_args['total_items'])) {
            $response['total_items_i18n'] = sprintf(
                /* translators: Number of items. */
                _n('%s item', '%s items', $this->_pagination_args['total_items']),
                number_format_i18n($this->_pagination_args['total_items'])
            );
        }
        if (isset($this->_pagination_args['total_pages'])) {
            $response['total_pages'] = $this->_pagination_args['total_pages'];
            $response['total_pages_i18n'] = number_format_i18n($this->_pagination_args['total_pages']);
        }

        die(wp_json_encode($response));
    }

    /**
     * Prepares the list of items for displaying.
     *
     * @return void
     */
    public function prepare_items($validate_nonce, $showSubscriptions = false): void {
        if(wp_verify_nonce($validate_nonce, 'validate_nonce')) {
            $this->_nonce = $validate_nonce;
            $order_by = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : '';
            $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : '';
            $search_term = isset($_POST['s']) ? sanitize_text_field($_POST['s']) : '';
            $invoiceList = get_option('lkn_wcip_invoices', array());
    
            // Deletes invoices that have no order.
            $invoicesWithExistingOrder = array_filter(
                $invoiceList,
                function ($invoiceId): bool {
                    $orderExists = wc_get_order($invoiceId) !== false;
    
                    return $orderExists;
                }
            );
    
            if (count($invoiceList) !== count($invoicesWithExistingOrder)) {
                $invoiceList = $invoicesWithExistingOrder;
                update_option('lkn_wcip_invoices', $invoiceList);
            }
    
            $per_page = 10;
            $current_page = $this->get_pagenum();
            $total_items = count($invoiceList);
    
            // only ncessary because we have sample data
            $found_data = $this->lkn_wcip_list_table_data($order_by, $order, $search_term, $invoiceList, $showSubscriptions);
            $this->items = array_slice($found_data, (($current_page - 1) * $per_page), $per_page);
    
            $this->set_pagination_args(array(
                'total_items' => $total_items,  //WE have to calculate the total number of items
                'per_page' => $per_page,     //WE have to determine how many items to show on a page
            ));
    
            $lkn_wcip_columns = $this->get_columns();
            $lkn_wcip_hidden = $this->get_hidden_columns();
            $ldul_sortable = $this->get_sortable_columns();
    
            $this->_column_headers = array($lkn_wcip_columns, $lkn_wcip_hidden, $ldul_sortable);
            $this->proccess_bulk_action();
        }
    }

    /**
     * Wp list table bulk actions
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete'),
        );
    }

    /**
     * WP list table row actions
     *
     * @param  array $item
     * @param  string $column_name
     * @param  string $primary
     *
     * @return void
     */
    public function handle_row_actions($item, $column_name, $primary) {
        if ($primary !== $column_name) {
            return '';
        }
        $orderId = $item['lkn_wcip_id'];

        $order = wc_get_order($orderId);
        
        // Pega o id da fatura atual
        $invoiceId = $order->get_meta('lkn_invoice_id');
        $invoice = wc_get_order($invoiceId);
        
        $editUrl = home_url('wp-admin/admin.php?page=edit-invoice&invoice=' . $orderId);
        $paymentUrl = $order->get_checkout_payment_url();

        if($order->get_meta('lkn_is_subscription')) {
            $editUrl = home_url('wp-admin/admin.php?page=edit-subscription&invoice=' . $orderId);
            //Evita erros caso a primeira fatura ainda não foi gerada
            if($invoice) {
                $paymentUrl = $invoice->get_checkout_payment_url();
                $orderId = $invoiceId;
            }
        }
        
        $action = array();
        $action['edit'] = '<a href="' . $editUrl . '">' . __('Edit') . '</a>';
        $action['payment'] = '<a href="' . $paymentUrl . '" target="_blank">' . __('Payment link', 'wc-invoice-payment') . '</a>';
        $action['generate_pdf'] = "<a class='lkn_wcip_generate_pdf_btn' data-invoice-id='$orderId' href='#'>" . __('Download invoice', 'wc-invoice-payment') . '</a>';

        return $this->row_actions($action);
    }

    /**
     * Display columns data
     *
     * @param  string $order_by
     * @param  string $order
     * @param  string $search_term
     * @param  array $invoiceList
     *
     * @return array
     */
    public function lkn_wcip_list_table_data($order_by = '', $order = '', $search_term = '', $invoiceList, $showSubscriptions) {
        ?>
<section style="margin: 20px 0 0 0; ">
    <?php
        if (!$showSubscriptions) {
            $editUrl = home_url('wp-admin/admin.php?page=new-invoice');
            ?>
            <div>
                <a href="<?php echo $editUrl; ?>" class="button button-primary"><?php echo __('Add invoice', 'wc-invoice-payment'); ?></a>
            </div>
            <?php
        }else{
            $editUrl = home_url('wp-admin/admin.php?page=new-invoice&invoiceChecked=\"active\"');
            ?>
            <div>
                <a href="<?php echo $editUrl; ?>" class="button button-primary"><?php echo __('Add subscription', 'wc-invoice-payment'); ?></a>
            </div>
            <?php
        }
    ?>
    
	<?php
        $data_array = array();

        if ($invoiceList) {
            $dateFormat = get_option('date_format');

            foreach ($invoiceList as $invoiceId) {
                $invoice = wc_get_order($invoiceId);
                //Verifica se a opção desejada é igual ao valor que define se é uma assinatura
                if($invoice->get_meta('lkn_is_subscription') == $showSubscriptions) {
                    $dueDate = $invoice->get_meta('lkn_exp_date');
                    $dueDate = empty($dueDate) ? '-' : gmdate($dateFormat, strtotime($dueDate));
                    $iniDate = $invoice->get_meta('lkn_ini_date');
                    $iniDate = empty($iniDate) ? '-' : gmdate($dateFormat, strtotime($iniDate));
                    
                    if($invoice->get_meta('lkn_subscription_id') != ""){
                        $subscription = wc_get_order($invoice->get_meta('lkn_subscription_id'));
                        $subscriptionInitialLimit = $invoice->get_meta('lkn_current_limit');
                        $subscriptionLimit = $subscription->get_meta('lkn_wcip_subscription_limit');
                        $fromSubscription = $subscriptionInitialLimit . '/' . $subscriptionLimit;
                        if(!$subscriptionLimit){
                            $fromSubscription = __('Subscription', 'wc-invoice-payment');
                        }
                    }
                                       
                    $data_array[] = array(
                        'lkn_wcip_id' => $invoiceId,
                        'lkn_wcip_client' => $invoice->get_billing_first_name(),
                        'lkn_wcip_status' => ucfirst(wc_get_order_status_name($invoice->get_status())),
                        'lkn_wcip_total_price' => get_woocommerce_currency_symbol($invoice->get_currency()) . ' ' . number_format($invoice->get_total(), wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator()),
                        'lkn_wcip_from_subscription' => $invoice->get_meta('lkn_subscription_id') == "" ? '-' : $fromSubscription,
                        'lkn_wcip_exp_date' => $dueDate,
                        'lkn_wcip_ini_date' => $iniDate
                    );
                }
            }
        } ?>
</section><?php
        usort($data_array, array($this, 'usort_reorder'));

        return $data_array;
    }

    /**
     * Gets a list of all, hidden and sortable columns
     *
     * @return array
     */
    public function get_hidden_columns() {
        return array();
    }

    /**
     * Gets a list of columns.
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'cb' => '<input type="checkbox" class="lkn-wcip-selected" />',
            'lkn_wcip_id' => __('Invoice', 'wc-invoice-payment'),
            'lkn_wcip_client' => __('Name', 'wc-invoice-payment'),
            'lkn_wcip_status' => __('Payment status', 'wc-invoice-payment'),
            'lkn_wcip_total_price' => __('Total', 'wc-invoice-payment'),
            'lkn_wcip_from_subscription' => __('Subscription', 'wc-invoice-payment'),
            'lkn_wcip_ini_date' => __('Start date', 'wc-invoice-payment'),
            'lkn_wcip_exp_date' => __('Due date', 'wc-invoice-payment'),
        );

        $page = isset($_GET['page']) ? $_GET['page'] : null;
        
        if($page == 'wc-subscription-payment'){
            unset($columns['lkn_wcip_from_subscription']);
        }

        return $columns;
    }

    /**
     * Return column value
     *
     * @return string
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'lkn_wcip_id':
            case 'lkn_wcip_client':
            case 'lkn_wcip_status':
            case 'lkn_wcip_total_price':
            case 'lkn_wcip_from_subscription':
            case 'lkn_wcip_exp_date':
            case 'lkn_wcip_ini_date':
                return $item[$column_name];
            default:
                return 'no list found';
        }
    }

    /**
     * Rows check box
     *
     * @return string
     */
    public function column_cb($items) {
        $top_checkbox = '<input type="checkbox" name="invoices[]" class="lkn-wcip-selected" value="' . esc_attr($items['lkn_wcip_id']) . '" />';

        return $top_checkbox;
    }

    /**
     * Sort an array and reorder in ascending or descending
     *
     * @param  array $a
     * @param  array $b
     *
     * @return int $result
     */
    public function usort_reorder($a, $b) {
        if(wp_verify_nonce($this->_nonce, 'validate_nonce')) {
            // If no sort, default to title
            $orderby = ( ! empty($_GET['orderby'])) ? sanitize_text_field($_GET['orderby']) : 'lkn_wcip_id';
            // If no order, default to desc
            $order = ( ! empty($_GET['order'])) ? sanitize_text_field($_GET['order']) : 'desc';
            // Determine sort order
            $result = strcmp($a[$orderby], $b[$orderby]);
            // Send final sort direction to usort
            return ('asc' === $order) ? $result : -$result;
        }
    }

    /**
     * Handles bulk actions requests
     *
     * @return void
     */
    public function proccess_bulk_action(): void {
        if ('delete' === $this->current_action() && wp_verify_nonce( $_POST['nonce_action_field'], 'nonce_action' )) {
            $invoicesDelete = array_map( 'sanitize_text_field', $_POST['invoices'] );
            $invoices = get_option('lkn_wcip_invoices');

            $invoices = array_diff($invoices, $invoicesDelete);

            for ($c = 0; $c < count($invoicesDelete); $c++) {
                $order = wc_get_order($invoicesDelete[$c]);
                $order->delete();

                // Excluindo evento cron
                $invoice_id = $invoicesDelete[$c];
                $scheduled_events = _get_cron_array();
                // verifica todos os eventos agendados
                foreach ($scheduled_events as $timestamp => $cron_events) {
                    foreach ($cron_events as $hook => $events) {
                        foreach ($events as $event) {
                            // Verifique se o evento está associado ao seu gancho (hook)
                            if ('generate_invoice_event' === $hook || 'lkn_wcip_cron_hook' === $hook) {
                                // Verifique se os argumentos do evento contêm o ID da ordem que você deseja remover
                                $event_args = $event['args'];
                                if (is_array($event_args) && in_array($invoice_id, $event_args)) {
                                    // Remova o evento do WP Cron
                                    wp_unschedule_event($timestamp, $hook, $event_args);
                                }
                            }
                        }
                    }
                }
            }
            update_option('lkn_wcip_invoices', $invoices);

            wp_redirect(sanitize_url($_SERVER['HTTP_REFERER']));
        }
    }
}
?>