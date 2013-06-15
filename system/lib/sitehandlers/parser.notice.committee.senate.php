<?php

/*
 * Class SenateCommitteeNoticeParser
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeNoticeParser extends SeekAction {
  
  function __construct() {
    $this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
    parent::__construct();
  }

}

