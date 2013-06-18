<?php

/*
 * Class SenateAdoptedresDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateAdoptedresDocumentModel extends SenateDocCommonDocumentModel {
  
  var $title_vc256uniq = NULL;
  var $sn_vc16 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $congress_tag_vc8 = NULL;

	var $resolution_SenateResolutionDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

}

