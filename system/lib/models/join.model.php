<?php

/*
 * Class ModelJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class ModelJoin extends DatabaseUtility {

  function __construct() {
    parent::__construct();
    // $this->dump_accessor_defs_to_syslog();
    // $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
  }

  function & remove() {
    // Nukes a Join record
    $id = $this->get_id();
    $this->syslog(__FUNCTION__,__LINE__,"(marker) - - - - - Nuke record [{$id}]"); 
    parent::remove();
    return $this;
  }

	function & set_referral_date($v) { return $this->set_dtm_attr($v,'referral_date'); }
	function get_referral_date() { return $this->get_dtm_attr('referral_date'); }

	function & set_reading_date($v) { return $this->set_dtm_attr($v,'reading_date'); }
	function get_reading_date() { return $this->get_dtm_attr('reading_date'); }

}

