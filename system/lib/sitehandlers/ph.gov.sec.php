<?php

/*
 * Class SecGovPh
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SecGovPh extends LegiscopeBase {
  
  function __construct() {
    $this->syslog( __FUNCTION__, 'FORCE', 'Using site-specific container class' );
    parent::__construct();
  }

  function seek() {
    $json_reply = parent::seek();
    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }

  function proxyform() {/*{{{*/
    $json_reply = parent::proxyform();
    $response = json_encode($json_reply);
    header('Content-Type: application/json');
    header('Content-Length: ' . strlen($response));
    $this->flush_output_buffer();
    if ( C('ENABLE_GENERATED_CONTENT_BUFFERING') ) {
      file_put_contents($this->seek_cache_filename, $response);
    }
    echo $response;
    exit(0);
  }/*}}}*/

  function common_unhandled_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Invoked for " . $urlmodel->get_url() );
    $common = new iReportParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $this->recursive_dump(($containers = $common->get_containers(
      //'#[tagname=frameset]'
      //'children[tagname=frameset]'
    )),'(marker) iReport --');

    $pagecontent = join('',$common->get_filtered_doc());

    $parser->json_reply = array('retainoriginal' => TRUE);

  }/*}}}*/

  function seek_postparse_bypath_9974a0f1ce6298c137f2f69beaac0373(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    //
    // ONLINE VIEWER HANDLER 
    //
    // Page actions are executed using JS event handlers:
    // - onGetDiv():  Descend PSIC hierarchy
    // - onNext(): Page forward through company name list
    // - onLast(): Jump to last entry in company name list 
    //
    // Manual triggering:
    // - Set radio coChoice := psic, checked
    // - Ignore the side effects of these assignments (these are type=HIDDEN controls) 
    //    window.document.oViewForm.chk0.checked=false;
    //    window.document.oViewForm.chk1.checked=false;
    //    window.document.oViewForm.chk2.checked=false;
    // - Set
    //    window.document.oViewForm.secNo.value="";
    //    window.document.oViewForm.coNameTemp.value="";
    // - Use SELECT.clsSelect[name=psicMajorDiv] value
    //    window.document.oViewForm.psicMajorDivDscp.value = window.document.oViewForm.psicMajorDiv.options[window.document.oViewForm.psicMajorDiv.selectedIndex].text;
    //    window.document.oViewForm.psicMajorDivDscpFilter.value = window.document.oViewForm.psicMajorDivDscp.value;
    // Submit oViewForm with these parameters changed.

    // Traversal algorithm:
    // - Extract list of PSIC major codes from clsSelect
    // - Create proxied links for each PSIC major code division 
    //   - psicMajorDivDscp <- Option text string
    //   - psicMajorDivDscp <- Option value
    //   - Proxy triggers POST request to https://ireport.sec.gov.ph/iview/onlineview.sx
    // - Proxy trigger response returns to this method, with a count of records expected

    $debug_method          = TRUE;
    $suppress_trigger_next = FALSE;
    $trigger_next          = TRUE;

    if ( $debug_method ) $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Invoked for " . $urlmodel->get_url() );

    $retainoriginal = FALSE;

    if ( is_array($parser->metalink_data) ) {
      if ( array_key_exists('retainoriginal', $parser->metalink_data) ) 
        $retainoriginal |= $parser->metalink_data['retainoriginal'] == TRUE;
      if ( $debug_method ) {
        if ( $retainoriginal ) $this->syslog( __FUNCTION__, __LINE__, "(marker) ***** **** *** ** * * * Setting 'retainoriginal'" );
        $this->recursive_dump($parser->metalink_data,'(marker) -- - - -----------  --- -- - --- --');
      }
    }

    $company_item = new SecCompanyRegistryDocumentModel();
    $scripter     = new iReportScriptParseUtility();
    $common       = new iReportParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $pagecontent  = $company_item->count() . " total records<hr/>";

    // Extract all containers

    $generic = new GenericParseUtility();
    $generic->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());
    $containers = $generic->get_containers();
    array_walk($containers, create_function(
      '& $a, $k, $s', 'if ( array_key_exists("children", $a) ) $s->resequence_children($a);'
    ),$generic);
    if ( $debug_method ) $this->recursive_dump($containers,'(marker) ------ ----- ---- --- -- - - -');

    $containers = $common->get_containers('children[tagname=html]',0);

    $common->resequence_children($containers);

    // Dump scripts

    $scripter->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    if ( $debug_method ) $this->recursive_dump(($containers),'(marker) iReport handler containers --');

    // Assemble proxy form

    $forms    = $containers;
    $forms    = $common->filter_nested_array($forms,'#[action*=.*|i]',0);
    $form_url = new UrlModel($forms['action'],TRUE);

    $hidden = $containers;
    $hidden = $common->filter_nested_array($hidden,'#[input-type*=hidden|text|i]');
    $hidden = $common->extract_key_value_table($hidden, 'name', 'value-default');

    $tablecells = $containers;
    $tablecells = $common->filter_nested_array($tablecells,'#[celltext*=.*|i]');
    if ( $debug_method ) $this->recursive_dump(($tablecells),'(marker) Table cells --');

    // Context-specific content generators

    $single_trigger = FALSE;

    do { /*{{{*/

      //////////////////////////////////////////////////////////////////////////////////
      // SOMETIMES BEHAVES SO STRANGELY
      //
      $got_company_info = FALSE;
      $company_info = array();
      $done = FALSE;
      foreach ( $tablecells as $tablecell ) {/*{{{*/
        if ( !$got_company_info ) {/*{{{*/
          $tablecell = strtoupper(preg_replace('@[^A-Z]@i', '', $tablecell['celltext']));
          if ( $tablecell == 'COMPANYINFORMATION') {
            array_push($company_info, array('name' => NULL, 'value' => NULL));
            $got_company_info = TRUE;
          }
          continue;
        }/*}}}*/
        $entry = array_pop($company_info);
        if ( $tablecell['column'] == 0 ) {
          $entry['name'] = $tablecell['celltext'];
          array_push($company_info, $entry);
          if ( $entry['name'] == 'Tel No' ) $done = TRUE;
          continue;
        }
        if ( $tablecell['column'] == 1 ) {
          $entry['value'] = $tablecell['celltext'];
          array_push($company_info, $entry);
          if ( $done ) {
            break;
          }
          array_push($company_info, array('name' => NULL, 'value' => NULL));
        }
      }/*}}}*/

      if ( $got_company_info && (0 < count($got_company_info)) ) {/*{{{*/
        $company_meta = $hidden;
        array_walk($company_info, create_function(
          '& $a, $k', '$a["name"] = explode(" ", preg_replace("@[^A-Z ]@i","",strtolower($a["name"]))); foreach ( $a["name"] as $t => $v ) { $a["name"][$t] = ucfirst(trim($v)); } $a["name"] = join("_",camelcase_to_array(join("",$a["name"])));'
        ));
        $company_meta_keys = array_keys($company_meta);
        array_walk($company_meta_keys, create_function(
          '& $a, $k', '$a = join("_",camelcase_to_array(ucfirst($a)));'
        ));
        $company_meta = array_combine(
          $company_meta_keys,
          $company_meta
        );
        $company_info = array_merge(
          $company_meta,
          $common->extract_key_value_table($company_info, 'name', 'value')
        );
        $company_info = array_filter($company_info);
        if ( $debug_method ) {/*{{{*/
          $this->recursive_dump(($tablecells),'(marker) Company data --');
          $this->recursive_dump(($company_info),'(marker) Company info --');
          $this->recursive_dump(($hidden),'(marker) Entry metadata --');
          $company_item->dump_accessor_defs_to_syslog();
        }/*}}}*/
        $company_item->fetch($company_info['sec_registration_no'], 'sec_registration_no');
        if ( !$company_item->in_database() ) {
          $new_item = $company_item->
            set_fetch_date(time())->
            set_contents_from_array($company_info,TRUE)->
            stow();
          $successful_insert = 0 < intval($new_item) ? "Created new iReport record {$new_item}" : "Failed to insert {$company_info['sec_registration_no']} ({$company_info['company_name']})";
          $this->syslog( __FUNCTION__, __LINE__, "(marker) -- {$successful_insert}" );
        }
        if ( !$debug_method ) $pagecontent .= join('',$common->get_filtered_doc());
        $retainoriginal = TRUE;
        break;
      }/*}}}*/

      //////////////////////////////////////////////////////////////////////////////////
      // RETRIEVE LINKS WHEN WE'RE IN THE COMPANY NAMES PAGING CONTEXT
      //
      $links = $containers;
      $links = $common->filter_nested_array($links, '#[url*=.*|i]');

      if ( is_array($links) && (0 < count($links)) ) {/*{{{*/

        $links = $common->extract_key_value_table($links, 'url', 'text');
        $have_next_page = 'javascript:onNext();'; 
        if ( array_key_exists($have_next_page, $links) ) {
          $links[$have_next_page] = 'Next';
        }
        $have_prev_page = 'javascript:onPrevious();'; 
        if ( array_key_exists($have_prev_page, $links) ) {
          $links[$have_prev_page] = 'Prev';
        }
        $links = array_filter($links);

        foreach ( $links as $eventhandler => $linktext ) {/*{{{*/
          if ( $linktext == 'Prev' ) {
            $matches = array(
              'coPage' => max((array_key_exists('coPage',$hidden) ? intval($hidden['coPage']) : 1) - 1, 1),
              'mode' => 1,
              'subaction' => 'coFilterIview',
            );
          } else if ( $linktext == 'Next' ) {
            $matches = array(
              'coPage' => (array_key_exists('coPage',$hidden) ? intval($hidden['coPage']) : 1) + 1,
              'mode' => 1,
              'subaction' => 'coFilterIview',
            );
          } else {
            $matches = $this->map_onlink_parameters($eventhandler);
            $matches['subaction'] = 'docFilter';
            if ( $debug_method ) {
              $this->syslog(__FUNCTION__,__LINE__,"(marker) --- -- --- -- - - {$linktext}"); 
              $this->recursive_dump($matches,'(marker) --- -- --- -- - -'); 
            }
          }
          // The $matches array is merged with the array of hidden fields 
          // on the company list pager, to generate a company name metalink
          $links[$eventhandler] = array(
            'linktext' => $linktext,
            'linktext_normal' => htmlspecialchars_decode($linktext),
            'metalink_data' => array_merge( $hidden, $matches ),
          );
        }/*}}}*/

        $company_links = array();
        $links = array_values($links);

        foreach ( $links as $link ) {/*{{{*/
          
          $metalink_data = $link['metalink_data'];
          $link_properties = array('metapager');
          if ( $link['linktext'] == 'Prev' ) {
            $trigger_next = TRUE;
            $link_properties[] = 'hilight';
            $link_properties[] = 'cached';
          } else if ( $link['linktext'] == 'Next' ) {
            $trigger_next = TRUE;
            $link_properties[] = 'hilight';
            $link_properties[] = 'uncached';
          } else {
            $link_properties[] = 'retains-original';
            $metalink_data['_LEGISCOPE_'] = array(
              'retainoriginal' => TRUE
            );
            $company_item->fetch($metalink_data['secNo'], 'sec_registration_no');
            $link_properties[] = $company_item->in_database() ? "cached" : "uncached";
          }

          $link_properties = join(' ', $link_properties);
          $fake_url = UrlModel::construct_metalink_fake_url($form_url, $metalink_data);
          $link = UrlModel::create_metalink($link['linktext'], $form_url, $metalink_data, $link_properties);

          $company_links[] = <<<EOH
<li>{$link}</li>
EOH;

        }/*}}}*/

        $company_links = join('',$company_links);
        $total_pages = intval($hidden['cntRecord']);
        if ( $total_pages > 0 ) {
          $total_pages = round($total_pages / 10);
          $percentage = round(intval($hidden['coPage']) * 100 / intval($total_pages),2);
          $total_pages = "/ {$total_pages}";
          $pagecontent .= <<<EOH
({$percentage}%) Page {$hidden['coPage']} {$total_pages} (<input type="text" id="jumpto" name="jumpto" value="" />)<br/>
<ul class="link-cluster" id="psic-level-0">{$company_links}</ul>
EOH;
        }

      }/*}}}*/

      //////////////////////////////////////////////////////////////////////////////////
      // Hardwire 'coChoice' to 'psic' below in order to traverse first level PSIC codes.
      $radio = array();
        // Extract radio button tag data
        $radio = $containers;
        $radio = $common->filter_nested_array($radio,'#[input-type*=radio|i]');
        $this->recursive_dump($radio,'(marker) --- -- - - radio');
        $radio_n = $radio;
        $radio_n = array_keys($common->extract_key_value_table($radio_n, 'name', 'value-default'));
        $radio_n = $radio_n[0];
        $radio_p = $radio;
        $radio_p = $common->extract_key_value_table($radio_p, 'value-default', 'checked');
        $radio_m = $radio_p; 
        array_walk($radio_m, create_function(
          '& $a, $k', '$a = "<input type=\"radio\" value=\"{$k}\" name=\"'.$radio_n.'\" />";'
        ));
        $radio = $radio_m;

      $select  = $containers;
      $select  = $common->filter_nested_array($select,'#[tagname=select]',0);

      $options = array();

      //////////////////////////////////////////////////////////////////////////////////
      // GENERATE PSIC ITEM SELECTION HTML CONTROL OVERRIDE ARRAY
      //
      if ( is_array($select) && array_key_exists('children', $select) ) { /*{{{*/

        $options = $select['children'];
        $options = $common->extract_key_value_table($options, 'text', 'value');

        $override_trigger_item = NULL;
        $confirm_override = array();

        if ( 0 < intval($hidden['cntRecord']) && 0 < intval($hidden['coPage']) ) {/*{{{*/
          // After receiving a form containing hidden controls with nonzero values for
          // - [cntRecord]
          // - [coPage]
          // we should resubmit the previously triggered metalink with these replacement parameters:
          $override_trigger_item = $hidden['psicMajorDivDscp'];
          // Keep the whort code
          $override_trigger_item = $options[$override_trigger_item];
          // Merge this with the entry ONLY with the matching entry 
          $confirm_override = array(
            'coChoice' => 'psic' ,
            'subaction' =>  "coFilterIview",
            'typeFindComp' => '' ,
            'name' => '' ,
            'secNo' => '' ,          
            'psicMajorDiv' =>  NULL, // Match value to current 'I' ,
            'isAlertFlag' => 1,              
            'isCheckCnt' => 1,  
            'mode' =>  1,
          );
          $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - TRIGGERED BY {$override_trigger_item}");
        }/*}}}*/

        $options = array_flip(array_filter($options));

      }/*}}}*/
      else {/*{{{*/
        $this->syslog(__FUNCTION__,__LINE__,"(marker) -- -- -- -- -- -- -- No selection yet");
      }/*}}}*/

      if ( $debug_method ) {/*{{{*/
        $this->recursive_dump($forms  , "(marker) Form");
        $this->recursive_dump($hidden , "(marker) Hidden");
        $this->recursive_dump($radio_n, "(marker) Radio");
        $this->recursive_dump($radio  , "(marker) Radio");
        $this->recursive_dump($select , "(marker) Select");
        $this->recursive_dump($options, "(marker) Options");
        $this->recursive_dump($links  , "(marker) links");
      }/*}}}*/

      //////////////////////////////////////////////////////////////////////////////////
      // GENERATE PSIC HIERARCHY LEVEL 0 LINKS
      //
      if ( is_array($options) && (0 < count($options)) ) {/*{{{*/

        $psic_links = array();

        // Determine the current PSIC hierarchy level
        $depthkeys = array(
          'psicMajorDiv',
          'psicDiv',
        );

        foreach ( $options as $value => $text ) {/*{{{*/
          $triggered = !is_null($override_trigger_item) && ($value == $override_trigger_item);
          $metalink_data = array_merge( $hidden, $triggered ? $confirm_override : array() );
          // Assign SELECT control value
          $metalink_data[$select['attrs']['NAME']] = $value;
          // Assign OPTION text attribute (really: THE TEXT ATTRIBUTE) to these hidden controls
          $metalink_data['psicMajorDivDscp'] = $text;
          $metalink_data['psicMajorDivDscpFilter'] = $text;
          // Hardwire the radio button selection to force PSIC depth 0 matches
          $metalink_data['coChoice'] = 'psic';
          $metalink_data['_LEGISCOPE_'] = array(
            'retainoriginal' => FALSE,
          );
          $metalink_information = $metalink_data;
          $retainoriginal = FALSE;

          if ( is_null($override_trigger_item) || ($metalink_data['psicMajorDiv'] == $override_trigger_item) ) {
            $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- -+- POST {$form_url}");
            $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - Value {$value}");
            $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - Text {$text}");
            $this->recursive_dump($metalink_data, "(marker) ----- ---- --- -- -");
          }

          // Construct main link

          $link_properties = array('fauxpost');
          // TODO: It is reasonable to directly execute a fetch here
          $jump_link = $metalink_information;
          if ( $triggered ) {/*{{{*/
            $single_trigger = TRUE;
            $suppress_trigger_next = TRUE;
            $trigger_next = FALSE;
            $link_properties[] = 'hilight';
            // $link_properties[] = 'uncached';
            $redirect_trigger = <<<EOH

var timerInstance = null;

function seek_uncached_entry() {
  $('ul[id=psic-level-0]').find('a[class*=uncached]').first().each(function(){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    $('#metalink').html(content);
    load_content_window($(this).attr('href'), true,$(this));
    $('#metalink').html('');
    $(this).removeClass('uncached').addClass('cached');
  });
}

$(function(){
  timerInstance = setTimeout(function(){
    seek_uncached_entry();
  },500);
});

EOH;
          }/*}}}*/
          $link_properties = join(' ', $link_properties);
          $fake_url = UrlModel::construct_metalink_fake_url($form_url, $jump_link);
          $link = UrlModel::create_metalink($text, $form_url, $jump_link, $link_properties);

          $link_properties   = array('fauxpost');
          $descend_hierarchy = $metalink_information;
          $link_properties   = join(' ', $link_properties);
          $descend_hierarchy['secNo'] = NULL;
          $descend_hierarchy['coNameTemp'] = NULL;
          $descend_hierarchy['mode'] = intval($descend_hierarchy['mode']);
          array_walk($descend_hierarchy,create_function(
            '& $a, $k', 'if ( 1 == preg_match("@^(is)@i", $k) ) $a = intval($a);'
          ));
          $descend_hierarchy['psicMajorDivDscpFilter'] = NULL;
          $descend_hierarchy['subaction'] = 'getPsicDiv';
          $descend_hierarchy['typeFindComp'] = $descend_hierarchy['chk1']; 

          $fake_url          = UrlModel::construct_metalink_fake_url($form_url, $descend_hierarchy);
          $text              = '<img src="images/button-down.gif" alt="&gt;" >';
            $this->syslog(__FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - Hier {$text}");
            $this->recursive_dump($descend_hierarchy, "(marker) ----- ---- --- -- -");
          $descend_hierarchy = UrlModel::create_metalink($text, $form_url, $descend_hierarchy, $link_properties);

          // Construct sub-link

          $psic_links[] = <<<EOH
<li>{$link} {$descend_hierarchy}</li>
EOH;
        }/*}}}*/

        $psic_links = join('',$psic_links);
        $pagecontent .= <<<EOH
<ul id="psic-level-0">{$psic_links}</ul>
EOH;
      }/*}}}*/

      break;

    }/*}}}*/
    while (TRUE);

    if ( $debug_method ) $pagecontent .= join('',$common->get_filtered_doc());


    // GENERATE INLINE SCRIPT DUMPS

    if ( $debug_method ) {/*{{{*/
      $this->recursive_dump(($scripts = $scripter->get_containers(
        //'#[tagname=frameset]'
        //'children[tagname=frameset]'
      )),'(marker) iReport scripts --');

      foreach ($scripts as $script) {/*{{{*/
        $script = array_filter($script);
        $script = join("\r", $script);
        $pagecontent .= <<<EOH

<hr/>
<textarea class="code-dump">
{$script}
</textarea>

EOH;
      }/*}}}*/
    }/*}}}*/

    $trigger_next &= !$suppress_trigger_next;
    if ( !$single_trigger )
    $redirect_trigger = is_null($override_trigger_item) && !$trigger_next ? NULL : <<<EOH

var timerInstance = null;

function seek_uncached_entry() {
  if ( typeof timerInstance != 'null' ) clearTimeout(timerInstance);
  if ( $('input[id=spider]').prop('checked') ) 
  $('ul[id=psic-level-0]').find('a[class*=uncached]').first().each(function(){
    var content_id = /^switch-/.test($(this).attr('id')) ? ('content-'+$(this).attr('id').replace(/^switch-/,'')) : null;
    var content = $('span[id='+content_id+']').html();
    $('#metalink').html(content);
    load_content_window($(this).attr('href'), $('input[id=seek]').prop('checked'),$(this));
    $('#metalink').html('');
    $(this).removeClass('uncached').addClass('cached');
    timerInstance = setTimeout((function(){seek_uncached_entry();}),1200);
  });
  else {
    window.status = 'Spider switch: '+$('input[id=spider]').prop('checked'); 
  }
}

$(function(){
  timerInstance = setTimeout(function(){
    seek_uncached_entry();
  },5000);
});

EOH;

    $pagecontent = <<<EOH
<div class="float-left link-cluster log-dumps">
{$pagecontent}
</div>

<script type="text/javascript">
$(function(){
  initialize_remote_links();
  $('div[id=original]').find('a[class*=legiscope-remote]').each(function(){
    if ( /{$parser->trigger_linktext}/.test($(this).html()) ) {
      $(this).addClass('hilight');
    }
  });
});
{$redirect_trigger}
</script>
EOH;

    if ( !$retainoriginal ) $pagecontent .= <<<EOH

<div class="alternate-original half-container" id="ireport-snapshot-block">&nbsp;</div>

EOH;


    // Final cleanup
    $pagecontent = preg_replace(
      array(
        '@([ ]*)\& \#@i',
        '@([ ]*)\&\#([0-9]*)\;([ ]*)@i',
      ),
      array(
        '&#',
        '&#$2;',
      ), 
      $pagecontent
    );

    if ( $retainoriginal ) $parser->json_reply = array('retainoriginal' => TRUE);

    // file_put_contents('./gorp.txt', $pagecontent);

  }/*}}}*/

  function seek_postparse_07563d07ecf3ea5369332b8a71b50023(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // LOGOUT HANDLER
    $this->ireport_login_page_parser($parser, $pagecontent, $urlmodel);
    unset($_SESSION["CF{$this->subject_host_hash}"]);
  }/*}}}*/

  function seek_postparse_acb2e3d4372135d20bf3a3840c7470a1(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    // LOGIN HANDLER
    $this->ireport_login_page_parser($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function seek_postparse_a1037be105a8f49576a6f877ab23bdc6(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    //
    // Netexport JSON log browser
    // $this->ireport_login_page_parser($parser, $pagecontent, $urlmodel);
    //
    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Invoked for " . $urlmodel->get_url() );
    $common   = new iReportParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $pagecontent = join('',$common->get_filtered_doc());

    // Dump entire structure
    $this->recursive_dump(($containers = $common->get_containers(
    )),'(marker) '. __LINE__ .'----- ---- --- -- - - Filter page ALL --');

    // Load frameset contents
    $this->recursive_dump(($containers = $common->get_containers(
      //'#[tagname=frameset]'
      'children[tagname=frameset]'
    )),'(marker) '. __LINE__ .' Filter page frames --');

    $is_frameset_container = count($containers) > 0;

    if ( $is_frameset_container ) $pagecontent = 'Frameset';

    $pagecontent_dedupe = array();
    $logpath = getcwd() . '/extra-data/logs';
    if ($handle = opendir($logpath)) {/*{{{*/
      $action_url = new UrlModel();
      while (false !== ($entry = readdir($handle))) {/*{{{*/

        $poll_archive = "{$logpath}/{$entry}";
        if (!($entry != "." && $entry != "..")) continue;
        if (!file_exists($poll_archive)) continue;

        $logcache = @json_decode(file_get_contents($poll_archive),TRUE);

        $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ---- --- -- - - Loading request log {$poll_archive}: " . 
          ($logcache == FALSE ? 'FAIL' : 'OK'));

        if ($logcache == FALSE) continue;

        $logcache = $common->filter_nested_array(
          $logcache,
          'entries[version=1.1]',0
        );

        array_walk($logcache,create_function(
          '& $a, $k', '$a = array("request" => $a["request"], "response" => $a["response"], "serverIPAddress" => $a["serverIPAddress"], "connection" => $a["connection"]);'
        ));
        // $this->recursive_dump($logcache,"(marker) ----- ---- --- -- - -");
        $dump_one = FALSE; 
        $get_urls = array();

        // Overwrite this URL with cached content
        $overwrite_me = 'https://ireport.sec.gov.ph/iview/onlineview.sx?subaction=loadFilter';
        $overwrite_hash = UrlModel::get_url_hash($overwrite_me);
        $test_url = new UrlModel($overwrite_me,TRUE);

        if ( $overwrite_hash != $test_url->get_urlhash() ) {/*{{{*/

          $this->syslog( __FUNCTION__, __LINE__, "(marker) +++++ +++++ +++++ ++ ++ ++ +++++ +++++ +++++  "); 
          $this->syslog( __FUNCTION__, __LINE__, "(marker) +++++ +++++ +++++ ++ ++ ++ AHA! Mis-saved hash for {$overwrite_me}: {$overwrite_hash} != " . $test_url->get_urlhash() ); 
          $this->syslog( __FUNCTION__, __LINE__, "(marker) +++++ +++++ +++++ ++ ++ ++ +++++ +++++ +++++  "); 

        }/*}}}*/
        else foreach ( $logcache as $action ) {/*{{{*/

          $request_url      = $action['request']['url'];
          $request_headers  = $action['request']['headers'];
          $method           = $action['request']['method'];
          $url_hash         = UrlModel::get_url_hash($request_url);

          if ( $method == 'GET' && array_key_exists($url_hash, $get_urls) ) {/*{{{*/
            if (0) {
              $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- ----- ----- -----  "); 
              $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- SKIPPING {$request_url}"); 
              $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- ----- ----- -----  "); 
            }
            continue;
          }/*}}}*/

          $get_urls[$url_hash] = $request_url; 
          $response_headers = $action['response']['headers'];
          $content          = $action['response']['content'];
          $content_type     = $content['mimeType'];
          $content_length   = $content['size'];
          $content          = $content['text'];
          $content_indic    = is_null($content) ? "({$content_length})" : "{$content_length}";

          $modified_time = NULL;
          $insert_id     = NULL;

          $action_url->debug_method = FALSE;
          $action_url->set_url($request_url, TRUE);
          $url_hash = $action_url->get_urlhash();
          $action_url->debug_method = FALSE;
          $overridden = FALSE;

          if ( $action_url->in_database() && ( $overwrite_hash == $url_hash ) ) {/*{{{*/
            $override_id = $action_url->get_id();
            $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- ----- ----- ----- OVERRIDE #{$override_id}"); 
            $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- ----- ----- ----- < ({$overwrite_hash}) {$overwrite_me}"); 
            $this->syslog( __FUNCTION__, __LINE__, "(marker) ----- ----- ----- -- -- -- ----- ----- ----- > ({$url_hash}) {$request_url} "); 
            $action_url->remove();
            $action_url->id = NULL;
            $overridden = TRUE;
          }/*}}}*/

          $request_headers = $this->extract_key_value_table($request_headers, 'name', 'value');
          $response_headers = $this->extract_key_value_table($response_headers, 'name', 'value');

          $fetch_time = $response_headers['Date']; 
          $timestring   = $fetch_time;
          $modified_time = $response_headers['Last-Modified'];
          $modified_time = strtotime($modified_time);
          $modified_time = FALSE === $modified_time ? NULL : $modified_time;

          if ( !$action_url->in_database() ) {/*{{{*/
            if ( $overridden ) {
              $this->recursive_dump($action,'(marker) - - - OVR - - -');
            }
            $response_headers['_request_'] = $request_headers;
            $insert_id = $action_url->
              set_pagecontent_c($content)->
              set_url_c($request_url,FALSE)->
              set_response_header($response_headers)->
              set_create_time(time())->
              set_last_fetch(time())->
              set_last_modified($modified_time)->
              stow();
            if ( !$insert_id ) {
              $this->recursive_dump($response_headers,"(marker) ----- ---- --- -- - -");
            } else {
              $content_indic = "[Added {$insert_id}]";
            }
          }/*}}}*/
          else {/*{{{*/
            $insert_id = $action_url->get_id();
          }/*}}}*/
          $this->syslog( __FUNCTION__, __LINE__, "(marker) -- {$method} (#{$insert_id}) {$modified_time} {$content_indic} {$request_url}"); 

          if ( $method == 'POST' ) {/*{{{*/
            // Dump POST request info
            $postdata = $action['request']['postData']['params'];
            $postdata = array_combine(
              array_map(create_function('$a', 'return $a["name"];'), $postdata),
              array_map(create_function('$a', 'return $a["value"];'), $postdata)
            );
            ksort($postdata);
            $fake_url = UrlModel::construct_metalink_fake_url($action_url, $postdata);
            $link = UrlModel::create_metalink($response_headers['Date'], $fake_url, $postdata, 'legiscope-remote cached');
            $action_url->set_url($fake_url, TRUE);
            if ( !$action_url->in_database() ) {/*{{{*/
              $action_url->
                set_pagecontent_c($content)->
                set_url_c($fake_url,FALSE)->
                set_response_header($response_headers)->
                set_create_time(time())->
                set_last_fetch(time())->
                set_last_modified($modified_time)->
                set_is_fake(TRUE)->
                stow();
              $this->recursive_dump($postdata,'(marker) POST - -- --- ---- -----');
              $this->syslog( __FUNCTION__, __LINE__, "(marker) - -- --- ---- ----- FAKE URL: () {$fake_url}"); 
              $this->recursive_dump($response_headers,'(marker) SRC - -- --- ---- -----');
            }/*}}}*/
            $timestring = strtotime($timestring);
            $pagecontent_dedupe[$timestring] = <<<EOH
<li>{$link}</li>
EOH;
          }/*}}}*/

        }/*}}}*/
        $test_url = NULL;
      }/*}}}*/
      closedir($handle);
    }/*}}}*/

    ksort($pagecontent_dedupe);
    $pagecontent .= join('', $pagecontent_dedupe);

    $pagecontent = <<<EOH
<div class="float-left link-cluster log-dumps">
<ul class="link-cluster">
{$pagecontent}
</ul>
</div>

<script type="text/javascript">
$(function(){
  initialize_remote_links();
});
</script>

<div class="alternate-original half-container" id="ireport-snapshot-block"></div>

EOH;


  }/*}}}*/

  function seek_postparse_8a8ccf225510fe9bf8b0f5dfd2d0edc2(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->ireport_login_page_parser($parser, $pagecontent, $urlmodel);
  }/*}}}*/

  function ireport_filter_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Invoked for " . $urlmodel->get_url() );
    $test_url = new UrlModel();
    $common   = new iReportParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $pagecontent = join('',$common->get_filtered_doc());

    if (0) {
    // Dump entire structure
    $this->recursive_dump(($containers = $common->get_containers(
    )),'(marker) '. __LINE__ .'----- ---- --- -- - - Filter page ALL --');

    // Load frameset contents
    $this->recursive_dump(($containers = $common->get_containers(
      //'#[tagname=frameset]'
      'children[tagname=frameset]'
    )),'(marker) '. __LINE__ .' Filter page frames --');
    }

    $is_frameset_container = count($containers) > 0;

    if ( $is_frameset_container ) $pagecontent = 'Frameset';

  }/*}}}*/

  function ireport_login_page_parser(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) -- Invoked for " . $urlmodel->get_url() );
    $common      = new iReportParseUtility();
    $common->
      set_parent_url($urlmodel->get_url())->
      parse_html($urlmodel->get_pagecontent(),$urlmodel->get_response_header());

    $pagecontent = join('',$common->get_filtered_doc());

    // Dump entire structure
    $this->recursive_dump(($containers = $common->get_containers(
    )),'(marker) '. __LINE__ .'----- ---- --- -- - - iReport ALL --');

    // Load frameset contents
    $this->recursive_dump(($containers = $common->get_containers(
      //'#[tagname=frameset]'
      'children[tagname=frameset]'
    )),'(marker) '. __LINE__ .' iReport Frames --');

    $is_frameset_container = count($containers) > 0;

    if ( $is_frameset_container ) $pagecontent = 'Frameset';

    $test_url = new UrlModel();
    $frameset_links = array();
    $this->recursive_dump($parser->linkset,'(marker) -- --- -- LS - -');

    foreach ( $containers as $frames ) foreach ( $frames as $frame ) {/*{{{*/
      $test_url->fetch($frame['frame-url'], 'url');
      if ( !$test_url->in_database() || $parser->from_network ) {
        $test_url->set_url($frame['frame-url']);
        $this->perform_network_fetch($test_url, $urlmodel, $test_url->get_url(), NULL, NULL);
      }
      if ( $is_frameset_container ) {
        $frameset_link = <<<EOH
<li><a href="{$frame['frame-url']}" class="legiscope-remote">{$frame['frame-url']}</a></li>
EOH;
        $frameset_links[] = $frameset_link;
        $alt_pagecontent .= <<<EOH
{$frameset_link}
EOH;
      } else {
        $alt_pagecontent = '';
      }
      // Load form contents from the workspace frame
      if ( trim($frame['frame-name']) == 'workspace' ) {/*{{{*/

        $common->
          reset()->
          set_parent_url($test_url)->
          parse_html($test_url->get_pagecontent(), $test_url->get_response_header());

        $this->recursive_dump(($framecontent = $common->get_containers('#[tagname=html]',0)),'(marker) LP --');

        $common->resequence_children($framecontent);

        $controls = $framecontent['children'];
        $form     = $common->filter_nested_array($framecontent['children'],'action[form=clientForm]',0);
        $controls = $common->filter_nested_array($controls,'#[input-type*=.*|i]');


        foreach ( $controls as $control ) {/*{{{*/
          $textinput_class = array("legiscope-proxy-form");
          if ( array_key_exists( $control['input-type'], array_flip(array("text","password")) ) ) {
            $textinput_class[] = 'clsText';
            $alt_pagecontent .= <<<EOH
<label for="{$control['name']}">{$control['name']}</label>
EOH;
          }
          $textinput_class = join(' ', $textinput_class);
          $alt_pagecontent .= <<<EOH
<input class="{$textinput_class}" type="{$control['input-type']}" name="{$control['name']}" value="{$control['value-default']}" /><br/>
EOH;
        }/*}}}*/

        $formwrapper = <<<EOH

<form id="ireport-login" method="post" action="{$form}">
{$pagecontent}
<form>

EOH;

        $this->syslog(__FUNCTION__,__LINE__, "(marker) -- {$form}");
        $this->recursive_dump($controls,'(marker) -- CF');

      }/*}}}*/
      $this->syslog(__FUNCTION__,__LINE__, "(marker) -- {$frame['frame-url']}");
      $this->recursive_dump($frame,"(marker) --");
    }/*}}}*/

    if ( $is_frameset_container ) {/*{{{*/
      // Create links for the frames referenced from this page.
      $parser->linkset = join('', $frameset_links);
      $parser->linkset = <<<EOH
<ul class="link-cluster">
{$parser->linkset}
</ul>
EOH;

      $pagecontent = <<<EOH
<ul class="link-cluster">
{$alt_pagecontent}
</ul>
EOH;
    }/*}}}*/
    else {/*{{{*/
      // Add Javascript intercept for login page.
      // After SUCCESSFUL login:
      // 1) Must load: /iview/onlineview.sx?subaction=loadFilter
      // 2) Generate intercept links for all frameset source pages
      // 3) Generate intercept links for select actions: 
      // - Logout: /iview/logoutClient.sx
      $pagecontent .= <<<EOH
<script type="text/javascript">
$('form[name=jvmForm]').remove();
$('form[name=clientForm]').submit(function(e){
  var url = $(this).attr('action');
  var data = $('input').serializeArray();
  var modifier = $('#seek').prop('checked');
  e.preventDefault();
  $.ajax({
    type     : 'POST',
    url      : '/proxyform/',
    data     : { url: url, data : data, modifier : modifier },
    cache    : false,
    dataType : 'json',
    beforeSend : (function() {
      display_wait_notification();
    }),
    complete : (function(jqueryXHR, textStatus) {
      remove_wait_notification();
    }),
    success  : std_seek_response_handler 
  });
  return false;
});
$('input[class*=clsText]').each(function(){
  $(this).keyup(function(e){
    if ($(e).attr('keyCode') == 13) {
      e.preventDefault();
      $('form[name=clientForm]').submit();
      return false;
    }
    return true;
  });
});
</script>
EOH;
    }/*}}}*/

  }/*}}}*/

  function generic(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/
    $this->syslog( __FUNCTION__, __LINE__, "Invoked for " . $urlmodel->get_url() );
    $this->common_unhandled_page_parser($parser,$pagecontent,$urlmodel);
  }/*}}}*/

  /** Utilities **/

  function map_onlink_parameters($eventhandler) {/*{{{*/
    $handler_params_regex = "@'([^']*)'[,]*@iU";
    $matches = array();
    preg_match_all($handler_params_regex, $eventhandler, $matches);

    $parameters = array(
      'secNo'            => NULL,
      'coChoice'         => NULL,
      'name'             => NULL,
      'psicMajorDiv'     => NULL,
      'psicDiv'          => NULL,

      'psicGrp'          => NULL,
      'psicCls'          => NULL,
      'psicCd'           => NULL,
      'psicMajorDivDscp' => NULL,
      'psicDivDscp'      => NULL,

      'psicGrpDscp'      => NULL,
      'psicClsDscp'      => NULL,
      'psicCdDscp'       => NULL,
      'levelPsic'        => NULL,

      'typeFindComp'     => NULL,
    );

    $matches = array_combine(
      array_keys($parameters),
      $matches[1]
    );
    return $matches;
  }/*}}}*/

}

