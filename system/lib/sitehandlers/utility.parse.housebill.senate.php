<?php

/*
 * Class SenateHousebillParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateHousebillParseUtility extends SenateDocAuthorshipParseUtility {
  
	var $filtered_content = array();
	var $senate_document_sn_regex_prefix = NULL;
	var $senate_document_sn_prefix = 'HBN';

  function __construct() {
    parent::__construct();
		$this->senate_document_sn_regex_prefix = 'senate house bill'; 
    $this->senate_document_sn_prefix = 'HBN';
  }

}

