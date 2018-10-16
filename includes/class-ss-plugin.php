<?php
namespace Simply_Static;

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

/**
 * The core plugin class
 */
class Plugin
{
    /**
     * Plugin version
     * @var string
     */
    const VERSION = '2.0.0';

    /**
     * The slug of the plugin; used in actions, filters, i18n, table names, etc.
     * @var string
     */
    const SLUG = 'simply-static-github-sync'; // keep it short; stick to alphas & dashes

    /**
     * Singleton instance
     * @var Simply_Static
     */
    protected static $instance = null;

    /**
     * An instance of the options structure containing all options for this plugin
     * @var Simply_Static\Options
     */
    protected $options = null;

    /**
     * View object
     * @var Simply_Static\View
     */
    protected $view = null;

    /**
     * Archive creation process
     * @var Simply_Static\Archive_Creation_Job
     */
    protected $archive_creation_job = null;

    /**
     * Current page name
     * @var string
     */
    protected $current_page = '';

    /**
     * Disable usage of "new"
     * @return void
     */
    protected function __construct()
    {
    }

    /**
     * Disable cloning of the class
     * @return void
     */
    protected function __clone()
    {
    }

    /**
     * Disable unserializing of the class
     * @return void
     */
    public function __wakeup()
    {
    }

    /**
     * Return an instance of the Simply Static Github Sync plugin
     * @return Simply_Static
     */
    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->includes();

            // Check for pending file download
            add_action('plugins_loaded', array( self::$instance, 'download_file' ));
            // Load the text domain for i18n
            add_action('plugins_loaded', array( self::$instance, 'load_textdomain' ));
            // Enqueue admin styles
            add_action('admin_enqueue_scripts', array( self::$instance, 'enqueue_admin_styles' ));
            // Enqueue admin scripts
            add_action('admin_enqueue_scripts', array( self::$instance, 'enqueue_admin_scripts' ));
            // Add the options page and menu item.
            add_action('admin_menu', array( self::$instance, 'add_plugin_admin_menu' ), 2);

            // Handle AJAX requests
            add_action('wp_ajax_static_archive_action', array( self::$instance, 'static_archive_action' ));
            add_action('wp_ajax_render_export_log', array( self::$instance, 'render_export_log' ));
            add_action('wp_ajax_render_activity_log', array( self::$instance, 'render_activity_log' ));

            // Filters
            add_filter('wp_mail_content_type', array( self::$instance, 'filter_wp_mail_content_type' ));
            add_filter('admin_footer_text', array( self::$instance, 'filter_admin_footer_text' ), 15);
            add_filter('update_footer', array( self::$instance, 'filter_update_footer' ), 15);

            add_filter('simplystatic.archive_creation_job.task_list', array( self::$instance, 'filter_task_list' ), 10, 2);

            self::$instance->options = Options::instance();
            self::$instance->view = new View();
            self::$instance->archive_creation_job = new Archive_Creation_Job();

            $page = isset($_GET['page']) ? $_GET['page'] : '';
            self::$instance->current_page = $page;

