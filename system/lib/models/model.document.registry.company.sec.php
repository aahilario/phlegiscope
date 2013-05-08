<?php

/*
 * Class SecCompanyRegistryDocumentModel
 * Legiscope - web site reflection framework
 *
 * Antonio A Hilario
 * Release license terms: GNU Public License V2
 */

class SecCompanyRegistryDocumentModel extends DatabaseUtility {

  var $sec_registration_no_vc64uniq = NULL; //  DS92000065
  var $company_name_vc128 = NULL; //  A.G. VERACION&ASSO. INC.
  var $industry_classification_vc64 = NULL; //  [EMPTY]
  var $company_type_vc64 = NULL; //  Stock Corporation
  var $secondary_license_vc64 = NULL; //  [EMPTY]
  var $date_of_registration_vc32 = NULL; //  04/07/1992
  var $registration_date_dtm = NULL; // date_of_registration
  var $annual_meeting_vc64 = NULL; //  [EMPTY]
  var $term_of_existence_vc32 = NULL; //  50
  var $company_status_vc32 = NULL; //  REVOKED
  var $address_vc256 = NULL; //  305 BAT VIEW HILL, PICOP TABON BISLIG SURIGAO SUR
  var $tel_no_vc64 = NULL; //  [EMPTY]
  var $fax_no_vc64 = NULL; //  [EMPTY]  
  var $fetch_date_utx = NULL;
  var $psic_cd_vc8 = NULL;
  var $psic_cls_vc8 = NULL;
  var $psic_grp_vc8 = NULL;
  var $psic_div_vc8 = NULL;
  var $psic_major_div_vc8 = NULL;
  
  function __construct() {
    parent::__construct();
  }

  function & set_sec_registration_no($v) { $this->sec_registration_no_vc64uniq = $v; return $this; }
  function get_sec_registration_no($v = NULL) { if (!is_null($v)) $this->set_sec_registration_no($v); return $this->sec_registration_no_vc64uniq; }

  function & set_company_name($v) { $this->company_name_vc128 = $v; return $this; }
  function get_company_name($v = NULL) { if (!is_null($v)) $this->set_company_name($v); return $this->company_name_vc128; }

  function & set_industry_classification($v) { $this->industry_classification_vc64 = $v; return $this; }
  function get_industry_classification($v = NULL) { if (!is_null($v)) $this->set_industry_classification($v); return $this->industry_classification_vc64; }

  function & set_company_type($v) { $this->company_type_vc64 = $v; return $this; }
  function get_company_type($v = NULL) { if (!is_null($v)) $this->set_company_type($v); return $this->company_type_vc64; }

  function & set_secondary_license($v) { $this->secondary_license_vc64 = $v; return $this; }
  function get_secondary_license($v = NULL) { if (!is_null($v)) $this->set_secondary_license($v); return $this->secondary_license_vc64; }

  function & set_date_of_registration($v) { $this->date_of_registration_vc32 = $v; return $this; }
  function get_date_of_registration($v = NULL) { if (!is_null($v)) $this->set_date_of_registration($v); return $this->date_of_registration_vc32; }

  function & set_annual_meeting($v) { $this->annual_meeting_vc64 = $v; return $this; }
  function get_annual_meeting($v = NULL) { if (!is_null($v)) $this->set_annual_meeting($v); return $this->annual_meeting_vc64; }

  function & set_term_of_existence($v) { $this->term_of_existence_vc32 = $v; return $this; }
  function get_term_of_existence($v = NULL) { if (!is_null($v)) $this->set_term_of_existence($v); return $this->term_of_existence_vc32; }

  function & set_company_status($v) { $this->company_status_vc32 = $v; return $this; }
  function get_company_status($v = NULL) { if (!is_null($v)) $this->set_company_status($v); return $this->company_status_vc32; }

  function & set_address($v) { $this->address_vc256 = $v; return $this; }
  function get_address($v = NULL) { if (!is_null($v)) $this->set_address($v); return $this->address_vc256; }

  function & set_tel_no($v) { $this->tel_no_vc64 = $v; return $this; }
  function get_tel_no($v = NULL) { if (!is_null($v)) $this->set_tel_no($v); return $this->tel_no_vc64; }

  function & set_fax_no($v) { $this->fax_no_vc64 = $v; return $this; }
  function get_fax_no($v = NULL) { if (!is_null($v)) $this->set_fax_no($v); return $this->fax_no_vc64; }

  function & set_fetch_date($v) { $this->fetch_date_utx = $v; return $this; }
  function get_fetch_date($v = NULL) { if (!is_null($v)) $this->set_fetch_date($v); return $this->fetch_date_utx; }  

  function & set_psic_cd($v) { $this->psic_cd_vc8 = $v; return $this; }
  function get_psic_cd($v = NULL) { if (!is_null($v)) $this->set_psic_cd($v); return $this->psic_cd_vc8; }

  function & set_psic_cls($v) { $this->psic_cls_vc8 = $v; return $this; }
  function get_psic_cls($v = NULL) { if (!is_null($v)) $this->set_psic_cls($v); return $this->psic_cls_vc8; }

  function & set_psic_grp($v) { $this->psic_grp_vc8 = $v; return $this; }
  function get_psic_grp($v = NULL) { if (!is_null($v)) $this->set_psic_grp($v); return $this->psic_grp_vc8; }

  function & set_psic_div($v) { $this->psic_div_vc8 = $v; return $this; }
  function get_psic_div($v = NULL) { if (!is_null($v)) $this->set_psic_div($v); return $this->psic_div_vc8; }

  function & set_psic_major_div($v) { $this->psic_major_div_vc8 = $v; return $this; }
  function get_psic_major_div($v = NULL) { if (!is_null($v)) $this->set_psic_major_div($v); return $this->psic_major_div_vc8; }
}

