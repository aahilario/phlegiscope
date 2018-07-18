<?php

legiscope_extend_include_path();

require_once('configuration.php');

LegiscopeBase::wordpress_enqueue_scripts();
LegiscopeBase::instantiate_by_host();
LegiscopeBase::image_request();
LegiscopeBase::javascript_request();
LegiscopeBase::stylesheet_request();
LegiscopeBase::model_action();


