<?php

/*
 * Class SenateResolutionParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateResolutionParseUtility extends SenateDocAuthorshipParseUtility {
  
  var $activity_summary = array();
	var $senate_document_sn_regex_prefix = NULL;
	var $senate_document_sn_prefix = 'SRN';

  function __construct() {
    parent::__construct();
		$this->senate_document_sn_regex_prefix = 'senate resolution'; 
    $this->senate_document_sn_prefix = 'SRN';
  }

}
