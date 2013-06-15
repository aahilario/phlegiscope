<?php

/*
 * Class CongressMemberListParseUtility
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class CongressMemberListParseUtility extends CongressCommonParseUtility {
  
  function __construct() {
    parent::__construct();
  }

	/** **/

  function seek_congress_memberlist(& $parser, & $pagecontent, & $urlmodel) {/*{{{*/

    $this->syslog( __FUNCTION__, __LINE__, "(marker) Invoked for " . $urlmodel->get_url() );

    $member = new RepresentativeDossierModel();

    $urlmodel->ensure_custom_parse();
    $this->debug_tags = FALSE;
    $this->
      set_parent_url($urlmodel->get_url())->
      parse_html(
        $urlmodel->get_pagecontent(),
        $urlmodel->get_response_header()
      );

    $pagecontent      = array();
    $surname_initchar = NULL;
    $section_break    = NULL;
    $districts        = array();

    $this->recursive_dump(($member_list = $this->get_containers(
      'children[tagname=div][id*=content_body_right|content_body_left|i]'
    )),'Extracted rows');

    $lc_surname_initchar = '';

    foreach ( $member_list as $item ) {/*{{{*/
      foreach( $item as $tag ) {/*{{{*/
        if ( !array_key_exists('url',$tag) ) continue;
        $bio_url = $tag['url'];
        $member->fetch($bio_url,'bio_url');

        $cdata = $tag['text'];

        if ( is_null($member->get_firstname()) ) {/*{{{*/
          $parsed_name = $member->parse_name($cdata);
          $member->
            set_fullname($cdata)->
            set_firstname($parsed_name['given'])->
            set_mi($parsed_name['mi'])->
            set_surname($parsed_name['surname'])->
            set_namesuffix($parsed_name['suffix'])->
            stow();
        }/*}}}*/

        $name_regex = '@^([^,]*),(.*)(([A-Z]?)\.)*( (.*))@i';
        $name_match = array();
        preg_match($name_regex, $cdata, $name_match);
        $name_match = array(
          'first-name'     => trim($name_match[2]),
          'surname'        => trim($name_match[1]),
          'middle-initial' => trim($name_match[6]),
        );
        $name_index = "{$name_match['surname']}-" . UrlModel::get_url_hash($tag['url']);
        $district = $this->reverse_iconv($tag['title']); 
        if ( empty($district) ) $district = 'Zz - Party List -';
        else {
          $district_regex = '@^([^,]*),(.*)@';
          $district_match = array();
          preg_match($district_regex, $district, $district_match);
          // $this->recursive_dump($district_match,__LINE__);
          $sub_district = trim($district_match[2]);
          $district = trim($district_match[1]);
        }
        if ( strlen($district) > 0 && !array_key_exists($district, $districts) ) $districts[$district] = array('00' => "<h1>" . preg_replace('@^Zz@','', $district) . "</h1>"); 

        $fullname = "{$name_match['first-name']} {$name_match['middle-initial']} {$name_match['surname']}";
        $surname_first = strtoupper(substr(trim($name_match['surname']),0,1));
        if ( !(strlen($surname_first) > 0) ) continue;
        if ( is_null($surname_initchar) ) $surname_initchar = $surname_first;
        if ( $surname_first != $surname_initchar ) {/*{{{*/
          $surname_initchar = $surname_first;
          $lc_surname_initchar = strtolower($surname_first);
          $section_break = <<<EOH
<br/><h1 class="surname-reset-children" id="surname-reset-{$lc_surname_initchar}">{$surname_first}</h1>
EOH;
        }/*}}}*/
        $urlhash = UrlModel::get_url_hash($bio_url);
        $link_attributes = array("human-element-dossier-trigger");
        if ( $member->in_database() ) $link_attributes[] = "cached";
        else $link_attributes[] = 'trigger';
        $link_attributes[] = "surname-cluster-{$lc_surname_initchar}";
        $link_attributes = join(' ', $link_attributes);
        $candidate_entry = <<<EOH
<span><a href="{$bio_url}" class="{$link_attributes}" id="{$urlhash}">{$fullname}</a></span>

EOH;
        $pagecontent[$name_index] = "{$section_break}{$candidate_entry}";
        $districts[$district][$name_index] = $candidate_entry; 
        $section_break = NULL;
      }/*}}}*/
    }/*}}}*/

    $districts = array_map(create_function('$a', 'ksort($a); return join("<br/>",$a);'), $districts);

    ksort($pagecontent);
    ksort($districts);

    $pagecontent = join('<br/>', $pagecontent);
    $districts = join('<br/>', $districts);

    $cache_state_reset_link = <<<EOH

EOH;
    $pagecontent = <<<EOH
<div class="congresista-dossier-list">
  <input class="reset-cached-links" type="button" value="Clear"> 
  <input class="reset-cached-links" type="button" value="Reset"> 
  <div class="float-left link-cluster" id="congresista-list">{$pagecontent}</div>
  <div class="float-left link-cluster">{$districts}</div>
</div>
<script type="text/javascript">
jQuery(document).ready(function(){
  jQuery('input[class=reset-cached-links]').click(function(e) {
    if (jQuery(this).val() == 'Reset') {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      jQuery(this).parent().first().find('a').each(function(){
        jQuery(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    } 
  });
  jQuery('h1[class=surname-reset-children]').click(function(e){
    var linkset = jQuery(this).attr('id').replace(/^surname-reset-/,'surname-cluster-');
    if ( jQuery(this).hasClass('on') ) {
      jQuery(this).removeClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('cached').removeClass('uncached').addClass("seek");
      });
    } else {
      jQuery(this).addClass('on');
      jQuery('a[class*='+linkset+']').each(function(){
        jQuery(this).removeClass('uncached').removeClass('seek').addClass("cached");
      });
    }
  });
  initialize_dossier_triggers();
});
</script>
<div id="human-element-dossier-container" class="alternate-original half-container"></div>
EOH;
/*
 * Javascript fragment to trigger cycling
 *   setTimeout(function(){
 *   jQuery('div[class*=float-left]').first().find('a[class*=trigger]').removeClass('trigger').click();
 *   },1000);
 */


	}/*}}}*/

}

