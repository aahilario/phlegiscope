<?php

/*
 * Class ConstitutionSectionModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Tue, 24 Jul 2018 15:15:18 +0000
 */

class ConstitutionSectionModel extends DatabaseUtility {
  
  var $section_content_vc8192;
  var $variant_ConstitutionSectionVariantsModel;
  var $commentary_ConstitutionCommentaryModel;
  var $created_dtm;
  var $updated_dtm;
  var $slug_vc255uniq;

  function __construct() {
    parent::__construct();
  }

  function & set_section_content($v) { $this->section_content_vc8192 = $v; return $this; } function get_section_content($v = NULL) { if (!is_null($v)) $this->set_section_content($v); return $this->section_content_vc8192; }
  function & set_created($v) { $this->created_dtm = $v; return $this; } function get_created($v = NULL) { if (!is_null($v)) $this->set_created($v); return $this->created_dtm; }
  function & set_updated($v) { $this->updated_dtm = $v; return $this; } function get_updated($v = NULL) { if (!is_null($v)) $this->set_updated($v); return $this->updated_dtm; }
  function & set_slug($v) { $this->slug_vc255uniq = $v; return $this; } function get_slug($v = NULL) { if (!is_null($v)) $this->set_slug($v); return $this->slug_vc255uniq; }

  function & fetch_by_section_slug( & $section_record, $section_slug )
  {/*{{{*/
    return $this
      ->join_all()
      ->where(array("slug" => $section_slug))
      ->record_fetch_continuation($section_record)
      ->syslog(  __FUNCTION__, __LINE__, "(marker) -- Section fetch result " . (is_null($section_record) ? "FALSE" : "TRUE") )
      // ->recursive_dump( $section_record, "(marker) -- 0 0 ")
      ;
  }/*}}}*/

  function & generate_missing_section( & $section_record, $section_slug, $variants_record, $content )
  {/*{{{*/
    if ( !isset($section_record['id']) ) {
      $section_record = NULL;
      $section_id = $this
        ->set_id(NULL)
        ->set_contents_from_array(array(
          'section_content' => $content,
          'variant' => $variants_record['id'],
          'created' => date('Y-m-d H:i:s', time()),
          'updated' => date('Y-m-d H:i:s', time()),
          'slug' => $section_slug,
        ))
        ->stow();

      if( 0 < $section_id ) { 

        $section_record['id'] = $section_id;

        if ( is_array($variants_record) && isset($variants_record['id']) ) {/*{{{*/
          // Experimental: Join Section record to SectionVariant 
          $section_to_variant    = [ $variants_record['id']   ];
          $section_to_commentary = [ $commentary_record['id'] ];
          $result = $this
            ->set_id( $section_id )
            ->create_joins('ConstitutionSectionVariantsModel', $section_to_variant   , FALSE);
          $result = $this
            ->set_id( $section_id )
            ->create_joins('ConstitutionCommentaryModel'     , $section_to_commentary, FALSE);
        }/*}}}*/

        $this->
          join_all()->
          syslog( __FUNCTION__, __LINE__, "(marker) -- Recorded section, storing commentary using section ID #{$section_id}" )->
          where(array('slug' => $section_slug))->
          record_fetch_continuation($section_record);
      }
      else {
        $this->
          syslog( __FUNCTION__, __LINE__, "(marker) -- No section record.  Will not record comment structure in DB." );
      }
    }
    return $this;
  }/*}}}*/

  function & fetch_related_sections( & $all_section_records, $slug ) 
  {/*{{{*/
    // SectionVariants <- SectionSectionVariantJoin <-> Section
    $debug_method = C('DEBUG_'.__FUNCTION__,FALSE);
    $sql = <<<EOS
SELECT a.section_variant, csj.`id` commentary_section, 
s2.`id` section, s2.`slug`, cm.`id` commentary, cm.`summary`, 
cm.`title`, cm.`link`, cm.`linkhash`, cm.`approved`, cm.`added`, cm.`updated` FROM (
  SELECT v.id section_variant FROM
    `constitution_section_model` s1 
    LEFT JOIN `constitution_section_constitution_section_variants_join` sj
      ON s1.`id` = sj.`constitution_section`
    LEFT JOIN `constitution_section_variants_model` v 
      ON sj.`constitution_section_variants` = v.`id`
  WHERE s1.`slug` = '{$slug}') a 
LEFT JOIN `constitution_section_constitution_section_variants_join` ssv
  ON a.`section_variant` = ssv.constitution_section_variants
LEFT JOIN `constitution_section_model` s2
  ON ssv.`constitution_section` = s2.id
LEFT JOIN `constitution_commentary_constitution_section_join` csj
  ON s2.id = csj.`constitution_section`
LEFT JOIN `constitution_commentary_model` cm
  ON csj.`constitution_commentary` = cm.id
ORDER BY length(cm.summary) DESC
EOS;
    $sql = preg_replace('@(\r|\n)@',' ',$sql);
    $this->query($sql);
    $section_record      = [];

    if ( array_key_exists('linkset', $all_section_records) ) {
      $n = 0;
      while ( $this->raw_recordfetch( $section_record ) ) {
        $n++;
        $all_section_records['linkset']['link'][$section_record['linkhash']] = [
          'link'    => $section_record['link'],
          'title'   => $section_record['title'],
          'summary' => $section_record['summary'],
          'join_id' => $section_record['commentary_section'],
        ];
      }
      $all_section_records['linkset']['links'] = $n;
    }
    else {
      $all_section_records = [];
      while ( $this->raw_recordfetch( $section_record ) ) {
        $all_section_records[] = $section_record;
        $section_record = [];
      }
    }
    if ( 0 == count($all_section_records) ) 
      $all_section_records = NULL;
    if ( $debug_method ) $this
      ->syslog( __FUNCTION__, __LINE__, "(marker) -- Query: {$sql}" );
    return $this;
  } /*}}}*/
}

