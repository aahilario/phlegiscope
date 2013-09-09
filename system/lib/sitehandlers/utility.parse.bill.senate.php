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

	function congress_session_column_link($child_link) {/*{{{*/
		// Convert Senate Bill links to POST actions that:
		// 1) First execute a GET to the original target URL; and then
		// 2) Execute a POST using input controls found on the page from step (1)
		$child_link['class'][] = 'fauxpost'; 
		$link_class = join(' ',$child_link['class']);
		$post_action_url = $child_link['url'];
		$sbn_metalink_parameters = array('_LEGISCOPE_' => array(
			'get_before_post' => TRUE,
			'post_action_url' => $post_action_url,
			'referrer' => $child_link['url'],
		));
		$link = UrlModel::create_metalink($child_link['text'], $child_link['url'], $sbn_metalink_parameters, $link_class);
		return $link;
    return <<<EOH
<a class="{$link_class}" id="{$child_link['hash']}" href="{$child_link['url']}">{$child_link['text']}</a>
EOH;
	}	/*}}}*/

  function generate_congress_session_column_markup(& $q, $query_regex) {/*{{{*/
    // Intercept the column generator call, and insert missing links
    // All links will share a common base URL, up to the query parameters.
    // For Senate Bills, only the document SN part (SBN-xxx) varies.
		$this->complete_series($q);
    return parent::generate_congress_session_column_markup($q, $query_regex);
  }/*}}}*/

	function generate_document_list_pager_entry($url, $link_class, $p) {/*{{{*/
		$link_class = join(' ', $link_class);
		$urlhash = UrlModel::get_url_hash($url);
		return <<<EOH
<a id="{$urlhash}" href="{$url}" class="{$link_class}" {target}>{$p} </a>
EOH;
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
