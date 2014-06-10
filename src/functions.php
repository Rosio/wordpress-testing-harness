<?php
// For adding hooks before loading WP
function tests_add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	global $wp_filter, $merged_filters;

	$idx = _test_filter_build_unique_id($tag, $function_to_add, $priority);
	$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
	unset( $merged_filters[ $tag ] );
	return true;
}

function _test_filter_build_unique_id($tag, $function, $priority) {
	global $wp_filter;
	static $filter_id_count = 0;

	if ( is_string($function) )
		return $function;

	if ( is_object($function) ) {
		// Closures are currently implemented as objects
		$function = array( $function, '' );
	} else {
		$function = (array) $function;
	}

	if (is_object($function[0]) ) {
		return spl_object_hash($function[0]) . $function[1];
	} else if ( is_string($function[0]) ) {
		// Static Calling
		return $function[0].$function[1];
	}
}

function _delete_all_posts() {
	global $wpdb;

	$all_posts = $wpdb->get_col("SELECT ID from {$wpdb->posts}");
	if ($all_posts) {
		foreach ($all_posts as $id)
			wp_delete_post( $id, true );
	}
}

function xml_to_array($in) {
	$p = new TestXMLParser($in);
	return $p->data;
}

function xml_find($tree /*, $el1, $el2, $el3, .. */) {
	$a = func_get_args();
	$a = array_slice($a, 1);
	$n = count($a);
	$out = array();

	if ($n < 1)
		return $out;

	for ($i=0; $i<count($tree); $i++) {
		if ($tree[$i]['name'] == $a[0]) {
			if ($n == 1)
				$out[] = $tree[$i];
			else {
				$subtree =& $tree[$i]['child'];
				$call_args = array($subtree);
				$call_args = array_merge($call_args, array_slice($a, 1));
				$out = array_merge($out, call_user_func_array('xml_find', $call_args));
			}
		}
	}

	return $out;
}

function xml_join_atts($atts) {
	$a = array();
	foreach ($atts as $k=>$v)
		$a[] = $k.'="'.$v.'"';
	return join(' ', $a);
}

function xml_array_dumbdown(&$data) {
	$out = array();

	foreach (array_keys($data) as $i) {
		$name = $data[$i]['name'];
		if (!empty($data[$i]['attributes']))
			$name .= ' '.xml_join_atts($data[$i]['attributes']);

		if (!empty($data[$i]['child'])) {
			$out[$name][] = xml_array_dumbdown($data[$i]['child']);
		}
		else
			$out[$name] = $data[$i]['content'];
	}

	return $out;
}

function dmp() {
	$args = func_get_args();

	foreach ($args as $thing)
		echo (is_scalar($thing) ? strval($thing) : var_export($thing, true)), "\n";
}

function dmp_filter($a) {
	dmp($a);
	return $a;
}

function get_echo($callable, $args = array()) {
	ob_start();
	call_user_func_array($callable, $args);
	return ob_get_clean();
}

// recursively generate some quick assertEquals tests based on an array
function gen_tests_array($name, $array) {
	$out = array();
	foreach ($array as $k=>$v) {
		if (is_numeric($k))
			$index = strval($k);
		else
			$index = "'".addcslashes($k, "\n\r\t'\\")."'";

		if (is_string($v)) {
			$out[] = '$this->assertEquals( \'' . addcslashes($v, "\n\r\t'\\") . '\', $'.$name.'['.$index.'] );';
		}
		elseif (is_numeric($v)) {
			$out[] = '$this->assertEquals( ' . $v . ', $'.$name.'['.$index.'] );';
		}
		elseif (is_array($v)) {
			$out[] = gen_tests_array("{$name}[{$index}]", $v);
		}
	}
	return join("\n", $out)."\n";
}

/**
 * Drops all tables from the WordPress database
 */
function drop_tables() {
	global $wpdb;
	$tables = $wpdb->get_col('SHOW TABLES;');
	foreach ($tables as $table)
		$wpdb->query("DROP TABLE IF EXISTS {$table}");
}

function print_backtrace() {
	$bt = debug_backtrace();
	echo "Backtrace:\n";
	$i = 0;
	foreach ($bt as $stack) {
		echo ++$i, ": ";
		if ( isset($stack['class']) )
			echo $stack['class'].'::';
		if ( isset($stack['function']) )
			echo $stack['function'].'() ';
		echo "line {$stack[line]} in {$stack[file]}\n";
	}
	echo "\n";
}

// mask out any input fields matching the given name
function mask_input_value($in, $name='_wpnonce') {
	return preg_replace('@<input([^>]*) name="'.preg_quote($name).'"([^>]*) value="[^>]*" />@', '<input$1 name="'.preg_quote($name).'"$2 value="***" />', $in);
}

$GLOBALS['_wp_die_disabled'] = false;
function _wp_die_handler( $message, $title = '', $args = array() ) {
	if ( !$GLOBALS['_wp_die_disabled'] ) {
		_default_wp_die_handler( $message, $title, $args );
	} else {
		//Ignore at our peril
	}
}

function _disable_wp_die() {
	$GLOBALS['_wp_die_disabled'] = true;
}

function _enable_wp_die() {
	$GLOBALS['_wp_die_disabled'] = false;
}

function _wp_die_handler_filter() {
	return '_wp_die_handler';
}

if ( !function_exists( 'str_getcsv' ) ) {
	function str_getcsv( $input, $delimiter = ',', $enclosure = '"', $escape = "\\" ) {
		$fp = fopen( 'php://temp/', 'r+' );
		fputs( $fp, $input );
		rewind( $fp );
		$data = fgetcsv( $fp, strlen( $input ), $delimiter, $enclosure );
		fclose( $fp );
		return $data;
	}
}

function _rmdir( $path ) {
	if ( in_array(basename( $path ), array( '.', '..' ) ) ) {
		return;
	} elseif ( is_file( $path ) ) {
		unlink( $path );
	} elseif ( is_dir( $path ) ) {
		foreach ( scandir( $path ) as $file )
			_rmdir( $path . '/' . $file );
		rmdir( $path );
	}
}

/**
 * Removes the post type and its taxonomy associations.
 */
function _unregister_post_type( $cpt_name ) {
	unset( $GLOBALS['wp_post_types'][ $cpt_name ] );
	unset( $GLOBALS['_wp_post_type_features'][ $cpt_name ] );

	foreach ( $GLOBALS['wp_taxonomies'] as $taxonomy ) {
		if ( false !== $key = array_search( $cpt_name, $taxonomy->object_type ) ) {
			unset( $taxonomy->object_type[$key] );
		}
	}
}

function _unregister_taxonomy( $taxonomy_name ) {
	unset( $GLOBALS['wp_taxonomies'][$taxonomy_name] );
}

function rand_str($len=32) {
	return substr(md5(uniqid(rand())), 0, $len);
}

// strip leading and trailing whitespace from each line in the string
function strip_ws($txt) {
	$lines = explode("\n", $txt);
	$result = array();
	foreach ($lines as $line)
		if (trim($line))
			$result[] = trim($line);

	return trim(join("\n", $result));
}