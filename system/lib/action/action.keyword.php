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

    $this->syslog( __FUNCTION__, __LINE__, "(marker) ----------------------------------");
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked from {$_SERVER['REMOTE_ADDR']}" );
		$this->recursive_dump($_POST,"(marker)");

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

		$limit_per_document = 100;

    if ( !(array_element($_SESSION,'last_fragment') == $fragment) && count($components) > 0 ) {

      $this->recursive_dump($components,__LINE__);
      $components = array_filter($components[1]);
      $components = join('(.*)', $components);

      $iterator = new RepublicActDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'title'  => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records["Z1{$record['sn']}.{$record['congress_tag']}"] = array(
          'title'       => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['title']),
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Republic Acts',
          'url'         => $iterator->remap_url($record['url']),
        );
 				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Republic Acts found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new HouseBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'sn'     => "REGEXP '({$components})'",
        )))->
				order(array('create_time' => 'DESC'))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records["{$record['sn']}.{$record['congress_tag']}"] = array(
          'title'       => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['title']),
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'House Bills',
          'url'         => $iterator->remap_url($record['url']),
        );
 				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) House Bills found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new SenateBillDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description'        => "REGEXP '({$components})'",
          'comm_report_info'   => "REGEXP '({$components})'",
          'main_referral_comm' => "REGEXP '({$components})'",
          'subjects'           => "REGEXP '({$components})'",
          'title'              => "REGEXP '({$components})'",
          'sn'                 => "REGEXP '({$components})'",
        )))->
				order(array('create_time' => 'DESC'))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records["{$record['sn']}"."{$record['congress_tag']}"] = array(
          'title'       => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['title']),
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
          'category'    => 'Senate Bills',
          'url'         => $iterator->remap_url($record['url']),
        );
				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Senate Bills found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new SenateResolutionDocumentModel();
      $iterator->
        where(array('OR' => array(
          'description' => "REGEXP '({$components})'",
          'subjects' => "REGEXP '({$components})'",
          'legislative_history' => "REGEXP '({$components})'",
          'title' => "REGEXP '({$components})'",
          'sn'                 => "REGEXP '({$components})'",
        )))->
				order(array('create_time' => 'DESC'))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
        $records["{$record['sn']}"] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
					'category'    => 'Senate Resolutions',
          'url'         => $iterator->remap_url($record['url']),
        );
				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Resolutions found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new SenatorDossierModel();
      $iterator->
        where(array('OR' => array(
          'fullname' => "REGEXP '({$components})'",
        )))->
				order(array('create_time' => 'DESC'))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
				// Remap a Representative name
				$record = array(
					'description' => "Senator {$record['fullname']}",
					'sn'          => $record['fullname'],
          'hash'        => UrlModel::get_url_hash($record['bio_url']),
					'url'         => $iterator->remap_url($record['bio_url']),
				);
        $records["A0-{$record['sn']}"] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
					'category'    => 'Senators',
          'url'         => $iterator->remap_url($record['url']),
        );
				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Senators found: {$count}");
      /////////////////////////////////////////////////
      $iterator = new RepresentativeDossierModel();
      $iterator->
        where(array('OR' => array(
          'fullname' => "REGEXP '({$components})'",
        )))->
				order(array('create_time' => 'DESC'))->
        recordfetch_setup();
      $record = array();
      $count = 0;
      while ( $iterator->recordfetch($record) ) {
				// Remap a Representative name
				$record = array(
					'description' => "Congressman, {$record['bailiwick']}",
					'sn'          => $record['fullname'],
          'hash'        => UrlModel::get_url_hash($record['bio_url']),
					'url'         => $iterator->remap_url($record['bio_url']),
				);
        $records["A0-{$record['sn']}"] = array(
          'description' => preg_replace('@('.$components.')@i','<span class="hilight">$1</span>', $record['description']),
          'sn'          => $record['sn'],
          'hash'        => UrlModel::get_url_hash($record['url']),
					'category'    => 'Representatives',
          'url'         => $iterator->remap_url($record['url']),
        );
				if ($count++ > $limit_per_document) {
					krsort($records);
					break;
				}
      }
      $this->syslog( __FUNCTION__, __LINE__, "(marker) Representatives found: {$count}");
      /////////////////////////////////////////////////

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

