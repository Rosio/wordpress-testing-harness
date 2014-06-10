<?php
// convert valid xml to an array tree structure
// kinda lame but it works with a default php 4 install
class TestXMLParser {
	var $xml;
	var $data = array();

	function testXMLParser($in) {
		$this->xml = xml_parser_create();
		xml_set_object($this->xml, $this);
		xml_parser_set_option($this->xml,XML_OPTION_CASE_FOLDING, 0);
		xml_set_element_handler($this->xml, array(&$this, 'startHandler'), array(&$this, 'endHandler'));
		xml_set_character_data_handler($this->xml, array(&$this, 'dataHandler'));
		$this->parse($in);
	}

	function parse($in) {
		$parse = xml_parse($this->xml, $in, sizeof($in));
		if (!$parse) {
			trigger_error(sprintf("XML error: %s at line %d",
			xml_error_string(xml_get_error_code($this->xml)),
			xml_get_current_line_number($this->xml)), E_USER_ERROR);
			xml_parser_free($this->xml);
		}
		return true;
	}

	function startHandler($parser, $name, $attributes) {
		$data['name'] = $name;
		if ($attributes) { $data['attributes'] = $attributes; }
		$this->data[] = $data;
	}

	function dataHandler($parser, $data) {
		$index = count($this->data) - 1;
		@$this->data[$index]['content'] .= $data;
	}

	function endHandler($parser, $name) {
		if (count($this->data) > 1) {
			$data = array_pop($this->data);
			$index = count($this->data) - 1;
			$this->data[$index]['child'][] = $data;
		}
	}
}