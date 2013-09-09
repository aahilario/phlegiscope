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

  function generate_congress_session_column_markup(& $q, $query_regex) {/*{{{*/
    // Intercept the column generator call, and insert missing links
    // All links will share a common base URL, up to the query parameters.
    // For Senate Bills, only the document SN part (SBN-xxx) varies.
		$this->complete_series($q);
    return parent::generate_congress_session_column_markup($q, $query_regex);
  }/*}}}*/

	// POST wall traversal (converting POST actions to proxied GET)
	function site_form_traversal_controls(UrlModel & $action_url, $form_controls ) {/*{{{*/
		$form_controls = parent::site_form_traversal_controls($action_url, $form_controls);
		// Senate Bill details include a legislative history summary table,
		// which is only returned after a POST action with 'magic' form controls
		// (filled in by the overridden parent method).
		$form_controls['_LEGISCOPE_']['skip_get'] = FALSE;
		$form_controls['_LEGISCOPE_']['get_before_post'] = TRUE;
		$form_controls['_LEGISCOPE_']['force_referrer'] = $action_url->get_url();
		return $form_controls;
	}/*}}}*/


}
