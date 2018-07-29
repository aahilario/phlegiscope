<?php

/*
 * Class ConstitutionSectionConstitutionSectionVariantsJoin
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 * Generated on Sun, 29 Jul 2018 16:18:58 +0000
 */

class ConstitutionSectionConstitutionSectionVariantsJoin extends DatabaseUtility {
  
  // Join table model
  var $constitution_section_ConstitutionSectionModel;
  var $constitution_section_variants_ConstitutionSectionVariantsModel;

  function __construct() {
    parent::__construct();
    if (C('DEBUG_'.get_class($this))) {
      $this->dump_accessor_defs_to_syslog();
      $this->recursive_dump($this->get_attrdefs(),'(marker) "+++++++"');
    }
  }

  function & set_constitution_section($v) { $this->constitution_section_ConstitutionSectionModel = $v; return $this; }
  function get_constitution_section($v = NULL) { if (!is_null($v)) $this->set_constitution_section($v); return $this->constitution_section_ConstitutionSectionModel; }

  function & set_constitution_section_variants($v) { $this->constitution_section_variants_ConstitutionSectionVariantsModel = $v; return $this; }
  function get_constitution_section_variants($v = NULL) { if (!is_null($v)) $this->set_constitution_section_variants($v); return $this->constitution_section_variants_ConstitutionSectionVariantsModel; }

}

