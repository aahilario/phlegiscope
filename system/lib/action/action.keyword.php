<?php

/*
 * Class KeywordAction
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class KeywordAction extends LegiscopeBase {
  
  function __construct() {
    // $this->syslog( __FUNCTION__, __LINE__, '(marker) Generic action handler.' );
    parent::__construct();
  }

  function keyword() {/*{{{*/

    $this->syslog( '----', __LINE__, "----------------------------------");
    $this->syslog( __FUNCTION__, __LINE__, "Invoked from {$_SERVER['REMOTE_ADDR']}" );

    ob_start();

    $referrer     = $this->filter_session('referrer','http://www.congress.gov.ph');

    $hostModel = new HostModel($referrer);
    $referrers = new UrlModel();
    $hostModel->increment_hits()->stow();
    $this->subject_host_hash = UrlModel::get_url_hash($hostModel->get_url(),PHP_URL_HOST);

    $fragment     = $this->filter_post('fragment');
    $decomposer   = '@([^ ]*) ?@i';
    $components   = array();
    $match_result = preg_match_all($decomposer, $fragment, $components);
    $records      = array();

    if ( !(array_element($_SESSION,'last_fragment') == $fragment) && count($components) > 0 ) {

      $this->recursive_dump($components,__LINE__);
      $components = array_filter($components[1]);
      $components = join('(.*)', $components);

      $iterator = new RepublicActDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Republic Acts',
          'url'         => $record['url'],
        );
        if ($count++ > 100) break;;
      }
      $this->syslog( '----', __LINE__, "Republic Acts found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new HouseBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'House Bills',
          'url'         => $record['url'],
        );
        if ($count++ > 20) break;;
      }
      $this->syslog( '----', __LINE__, "House Bills found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new SenateBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'comm_report_info' => "REGEXP '({$components})'",
          'main_referral_comm' => "REGEXP '({$components})'",
          'subjects' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records[] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Senate Bills',
          'url'         => $record['url'],
        );
        if ($count++ > 20) break;;
      }
      $this->syslog( '----', __LINE__, "Senate Bills found: {$count}");
      /////////////////////////////////////////////////

      if (0) foreach ( $records as $dummyindex => $record ) {
        $referrers->fetch($record['url'],'url');
        $records[$dummyindex]['referrers'] = $referrers->referrers('url');
      }
    }

    $_SESSION['last_fragment'] = $fragment;

    $json_reply = array(
      'count' => count($records),
      'records' => $records,
      'regex' => $components,
      'retainoriginal' => TRUE,
    );

    $this->exit_cache_json_reply($json_reply,get_class($this));

  }/*}}}*/


}

