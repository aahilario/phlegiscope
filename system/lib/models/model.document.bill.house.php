<?php

/*
 * Class HouseBillDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class HouseBillDocumentModel extends LegislativeCommonDocumentModel {
  
  var $sn_vc64uniq = NULL;
  var $congress_tag_vc8 = NULL;
  var $description_vc4096 = NULL;
  var $create_time_utx = NULL;
  var $last_fetch_utx = NULL;
  var $searchable_bool = NULL;
  var $url_vc4096 = NULL; // Filed
  var $url_history_vc4096 = NULL; // Whenever available
  var $url_engrossed_vc4096 = NULL;
  var $status_vc1024 = NULL;
  var $content_blob = NULL; //  Current master document, unversioned.  Versioned documents should probably be attached as ContentDocumentModel records.

  // The content of linked documents, e.g. those referred to within House Bill source pages,
  // should probably be stored as Join edges, to facilitate revision marking.
  // That way, only immutable properties are stored in DocumentModel objects;
  // this means edges (relationships between graph nodes) carry payload data
  // aside from their name (which indicates that it is a generic relation); and
  // subclassing *Joins can then allow behaviors to be assigned that manipulate
  // that relationship.  Right now the only shared behaviors that make sense to
  // implement on generic *Joins are stow() and fetch().  I would like to implement
  // a factory subclass that allows a Pending in Committee state (which implies
  // a start date) to implement a schedule_reading($on_date) method, which marks
  // the Pending state as having ended (marked with an end date), and that additionally
  // generates a new *Join record (graph edge) with a scheduled[date] reading stage[nth reading]. 

  //var $date_read_utx = NULL;
  //var $house_approval_date_utx = NULL;
  //var $significance_vc16 = NULL;

  var $housebill_HouseBillDocumentModel = NULL;              // Reference to other house bills, indicating the relationship (e.g. substitution).
  var $republic_act_RepublicActDocumentModel = NULL;         // Republic Act toward which this House Bill contributed essential language and intent.
  var $representative_RepresentativeDossierModel = NULL;     // Several types of association between a house bill and representatives (authorship, etc.).
  var $committee_CongressionalCommitteeDocumentModel = NULL; // Reference to Congressional Committees, bearing the status of a House Bill, or the principal committee.
  var $ocrcontent_ContentDocumentModel = NULL; // Used here to contain OCR versions  

  function __construct() {/*{{{*/
    parent::__construct();
  }/*}}}*/

  function & set_content($v) { $this->content_blob = $v; return $this; }
  function get_content($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->content_blob; }

  function & set_ocrcontent($v) { $this->ocrcontent_ContentDocumentModel = $v; return $this; }
  function get_ocrcontent($v = NULL) { if (!is_null($v)) $this->set_content($v); return $this->ocrcontent_ContentDocumentModel; }

  function & set_status($meta) {/*{{{*/
    $this->status_vc1024 = is_array($meta)
      ?  json_encode($meta)
      : $meta
      ;
    if ( FALSE == $this->status_vc1024 ) $this->status_vc1024 = json_encode(array());
    return $this;
  }/*}}}*/
  function get_status($as_array = TRUE) {/*{{{*/
    if ( is_null($this->status_vc1024) ) {
      $parsed = $as_array ? array() : NULL;
    } else if ( is_array($this->status_vc1024) ) {
      $parsed = @json_encode($this->status_vc1024);
      if ( FALSE == $parsed ) {
        $parsed = $as_array ? array() : NULL;
      } else {
        $this->status_vc1024 = $parsed;
        if ( $as_array ) $parsed = @json_decode($parsed,TRUE);
      }
    } else if ( is_string($this->status_vc1024) ) {
      $parsed = $as_array ? @json_decode($this->status_vc1024,TRUE) : $this->status_vc1024;
      if ( FALSE === $parsed ) {
        $parsed = $as_array ? array() : NULL;
      }
    }
    return $parsed;
  }/*}}}*/

  function & set_sn($v) { $this->sn_vc64uniq = $v; return $this; }
  function get_sn($v = NULL) { if (!is_null($v)) $this->set_sn($v); return $this->sn_vc64uniq; }

  function & set_create_time($v) { $this->create_time_utx = $v; return $this; }
  function get_create_time($v = NULL) { if (!is_null($v)) $this->set_create_time($v); return $this->create_time_utx; }

  function & set_last_fetch($v) { $this->last_fetch_utx = $v; return $this; }
  function get_last_fetch($v = NULL) { if (!is_null($v)) $this->set_last_fetch($v); return $this->last_fetch_utx; }

  function & set_searchable($v) { $this->searchable_bool = $v; return $this; }
  function get_searchable($v = NULL) { if (!is_null($v)) $this->set_searchable($v); return $this->searchable_bool; }

  function & set_description($v) { $this->description_vc4096 = $v; return $this; }
  function get_description($v = NULL) { if (!is_null($v)) $this->set_description($v); return $this->description_vc4096; }

  function & set_congress_tag($v) { $this->congress_tag_vc8 = $v; return $this; }
  function get_congress_tag($v = NULL) { if (!is_null($v)) $this->set_congress_tag($v); return $this->congress_tag_vc8; }

  function & set_url($v) { $this->url_vc4096 = $v; return $this; }
  function get_url($v = NULL) { if (!is_null($v)) $this->set_url($v); return rtrim($this->url_vc4096,'/'); }  

  function & set_url_history($v) { $this->url_history_vc4096 = $v; return $this; }
  function get_url_history($v = NULL) { if (!is_null($v)) $this->set_url_history($v); return $this->url_history_vc4096; }

  function & set_url_engrossed($v) { $this->url_engrossed_vc4096 = $v; return $this; }
  function get_url_engrossed($v = NULL) { if (!is_null($v)) $this->set_url_engrossed($v); return $this->url_engrossed_vc4096; }

  // function & set_date_read($v) { $this->date_read_utx = $v; return $this; }
  // function get_date_read($v = NULL) { if (!is_null($v)) $this->set_date_read($v); return $this->date_read_utx; }

  // function & set_house_approval_date($v) { $this->house_approval_date_utx = $v; return $this; }
  // function get_house_approval_date($v = NULL) { if (!is_null($v)) $this->set_house_approval_date($v); return $this->house_approval_date_utx; }

  // function & set_significance($v) { $this->significance_vc16 = $v; return $this; }
  // function get_significance($v = NULL) { if (!is_null($v)) $this->set_significance($v); return $this->significance_vc16; }

  ///////////////////////////////////////////////////////////////////////

  function resolve_housebill_status_referents(& $bill_cache) {/*{{{*/
    $substitutions = array_filter(array_map(create_function('$a','return is_array(nonempty_array_element($a["meta"],"status")) ? $a["meta"]["status"] : NULL;'),$bill_cache));
    $suffixes      = array();
    // Get substitution SN parts (whole, prefix, pad zeroes, and )
    $this->filter_nested_array($substitutions,"sn[referent*=substituted-by]");
    foreach ( $substitutions as $bill_cache_key => $sn_parts ) {
      $suffix = array_element($sn_parts,3);
      $bill_cache[$bill_cache_key]['meta']['status'] = nonempty_array_element($bill_cache[$bill_cache_key]['meta']['status'],'original','---');
      if ( empty($suffix) ) continue;
      if ( !array_key_exists($suffix, $suffixes) ) {
        $suffixes[$suffix] = array(
          'id' => NULL,
          'bills' => array()
        );
      }
      // Record the distinct SNs affected by this ID assignment
      $suffixes[$suffix]['bills'][] = $bill_cache_key;
    }
    ksort($suffixes);

    // Find House Bill record matching SNs of the substitutes, keep 
    // their record IDs in $suffixes[$suffix]['id']
    $regex    = join('|', array_keys($suffixes));
    $regex    = "^([A-Z]*)([0]{1,})({$regex})$";
    $this->where(array('AND' => array('sn' => "REGEXP '{$regex}'")))->recordfetch_setup();
    $document = array();
    $regex    = "^([A-Z]*)([0]{1,})([0-9]*)$";
    while ( $this->recordfetch($document) ) {
      $matches = array();
      $sn      = array_element($document,'sn');
      if ( empty($sn) ) continue;
      if (!(1 == preg_match("@{$regex}@i", $sn, $matches))) continue;
      $suffix = array_element($matches,3);
      if ( empty($suffix) ) continue;
      if ( array_key_exists($suffix, $suffixes) ) {
         $suffixes[$suffix]['id'] = $document['id'];
      }
    }

    // Create Join record specifying foreign table ID in fkey, e.g.
    // Array ( 'fkey' => < ID of substitute HB record >, <HouseBillHouseBillJoin attrs> )
    foreach ( $suffixes as $suffix => $map ) {
      if ( is_null($bill_id  = array_element($map,'id')) ) continue;
      $bill_sns = array_element($map,'bills');
      foreach ( $bill_sns as $sn ) {
        $bill_cache[$sn]['housebill'] = array(
          'fkey' => $bill_id,
          'congress_tag' => array_element($bill_cache[$sn],'congress_tag'),
          'jointype' => 'substituted-by'
        );
        // $this->recursive_dump($bill_cache[$sn],"(marker) -- {$sn} --");
      }
    }
  }/*}}}*/

  function final_cleanup_parsed_housebill_cache(& $a, $k) {/*{{{*/

    $debug_method = FALSE;
    // Move nested array elements into place, to allow use of 
    // set_contents_from_array()
    array_walk($a['links'],create_function('& $a, $k', '$a = rtrim($a,"/");'));

    $links = array_element($a,"links",array());
    $meta  = array_element($a,"meta",array());

    $a["url"]           = array_element($links,"filed");
    $a["url_engrossed"] = array_element($links,"engrossed");
    $a["url_history"]   = array_element($links,"url_history");
    $a["status"]        = array_element($meta,"status");
    $a["create_time"]   = time();

    // Obtain principal author record ID
    if ( !is_null(($principal_author = array_element($meta,'principal-author'))) ) {/*{{{*/
      $original_name = $principal_author;
      $mapped = $this->
        get_foreign_obj_instance('representative')->
        replace_legislator_names_hotlinks($principal_author);
      $mapped = !is_null($mapped) ? array_element($principal_author,'id') : NULL;
      if ( !is_null($mapped) ) {
        $a['representative'] = array(
          'relation_to_bill' => 'principal-author',
        );
        $a['meta']['principal-author'] = array(
          'raw' => $original_name,
          'new' => "{$original_name} ({$principal_author['fullname']})",
          'parse' => $principal_author,
          'mapped' => $mapped,
        );
      }
    }/*}}}*/

    // Obtain primary committee record ID
    if ( !is_null(($main_committee = array_element($meta,'main-committee'))) ) {/*{{{*/

      $a['committee'] = array(
        'jointype' => 'main-committee',
      );

      // Perform tree search (PHP assoc array), as it will be inefficient to 
      // try to look up each name as it recurs in the input stream.
      // We'll assume that there will be (at most) a couple hundred distinct committee names
      // dealt with here, so we can reasonably generate a lookup table
      // of committee names containing name match regexes and committee record IDs.
      $name = $main_committee;
      if ( !array_key_exists($name,$this->committee_regex_lookup) ) {/*{{{*/
        // Test for a full regex match against the entire lookup table 
        // before adding the committee name and regex pattern to the tree
        $committee_name_regex = LegislationCommonParseUtility::committee_name_regex($name);
        if ( 0 < count($this->committee_regex_lookup) ) {/*{{{*/
          $m = array_filter(array_combine(
            array_keys($this->committee_regex_lookup),
            array_map(create_function(
              '$a', 'return 1 == preg_match("@" . array_element($a,"regex") . "@i","' . $name . '") ? $a : NULL;'
            ),$this->committee_regex_lookup)
          )); 
          $n = count($m);
          if ( $n == 0 ) {
            // No match, probably a new name, hence no ID yet found
            $mapped = NULL;
          } else if ( $n == 1 ) {
            // Matched exactly one name, no need to create new entry
            $name = NULL;
            $mapped = array_element($m,'id');
          } else {
            // Matched multiple records
            $mapped = $m;
          }
        }/*}}}*/
        if ( !is_null($name) ) $this->committee_regex_lookup[$name] = array(
          'committee_name' => $name,
          'regex'          => $committee_name_regex,
          'id'             => 'UNMAPPED'
        );
      }/*}}}*/
      else {
        // Assign an existing ID. This branch is executed when
        // the committee name regexes are updated before each call
        // to this iterator.
        $mapped = array_element($this->committee_regex_lookup[$name],'id');
      }
      $a['meta']['main-committee'] = array(
        'raw' => $main_committee,
        'mapped' => $mapped,
      );
    }/*}}}*/

    // Parse 'Status' field.  This is messy, and relies on fixed regex matches
    // on mutable input.
    if ( !is_null(($status = array_element($meta,'status'))) ) {/*{{{*/

      // FIXME: Contrive to transfer this code to CongressHbListParseUtility.
      // Generate Join elements in [housebill] or [republic_act], depending
      // on the result of parsing this single [status] string.
      $status_map_regex = array(
        '^approved by the committee (on .* )?on ([-0-9]*)'                                                         => 'approved-by-committee|$2',
        '^approved by the house on ([-0-9]*), transmitted to on ([-0-9]*) and received by the Senate on ([-0-9]*)' => 'transmitted-to-senate|$1|$2|$3',
        '^Approved by the House on ([-0-9]*) and transmitted to the Senate on ([-0-9]*)'                           => 'transmitted-to-senate|$1|$2|',
        '^pending with the committee on (.*) since ([-0-9]*)'                                                      => 'pending-with-committee|$1|$2',
        '^bill pending with (.*) \(.*\)'                                                                           => 'pending-with-committee|$1|$2',
        '^vetoed by the president on([-0-9 ]*)'                                                                    => 'vetoed|$1',
        '^Under deliberation by (.*) on ([-0-9]*)'                                                                 => 'under-deliberation|$2',
        '^transm.*itted to the committee on (.*) on ([-0-9]*)'                                                     => 'transmitted-to-committee|$1|$2',
        '^substituted by ([A-Z0-9]*)'                                                                              => 'substituted-by|$1',
        '^consolidated into ([A-Z0-9]*)'                                                                           => 'consolidated-into|$1',
        '^republic act \(?([A-Z0-9]*)\)? (enacted on ([-0-9]*))*'                                                  => 'republic-act|$1|$3',
        '^adopted resolution \(pending with the committee on (.*) since ([-0-9]*)\)'                               => 'adopted-pending|$1|$2',
        '^passed by the senate (with amendments )* on ([-0-9]*)'                                                   => 'passed-by-senate|$2',
        '^printed copies distributed .* on ([-0-9]*)'                                                              => 'distributed|$1',
        '^unfinished business \(period of (.*)\)'                                                                  => 'unfinished-biz|$1',
        '^change of committee referral requested on ([-0-9]*)'                                                     => 'referral-change|$1',
        '^referred to stakeholders on ([-0-9]*)'                                                                   => 'to-stakeholders|$1',
        '^passed by the senate .* on ([-0-9]*)'                                                                    => 'passed-senate|$1',
        '^Under study by .*TWG.* on ([-0-9]*)'                                                                     => 'under-twg-study|$1',
      );

      $status_remapped = preg_replace(
        array_map(create_function('$a','return "@{$a}@i";'), array_keys($status_map_regex)),
        array_values($status_map_regex),
        $status
      );

      $state        = explode('|', $status_remapped);
      $referent     = array_element($state,0);
      switch( $referent ) {
        case 'pending-with-committee':
          // Uninteresting, unless the committee isn't the primary referral destination.
          $committee_name = nonempty_array_element($state,1);
          if ($debug_method) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) ------- {$state} {$a['sn']}: {$status_remapped} ---- {$status}");
            $this->recursive_dump($a,"(marker) --");
          }
          break;
        case 'substituted-by':
          $substitution_sb = nonempty_array_element($state,1);
          $subst_sn_parts = array();
          $subst_sn_regex = '@^([A-Z]*)([0]*)([0-9]*)@i';
          if ( 1 == preg_match($subst_sn_regex,$substitution_sb,$subst_sn_parts) ) {
            $a['meta']['status'] = array(
              'original'     => $status,
              'subst_prefix' => array_element($subst_sn_parts,1),
              'referent'     => $referent,
              'sn'           => $subst_sn_parts, // Element 0 should contain the original
              'mapped'       => NULL
            );
            if ($debug_method) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) ------- {$state} {$a['sn']}: {$status_remapped} ---- {$status}");
              $this->recursive_dump($a['meta']['substituted-by'],"(marker) --");
            }
          }
          break;
        case 'republic-act':
          $republic_act = nonempty_array_element($state,1);
          $enactment_date = nonempty_array_element($state,2);
          $a['meta']['status'] = array(
            'original'     => $status,
            'referent'     => $referent,
            'sn'           => $republic_act,
            'enactment'    => $enactment_date,
            'mapped'       => NULL,
          );
          if (TRUE||$debug_method) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) ------- {$state} {$a['sn']}: {$status_remapped} ---- {$status}");
            $this->recursive_dump($a['meta']['substituted-by'],"(marker) --");
          }
          break;
        default:
          $state = ( $status_remapped == $status ) ? "UNMAPPED" : "Status";
          if ($debug_method) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) ------- {$state} {$a['sn']}: {$status_remapped} ---- {$status}");
          }
          break;
      }

    }/*}}}*/

    return TRUE;
  }/*}}}*/

  function cache_parsed_records(& $bill_cache_source, $congress_tag, $from_network) {/*{{{*/

    $this->committee_regex_lookup = array();

    $debug_method = FALSE;

    if ( !is_array($bill_cache_source) ) {
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Nothing parsed. Leaving.");
       return;
    }

    $bill_cache = array_map(create_function('$a','return array_element($a,"sn");'),$bill_cache_source);

    if (is_array($bill_cache) && (0 < count($bill_cache))) {
      $bill_cache = array_combine(
        $bill_cache,
        $bill_cache_source
      );
    } else return;

    // Transform [meta] records, by moving their content into appropriate
    // Join property containers.
    //
    // The reasons that we defer assigning those attributes during parse are that
    //
    // 0) The attributes we are storing / preparing for storage are tied to
    //    neither Committee nor Representative models, but in the relationship
    //    between the House Bill and the objects that wrap those two concepts;
    //
    // 1) Preparing the parsed data is a Join-specific action that may occur
    //    in contexts other than during parsing new House Bill records;  
    //    writing shorter methods will ease their reuse elsewhere; and
    //
    // 2) The parsing algorithm is simplified by deferring updating the
    //    Committee table foreign key until just before this method is called,
    //    so we can only move nested array members around here, to allow us
    //    to call set_contents_from_array($record) later.

    // Add last_fetch and congress_tag attributes
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', 'if ( $s["n"] ) $a["last_fetch"] = time(); if ( is_null(array_element($a,"congress_tag"))) $a["congress_tag"] = $s["c"];'
    ),array('n' => $from_network, 'c' => intval($congress_tag)));

    // Cleanup before omitting preexisting records
    // - Also, populate the committee name lookup table
    array_walk($bill_cache,create_function(
      '& $a, $k, $s', '$s->final_cleanup_parsed_housebill_cache($a,$k);'
    ), $this);

    // Update the lookup table
    $this->
      get_foreign_obj_instance('committee')->
      update_committee_name_regex_lookup(
        $bill_cache,
        $this->committee_regex_lookup
      );

    // Update status (create Joins with appropriate attributes)
    $this->resolve_housebill_status_referents($bill_cache);
    $this->get_foreign_obj_instance('republic_act')->resolve_republic_act_consequents($bill_cache,'republic_act');
    $this->get_join_instance('committee')->prepare_cached_records($bill_cache);
    $this->get_join_instance('representative')->prepare_cached_records($bill_cache);
   
    if ( $debug_method ) $this->recursive_dump($bill_cache,"(marker) -- Return to caller --");
    $bill_cache_source = $bill_cache;

    $bill_sns = array_keys($bill_cache);

    // Cleanup extant records

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Searching for matches to Congress {$congress_tag} " . join(',',$bill_sns));

    $this->debug_final_sql = FALSE;
    $this->
      join_all()->
      where(array('AND' => array(
        '`a`.`congress_tag`' => intval($congress_tag),
        '`a`.`sn`' => $bill_sns
      )))->
      recordfetch_setup();
    $this->debug_final_sql = FALSE;

    $hb                    = array();
    $debug_checker         = FALSE;
    $join_exclusions       = $this->get_joins();

    if ( $debug_checker) $this->recursive_dump($join_exclusions,"(marker) -- JOINS --");

    while ( $this->recordfetch($hb,TRUE) ) {/*{{{*/
      
      // TODO: If an extant Join matches what has been passed in, then remove both
      // TODO: If no data has been modified, remove the bill_cache entry
      $sn = array_element($hb,'sn');
      if ( $debug_checker ) {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) --- - --- For {$hb['sn']} --- - ---");
        // $this->recursive_dump($hb,"(marker) --- DB ---");
      }/*}}}*/
      if ( array_key_exists($sn,$bill_cache) ) {
        unset($bill_cache[$sn]['links']);
        unset($bill_cache[$sn]['meta']);
        $bill_cache[$sn]['id'] = $hb['id'];
        unset($bill_cache[$sn]['create_time']);
        ksort($bill_cache[$sn]);

        // Clear empty Join attributes in the DB record
        array_walk($hb,create_function(
          '& $a, $k, $s', 'if ( array_key_exists($k, $s) ) { $a = array_filter($a); if ( empty($a) ) $a = NULL; }'
        ), $join_exclusions);  

        // Set missing congress_tag attribute in the DB Join records
        array_walk($bill_cache[$sn],create_function(
          '& $a, $k, $s', 'if ( array_key_exists($k, $s["j"]) && is_null(array_element($a,"congress_tag"))) $a["congress_tag"] = $s["c"];'
        ), array('j' => $join_exclusions, 'c' => intval($congress_tag)));  

        $hb = array_filter($hb);

        // Get keys common to structure and parsed content
        $intersection = array_intersect_key(
          $hb,
          $bill_cache[$sn]
        );

        if ( $debug_checker ) $this->recursive_dump($hb,"(marker) -- intersect - {$sn}.{$hb['congress']} #{$hb['id']} - --");
        ksort($intersection);

        // Get difference between parsed record and existing DB record
        $delta = array_diff(
          $bill_cache[$sn],
          $intersection
        );
        if ( 0 < count($delta) ) {
          if ( $debug_method ) {/*{{{*/
            $this->syslog(__FUNCTION__,__LINE__,"(marker) --- - --- For {$hb['sn']} --- - ---");
            $this->recursive_dump($bill_cache[$sn],"(marker) -- parse - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
            $this->recursive_dump($hb,"(marker) -- inter - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
            $this->recursive_dump($delta,"(marker) -- delta - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
          }/*}}}*/
        }
        else {
          if ( $debug_checker ) $this->recursive_dump($bill_cache[$sn],"(marker) -- skip - {$sn}.{$hb['congress_tag']} #{$hb['id']} - --");
          unset($bill_cache[$sn]);
        }
      }
      else {
        $bill_cache[$sn]['create_time'] = time();
        $this->syslog(__FUNCTION__,__LINE__,"(marker) --!!! No match for {$hb['sn']}");
      }
    }/*}}}*/

    if ( $debug_checker ) $this->syslog(__FUNCTION__,__LINE__,"(marker) Matches: " . count($bill_cache));

    $bill_cache = array_filter($bill_cache);

    // Stow Joins between each bill and main committee

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump($this->fetch_combined_property_list(), "(marker) P  - - - - -");
      $this->syslog(__FUNCTION__,__LINE__,"(marker) Storing " . join(', ', array_keys($bill_cache)));
    }/*}}}*/

    // Store records not found in DB or that are updated

    while ( 0 < count($bill_cache) ) {/*{{{*/

      $cache_entry = array_filter(array_pop($bill_cache)); 

      if (!is_null(array_element($cache_entry,'republic_act'))) {
      }
      else if ((is_null(array_element($cache_entry,'url')) || empty($cache_entry['url']) ) && 
        (is_null(array_element($cache_entry,'url_history')) || empty($cache_entry['url_history']) ) &&
        (is_null(array_element($cache_entry,'url_engrossed')) || empty($cache_entry['url_engrossed']) )
      ) {
        $this->syslog(__FUNCTION__,__LINE__,"(error) No URL; entry is not traceable to a source.");
        $this->recursive_dump($cache_entry,"(marker) - - -");
        continue;
      }

      unset($cache_entry['links']);
      unset($cache_entry['meta']);

      if (is_null(array_element($cache_entry,'congress_tag'))) {
        $this->syslog(__FUNCTION__,__LINE__,"(error) No Congress number available. Cannot stow entry.");
        $this->recursive_dump($cache_entry,"(marker) - - -");
        continue;
      }

      if ( $debug_method ) {
        $this->recursive_dump($cache_entry, "(marker) E {$cache_entry['sn']}:{$cache_entry['id']} - - - - -");
      }

      // Ensure that existing records are loaded before update. 
      $bill_id      = array_element($cache_entry,'id');
      $sn           = array_element($cache_entry,'sn');
      $congress_tag = array_element($cache_entry,'congress_tag');

      if (!is_null($congress_tag) && !is_null($sn)) {
        $this->
          set_id(NULL)->
          where(array('AND' => array(
            'sn' => $sn,
            'congress_tag' => $congress_tag,
          )))->recordfetch_setup();
        $bill_id = NULL;
        while ( $this->recordfetch($record,TRUE) ) {
          // Additional records that result from left joins are simply discarded.
          if ( is_null($bill_id) ) {
             $bill_id = $record['id'];
            if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- -- Matched record {$record['sn']}.{$record['congress_tag']} #{$record['id']}");
          }
        }
        if ( is_null($bill_id) ) {
          $this->syslog(__FUNCTION__,__LINE__,"(marker) -- -- New record {$record['sn']}.{$record['congress_tag']}");
        }
      } else {
        $bill_id = NULL;
      }

      $bill_id = $this->
        set_id($bill_id)->
        set_contents_from_array($cache_entry,TRUE)->
        stow();

      if ( 0 < intval($bill_id) ) {
        $error = $this->error();
        if ($debug_method || !empty($error)) $this->syslog(__FUNCTION__,__LINE__,"(marker) -- Stored {$bill_id} {$cache_entry['sn']}.{$cache_entry['congress_tag']}");
      } else {
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- FAILED TO STORE/UPDATE {$cache_entry['sn']}.{$cache_entry['congress_tag']}");
        $this->recursive_dump($cache_entry,"(marker) - - -");
      }
      $cache_entry = NULL;
    }/*}}}*/

    $this->syslog(__FUNCTION__,__LINE__,"(critical) Done with DB updates.");

  }/*}}}*/
    
}
