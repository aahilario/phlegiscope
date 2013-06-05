<?php

/*
 * Class CongressRaListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressHbListParseUtility extends CongressCommonParseUtility {
  
	var $splstack = NULL;

  private $linkmap;
  private $meta;
  private $committee_regex_lookup; 
  private $cache_limit = 100;

  private $house_bill; 
  private $committee;  
  private $dossier;

  function __construct() {
    parent::__construct();
		$this->splstack = new SplStack();
		// House Bill listing structure:
		// <li>
		//   <span>HB00001</span>
		//   <a>[History]</a>
		//   <a>[Text As Filed 308k]</a>
		//   <span>AN ACT EXEMPTING ALL MANUFACTURERS AND IMPORTERS OF HYBRID VEHICLES FROM THE PAYMENT OF CERTAIN TAXES AND FOR OTHER PURPOSES </span>
		//   <span>Principal Author: SINGSON, RONALD VERSOZA</span>
		//   <span>Main Referral: WAYS AND MEANS</span>
		//   <span>Status: Substituted by HB05460</span>
		// </li>
  }

  function __destruct() {
    $this->syslog( __FUNCTION__,__LINE__,"(marker) ----------------------- > Memory load: " . memory_get_usage(TRUE) );
		unset($this->splstack               );// = NULL;
		unset($this->parent_url             );// = NULL;
		unset($this->meta                   );// = NULL;
		unset($this->linkmap                );// = NULL;
		unset($this->committee_regex_lookup );// = NULL;
		unset($this->bill_cache             );// = NULL;
		unset($this->house_bill             );// = NULL;
		unset($this->committee              );// = NULL;
		unset($this->dossier                );// = NULL;
    gc_collect_cycles();
    $this->syslog( __FUNCTION__,__LINE__,"(marker) ----------------------- < Memory load: " . memory_get_usage(TRUE) );
  }

  function ru_span_open(& $parser, & $attrs, $tag) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'] = array();
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
		return TRUE;
  }/*}}}*/
  function ru_span_cdata(& $parser, & $cdata) {/*{{{*/
		$this->pop_tagstack();
		$this->current_tag['cdata'][] = trim($cdata);
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']} {$cdata}" );
    return TRUE;
  }/*}}}*/
  function ru_span_close(& $parser, $tag) {/*{{{*/
		$this->pop_tagstack();
		$span_class = array_element($this->current_tag['attrs'],'CLASS');
		$span_type  = array(
			strtolower(0 < strlen($span_class) ? $span_class : 'desc')
			=> is_array($this->current_tag['cdata']) ? array_filter($this->current_tag['cdata']) : array(),
				'seq' => array_element($this->current_tag['attrs'],'seq')
		);
		$this->add_to_container_stack($span_type);
		$this->push_tagstack();
		if ($this->debug_tags) $this->syslog( __FUNCTION__, 'FORCE', "--- {$this->current_tag['tag']}" );
    return FALSE;
  }/*}}}*/

  function ru_a_close(& $parser, $tag) {/*{{{*/
		parent::ru_a_close($parser,$tag);
		if ( array_key_exists('ONCLICK',$this->current_tag['attrs']) )
			return FALSE;
    return TRUE;
  }/*}}}*/

  // Post-parse methods

  function obtain_congress_number_from_listing() {/*{{{*/
		// May 2013, 15th Congress, bill listing structure
    // Hack to obtain Congress number from the first few entries of the array
		$debug_method = FALSE;
    $congress_tag = NULL;
    $counter = 0;
    foreach ( $this->containers_r() as $entry ) {/*{{{*/
      if ( $counter > 10 ) break;
      if ( $debug_method ) {
        $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - - - - - - - Testing entry {$counter}");
        $this->recursive_dump($entry,"(marker) - - - -");
      }
      $congress_line_match = array();
      $textentry = array_element($entry,'text');
      if (is_null($textentry)) continue;
      $counter++;
      if (1 == preg_match('@([0-9]*)(.*) Congress(.*)@i', $textentry, $congress_line_match) ) {
        $congress_tag = array_element($congress_line_match,1);
        $this->syslog( __FUNCTION__, __LINE__, "(marker) Apparent Congress number: {$congress_tag}");
        break;
      }
    } /*}}}*/
    return $congress_tag;
  }/*}}}*/

  function convert_containers_to_stack() {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Reverse stream sequence, treat array as stack. Memory load: " . memory_get_usage(TRUE));
    reset($this->containers_r());
    krsort($this->containers_r());
    reset($this->containers_r());

  }/*}}}*/

	function & convert_splstack_to_containers() {/*{{{*/
		$containers =& $this->clear_containers()->containers_r();
		while ( !$this->splstack->isEmpty() ) array_push($containers, $this->splstack->pop());
		krsort($containers);
		$containers = json_encode($containers);
		$containers = json_decode($containers,TRUE);
		return $this;
	}/*}}}*/

  function convert_containers_to_splstack() {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Reverse stream sequence, treat array as stack. Memory load: " . memory_get_usage(TRUE));
    reset($this->containers_r());
		while ( 0 < count($this->containers_r()) ) {
			$element = array_pop($this->containers_r());
			$this->splstack->push($element);
		}
		$this->clear_containers();

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Transferred content to splstack, Memory load: " . memory_get_usage(TRUE));

  }/*}}}*/

  function reduce_bill_listing_structure() {/*{{{*/
		// May 2013, 15th Congress, bill listing structure
		$this->clear_temporaries();
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Filtering in place");
    $this->filter_nested_array($this->containers_r(),
      'children[tagname*=div][class*=padded]{#[seq*=.*|i]}'
    );
    $this->syslog( __FUNCTION__, __LINE__, "(marker) Reordering using sequence in stream");
    array_walk($this->containers_r(),create_function(
      '& $a, $k, $s', 'if ( 0 < count($a) ) $s->reorder_with_sequence_tags($a); else $a = NULL;'
    ),$this);

    // Haskell me now, please. Crunch down array to just the list of bills
    $this->assign_containers(array_values(array_filter($this->containers_r())),0);
    $this->assign_containers(array_values($this->containers_r()));
		gc_collect_cycles();
  }/*}}}*/

  function initialize_bill_parser(& $urlmodel) {/*{{{*/

		$this->splstack->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_DELETE); 
    $this->parent_url   = UrlModel::parse_url($urlmodel->get_url());

    $this->meta = array(
      'status'           => 'Status',
      'principal-author' => 'Principal Author',
      'main-committee'   => 'Main Referral',
    );

    $this->linkmap = array(
      '@(\[(.*)as filed(.*)\])@i'  => 'filed',
      '@(\[(.*)engrossed(.*)\])@i' => 'engrossed',
    );

    $this->committee_regex_lookup = array();

    $this->house_bill   = new HouseBillDocumentModel();
    $this->committee    = new CongressionalCommitteeDocumentModel();
    $this->dossier      = new RepresentativeDossierModel();

  }/*}}}*/

  function get_parsed_housebill_template( $hb_number, $congress_tag = NULL ) {/*{{{*/
    return array(
      'sn'           => $hb_number,
      'last_fetch'   => time(),
      'description'  => NULL,
      'congress_tag' => $congress_tag,
      'url_history'  => NULL, // Document activity history
      'url_engrossed' => NULL, // "Text as engrossed ..." URL.
      'url'          => NULL,
      'representative' => array(
        'relation_to_bill' => NULL,
      ),
      'committee'    => array(
        'jointype' => NULL,
      ),
			'meta'         => array(
				'main-committee' => array('mapped' => NULL),
				'principal-author' => array('mapped' => NULL),
			),
      'links'        => array(),
    );
  }/*}}}*/

	function do_housebill_store() {/*{{{*/

		$this->committee->update_committee_name_regex_lookup($this->committee_regex_lookup);

		array_walk($this->bill_cache,create_function(
			'& $a, $k, $s', '$a["meta"]["main-committee"]["mapped"] = array_element(array_element($s,$a["meta"]["main-committee"]["raw"],array()),"id");'
		),$this->committee_regex_lookup);

		$this->house_bill->cache_parsed_housebill_records($this->bill_cache);

		$this->bill_cache = NULL; 
		unset($this->bill_cache);
		gc_collect_cycles();
		$this->bill_cache = array();

	}/*}}}*/

	function parse_bill_registry($congress_tag, $batch_size) {/*{{{*/

		$debug_method = FALSE;

		$this->bill_cache = array();
		$current_bill     = array();
		$bill_count       = 0;

		do {/*{{{*/

			// Test collected bills against database

			if ( $this->cache_limit <= count($this->bill_cache) ) {/*{{{*/

				$this->do_housebill_store();

				$n_c = count($this->committee_regex_lookup);
				$this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Bills processed: {$bill_count} CRL {$n_c} " . memory_get_usage(TRUE));
			}/*}}}*/

			// Remove bill component from stack

      unset($container);

			if ( $this->splstack->isEmpty() ) break;

			$container = $this->splstack->pop();

			if ( array_key_exists('bill-head', $container) ) {/*{{{*/
				// New entry causes prior entries to be flushed to stack
				$hb_number = join('', array_element($container,'bill-head'));

				$container = NULL;
				unset($container);

				if (!is_null(array_element($current_bill,'sn'))) { /*{{{*/
					$this->bill_cache[array_element($current_bill,'sn')] = $current_bill;
				}/*}}}*/

				if ( $debug_method ) {/*{{{*/
					$this->syslog( __FUNCTION__, __LINE__, "(marker) - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -");
					$this->recursive_dump($current_bill, "(marker) - - - - - " . array_element($current_bill,'sn'));
				}/*}}}*/

				$current_bill = NULL;
				unset($current_bill);
				gc_collect_cycles();

				$current_bill = $this->get_parsed_housebill_template($hb_number, $congress_tag); 

				$current_bill['representative']['relation_to_bill'] = 'principal-author';
				$current_bill['committee']['jointype'] = 'main-committee';

				$bill_count++;

				continue;

			}/*}}}*/

			if ( array_key_exists('meta', $container) ) {/*{{{*/
				$matches = array();
				if ( 1 == preg_match('@^('.join('|',$this->meta).'):(.*)$@i',join('',$container['meta']), $matches) ) {/*{{{*/
					$matches[1] = trim(preg_replace(array_map(create_function('$a','return "@{$a}@i";'),$this->meta),array_keys($this->meta),$matches[1]));
					$matches[2] = trim($matches[2]);
					$mapped = NULL;
					switch( $matches[1] ) {/*{{{*/
					case 'principal-author':
						$name = $matches[2];
						// FIXME: Decompose replace_legislator_names_hotlinks, factor out markup generator
						$mapped = $this->dossier->replace_legislator_names_hotlinks($name);
						$mapped = !is_null($mapped) ? array_element($name,'id') : NULL;
						if ( !is_null($mapped) ) $matches[2] = "{$matches[2]} ({$name['fullname']})";
						break;

					case 'main-committee':
						// Perform tree search (PHP assoc array), as it will be inefficient to 
						// try to look up each name as it recurs in the input stream.
						// We'll assume that there will be (at most) a couple hundred distinct committee names
						// dealt with here, so we can reasonably generate a lookup table
						// of committee names containing name match regexes and committee record IDs.
						$name = $matches[2];
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
							if ( !is_null($name) )
								$this->committee_regex_lookup[$name] = array(
									'committee_name' => $name,
									'regex'          => $committee_name_regex,
									'id'             => 'UNMAPPED' // Fill this in just before invoking cache_parsed_housebill_records()
								);
						}/*}}}*/
						else {
							// Assign an existing ID
							$mapped = array_element($this->committee_regex_lookup[$name],'id');
						}
						break;
					default:
            // By default
						break;
					}/*}}}*/
					$current_bill['meta'][$matches[1]] = array( 'raw' => $matches[2], 'mapped' => $mapped );
				}/*}}}*/
				unset($matches);
				unset($mapped);
				unset($container);
				gc_collect_cycles();
				continue;
			}/*}}}*/

			if ( array_key_exists('desc', $container) ) {/*{{{*/
				$current_bill['description'] = join('',$container['desc']);
				unset($container);
				gc_collect_cycles();
				continue;
			}/*}}}*/

			if ( array_key_exists('onclick', $container) ) {/*{{{*/
				$matches = array();
				$popup_regex = "display_Window\('(.*)','(.*)'\)";
				if ( 1 == preg_match("@{$popup_regex}@i", $container['onclick'], $matches) ) {
					$matches = array_element($matches,1);
					if ( !is_null($matches) ) {
						$matches = array('url' => $matches);
						$matches = UrlModel::normalize_url($this->parent_url, $matches);
						$current_bill['url_history'] = $matches;
					}
				}
				unset($matches);
				unset($container);
				gc_collect_cycles();
				continue;
			}/*}}}*/

			if ( array_key_exists('url', $container) ) {/*{{{*/
				$linktype = preg_replace(array_keys($this->linkmap),array_values($this->linkmap), array_element($container,'text')); 
				$current_bill['links'][$linktype] = array_element($container,'url');
				unset($linktype);
				unset($container);
				gc_collect_cycles();
				continue;
			}/*}}}*/

		}/*}}}*/
		while (0 < count($this->splstack->count()) && ($bill_count < $batch_size));

		// Deplete remaining House Bill cache entries
		if ( 0 < count($this->bill_cache) ) {/*{{{*/

			$this->do_housebill_store();
			$bill_count += count($this->bill_cache);

		}/*}}}*/

		if ( $debug_method ) {
      $this->syslog( __FUNCTION__, __LINE__, "(marker) - - - Committee Lookups" ); 
      $this->recursive_dump($this->committee_regex_lookup, "(marker) - - - - - Committee Lookup Table");
		}

		return $bill_count;
	}/*}}}*/

}

