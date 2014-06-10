<?php
namespace Rosio\WordPressTestingHarness;

class Bootstrapper
{
	private $bootstrapLocation = '';
	private $isMultisite = false;
	private $adminPassword = '';

	private $settings;

	public function __construct (array $settings = array())
	{
		$settings = array_merge(array(
			'bootstrap-path' => null,

			'db-name' => 'wp_test',
			'db-user' => 'root',
			'db-pass' => 'root',
			'db-host' => 'localhost',
			'db-prefix' => 'wptests_',
			'db-charset' => '',
			'db-collate' => '',
			'wplang' => '',

			'wordpress-path' => __DIR__ . '/../../../wordpress/wordpress',
			'domain' => 'localhost',
			'admin-email' => 'admin@example.org',
			'blog-title' => 'Tests',
			'admin-password' => 'admin',

			'always-reinstall' => false,

			'php-binary' => 'php',
			'testdata-path' => __DIR__ . '/../data',

		), $settings);

		$this->settings = $settings;

		foreach ($settings as $name => $value)
		{
			switch ($name)
			{
				case 'db-name':
					define('DB_NAME', $value);
					break;
				case 'db-user':
					define('DB_USER', $value);
					break;
				case 'db-pass':
					define('DB_PASSWORD', $value);
					break;
				case 'db-host':
					define('DB_HOST', $value);
					break;
				case 'db-charset':
					define('DB_CHARSET', $value);
					break;
				case 'db-collate':
					define('DB_COLLATE', $value);
					break;
				case 'domain':
					define('WP_TESTS_DOMAIN', $value);
					break;
				case 'admin-email':
					define('WP_TESTS_EMAIL', $value);
					break;
				case 'blog-title':
					define('WP_TESTS_TITLE', $value);
					break;
				case 'php-binary':
					define('WP_PHP_BINARY', $value);
					break;
				case 'wplang':
					define('WPLANG', $value);
					break;

				case 'testdata-path':
					define('DIR_TESTDATA', $value);
					break;

				case 'bootstrap-path':
					$this->bootstrapLocation = $value;
					break;
				case 'always-reinstall':
					$this->alwaysReinstall = $value;

				case 'db-prefix':
					$GLOBALS['table_prefix'] = $value;
					break;

				case 'admin-password':
					$this->adminPassword = $value;
					break;

				case 'wordpress-path':
					define('ABSPATH', rtrim($value, '/\\') . '/');
					break;

				default:
					throw new \InvalidArgumentException("Argument $name is not a valid setting for the WordPress Bootstrapper.");
			}
		}

		require_once __DIR__ . '/functions.php';

		$this->isMultisite = (int) (defined('WP_TESTS_MULTISITE') && WP_TESTS_MULTISITE);

		if (isset($argv[1]) && $argv[1] === 'install')
			$this->bootstrap();
		else
			$this->install();
	}

	public function boostrap ()
	{
		/*
		 * Globalize some WordPress variables, because PHPUnit loads this file inside a function
		 * See: https://github.com/sebastianbergmann/phpunit/issues/325
		 *
		 * These are not needed for WordPress 3.3+, only for older versions
		*/
		global $table_prefix, $wp_embed, $wp_locale, $_wp_deprecated_widgets_callbacks, $wp_widget_factory;

		// These are still needed
		global $wpdb, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer;

		$this->startHeadlessRequest();

		// Install WordPress
		system(WP_PHP_BINARY . ' ' . escapeshellarg($this->bootstrapLocation) . ' install');

		// Mock PHP Mailer
		$GLOBALS['phpmailer'] = new MockPHPMailer();

		$GLOBALS['wppf'] = new WPProfiler();

		function wppf_start($name) {
			$GLOBALS['wppf']->start($name);
		}

		function wppf_stop() {
			$GLOBALS['wppf']->stop();
		}

		function wppf_results() {
			return $GLOBALS['wppf']->results();
		}

		function wppf_print_summary() {
			$GLOBALS['wppf']->print_summary();
		}
	}

	public function install ()
	{
		error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);

		define('WP_INSTALLING', true);

		$this->startHeadlessRequest();

		if ($this->isMultisite)
		{
			echo "Running as multisite..." . PHP_EOL;
			define( 'MULTISITE', true );
			define( 'SUBDOMAIN_INSTALL', false );
			define( 'DOMAIN_CURRENT_SITE', WP_TESTS_DOMAIN );
			define( 'PATH_CURRENT_SITE', '/' );
			define( 'SITE_ID_CURRENT_SITE', 1 );
			define( 'BLOG_ID_CURRENT_SITE', 1 );
			$GLOBALS['base'] = '/';
		}
		else
		{
			echo "Running as single site..." . PHP_EOL;
		}

		// Preset WordPress options defined in bootstrap file.
		// Used to activate themes, plugins, as well as  other settings.
		if(isset($GLOBALS['wp_tests_options'])) {
			function wp_tests_options( $value ) {
				$key = substr( current_filter(), strlen( 'pre_option_' ) );
				return $GLOBALS['wp_tests_options'][$key];
			}

			foreach ( array_keys( $GLOBALS['wp_tests_options'] ) as $key ) {
				tests_add_filter( 'pre_option_'.$key, 'wp_tests_options' );
			}
		}

		// Load WordPress
		require_once ABSPATH . 'wp-settings.php';

		// Delete any default posts & related data
		_delete_all_posts();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		require_once ABSPATH . 'wp-includes/wp-db.php';

		define('WP_TESTS_VERSION_FILE', ABSPATH . '.wp-tests-version');

		$wpdb->suppress_errors();
		$installed = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'siteurl'" );
		$wpdb->suppress_errors(false);

		$hash = get_option('db_version') . ' ' . (int) $multisite . ' ' . sha1(json_encode($this->settings));

		if (!$alwaysReinstall && $installed && file_exists(WP_TESTS_VERSION_FILE) && file_get_contents(WP_TESTS_VERSION_FILE) == $hash)
			return;

		$wpdb->query('SET storage_engine = INNODB');
		$wpdb->select(DB_NAME, $wpdb->dbh);

		echo "Installing..." . PHP_EOL;

		foreach ($wpdb->tables() as $table => $prefixed_table)
		{
			$wpdb->query("DROP TABLE IF EXISTS $prefixed_table");
		}

		foreach ($wpdb->tables( 'ms_global' ) as $table => $prefixed_table)
		{
			$wpdb->query("DROP TABLE IF EXISTS $prefixed_table");

			// We need to create references to ms global tables.
			if ( $multisite )
				$wpdb->$table = $prefixed_table;
		}

		wp_install(WP_TESTS_TITLE, 'admin', WP_TESTS_EMAIL, true, null, 'admin');

		if ($multisite)
		{
			echo "Installing network..." . PHP_EOL;

			define('WP_INSTALLING_NETWORK', true);

			$title = WP_TESTS_TITLE . ' Network';
			$subdomain_install = false;

			install_network();
			populate_network(1, WP_TESTS_DOMAIN, WP_TESTS_EMAIL, $title, '/', $subdomain_install);
		}

		file_put_contents(WP_TESTS_VERSION_FILE, $hash);
	}

	protected function startHeadlessRequest ()
	{
		$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
		$_SERVER['HTTP_HOST'] = WP_TESTS_DOMAIN;
		$PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
	}
}