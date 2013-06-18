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
		$this->senate_document_sn_regex_prefix = 'senate resolution'; 
    $this->senate_document_sn_prefix = 'HBN';
  }

	function modify_congress_session_item_markup(& $pagecontent, $session_select) {/*{{{*/
		if ( is_array($session_select) ) {
			$alt_switcher = array();
			$this->recursive_dump($session_select, "(marker) - - - - Mode switcher");
			foreach ( $session_select as $switcher ) {
				$optval   = array_element($switcher,'optval');
				$markup   = array_element($switcher,'markup');
				$linktext = array_element($switcher,'linktext');
				$alt_switcher[] = <<<EOH
<span class="listing-mode-switcher" id="listing-mode-{$optval}">{$markup}</span>
EOH;
			}
			$switcher = join(' ', $alt_switcher);
			$pagecontent = str_replace('[SWITCHER]', $switcher, $pagecontent);
		}
	}/*}}}*/

}

