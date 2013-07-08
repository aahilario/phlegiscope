<?php

/*
 * Class SenateCommitteeModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeModel extends SenateDocCommonDocumentModel {
  
  var $committee_name_vc256uniq = NULL;
  var $short_code_vc32 = NULL;
  var $jurisdiction_vc1024 = NULL;
  var $is_permanent_bool = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $url_vc4096 = NULL;

  var $senator_SenatorDossierModel = NULL; // Relationships of Senator to this committee
  var $senate_bill_SenateBillDocumentModel = NULL;
  var $senate_housebill_SenateHousebillDocumentModel = NULL;

  function __construct() {
    parent::__construct();
  }

  function get_senator() { return $this->senator_SenatorDossierModel; }
  function & set_senator($v) { $this->senator_SenatorDossierModel = $v; return $this; }

  function get_senate_bill() { return $this->senate_bill_SenateBillDocumentModel; }
  function & set_senate_bill($v) { $this->senate_bill_SenateBillDocumentModel = $v; return $this; }

  function get_senate_housebill() { return $this->senate_housebill_SenateHousebillDocumentModel; }
  function & set_senate_housebill($v) { $this->senate_housebill_SenateHousebillDocumentModel = $v; return $this; }

  function & set_committee_name($v) { $this->committee_name_vc256uniq = $v; return $this; }
  function get_committee_name($v = NULL) { if (!is_null($v)) $this->set_committee_name($v); return $this->committee_name_vc256uniq; }

  function & set_short_code($v) { $this->short_code_vc32 = $v; return $this; }
  function get_short_code($v = NULL) { if (!is_null($v)) $this->set_short_code($v); return $this->short_code_vc32; }

  function & set_jurisdiction($v) { $this->jurisdiction_vc1024 = $v; return $this; }
  function get_jurisdiction($v = NULL) { if (!is_null($v)) $this->set_jurisdiction($v); return $this->jurisdiction_vc1024; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_is_permanent($v) { $this->is_permanent_bool = $v; return $this; }
  function get_is_permanent($v = NULL) { if (!is_null($v)) $this->set_is_permanent($v); return $this->is_permanent_bool; }  

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return $this->url_vc4096; }  

  function & set_id($v) { $this->id = $v; return $this; }

  function stow_committee( $committee_name ) {/*{{{*/
    $committee_name = $this->cleanup_committee_name($committee_name);
    $this->fetch_by_committee_name($committee_name);
    $short_code = trim(preg_replace('@[^A-Z]@','',$committee_name));
    $id = $this->in_database()
      ? $this->get_id()
      :  $this->
        set_committee_name($committee_name)->
        set_short_code($short_code)->
        set_jurisdiction(NULL)->
        set_is_permanent('FALSE')->
        set_create_time(time())->
        set_last_fetch(time())->
        stow()
      ;
    return $id;
  }/*}}}*/

  function cleanup_committee_name($committee_name) {/*{{{*/
    $committee_name = str_replace(array("\x09","[BR]",'%20'),array(""," ",' '),trim($committee_name));
    $committee_name = preg_replace(
      array("@[^,'A-Z0-9 ]@i",'@[ ]+@'),
      array('',' '),$committee_name);
    return trim($committee_name);
  }/*}}}*/

  function cursor_fetch_by_name_regex(& $search_name) {/*{{{*/
    return $this->fetch_by_name_regex($search_name, TRUE);
  }/*}}}*/

  function fetch_by_name_regex(& $search_name, $cursor = FALSE) {/*{{{*/

    $debug_method = TRUE;

    if ( !$cursor ) {
      // The limit() call is necessary and reasonable since large resultsets
      // can choke the application, and should be retrieved with a cursor anyway.
      $search_name = $this->
        limit(1,0)->
        fetch(array(
          'LOWER(committee_name)' => "REGEXP '({$search_name})'"
        ),'AND');
      $result = $this->in_database();
    } else if ( is_null($search_name) ) {
      $result = $this->recordfetch($search_name,TRUE);
    } else {
      // Return a record in the search_name parameter
      $this->
        join_all()->
        where(array('AND' => array(
        'committee_name' => "REGEXP '({$search_name})'"
      )))->recordfetch_setup();
      $result = $this->recordfetch($search_name,TRUE);
    }
    if ( $result ) {
      if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__, "(marker) Found record " . $this->get_id() . " (" . $this->get_committee_name() . ")");
    } else {
      $this->syslog(__FUNCTION__,__LINE__, 
        $cursor
        ? "(marker) No cursor match"
        :  "(marker) Failed to match record using regex {$search_name}"
      );
    }
    return $result;
  }/*}}}*/

  function fetch_by_committee_name($committee_name, $cursor = FALSE) {/*{{{*/

    $debug_method = FALSE;

    $search_name = LegislationCommonParseUtility::committee_name_regex($committee_name);

    if ( FALSE == $search_name ) {
      $this->syslog(__FUNCTION__,__LINE__,"(error) - - - - Unparseable committee name '{$committee_name}'");
      return FALSE;
    }

    return $this->fetch_by_name_regex($search_name, $cursor);

  }/*}}}*/

}
