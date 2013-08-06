<?php

/*
 * Class SenateRootnode
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SenateRootnode extends GlobalRootnode {
  
  function __construct($request_uri) {
    parent::__construct($request_uri);
    $this->debug_memory_usage_delta = TRUE;
    $this->register_derived_class();
  }

  function committee($uri) {/*{{{*/

    ob_start();

    $committee = new SenateCommitteeModel();

    $zeroth    = array_shift($uri);

    $markup = "Index for " . __FUNCTION__;

    $this->syslog(__FUNCTION__,__LINE__,"(marker) Zeroth '{$zeroth}'");

    if ( 0 < strlen($zeroth) ) {/*{{{*/
      $leading   = SenateCommitteeListParseUtility::committee_permalink_to_regex($zeroth);
      $n         = array_flip(array_keys($committee->get_joins()));

      array_walk($n,create_function('& $a, $k', '$a = array("primary" => array(), "secondary" => array());'));

      $markup = <<<EOH
<h2>{committee_name}</h2>
<hr/>
<p>{jurisdiction}</p>
<h3>Leadership</h3>
<p><a class="legiscope-remote" href="{senator.bio_url}">{senator.fullname}</a> ({senator:role})</p> 
<h3>Legislation</h3>
<h4>Primary</h4>
<h4>Secondary</h4>
EOH;

      $element = $leading;

      $k = 0;

      if ( $committee->cursor_fetch_by_name_regex($element) ) do {

        if ( ($k == 0) || $debug_method ) {
          $this->syslog( __FUNCTION__, __LINE__, "(marker) '{$zeroth}' {$element['committee_name']} #{$element['id']}" );
          $markup = $committee->substitute($markup);
        }

        $senate_bill_jointype = nonempty_array_element($element['senate_bill'],'join');
        $jointype             = nonempty_array_element($senate_bill_jointype,'referral_mode');
        $senate_bill_datatype = nonempty_array_element($element['senate_bill'],'data');
        $senate_bill_id       = nonempty_array_element($senate_bill_datatype,'id',0);

        if ( !is_null($jointype) && !is_null($senate_bill_id) )
          $n['senate_bill'][$jointype][$senate_bill_id] = NULL;

        $senate_bill_jointype = nonempty_array_element($element['senate_housebill'],'join');
        $jointype             = nonempty_array_element($senate_bill_jointype,'referral_mode');
        $senate_bill_datatype = nonempty_array_element($element['senate_housebill'],'data');
        $senate_bill_id       = nonempty_array_element($senate_bill_datatype,'id',0);

        if ( !is_null($jointype) && !is_null($senate_bill_id) )
          $n['senate_housebill'][$jointype][$senate_bill_id] = NULL;

        $k++;

        $element = NULL;
        if ( $k > 5 ) break;
      } while ( $committee->recordfetch($element,TRUE) );

      unset($n['senator']);

      array_walk($n, create_function(
        '& $a, $k', '$a["primary"] = count($a["primary"]); $a["secondary"] = count($a["secondary"]);'
      ));

      $joins = $committee->get_joins();

      $this->recursive_dump($this->node_uri,"(marker) ---");
      $this->recursive_dump($joins,"(marker) - - -");
      $this->recursive_dump($n,"(marker) -<>- -");

    }/*}}}*/

    $target_url = '/' . join('/',$this->node_uri);

    $json_reply = array(
      'url'            => $target_url,
      'message'        => '', 
      'httpcode'       => 200,
      'retainoriginal' => TRUE,
      'subcontent'     => $markup,
      'defaulttab'     => 'processed',
      'referrer'       => $this->filter_session('referrer'),
      'contenttype'    => 'text/html',
    );

    $output_buffer = ob_get_clean();

    unset($output_buffer);

    $this->exit_cache_json_reply($json_reply,get_class($this));

    exit(0);

  }/*}}}*/

}

