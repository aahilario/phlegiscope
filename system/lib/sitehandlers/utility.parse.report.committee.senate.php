<?php

/*
 * Class SenateCommitteeReportParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateCommitteeReportParseUtility extends SenateJournalParseUtility {
  
  function __construct() {
    parent::__construct();
  }

  function parse_activity_summary(array & $committee_report_data) {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - - Invoking parser.");
      $this->recursive_dump($committee_report_data, __METHOD__ );
    }

    $pagecontent = '';

    $test_url = new UrlModel();

    // TODO:  Deal with this redundancy later.
    $committee_report = array(
      'content'        => json_encode($committee_report_data),
      'date_filed'     => 0,
      '__committees__' => array(),
      '__documents__'  => array(),
    );

    $section_name = NULL;
    $committee = new SenateCommitteeModel();

    foreach ( $committee_report_data as $n => $e ) {/*{{{*/

      if ( array_key_exists('metadata',$e) ) {/*{{{*/

        $e = $e['metadata'];
        $pagecontent .= <<<EOH
<br/>
<div>
{$e['congress']} Congress Committee Report #{$e['report']}<br/>
Filed: {$e['filed']}<br/>
</div>
EOH;
        $committee_report['congress_tag'] = preg_replace('@[^0-9]@i','', $e['congress']);
        $committee_report['sn'] = $e['report'];
        $committee_report['date_filed'] = empty($e['n_filed']) ? 0 : $e['n_filed'];

        continue;
      }/*}}}*/

      if ( intval($n) == 0 ) {/*{{{*/
        if ( is_array(array_element($e,'content')) ) foreach ($e['content'] as $entry) {/*{{{*/
          if ( array_key_exists('url',$entry) ) {/*{{{*/
            $properties = array('legiscope-remote','journal-pdf');
            $url = array_element($entry,'url');
            $document_is_cached = $test_url->is_cached($url);
            $properties[] = $document_is_cached ? 'cached' : 'uncached';
            $properties = join(' ', $properties);
            $urlhash = UrlModel::get_url_hash($url);
            $pagecontent .= <<<EOH
<b>{$e['section']}</b>  (<a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">PDF</a>)<br/>
EOH;
            $committee_report['doc_url'] = $entry['url'];
            $committee_report['doc_urlid'] = $document_is_cached ? $test_url->get_id() : NULL;
            continue;
          }/*}}}*/
          if ( !(FALSE == strtotime($entry['text']) ) ) {/*{{{*/
            $pagecontent .= <<<EOH
Published {$entry['text']}<br/><br/>
EOH;




            continue;
          }/*}}}*/
        }/*}}}*/
        continue;
      }/*}}}*/
      $section_name = $e['section'];
      $pagecontent .= <<<EOH
<br/>
<b>{$e['section']}</b>
<br/>
EOH;
      if ( 1 == preg_match('@remark@i', $e['section'])) {/*{{{*/
        // Reporting committees
        $lines = array();
        foreach ( $e['content'] as $line ) {
          $lines[] = <<<EOH
<li>{$line}</li><br/>

EOH;
        }
        $lines = join(' ',$lines);
        $pagecontent .= <<<EOH
<ul>{$lines}</ul>
EOH;
        continue;
      }/*}}}*/
      // Reporting committees
      if ( 1 == preg_match('@reporting committee@i', $e['section'])) {/*{{{*/
        $lines = array();
        foreach ( $e['content'] as $committee_name ) {
          $committee->fetch_by_committee_name($committee_name);
          $properties = array('legiscope-remote');
          if ( $committee->in_database() ) {
            $committee_name = $committee->get_committee_name();
            $committee_url = SenateCommitteeListParseUtility::get_committee_permalink_uri($committee_name);
            $committee_report['__committees__'][$committee->get_id()] = array(
              'id'             => $committee->get_id(),
              'committee_name' => $committee_name,
            );
            $properties[] = 'cached';
            $properties = join(' ', $properties);
            $urlhash = UrlModel::get_url_hash($committee_url);
            $lines[] = <<<EOH
<li><a id="{$urlhash}" class="{$properties}" href="/{$committee_url}">{$committee_name}</a></li>

EOH;
          } else {
            $properties = join(' ', $properties);
            $lines[] = <<<EOH
<li>{$committee_name}</li>

EOH;
          }
        }
        $lines = join(' ',$lines);
        $pagecontent .= <<<EOH
<ul>{$lines}</ul>

EOH;
        continue;
      }/*}}}*/
      $lines = array();
      $sorttype = NULL;
      $sn_regex = '@^(.*)-([0-9]*)@i';
      if ( is_array($e['content'])) foreach ($e['content'] as $entry) {/*{{{*/
        $properties = array('legiscope-remote','suppress-reorder');
        $matches = array();
        $title = $entry['text'];
        $pattern = '@^([^:]*)(:| - )(.*)@i';
        // Generate markup and tabulate document links (by document type),
        // the latter being used to create Joins to this Committee Report
        if ( 1 == preg_match($pattern, $title, $matches) ) {
          $title = $matches[1];
          $desc = $matches[3];
          $is_cached = $test_url->is_cached($entry['url']);
          $properties[] = $is_cached ? 'cached' : 'uncached';
          $properties = join(' ', $properties);
          $urlhash = UrlModel::get_url_hash($entry['url']);
          $sortkey = preg_replace('@[^0-9]@','',$title);
          if ( is_null($sorttype) )
            $sorttype = 0 < intval($sortkey) ? SORT_NUMERIC : SORT_REGULAR;
          $sortkey = 0 < intval($sortkey) ? intval($sortkey) : $title;
          $lines[$sortkey] = <<<EOH
<li><a id="{$urlhash}" class="{$properties}" href="{$entry['url']}">{$title}</a>: {$desc}</li>

EOH;
          // Because Senate documents are parsed here, we also store links between
          // this Committee report and those documents here.
          if ( $is_cached && ( 1 == preg_match('@^matters reported@i', $section_name ) ) ) {
            $sn_components = array();
            $q_component  = $test_url->get_query_element('q');
            $congress_tag = $test_url->get_query_element('congress');
            if ( 1 == preg_match($sn_regex, $q_component, $sn_components) ) {
              // Construct a nested array, committees may deliberate more than
              // one type of document in proceedings recorded in a report.
              if ( !array_key_exists($sn_components[1], $committee_report['__documents__']) ) $committee_report['__documents__'][$sn_components[1]] = array();
              $committee_report['__documents__'][$sn_components[1]][$sn_components[2]] = array(
                'congress_tag' => $congress_tag,
                'doctype' => $sn_components[1],
                'sn_suffix' => $sn_components[2], 
              );
            }
          } else {
            // FIXME: Trigger a fetch event on this URL, either by
            // FIXME: a)  Enabling a 'click' event on the link, identified by the hash value of it's target URL; or
            // FIXME: b)  Executing a series of server side POST requests 
          }
        }
      }/*}}}*/
      ksort($lines,$sorttype);
      $lines = join(' ',$lines);
      $pagecontent .= <<<EOH
<ul>{$lines}</ul>
EOH;

    }/*}}}*/

    $pagecontent .= <<<EOH
<br/>
<hr/>
EOH;

    if ( $debug_method ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Final committable report data");
      $this->recursive_dump($committee_report,'(marker) Report');
    }

    $committee_report_data = $committee_report;

    return $pagecontent;

  }/*}}}*/

}

