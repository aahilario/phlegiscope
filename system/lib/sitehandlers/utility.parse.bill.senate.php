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

  function generate_congress_session_column_markup(& $q, $query_regex) {/*{{{*/
    // Intercept the column generator call, and insert missing links
    // All links will share a common base URL, up to the query parameters.
    // For Senate Bills, only the document SN part (SBN-xxx) varies.
		$this->complete_series($q);
    return parent::generate_congress_session_column_markup($q, $query_regex);
  }/*}}}*/

}
