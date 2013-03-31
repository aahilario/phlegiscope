<?php

/*
 * Class UrlEdgeModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class UrlEdgeModel extends DatabaseUtility {
  
  var $a_int11 = NULL;
  var $b_int11 = NULL;

  function __construct($a = NULL, $b = NULL) {
    parent::__construct();
    $this->a_int11 = NULL;
    $this->b_int11 = NULL;
    if ( !is_null($a) && !is_null($b) ) {
      $this->fetch($a, $b);
    }
  }

  function fetch($a, $b) {
    // Translates to where(array('AND' => array('a' => $a, 'b' => $b) ))
    return parent::fetch(array(
      'a' => $a,
      'b' => $b,
    ), 'AND');
  }

  function fetch_children($a) {
    return parent::fetch($a, 'a');
  }

  function stow($a, $b) {
    $a = intval($a);
    $b = intval($b);
    if ( $a > 0 && $b > 0 ) {
      $this->a_int11 = $a;
      $this->b_int11 = $b;
      parent::stow();
    }
  }

}
