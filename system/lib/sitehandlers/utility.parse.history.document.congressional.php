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

  function parse_document_history(UrlModel & $urlmodel, $contents) {/*{{{*/

    $debug_method = FALSE;

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

		$t = array(
			'id'             => nonempty_array_element($document,'id'),
			'sn'             => nonempty_array_element($document,'sn'),
			'congress_tag'   => nonempty_array_element($document,'congress_tag'),
			'description'    => nonempty_array_element($document,'description'),
			'create_time'    => time(),
			'last_fetch'     => time(),
			'searchable'     => NULL,
			'url'            => NULL,
			'url_history'    => $urlmodel->get_url(),
			'url_engrossed'  => NULL,
			'status'         => NULL,

			'housebill'      => NULL, // Reference to other house bills, indicating the relationship (e.g. substitution).
			'republic_act'   => NULL, // Republic Act toward which this House Bill contributed essential language and intent.
			'representative' => nonempty_array_element($document,'representative'), // Several types of association between a house bill and representatives (authorship, etc.).
			'content'        => nonempty_array_element($document,'[history_data]'), // Content BLOB edges.
			'committee'      => nonempty_array_element($document,'committee'), // Reference to Congressional Committees, bearing the status of a House Bill, or the principal committee.
		);


    return $document;

  }/*}}}*/

	function cleanup_parsed_record(& $document) {/*{{{*/

    $this->recursive_dump($document,"(marker) Parsed");
		array_walk($document,create_function('& $a, $k, $s', '$a = $s->remap_parsed_document_history_entry($a, $k);'),$this);

		$hb = new HouseBillDocumentModel();
		$hb_joins = $hb->get_joins();

		$final_document = array();
		// Group related join attributes
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

		$document = $final_document;
		$final_document['id'] = NULL;
		$join_intersection = array_intersect_key($document,$hb_joins); 
    $this->recursive_dump($join_intersection,"(marker) Joins");

		// Determine the database ID record number, if it already exists
		$hb->join_all()->where(array('AND' => array(
			'congress_tag' => $document['congress_tag'],
			'sn' => $document['sn'],
		)))->recordfetch_setup();

		$document = NULL;
		while ( $hb->recordfetch($document) ) {
			if ( is_null($final_document['id']) ) {
				$final_document['id'] = $document['id'];
			}
		}

		if ( '--' == nonempty_array_element($final_document,'id','--') ) {
		}

 		// Take a second pass to clean up Join properties
		$document = $final_document;
		$significance = nonempty_array_element($document,'significance');
		$legislative_history = nonempty_array_element($document,'legislative-history');
		unset($document['legislative-history']);
		unset($document['significance']);
		$final_document = $document;
		foreach ( $document as $k => $v ) {
			switch ( $k ) {
				case 'representative':
					$final_document[$k] = array();
					foreach ( $v as $attr => $vals ) {
					}
					break;
				case 'committee':
					$final_document[$k] = array();
					$committee = nonempty_array_element($v,'committee');
					unset($v['committee']);
					if ( $hb->get_join_object('committee','ref','obj')->fetch_by_committee_name($committee) ) {
						$id = intval($hb->get_join_object('committee','ref','obj')->get_id());
						$final_document[$k][] = array(
						  $id => array(
								'jointype' => 'main-committee',
								'congress_tag' => $document['congress_tag'],
								'create_time' => time(),
								'blob' => array_merge(
									$legislative_history,
									$v,
									array(
										'significance' => $significance,
									)
								)
							)
						);	
					} else {
					}
						 
					foreach ( nonempty_array_element($v,'committees',array()) as $attr => $vals) {
						$this->syslog(__FUNCTION__,__LINE__,"(marker) Must match {$vals}");
					}
					unset($v['committees']);
					break;
			}
		}

   $this->recursive_dump($final_document,"(marker) Remapped");

		return $final_document;
	}/*}}}*/

	function remap_parsed_document_history_entry(& $a, $k) {
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
	}
}

