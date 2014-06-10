WordPress Testing Harness
===

This library is meant to make it easier to test plugins and themes with the WordPress runtime via PHPUnit.  
This is basically just a rewrite of [kurtpayne/wordpress-unit-tests](https://github.com/kurtpayne/wordpress-unit-tests).

Usage:
---

1. Add this library to your plugin or themes composer.php:
```javascript
	"autoload-dev": {
		"rosio/wordpress-testing-harness": "~1.0"
	}
```

2. Configure your phpunit's bootstrap.php file:
```php
new Rosio\WordPressTestingHarness\Bootstrapper(array(
	'bootstrap-path' => __DIR__,

	'db-name' => 'wp_test',
	'db-user' => 'root',
	'db-pass' => 'root',
	'db-host' => 'localhost',
	'db-prefix' => 'wptests_',
	'db-charset' => '',
	'db-collate' => '',
	'wplang' => '',

	'wordpress-path' => __DIR__ . '/../wp',
	'domain' => 'wp.localhost',
	'admin-email' => 'admin@example.org',
	'blog-title' => 'Tests',
	'admin-password' => 'admin',

	'always-reinstall' => false,

	'php-binary' => 'php',
	'testdata-path' => __DIR__ . '/../data',
));
```