<?php 
$menu_title = get_option('phlegiscope_menutitle');
echo <<<EOH
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br/></div>
  <h2>{$menu_title} Curator's Page</h2>
</div>
EOH;