            Upgrade_Handler::run();
        }

        return self::$instance;
    }

    /**
     * Include required files
     * @return void
     */
    private function includes()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/shims.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/phpuri.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/PhpSimple/HtmlDomParser.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/libraries/wp-background-processing/wp-background-processing.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-options.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-view.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-url-extractor.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-url-fetcher.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-archive-creation-job.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-setup-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-fetch-urls-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-transfer-files-locally-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-create-zip-archive.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-wrapup-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-cancel-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/tasks/class-ss-github-sync-task.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-query.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/models/class-ss-model.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/models/class-ss-page.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-diagnostic.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-sql-permissions.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-upgrade-handler.php';
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-ss-util.php';
    }

    /**
     * Enqueue admin-specific style sheets for this plugin's admin pages only
     * @return void
     */
    public function enqueue_admin_styles()
    {
        // Plugin admin CSS. Tack on plugin version.
        wp_enqueue_style(self::SLUG . '-admin-styles', plugin_dir_url(dirname(__FILE__)) . 'css/admin.css', array(), self::VERSION);
    }

    /**
     * Enqueue admin-specific javascript files for this plugin's admin pages only
     * @return void
     */
    public function enqueue_admin_scripts()
    {
        // Plugin admin JS. Tack on plugin version.
        if ($this->current_page === 'simply-static-github-sync') {
            wp_enqueue_script(self::SLUG . '-generate-styles', plugin_dir_url(dirname(__FILE__)) . 'js/admin-generate.js', array(), self::VERSION);
        }

        if ($this->current_page === 'simply-static-github-sync_settings') {
            wp_enqueue_script(self::SLUG . '-settings-styles', plugin_dir_url(dirname(__FILE__)) . 'js/admin-settings.js', array(), self::VERSION);
        }
    }

    /**
     * Register the administration menu for this plugin into the WordPress Dashboard menu.
     * @return void
     */
    public function add_plugin_admin_menu()
    {

        // Add main menu item
        add_menu_page(
            __('Simply Static Github Sync', 'simply-static-github-sync'),
            __('Simply Static Github Sync', 'simply-static-github-sync'),
            'edit_posts',
            self::SLUG,
            array( self::$instance, 'display_generate_page' ),
            'dashicons-media-text'
        );

        add_submenu_page(
            self::SLUG,
            __('Generate Static Site', 'simply-static-github-sync'),
            __('Generate', 'simply-static-github-sync'),
            'edit_posts',
            self::SLUG,
            array( self::$instance, 'display_generate_page' )
        );

        add_submenu_page(
            self::SLUG,
            __('Simply Static Github Sync Settings', 'simply-static-github-sync'),
            __('Settings', 'simply-static-github-sync'),
            'manage_options',
            self::SLUG . '_settings',
            array( self::$instance, 'display_settings_page' )
        );

        add_submenu_page(
            self::SLUG,
            __('Simply Static Github Sync Diagnostics', 'simply-static-github-sync'),
            __('Diagnostics', 'simply-static-github-sync'),
            'manage_options',
            self::SLUG . '_diagnostics',
            array( self::$instance, 'display_diagnostics_page' )
        );
    }

    /**
     * Handle requests for creating a static archive and send a response via ajax
     * @return void
     */
    public function static_archive_action()
    {
        check_ajax_referer('simply-static-github-sync_generate');
        if (! current_user_can('edit_posts')) {
            die(__('Not permitted', 'simply-static-github-sync'));
        }

        $action = $_POST['perform'];

        if ($action === 'start') {
            Util::delete_debug_log();
            Util::debug_log("Received request to start generating a static archive");
            $this->archive_creation_job->start();
        } elseif ($action === 'cancel') {
            Util::debug_log("Received request to cancel static archive generation");
            $this->archive_creation_job->cancel();
        }

        $this->send_json_response_for_static_archive($action);
    }

    /**
     * Render json+html for response to static archive creation
     * @return void
     */
    public function send_json_response_for_static_archive($action)
    {
        $done = $this->archive_creation_job->is_job_done();
        $current_task = $this->archive_creation_job->get_current_task();

        $activity_log_html = $this->view
            ->set_template('_activity_log')
            ->assign('status_messages', $this->options->get('archive_status_messages'))
            ->render_to_string();

        // send json response and die()
        wp_send_json(array(
            'action' => $action,
            'activity_log_html' => $activity_log_html,
            'done' => $done // $done
        ));
    }

    /**
     * Render the activity log and send it via ajax
     * @return void
     */
    public function render_activity_log()
    {
        check_ajax_referer('simply-static-github-sync_generate');
        if (! current_user_can('edit_posts')) {
            die(__('Not permitted', 'simply-static-github-sync'));
        }

        // $archive_manager = new Archive_Manager();

        $content = $this->view
            ->set_template('_activity_log')
            ->assign('status_messages', $this->options->get('archive_status_messages'))
            ->render_to_string();

        // send json response and die()
        wp_send_json(array(
            'html' => $content
        ));
    }

    /**
     * Render the export log and send it via ajax
     * @return void
     */
    public function render_export_log()
    {
        check_ajax_referer('simply-static-github-sync_generate');
        if (! current_user_can('edit_posts')) {
            die(__('Not permitted', 'simply-static-github-sync'));
        }

        $per_page = $_POST['per_page'];
        $current_page = $_POST['page'];
        $offset = (intval($current_page) - 1) * intval($per_page);

        $static_pages = Page::query()
            ->limit($per_page)
            ->offset($offset)
            ->find();
        $http_status_codes = Page::get_http_status_codes_summary();
        $total_static_pages = array_sum(array_values($http_status_codes));
        $total_pages = ceil($total_static_pages / $per_page);

        $content = $this->view
            ->set_template('_export_log')
            ->assign('static_pages', $static_pages)
            ->assign('http_status_codes', $http_status_codes)
            ->assign('current_page', $current_page)
            ->assign('total_pages', $total_pages)
            ->assign('total_static_pages', $total_static_pages)
            ->render_to_string();

        // send json response and die()
        wp_send_json(array(
            'html' => $content
        ));
    }

    /**
     * Render the page for generating a static site
     * @return void
     */
    public function display_generate_page()
    {
        $done = $this->archive_creation_job->is_job_done();

        $this->view
            ->set_layout('admin')
            ->set_template('generate')
            ->assign('archive_generation_done', $done)
            ->render();
    }

    /**
     * Render the options page
     * @return void
     */
    public function display_settings_page()
    {
        if (isset($_POST['_settings'])) {
            $this->save_options();
        } elseif (isset($_POST['_reset'])) {
            $this->reset_plugin();
        }

        $this->view
            ->set_layout('admin')
            ->set_template('settings')
            ->assign('origin_scheme', Util::origin_scheme())
            ->assign('origin_host', Util::origin_host())
            ->assign('destination_scheme', $this->options->get('destination_scheme'))
            ->assign('destination_host', $this->options->get('destination_host'))
            ->assign('temp_files_dir', $this->options->get('temp_files_dir'))
            ->assign('additional_urls', $this->options->get('additional_urls'))
            ->assign('additional_files', $this->options->get('additional_files'))
            ->assign('urls_to_exclude', $this->options->get('urls_to_exclude'))
            ->assign('delivery_method', $this->options->get('delivery_method'))
            ->assign('local_dir', $this->options->get('local_dir'))
            ->assign('delete_temp_files', $this->options->get('delete_temp_files'))
            ->assign('destination_url_type', $this->options->get('destination_url_type'))
            ->assign('relative_path', $this->options->get('relative_path'))
            ->assign('github_user', $this->options->get('github_user'))
            ->assign('github_email', $this->options->get('github_email'))
            ->assign('github_access_token', $this->options->get('github_access_token'))
            ->assign('github_repository', $this->options->get('github_repository'))
            ->assign('github_branch', $this->options->get('github_branch'))
            ->render();
    }

    /**
     * Save the options from the options page
     * @return void
     */
    public function save_options()
    {
        check_admin_referer('simply-static-github-sync_settings');

        // Set destination url type / scheme / host
        $destination_url_type = $this->fetch_post_value('destination_url_type');

        if ($destination_url_type == 'offline') {
            $destination_scheme = '';
            $destination_host = '.';
        } elseif ($destination_url_type == 'relative') {
            $destination_scheme = '';
            $destination_host = '';
        } else {
            $destination_scheme = $this->fetch_post_value('destination_scheme');
            $destination_host = untrailingslashit($this->fetch_post_value('destination_host'));
        }

        // Set URLs to exclude
        $urls_to_exclude = array();
        $excludables = $this->fetch_post_array_value('excludable');

        foreach ($excludables as $excludable) {
            $url = trim($excludable['url']);
            // excluding the template row (always has a blank url) and any rows
            // that the user didn't fill in
            if ($url !== '') {
                array_push($urls_to_exclude, array(
                    'url' => $url,
                    'do_not_save' => $excludable['do_not_save'],
                    'do_not_follow' => $excludable['do_not_follow'],
                ));
            }
        }

        // Set relative path
        $relative_path = $this->fetch_post_value('relative_path');
        $relative_path = untrailingslashit(Util::add_leading_slash($relative_path));

        // Save settings
        $this->options
            ->set('destination_scheme', $destination_scheme)
            ->set('destination_host', $destination_host)
            ->set('temp_files_dir', Util::trailingslashit_unless_blank($this->fetch_post_value('temp_files_dir')))
            ->set('additional_urls', $this->fetch_post_value('additional_urls'))
            ->set('additional_files', $this->fetch_post_value('additional_files'))
            ->set('urls_to_exclude', $urls_to_exclude)
            ->set('delivery_method', $this->fetch_post_value('delivery_method'))
            ->set('local_dir', Util::trailingslashit_unless_blank($this->fetch_post_value('local_dir')))
            ->set('delete_temp_files', $this->fetch_post_value('delete_temp_files'))
            ->set('destination_url_type', $destination_url_type)
            ->set('github_user', $this->fetch_post_value('github_user'))
            ->set('github_email', $this->fetch_post_value('github_email'))
            ->set('github_access_token', $this->fetch_post_value('github_access_token'))
            ->set('github_repository', $this->fetch_post_value('github_repository'))
            ->set('github_branch', $this->fetch_post_value('github_branch'))
            ->save();

        $message = __('Your changes have been saved.', 'simply-static-github-sync');
        $this->view->add_flash('updated', $message);
    }

    /**
     * Render the diagnostics page
     * @return void
     */
    public function display_diagnostics_page()
    {
        if (isset($_POST['_diagnostics'])) {
            $this->save_diagnostics_options();
        } elseif (isset($_POST['_email_debug_log'])) {
            $this->email_debug_log();
        }

        $debug_file = Util::get_debug_log_filename();
        $debug_file_exists = file_exists($debug_file);
        $debug_file_url = plugin_dir_url(dirname(__FILE__)) . basename($debug_file);

        $diagnostic = new Diagnostic();
        $results = $diagnostic->results;

        $this->view
            ->set_layout('admin')
            ->set_template('diagnostics')
            ->assign('debugging_mode', $this->options->get('debugging_mode'))
            ->assign('debug_file_exists', $debug_file_exists)
            ->assign('debug_file_url', $debug_file_url)
            ->assign('results', $results)
            ->render();
    }

    /**
     * Save the options from the diagnostics page
     * @return void
     */
    public function save_diagnostics_options()
    {
        check_admin_referer('simply-static-github-sync_diagnostics');

        // Save settings
        $this->options
            ->set('debugging_mode', $this->fetch_post_value('debugging_mode'))
            ->save();

        $message = __('Your changes have been saved.', 'simply-static-github-sync');
        $this->view->add_flash('updated', $message);
    }

    /**
     * Send the debug log to the desired email address
     * @return void
     */
    public function email_debug_log()
    {
        check_admin_referer('simply-static-github-sync_email_debug_log');

        $debug_file = Util::get_debug_log_filename();
        if (file_exists($debug_file)) {
            $email = $this->fetch_post_value('email_address');

            $zip_filename = $debug_file . '.zip';
            $zip_archive = new \PclZip($zip_filename);

            if ($zip_archive->create($debug_file) === 0) {
                $message = __('Unable to create a ZIP of the debug log.', 'simply-static-github-sync');
                $this->view->add_flash('error', $message);
            } else {
                $content = '';

                ob_start();
                phpinfo();
                $phpinfo = ob_get_contents();
                ob_get_clean();

                $content .= $phpinfo;

                if (wp_mail($email, 'Simply Static Github Sync Debug Log', $content, '', $zip_filename) === true) {
                    $message = sprintf(__('Debug log successfully sent to: %s', 'simply-static-github-sync'), $email);
                    $this->view->add_flash('updated', $message);
                } else {
                    $message = __('We encountered an error when attempting to send out the debug log.', 'simply-static-github-sync');
                    $this->view->add_flash('error', $message);
                }

                unlink($zip_filename);
            }
        }
    }

    /**
     * Fetch a POST variable by name and sanitize it
     * @param  string $variable_name Name of the POST variable to fetch
     * @return string                Value of the POST variable
     */
    public function fetch_post_value($variable_name)
    {
        $input = filter_input(INPUT_POST, $variable_name);
        // using explode/implode to keep linebreaks in text areas
        return implode("\n", array_map('sanitize_text_field', explode("\n", $input)));
    }

    /**
     * Fetch a POST array variable by name and sanitize it
     * @param  string $variable_name Name of the POST variable to fetch
     * @return string                Value of the POST variable
     */
    public function fetch_post_array_value($variable_name)
    {
        return filter_input(INPUT_POST, $variable_name, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
    }

    /**
     * Reset the plugin back to its original state
     * @return void
     */
    public function reset_plugin()
    {
        check_admin_referer('simply-static-github-sync_reset');

        // Delete Simply Static Github Sync's settings
        delete_option('simply-static-github-sync');
        // Drop the Pages table
        Page::drop_table();
        // Set up a new instance of Options
        $this->options = Options::reinstance();
        // Set up default options and re-create the Pages table
        Upgrade_Handler::run();

        $message = __('Plugin reset complete.', 'simply-static-github-sync');
        $this->view->add_flash('updated', $message);
    }

    /**
     * Loads the plugin language files
     * @return void
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            self::SLUG,
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    /**
     * Set wp_mail to send out html emails
     * @return string
     */
    public function filter_wp_mail_content_type()
    {
        return 'text/html';
    }

    /**
     * Display support text in footer when on plugin page
     * @return string
     */
    public function filter_admin_footer_text($text)
    {
        if (! self::$instance->in_plugin()) {
            return $text;
        }

        $contact_support = '<a target="_blank" href="https://wordpress.org/support/plugin/simply-static-github-sync#new-post">'
            . __('Contact Support', 'simply-static-github-sync') . '</a> | ';
        $add_your_rating = str_replace(
                '[stars]',
                '<a target="_blank" href="https://wordpress.org/support/plugin/simply-static-github-sync/reviews/#new-post" >&#9733;&#9733;&#9733;&#9733;&#9733;</a>',
                __('Enjoying Simply Static Github Sync? Add your [stars] on wordpress.org.', 'simply-static-github-sync')
            );

        return $contact_support . $add_your_rating;
    }

    /**
     * Display plugin version in footer when on plugin page
     * @return string
     */
    public function filter_update_footer($text)
    {
        if (! self::$instance->in_plugin()) {
            return $text;
        }

        $version_text = __('Simply Static Github Sync Version') . ': <a title="' . __('View what changed in this version', 'simply-static-github-sync') . '" href="https://wordpress.org/plugins/simply-static-github-sync/changelog/">' . self::VERSION . '</a>';

        return $version_text;
    }

    /**
     * Return the task list for the Archive Creation Job to process
     * @param  array  $task_list       The list of tasks to process
     * @param  string $delivery_method The method of delivering static files
     * @return array                   The list of tasks to process
     */
    public function filter_task_list($task_list, $delivery_method)
    {
        array_push($task_list, 'setup', 'fetch_urls');
        if ($delivery_method === 'zip') {
            array_push($task_list, 'create_zip_archive');
        } elseif ($delivery_method === 'local') {
            array_push($task_list, 'transfer_files_locally');
        }
        array_push($task_list, 'wrapup');
        array_push($task_list, 'github_sync');

        return $task_list;
    }

    /**
     * Check for a pending file download; prompt user to download file
     * @return null
     */
    public function download_file()
    {
        $file_name = isset($_GET[ self::SLUG . '_zip_download' ]) ? $_GET[ self::SLUG . '_zip_download' ] : null;
        if ($file_name) {
            if (! current_user_can('edit_posts')) {
                die(__('Not permitted', 'simply-static-github-sync'));
            }

            // Don't allow path traversal
            if (strpos($file_name, '../') !== false) {
                exit('Invalid Request');
            }

            // File must exist
            $file_path = path_join(self::$instance->options->get('temp_files_dir'), $file_name);
            if (! file_exists($file_path)) {
                exit('Files does not exist');
            }

            // Send file
            header('Content-Description: File Transfer');
            header('Content-Disposition: attachment; filename=' . $file_name);
            header('Content-Type: application/zip, application/octet-stream; charset=' . get_option('blog_charset'), true);
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($file_path);
            exit();
        }
    }

    /**
     * Are we currently within the plugin?
     * @return boolean true if we're within the plugin, false otherwise
     */
    public function in_plugin()
    {
        return strpos($this->current_page, self::SLUG) === 0;
    }

    /**
     * Return whether or not debug mode is on
     * @return boolean Debug mode enabled?
     */
    public function debug_on()
    {
        return $this->options->get('debugging_mode') === '1';
    }
}
