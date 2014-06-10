WordPress Testing Harness
===

This library is meant to make it easier to test plugins and themes with the WordPress runtime via PHPUnit.  
This is basically just a rewrite of [kurtpayne/wordpress-unit-tests](https://github.com/kurtpayne/wordpress-unit-tests).

Usage:
---

1. Add this library, your testing library, and your WordPress deployment of choice to your plugin or themes composer.php:
	```javascript
		"autoload-dev": {
			"psr-4": {
				"Plugin\\SomePlugin\\": "tests/"
			}
		},
		"require-dev": {
			"phpunit/phpunit": "~3.7",
			"rosio/wordpress-testing-harness": "dev-master",
			"johnpbloch/wordpress": "~3.8"
		},
	```
	You don't actually have to include WordPress, and instead have it download to the correct folder during testing (which means you can then test multiple versions of WordPress easily), but it's recommended to add one for easy local testing.

2. Configure your phpunit.xml.dist
	```xml
	<?xml version="1.0" encoding="UTF-8"?>
	<phpunit backupGlobals="false"
	         beStrictAboutTestSize="true"
	         backupStaticAttributes="false"
	         bootstrap="tests/bootstrap.php"
	         colors="true"
	         convertErrorsToExceptions="true"
	         convertNoticesToExceptions="true"
	         convertWarningsToExceptions="true"
	         processIsolation="false"
	         stopOnFailure="false"
	         syntaxCheck="false"
	>
	    <testsuites>
	        <testsuite name="Application Test Suite">
	            <directory suffix="Test.php">./tests/</directory>
	        </testsuite>
	    </testsuites>
	</phpunit>

	```

3. Configure your phpunit's tests/bootstrap.php file:
	```php
	new Rosio\WordPressTestingHarness\Bootstrapper(array(
		'bootstrap-path' => __FILE__,

		'db-name' => 'wp_test',
		'db-user' => 'root',
		'db-pass' => '',
		'db-host' => 'localhost',
		'db-prefix' => 'wptests_',
		'db-charset' => '',
		'db-collate' => '',
		'wplang' => '',

		'wordpress-path' => __DIR__ . '/../wordpress',
		'domain' => 'wp.localhost',
		'admin-email' => 'admin@example.org',
		'blog-title' => 'Tests',
		'admin-password' => 'admin',

		'always-reinstall' => false,

		'php-binary' => 'php',
		'testdata-path' => __DIR__ . '/../data',
	));
	```

4. Write your first test!
	```php
	<?php
	namespace Plugin\SomePlugin;

	use WP_UnitTestCase;
	use \Mockery as m;

	class MainPluginTest extends WP_UnitTestCase
	{
		public function setUp()
		{

		}

		public function tearDown()
		{
			m::close();
		}

		public function testPluginIsCreated()
		{
			$this->assertInstanceOf('Plugin\SomePlugin\MainPlugin', getSomePlugin());
		}
	}
	```