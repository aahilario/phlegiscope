<?php

/*
 * Class UrlUrlJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class UrlUrlJoin extends ModelJoin {
  
  // Join table model
  var $left_url_UrlModel = NULL;
  var $right_url_UrlModel = NULL;

  function __construct($a = NULL, $b = NULL) {
    parent::__construct();
    $this->left_url_UrlModel = NULL;
    $this->right_url_UrlModel = NULL;
    $this->dump_accessor_defs_to_syslog();
    $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    if ( !is_null($a) && !is_null($b) ) {
      $this->fetch($a, $b);
    }
  }

  function fetch($a, $b) {
    // Translates to where(array('AND' => array('a' => $a, 'b' => $b) ))
    return parent::fetch(array(
      'left_url' => $a,
      'right_url' => $b,
    ), 'AND');
  }

  function fetch_children($a) {
    return parent::fetch($a, 'left_url');
  }

  function stow($a, $b) {
    $a = intval($a);
    $b = intval($b);
    if ( $a > 0 && $b > 0 ) {
      $this->set_left_url($a);
      $this->set_right_url($b);
      return parent::stow();
    }
    return FALSE;
  }

  function & set_left_url($v) { $this->left_url_UrlModel = $v; return $this; }
  function get_left_url($v = NULL) { if (!is_null($v)) $this->set_left_url($v); return $this->left_url_UrlModel; }

  function & set_right_url($v) { $this->right_url_UrlModel = $v; return $this; }
  function get_right_url($v = NULL) { if (!is_null($v)) $this->set_right_url($v); return $this->right_url_UrlModel; }

}

