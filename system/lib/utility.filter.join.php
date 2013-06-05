<?php

/*
 * Class JoinFilterUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class JoinFilterUtility extends FilterUtility {

  static function get_selector_regex() {
    $selector_regex = '@({([^}]*)}|\[([^]]*)\]|(([-_0-9a-z=#]*)*)[,]*)@';
    return $selector_regex;
  }

  static function component_condition_input_check($sa, $attparts) {
    return NULL;
  }

  static function component_attribute_parts($sa) {
    return "{$sa}";
  }

  static function get_returnable_element($attr) {
    return 'TABLENAME["'.$attr.'"]';
  }

  static function get_condition_exact_match($attr,$val) {
		// The decision to quote an attribute value should probably be left
		// to the caller.  This is consistent with present usage of the 
		// where() method, which is passed an associative array of key-value
		// parameters.
    // return "{join}.`{$attr}` = QUOT({$val})";
		return array(
			'attr' => "{join}.`{$attr}`",
			'val'  => $val,
			'str'  => "{join}.`{$attr}` = QUOT({$val})"
		);
  }

  static function get_condition_regex_match($val, $regex_modifier, $attparts) {
    // return "{join}.`{$attparts}` REGEXP '({$val})'";
		return array(
			'attr' => "{join}.`{$attparts}`",
			'val' => $val,
			'str' => "{join}.`{$attparts}` REGEXP '({$val})'",
		);
  }

  static function get_condition_regex_matched_returnable($attr) {
    return '['.$attr.']';
  }

  static function get_returnable_value_array($returnable, $d) {
    $returnable_map = create_function('$a', 'return "`{'.($d == 0 ? 'join' : 'ftable').'}`.`{$a}`";');
		return $d == 0
			? 'REF[ ' . join(',',array_map($returnable_map, $returnable)) . ']'
			: array_map($returnable_map, $returnable)
			;
  }

  static function get_returnable_value_array_singleentry($returnable, $d) {
    $returnable_map = create_function('$a', '$m = array(); return !(1 == preg_match("@^([#])(.*)@i", $a, $m)) ? "{$a}" : ( 0 < strlen($m[2]) ? "{$m[2]}" : "\$a" ) ;');
    return join(',',array_map($returnable_map, $returnable));
  }

	static function get_returnable_value_element($returnable) {
		return '';
	}

  static function return_map_condition($conditions, $returnable_match, $d) {
		return $d == 0
			?	array(
					'target' => trim($returnable_match,'`'),
					'conditions' => $conditions,
				)	
				: array(
					'fields' => $returnable_match,
					'conditions' => array_map(create_function('$a', 'return str_replace("{join}","{ftable}",$a);'),$conditions)
				)
			;
  }

}

