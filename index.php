<?php

include_once('system/core.php');

?><!DOCTYPE html>
<html lang="en">
<head>
  <title id='doctitle'>LegiScope</title>
  <meta charset="utf8"/>
  <script type="text/javascript" src="js/jquery.js"></script>
  <script type="text/javascript" src="js/spider.js"></script>
  <script type="text/javascript" src="js/pdf.js"></script>
  <script type="text/javascript" src="js/interactivity.js"></script>
  <link href="css/basic.css" rel="stylesheet" type="text/css" />
  <script type="text/javascript">
    PDFJS.workerSrc = 'js/pdf.js';
  </script>
  </style>
</head>
<body>
<script type="text/javascript">
$(function(){
  $('#keywords').val('');
  initialize_spider();
});
</script>
<div class="access-bar">
  <span class="access-bar-container">Search <input id="keywords" type="text"/></span>
  <span class="access-bar-container">&nbsp;&nbsp;Cache <input id="cache" type="checkbox" value="1" /></span>
  <span class="access-bar-container">&nbsp;&nbsp;Seek <input id="seek" type="checkbox" value="1" /></span>
  <span class="access-bar-container">&nbsp;&nbsp;Spider <input id="spider" type="checkbox" value="1" /></span>
  <span class="access-bar-container">&nbsp;&nbsp;Proxy <input id="proxy" type="checkbox" value="1" /></span>
  <span class="access-bar-container" id="search-wait"></span>
  <div class="contenttabs" id="contenttabs"></div>
</div>
<div class="poc">

  <div id="controlpanel" class="panels">
    <input id="siteURL" type="text" />
    <div id="permalinks" class="linkset">
      <ul class="link-cluster">
        <li><a class="legiscope-remote" href="http://www.gov.ph/section/legis/">Official Gazette</a></li>
        <li><a class="legiscope-remote" href="http://www.gov.ph/the-philippine-constitutions/">O.G. - Constitutions</a></li>
        <li><a class="legiscope-remote" href="http://www.senate.gov.ph">Senate</a></li>
        <li><a class="legiscope-remote" href="http://www.congress.gov.ph">Congress</a></li> 
        <li><a class="legiscope-remote" href="http://www.sec.gov.ph">Securities and Exchange Commission</a></li> 
        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/">GMA News</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/senator">Senators</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/partylist">Party List</a></li>
        <li><a class="legiscope-remote" href="http://www.gmanetwork.com/news/eleksyon2013/results/local">Local</a></li>
        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/login.jsp">SEC iView</a></li> 
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/client_login.jsp">SEC iReport Login</a></li>
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/onlineview.sx?subaction=loadFilter">SEC iReport Filter</a></li>
        <li><a class="legiscope-remote" href="https://ireport.sec.gov.ph/iview/logoutClient.sx?subaction=logout">SEC iReport Logout</a></li>
        <li>- - - - - -</li>
        <li><a class="legiscope-remote" href="http://www.dbm.gov.ph">Department of Budget and Management</a></li> 
        <li><a class="legiscope-remote" href="http://www.denr.gov.ph">Department of Environment and Natural Resources</a></li> 
        <li><a class="legiscope-remote" href="http://emb.gov.ph">Environment Management Bureau</a></li> 
        <li><a class="legiscope-remote" href="http://www.comelec.gov.ph">COMELEC</a></li>
        <li><a class="legiscope-remote" href="http://www.comelec.gov.ph/?r=laws/CompleteList">COMELEC - RL</a></li>
      </ul>
      <hr/>
    </div>
    <div id="linkset" class="linkset"></div>
  </div> <!-- controlpanel -->

  <div id="sitecontent" class="panels">
    <span id="metalink" style="display:none"></span>
    <div class="contentwindow hidden" id="markup"></div>
    <div class="contentwindow hidden" id="responseheader"></div>
<?php if ( C('DISPLAY_ORIGINAL') ) { ?>
    <div class="contentwindow hidden" id="original"></div>
<?php } ?>
    <div class="contentwindow hidden" id="issues">
      <div><a class="legiscope-remote" href="http://<?=SITE_URL?>">Problems to solve:</a></div>
<ul>
  <li>Forms are used to specify parameters - these are transformable to live content.</li>
  <li class="actionable">Build a "human simulator" to extract cross-form links and content.</li>
  <li>Use <code>pdfimages</code> to convert PDFs to OCR-processable images.</li>
  <li>Explore idea of decomposing target site markup, for recomposition by users.</li>
  <li>...</li>
</ul>

      </div>
    </div>
</div> <!-- poc -->

</body>
</html>
<?php
// 202.57.33.10
