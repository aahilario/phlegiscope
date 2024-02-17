const remote = require('webdriverio');
const WebDriverAjax = require('wdio-intercept-service');
//const {By, Builder, WebElement, Browser, Key, until} = require('selenium-webdriver');
//const firefox = require('selenium-webdriver/firefox');
//const LogInspector = require('selenium-webdriver/bidi/logInspector');
//const WebDriver = require('webdriver');
//const BidiCDPConnection = require('./bidiCDPConnection.ts');

const fs = require('fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const chalk = require("chalk");
const url = require("node:url");

//const targetUrl = 'https://congress.gov.ph/legisdocs/?v=bills#HistoryModal';
//const element_xpath = '/html/body/div[2]/div/div[1]/div[2]';
//const targetFile = 'bills-text.html'; 
//const sampleCount = 100;

const targetUrl     = 'https://congress.gov.ph';
const element_xpath = '/html';
const sampleCount   = 0;
const loggedTags = new Map([
  [ "LINK", 0 ],
  [ "A"   , 0 ]
]);
let targetFile    = ''; // 'congress.gov.ph.html';

const pad = " ";

// Globally-scoped Selenium instance
let driver;
let loadedUrl;
let parsedUrl;
let pageUrls = new Map;
let pageHosts = new Map;
let extractedUrls; 

function normalizeUrl( u )
{//{{{
  let fromPage = url.parse(u);
  // Only fill in missing URL components from corresponding targetUrl parts
  for ( e in parsedUrl ) {
    let val = parsedUrl[e] || '';
    if ( val.length > 0 && (fromPage[e] || '').length == 0 ) {
      fromPage[e] = parsedUrl[e];
    }
  }
  return ''.concat(fromPage.protocol,'//',fromPage.hostname.concat('/',fromPage.path).replace(/[\/]{1,}/gi,'/').replace(/\/\.\//,'/').replace(/\/$/,''));
}//}}}

function stashPageUrl( u )
{
  pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
}

function stashUrlDomain( u )
{
  let p = url.parse(u);
  let h = p.host;
  if ( h.length ) {
    pageHosts.set( h, (pageHosts.get(h) || 0) + 1 );
  }
}

function detag( index, element, depth = 0, elementParent = null, indexlimit = 0  )
{//{{{

  const $ = cheerio.load( element );
  const tagname = $("*").prop('tagName');

  if ( depth == 0 ) {
    if ( indexlimit > 0 && index >= indexlimit )
      return false;
  }

  const className = $(tagname).prop("class") || ''; // element.attribs && element.attribs.class ? element.attribs.class : '';

  const logthis = loggedTags.has(tagname);

  if ( tagname == null ) {
    if ( String(element.data).trim().length > 0 ) {
      let parentName = $(elementParent).prop('tagName');
      if ( loggedTags.has( parentName ) || loggedTags.has("*") ) {
        //console.log( "%s%d> '%s'", pad.repeat(depth<<1), index, String(element.data).trim() );
        console.log( "%s%d ^[%s] > '%s'", pad.repeat(depth<<1), index, parentName, String(element.data).trim() );
      }
    }
  }
  else if ( tagname == 'META' ) {
    let attrlist = new Array;
    for ( a in element.attribs ) {
      attrlist.push( a.concat(': ',element.attribs[a]) );
    }
    if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, attrlist.join(', ') );
  }
  else if ( tagname == 'LINK' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, $("*").attr('rel').concat(": ",urlText) );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'A' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    if ( logthis ) console.log( "%s%d: %s{%s} %s", pad.repeat(depth<<1), index, tagname, className, urlText );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'SCRIPT' ) {
    urlText = normalizeUrl( $("*").attr('src') );
    if ( logthis ) console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, ($("*").attr('type') || '').concat(": ",urlText) );
    stashPageUrl( urlText );
    stashUrlDomain( urlText );
  }
  else if ( tagname == 'BR' ) {
    if ( logthis ) console.log( "%s%d: ---------------------------------------", pad.repeat(depth<<1), index );
  }
  else if ( tagname.match(/(DIV|P|SPAN|LI)/) ) {
    if ( logthis ) console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, className );
  }
  else if ( loggedTags.has("*") ) {
    console.log( "%s%d: %s", pad.repeat(depth<<1), index, tagname );
  }

  // Recurse to a reasonable tag nesting depth
  if ( depth < 20 ) {
    $(tagname).contents().each(function (i, e) {
      detag( i, e, depth + 1, $(tagname), indexlimit );
    });
    // Newline between containing chunks
    if ( 0 == depth ) console.log( "" );
  }
  return true;
};//}}}

async function extract_urls( data, target )
{//{{{
  const $ = cheerio.load( data );
  console.log("Fetched markup.  Parsing...");
  $('html')
    .children()
    .each( function(i,e) { detag(i,e,0,null,sampleCount); });

  let pageUrlsArray = new Array;

  if ( pageUrls.size > 0 ) {

    urlListFile = targetFile.concat(".urls.json");
    console.log("Dumping %d URLs rooted at '%s'", pageUrls.size, target ); 

    for ( const url of pageUrls.keys() ) {
      pageUrlsArray.push( url );
    }

    fs.writeFile( urlListFile, JSON.stringify( pageUrlsArray ), function(err) {
      if ( err ) {
        console.log( "Failed to write URLs from page at '%s'", target );
      }
      else {
        console.log( "Wrote %d URLs from '%s' into '%s'", pageUrls.size, target, urlListFile );
      }
    });
  }
  return pageUrlsArray;
};//}}}

