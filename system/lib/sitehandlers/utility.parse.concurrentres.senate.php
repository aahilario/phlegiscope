<?php

/*
 * Class SenateConcurrentresParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateConcurrentresParseUtility extends SenateDocAuthorshipParseUtility {
  
	var $senate_document_sn_regex_prefix = NULL;
	var $senate_document_sn_prefix = 'SCR';

  function __construct() {
    parent::__construct();
		$this->senate_document_sn_regex_prefix = 'senate concurrent resolution'; 
		$this->senate_document_sn_prefix = 'SCR';
  }

}

