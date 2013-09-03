<?php

/*
 * Class LinkAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class LinkAction extends LegiscopeBase {
  
  function __construct() {
    parent::__construct();
  }

	function link() {

		$link      = preg_replace('@^m-@i','',$this->filter_post('link'));
		$link      = preg_replace('@[^A-F0-9]@i','', $link);
		$page      = $this->filter_post('url');
		$force_log = $this->filter_post('cache');
    if ( $force_log == 'true' ) DatabaseUtility::$force_log = TRUE;

		$urlmodel = new UrlModel();
		$new_state = 0;

		if ( $urlmodel->retrieve($link,'urlhash')->in_database() ) {
			$url = $urlmodel->get_url();
			$state = 0 < intval($urlmodel->get_is_source_root()) ? 0 : 1;
			$this->syslog(__FUNCTION__,__LINE__,"(critical) Mark state {$state} URL {$url} on page {$page}"); 
			$url_id = $urlmodel->
				set_is_source_root($state)->
				fields(array('is_source_root'))->
				stow();
			$new_state = $urlmodel->get_is_source_root($state);
		}

		$this->syslog( __FUNCTION__, __LINE__, "(critical) Invoked for {$link}");

		$json_reply['linkset'] = $link;
		$json_reply['state'] = $new_state; 

    $this->exit_cache_json_reply($json_reply,get_class($this));

	}

}