function fetch_and_extract( target )
{
  // Walk through all nodes in DOM

  let state_timeout = 100;

  parsedUrl = url.parse(target);

  console.log( "Target: %s", target );

  // Generate targetFile path based on URL path components.
  if ( 0 == targetFile.length ) {//{{{
    let relativePath = new Array();
    let pathParts = parsedUrl.host.concat('/', parsedUrl.path).replace(/[\/]{1,}/gi,'/').replace(/[\/]{1,}$/,'').replace(/^[\/]{1,}/,'').split('/');
    let pathNow = '';
    while ( pathParts.length > 0 ) {
      let part = pathParts.shift();
      relativePath.push(part);
      pathNow = relativePath.join('/');
      if ( fs.existsSync( pathNow ) ) continue;
      fs.mkdirSync( pathNow );
    }
    targetFile = pathNow.concat('/index.html');
    console.log( 'Target path computed as %s', targetFile );
  }//}}}

  // Record this URL as the first unique entry
  pageUrls.set( target, 1 ); 

  let fileProps;
  
  try {
    fileProps = fs.statSync( targetFile );
    console.log( "Target '%s' props:", targetFile, fileProps );
  } catch(e) {
    console.log("Funky");
    fileProps = null;
  }

  if ( !fileProps ) {

    let browser;
    state_timeout = 20000;

    console.log(WebDriverAjax);
    //before(async function () {
    //  driver = await new Builder()
    //    .setFirefoxOptions(new firefox.Options().enableBidi())
    //    .forBrowser('firefox')
    //    .build();
    //});
    // after(async () => await driver.quit());

//    const interceptServiceLauncher = WebdriverAjax();
    
    beforeAll(async () => {
      browser = await remote({
        capabilities: {
          browserName: 'firefox',
          'moz:firefoxOptions': {
            binary: '/opt/firefox/firefox'
          },
          'wdio:geckodriverOptions' : {
            binary: '/usr/local/bin/geckodriver'
          }
        }
      });
//      interceptServiceLauncher.before(null, null, browser);
    })
    
//    beforeEach(async () => {
//      interceptServiceLauncher.beforeTest();
//    })
//    
//    afterAll(async () => {
//      // await client.deleteSession();
//    });

    it('Fetch '.concat(target), async function () {

      // To obtain HTTP responses
      // See https://stackoverflow.com/questions/73302181/how-to-get-http-responsebody-using-selenium-cdp-javascript
      
      //await driver.get(target);
      await browser.url(target);
      // await browser.setupInterceptor();

      //let title = await driver.getTitle();
      //loadedUrl = await driver.getCurrentUrl();
      let loadedUrl = await browser.getUrl();
      let title = browser.$('title').getText();

      assert.equal("House of Representatives", title);
      //await driver.manage().setTimeouts({implicit: 500});

      console.log( "Loaded URL %s", loadedUrl );

      //let markup = await driver.getPageSource();
      let markup = await browser.$('html').getHtml();

      extractedUrls = extract_urls( markup, target );

      await fs.writeFile(targetFile, markup, function(err) {
        if ( err ) {
          console.log( "Unable to write %s to %s: %s", loadedUrl, targetFile, err );
        }
        else {
          console.log( "Wrote %s to %s", loadedUrl, targetFile );
        }
      });
    });
  }
  else {
    state_timeout = 2000;
    data = fs.readFileSync(targetFile);
    it("Parse ".concat(target), async function() {
      extractedUrls = extract_urls( data, target );
    });
  }
  console.log( "Resolution deadline: %dms", state_timeout );
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(extractedUrls);
    },state_timeout);
  });
}


async function execute_extraction() {

  // Invoke fetch_and_extract( targetUrl );
 
  let unique_host_path = new Map;

  const result = await fetch_and_extract( targetUrl );

  while ( result.length > 0 ) {

    let u = url.parse( result.shift() );

    let unique_entry = u.protocol.concat("//", u.hostname, u.pathname);

    console.log("%d: Host %s pathname '%s' query %s hash %s unique %s", 
      result.length,
      u.host, 
      u.pathname, 
      u.query ? "'".concat(u.query,"'") : '<null>',
      u.hash || '<null>',
      unique_entry, 
      unique_host_path.get( unique_entry )
    );

    if ( unique_host_path.get( unique_entry ) === undefined ) {
      unique_host_path.set( unique_entry, 1 );
    }
    else {
      unique_host_path.set( unique_entry, unique_host_path.get( unique_entry ) + 1 );
    }
  }

  console.log( "Obtained %d unique pathnames (sans URL query part)", unique_host_path.size );

  for ( const url of unique_host_path.keys() ) {
    console.log( url );
  }
}

execute_extraction();

