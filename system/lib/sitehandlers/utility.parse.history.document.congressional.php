<?php

/*
 * Class CongressionalDocumentHistoryParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressionalDocumentHistoryParseUtility extends CongressCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

	function congressional_housebill_history(& $urlmodel) {/*{{{*/

		$debug_method = FALSE;
		// Return the database ID of the House Bill HouseBillDocumentModel
		$this->standard_parse($urlmodel);

		$document = $this->get_containers('children[tagname*=body]',0);
		$contents = nonempty_array_element(array_values($document),0);
		$contents = nonempty_array_element($contents,'text');

		// parse_document_history actually retrieves the database ID of the
		// HouseBillDocumentModel record identified in the history text,
		// using the document SN and Congress session number
		$document = $this->parse_document_history($urlmodel, $contents);

		if ( $debug_method ) $this->recursive_dump($document,'(critical)');

		$document_id = nonempty_array_element($document,'id');

		$hb = new HouseBillDocumentModel();
		$joins = $hb->get_joins();
		$join_elements = array_intersect_key($document, $joins);
		if ( $debug_method ) $this->recursive_dump($join_elements,"(critical) Joins {$document['sn']}"); 

		if ( is_null($document_id) ) {
			$document_id = $hb->
				set_contents_from_array($document)->
				fields(array_keys($document))->
				stow();
		}
		else {
			$this->syslog(__FUNCTION__,__LINE__,"(critical) Record already exists (#{$document_id}). Must scan for changes.");
			$this->recursive_dump($contents,"(critical)");
			$actual = $hb->join_all()->fetch($document_id,'id');
			$this->recursive_dump($actual,"(critical)");
		}

		if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(marker) - Committed record #" . $hb->get_id());

		if ( 0 < intval($document_id) ) { 
			foreach ( $join_elements as $attrname => $join_records ) {
				$propername = nonempty_array_element($joins[$attrname],'propername');
				$full_foreign_name = join('_',array($attrname,join('_',camelcase_to_array($propername))));
				if ( $debug_method ) {
					$this->syslog(__FUNCTION__,__LINE__,"(critical) Testing for duplicates in {$propername}");
					$this->recursive_dump($join_records,"(critical) *");
				}
				// Extract foreign table IDs
				$foreign_obj_ids = array_flip(array_map(create_function(
					'& $a', 'foreach ( $a as $id => $attrs ) return $id;'
				),$join_records));	
				ksort($foreign_obj_ids);
				if ( $debug_method ) $this->recursive_dump($foreign_obj_ids,"(critical) +");
				$hb->
					join(array($attrname))->
					where(array('AND' => array(
						'`a`.`id`' => $document_id,
						"{{$full_foreign_name}}.`id`" => array_keys($foreign_obj_ids),
					)))->
					recordfetch_setup();
				$hb_rec = array();
				while ( $hb->recordfetch($hb_rec,TRUE) ) {
					if ( $debug_method ) {
						$this->syslog(__FUNCTION__,__LINE__,"(critical) Testing against {$hb_rec['sn']} <- {$full_foreign_name}");
						$this->recursive_dump($hb_rec,"(critical) *");
					}
					$data = nonempty_array_element($hb_rec,$attrname);
					$data = nonempty_array_element($data['data'],'id');
					if ( 0 < intval($data) ) {
						$this->syslog(__FUNCTION__,__LINE__,"(critical) Omitting data record #{$data}");
						$join_records[$foreign_obj_ids[$data]] = NULL;
					}
				}	
				$join_records = array_filter($join_records);
				// Commit what records remain in $join_records, after checking for duplicates
				while ( 0 < count($join_records) ) {
					$join_record   = array_shift($join_records);
					if ( $debug_method ) $this->recursive_dump($join_record,"(critical) Record {$attrname}"); 
					$commit_result = $hb->create_joins($propername,$join_record);
					if ( $debug_method ) $this->recursive_dump($commit_result,"(critical) Commitres {$attrname}"); 
				}
			}
		}
		else {
			$this->syslog(__FUNCTION__,__LINE__,"(critical) Unable to match a House Bill document record to the history.");
			$this->recursive_dump($contents,"(critical)");
		}

		return $document_id;

	}/*}}}*/

  function parse_document_history(UrlModel & $urlmodel, $contents) {/*{{{*/

    $debug_method = TRUE;

    if ( !is_array($contents) || 0 == count($contents) ) return NULL;

    if ( $debug_method ) $this->recursive_dump($contents,"(marker)");

    $target_congress = $urlmodel->get_query_element('congress');

    $contents = array_filter($contents);
    // Map document parts into standard document attributes
    $headers = array(
      '@^no. @i' => 'sn',
      '@^full title([ :]*)@i' => 'description',
      '@^significance([ :]*)@i' => 'significance',
      '@^co-authors([ :]*)@i' => '[lines]:representative:co-author',
      '@^by congress[^ ]*[ ]*(.*)@i' => 'representative:principal-author($1)',
      '@^date read([ :]*)(.*)@i' => 'legislative-history:reading-date($2)',
      '@^date filed on([ :]*)(.*)@i' => 'legislative-history:filing-date($2)',
      '@^secondarily referred to the committee([^ ]*) on (.*)@i' => 'committee:committees($2)',
      '@^referral on ([0-9-]*) to the committee on (.*)@i' => 'committee:referral-date($1):committee($2)',
    );
    $document = array('congress_tag' => $target_congress);

    $lines = NULL;

    $in_sublist = FALSE;

    while ( 0 < count($contents) ) {/*{{{*/
      $line = array_shift($contents);
      // Skip empty
      $stripped = trim(preg_replace('@[^-A-Z0-9ñ,. ]@i','',$line));
      if ( 0 == strlen($stripped) ) continue;
      $split_line = explode('|',preg_replace(
        array_keys($headers),
        array_map(create_function('$a','return "{$a}|";'),$headers),
        $line
      ));
      $line_heading = nonempty_array_element($split_line,0);
      $line_content = nonempty_array_element($split_line,1);
      // Test for record property suitable for use as a database attribute name, CSS selector name or PHP variable name
      $cleanheading = preg_replace('@[^A-Z-_]@i','',$line_heading);
      if ( $cleanheading == $line_heading ) {
        $document[$line_heading] = $line_content;
        continue;
      }
      // Expand line headings with embedded data
      $regex_matches = array();
      if ( 1 == preg_match('@^([^:]*):(.*)@i', $line_heading, $regex_matches) ) {/*{{{*/
        array_shift($regex_matches);
        $attribute_name = array_shift($regex_matches); // Attribute name
        // Find out whether the attribute found is followed by lines of data 
        if ( '[lines]' == $attribute_name ) {
          $lines = array_shift($regex_matches);
          continue;
        } else $lines = NULL;
        $properties     = explode(':',array_shift($regex_matches));
        if ( !array_key_exists($attribute_name, $document) ) $document[$attribute_name] = array();
        $document[$attribute_name] = array_merge(
          $document[$attribute_name],
          $properties
        );
        continue;
      }/*}}}*/
      else if ( !is_null($lines) ) {
        $document[$lines][] = $stripped;
      }
    }/*}}}*/

    // Remap contents into an array usable by
    //  DocumentModel::set_contents_from_array()

    $document = $this->cleanup_parsed_record($document);
    $document['url_history'] = $urlmodel->get_url();
    if ( is_null($document['last_fetch']) ) $document['last_fetch'] = $urlmodel->get_last_fetch();

    return $document;

  }/*}}}*/

  function cleanup_parsed_record(& $document) {/*{{{*/

    $debug_method = FALSE;

    if ( $debug_method ) $this->recursive_dump($document,"(marker) Parsed");

    array_walk($document,create_function('& $a, $k, $s', '$a = $s->remap_parsed_document_history_entry($a, $k);'),$this);

    $final_document = array();
    // Get intersection between parsed document properties and our Joins 
    // Group related join attributes, so that Join attributes of the same type
    // are collected in a list 
    $hb = new HouseBillDocumentModel();
    $hb_joins = $hb->get_joins();

    foreach ( $document as $k => $v ) {/*{{{*/
      $kw     = preg_split('@:@', $k);
      $subkey = nonempty_array_element($kw,1);
      $k      = nonempty_array_element($kw,0);
      if ( array_key_exists($k, $hb_joins) ) {
        if ( array_key_exists($k, $v) ) {
          // Reduce nested key
          $va = nonempty_array_element($v,$k);
          $extant = nonempty_array_element($document,$k,array());
          if ( empty($va) ) {
            $final_document[$k] = $v;
            continue;
          }
          if ( is_array($va) ) {
            $final_document[$k] = array_merge($va, $extant);
            continue;
          }
        }
        $final_document[$k] = $v;
        continue;
      }
      $final_document[$k] = $v;
    }/*}}}*/

    $final_document['congress_tag'] = intval($document['congress_tag']);
    $final_document['sn']           = preg_replace('@[^-A-Z0-9]@i','',$document['sn']);

    // Determine the database ID record number, if it already exists
    $filter = array(
      '`a`.`congress_tag`' => $final_document['congress_tag'],
      '`a`.`sn`'           => $final_document['sn'],
    );
    // House Bill document records are unique, with multiple ModelJoin members. 
    $final_document['id'] = $hb->join_all()->retrieve($filter,'AND')->in_database()
      ? $hb->get_id()
      : NULL
      ;

    if (!is_null(array_element($final_document,'id'))) {
      $final_document['create_time'] = method_exists($hb,'get_create_time') ? $hb->get_create_time() : NULL;
      $final_document['last_fetch']  = method_exists($hb,'get_last_fetch') ? $hb->get_last_fetch() : NULL;
    }
    else {
    }  

    if ( $debug_method ) $this->recursive_dump($final_document,"(marker) regrouped");

    $classname = get_class($hb);

    if ( $debug_method ) $this->syslog(__FUNCTION__,__LINE__,"(critical) Found {$classname} {$final_document['sn']}.{$final_document['congress_tag']} #{$final_document['id']}");

    // Take a second pass to clean up Join properties
    $significance        = nonempty_array_element($final_document,'significance');
    $legislative_history = nonempty_array_element($final_document,'legislative-history');
    unset($final_document['legislative-history']);
    unset($final_document['significance']);

    $document = $final_document;
    foreach ( $document as $k => $v ) {/*{{{*/
      switch ( $k ) {
      case 'representative':
        $final_document[$k] = array();
        // if ( !array_key_exists($k,$final_document) ) $final_document[$k] = array();
        // $this->recursive_dump($v,"(critical) {$k}---");
        foreach ( $v as $attr => $vals ) {/*{{{*/
          if ( is_array($vals) ) {
            while ( 0 < count($vals) ) {
              $val = array_shift($vals);
              if ( $hb->get_foreign_obj_instance('representative')->fetch_by_fullname($val) ) {
                if ( 0 < ($id = intval($hb->get_foreign_obj_instance('representative')->get_id())) )
                  $final_document[$k][] = array(
                    $id => array(
                      'relation_to_bill' => $attr,
                      'congress_tag' => $document['congress_tag'],
                      'create_time' => time(),
                    )
                  );
              }
            }
            continue;
          }
          if ( !is_string($vals) ) continue;
          if ( $hb->get_foreign_obj_instance('representative')->fetch_by_fullname($vals) ) {
            if ( 0 < ($id = intval($hb->get_foreign_obj_instance('representative')->get_id())) )
              $final_document[$k][] = array(
                $id => array(
                  'relation_to_bill' => $attr,
                  'congress_tag' => $document['congress_tag'],
                  'create_time' => time(),
                )
              );
          }
        }/*}}}*/
        break;
      case 'committee':
        $final_document[$k] = array();
        // if ( !array_key_exists($k,$final_document) ) $final_document[$k] = array();
        $committee = nonempty_array_element($v,'committee');
        unset($v['committee']);
        $hb->debug_final_sql = TRUE;
        if ( $hb->get_foreign_obj_instance('committee')->fetch_by_committee_name($committee) ) {/*{{{*/
          if (0 < ($id = intval($hb->get_foreign_obj_instance('committee')->get_id()))) {
            $payload = array_merge(
              $legislative_history,
              $v,
              array(
                'significance' => $significance,
              )
            );
            // TODO: Figure out whether or not to denormalize the [significance] attribute.
            $final_document[$k][] = array(
              $id => array(
                'jointype' => 'main-committee',
                'congress_tag' => $document['congress_tag'],
                'create_time' => time(),
                'payload' => json_encode($payload)
              )
            );
          }
        }/*}}}*/
        $hb->debug_final_sql = FALSE;

        if ( $debug_method ) { 
          foreach ( nonempty_array_element($v,'committees',array()) as $attr => $vals) {
            $this->syslog(__FUNCTION__,__LINE__,"(marker) Must match {$vals}");
          }
        }
        unset($v['committees']);
        break;
      }
    }/*}}}*/

    if ( $debug_method ) $this->recursive_dump($final_document,"(marker) Remapped");

    return $final_document;
  }/*}}}*/

  function remap_parsed_document_history_entry(& $a, $k) {/*{{{*/
    // Convert a history document line into an associative array entry,
    // splitting the line by a ':', or converting a "key(value)" string
    // to an entry $a[key] => value
    if ( !is_array($a) ) return $a;
    $key_splitter = preg_split('@:@',$k);
    $matcher = '@^([^(]*)\(([^)]*)\)@i';
    $p = $a;
    $a = array();
    foreach ( $p as $v ) {
      $components = array();
      if ( 1 < count($key_splitter) ) {
        if ( !array_key_exists($key_splitter[0], $a) )
          $a[$key_splitter[0]] = array( $key_splitter[1] => array() );
        $a[$key_splitter[0]][$key_splitter[1]][] = $v;
        continue;
      }
      if ( 1 == preg_match($matcher, $v, $components) ) {
        $a[$components[1]] = $components[2];
      }
    }
    return $a;
  }/*}}}*/

}

