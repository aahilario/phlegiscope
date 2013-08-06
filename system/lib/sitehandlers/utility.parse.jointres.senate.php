<?php

/*
 * Class SenateJointresParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateJointresParseUtility extends SenateDocAuthorshipParseUtility {
  
  var $activity_summary = array();
	var $senate_document_sn_regex_prefix = NULL;
	var $senate_document_sn_prefix = 'SJR';

  function __construct() {
    parent::__construct();
		$this->senate_document_sn_regex_prefix = 'senate joint resolution'; 
    $this->senate_document_sn_prefix = 'SJR';
  }

}

