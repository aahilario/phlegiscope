<?php

/*
 * Class HouseJournalDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseJournalDocumentModel extends UrlModel {
  
  var $title_vc256 = NULL;
  var $create_time_utx = NULL;
  var $sn_vc64 = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;
  var $session_tag_vc8 = NULL;
  var $url_vc4096 = NULL;
	var $content_blob = NULL;
	var $pdf_fetch_time_utx = NULL;
	var $pdf_url_vc4096 = NULL;

  function __construct() {
    parent::__construct();
  }

	function fetch_session_item_source(& $session_select, $fake_form_url, $target_congress) {
		// All parameters are used to generate a fake session_select array,
		// used as input parameter to generate_congress_session_item_markup().
		// The content of that array is dependent on the current, target Congress.
		$debug_method = FALSE;
		$session_select = array();
		$r = array();
		$this->where(array('OR' => array(
			'url' => "REGEXP '.*'",
			'pdf_url' => "REGEXP '.*'"
		)))->recordfetch_setup();
		$e = NULL;

		$faux_url       = $fake_form_url->get_url();
		$fake_form_url->add_query_element('congress', NULL, FALSE, TRUE);

		while ( $this->recordfetch($e) ) {
			$session_tag  = array_element($e,'session_tag') . 'R';
			$congress_tag = array_element($e,'congress_tag');
			$url          = array_element($e,'url');
			$pdf_url      = array_element($e,'pdf_url');
			$title        = array_element($e,'title');
			if ( !array_key_exists($congress_tag,$r) ) $r[$congress_tag] = array();
			if ( !array_key_exists($session_tag,$r[$congress_tag]) ) $r[$congress_tag][$session_tag] = array();
			$links = array(
				'pdf' => $pdf_url,
				'html' => $url,
			);
			$links = array_filter($links);
			if ( 1 == count($links) ) {/*{{{*/
				list ( $linktype, $url ) = each($links);
				$r[$congress_tag][$session_tag][$title] = array(
					'hash' => UrlModel::get_url_hash($url),
					'url' => $url,
					'text' => $title,
				);
			}/*}}}*/
		 	else {/*{{{*/
				$r[$congress_tag][$session_tag][$title] = array(
					'hash' => UrlModel::get_url_hash($links['html']),
					'url' => $links['html'],
					'text' => $title,
				);
				$title = "{$title} PDF";
				$r[$congress_tag][$session_tag][$title] = array(
					'hash' => UrlModel::get_url_hash($links['pdf']),
					'url' => $links['pdf'],
					'text' => $title,
				);
			}/*}}}*/

			// Replicate the action of extract_house_session_select,
			// but using the target congress 
			if ( !array_key_exists($session_tag, $session_select) ) {
				$control_set = array(
					'page'     => array_element($e,'session_tag'),
					'congress' => $target_congress,
				);
				$linktext       = $session_tag; // "{$session_tag} Regular Session";
				$generated_link = UrlModel::create_metalink(
					$linktext,
					$fake_form_url->get_url(),
					$control_set,
					'fauxpost'
				);
        $controlset_json_base64 = base64_encode(json_encode($control_set));
        $controlset_hash        = md5($controlset_json_base64);
				$session_select[$session_tag] = array(
          'metalink'        => $controlset_json_base64,
					'metalink_hash'   => $controlset_hash,
					'metalink_source' => $control_set,
          'faux_url'        => $faux_url, 
          'linktext'        => $linktext, 
          'optval'          => $session_tag,
          'markup'          => $generated_link,
          'url_model'       => NULL,
				);
			}
		}
		if ( 0 < count($session_select) ) $session_select = array_combine(
			array_map(create_function('$a', 'return $a["metalink_hash"];'), $session_select),
			array_map(create_function('$a', 'unset($a["metalink_hash"]); return $a;'), $session_select)
		);
		if ( $debug_method ) $this->recursive_dump($session_select,"(marker) - -- - --");
		return $r;
	}

	function & set_title($v) { $this->title_vc256 = $v; return $this; }
	function get_title($v = NULL) { if (!is_null($v)) $this->set_title($v); return $this->title_vc256; }

	function & set_sn($v) { $this->sn_vc64 = $v; return $this; }
	function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64; }

	function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
	function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

	function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
	function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

	function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
	function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

	function & set_url($v) { $this->url_vc4096 = $v; return $this; }
	function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }

	function & set_content($v) { $this->content_blob = $v; return $this; }
	function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

	function & set_pdf_fetch_time($v) { $this->pdf_fetch_time_utx = $v; return $this; }
	function get_pdf_fetch_time($v = NULL) { if (!is_null($v)) $this->set_pdf_fetch_time($v); return $this->pdf_fetch_time_utx; }

	function & set_pdf_url($v) { $this->pdf_url_vc4096 = $v; return $this; }
	function get_pdf_url($v = NULL) { if (!is_null($v)) $this->set_pdf_url($v); return $this->pdf_url_vc4096; }

	function & set_session_tag($v) { $this->session_tag_vc8 = $v; return $this; }
	function get_session_tag($v = NULL) { if (!is_null($v)) $this->set_session_tag($v); return $this->session_tag_vc8; }

	function & set_id($id) {
		$this->id = $id;
		return $this;
	}
}

