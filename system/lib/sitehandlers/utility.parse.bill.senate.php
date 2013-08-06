<?php

/*
 * Class SenateBillParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateBillParseUtility extends SenateDocAuthorshipParseUtility {
  
  var $filtered_content = array();
  var $senate_document_sn_regex_prefix = NULL;
  var $senate_document_sn_prefix = 'SBN';

  function __construct() {
    parent::__construct();
    $this->senate_document_sn_regex_prefix = 'senate bill'; 
    $this->senate_document_sn_prefix = 'SBN';
  }

}
