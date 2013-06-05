<?php

/*
 * Class ArrayFilterUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ArrayFilterUtility extends FilterUtility {

  static function get_selector_regex() {
    $selector_regex = '@({([^}]*)}|\[([^]]*)\]|(([-_0-9a-z=#]*)*)[,]*)@';
    return $selector_regex;
  }

  static function component_condition_input_check($sa, $attparts) {
    return 'array_key_exists("'.$sa.'", $a'.$attparts.')';
  }

  static function component_attribute_parts($sa) {
    return "['{$sa}']";
  }

  static function get_returnable_element($attr) {
    return '$a["'.$attr.'"]';
  }

  static function get_condition_exact_match($attr,$val) {
    return '$a["'.$attr.'"] == "'.$val.'"';
  }

  static function get_condition_regex_match($val, $regex_modifier, $attparts) {
    return '1 == preg_match("@('.$val.')@'.$regex_modifier.'",$a'.$attparts.')'; 
  }

  static function get_condition_regex_matched_returnable($attr) {
    return '$a["'.$attr.'"]';
  }

  static function get_returnable_value_array($returnable, $d) {
    $returnable_map   = create_function('$a', 'return "\"{$a}\" => \$a[\"{$a}\"]";');
    return 'array(' . join(',',array_map($returnable_map, $returnable)) .')';
  }

  static function get_returnable_value_array_singleentry($returnable, $d) {
    $returnable_map   = create_function('$a', '$m = array(); return !(1 == preg_match("@^([#])(.*)@i", $a, $m)) ? "\$a[\"{$a}\"]" : ( 0 < strlen($m[2]) ? "\$a[\"$m[2]\"]" : "\$a" ) ;');
    return join(',',array_map($returnable_map, $returnable));
  }

	static function get_returnable_value_element($returnable) {
		return '$a';
	}

  static function return_map_condition($conditions, $returnable_match, $d) {
    return 'return ' . join(' && ', $conditions) . ' ? ' . $returnable_match . ' : NULL;';
  }

}

