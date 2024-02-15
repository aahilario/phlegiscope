const {By, Builder, WebElement, Browser, Key, until} = require('selenium-webdriver');
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
const targetFile    = 'congress.gov.ph.html';
const sampleCount   = 0;

const pad = " ";

// Globally-scoped Selenium instance
let driver;
let loadedUrl;
let parsedUrl;
let pageUrls = new Map;

parsedUrl = url.parse(targetUrl);

function normalizeUrl( u )
{
  let fromPage = url.parse(u);
  // Only fill in missing URL components from corresponding targetUrl parts
  for ( e in parsedUrl ) {
    let val = parsedUrl[e] || '';
    if ( val.length > 0 && (fromPage[e] || '').length == 0 ) {
      fromPage[e] = parsedUrl[e];
    }
  }
  return ''.concat(fromPage.protocol,'://',fromPage.hostname.concat('/',fromPage.path).replace(/[\/]{1,}/gi,'/').replace(/\/\.\//,'/'));
}

function detag( index, element, depth = 0, indexlimit = 0  )
{//{{{

  const $ = cheerio.load( element );
  const tagname = $("*").prop('tagName');

  if ( depth == 0 ) {
    if ( indexlimit > 0 && index >= indexlimit )
      return false;
  }

  const className = element.attribs && element.attribs.class ? element.attribs.class : '';

  if ( tagname == null ) {
    if ( String(element.data).trim().length > 0 ) {
      console.log( "%s%d> '%s'", pad.repeat(depth<<1), index, String(element.data).trim() );
    }
  }
  else if ( tagname == 'META' ) {
    let attrlist = new Array;
    for ( a in element.attribs ) {
      attrlist.push( a.concat(': ',element.attribs[a]) );
    }
    console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, attrlist.join(', ') );
  }
  else if ( tagname == 'LINK' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, $("*").attr('rel').concat(": ",urlText) );
    //if ( !pageUrls.has(urlText) ) pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
    pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
  }
  else if ( tagname == 'A' ) {
    urlText = normalizeUrl( $("*").attr('href') );
    console.log( "%s%d: %s{%s} %s", pad.repeat(depth<<1), index, tagname, className, urlText );
    //if ( !pageUrls.has(urlText) ) pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
    pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
  }
   else if ( tagname == 'SCRIPT' ) {
    urlText = normalizeUrl( $("*").attr('src') );
    console.log( "%s%d: %s %s", pad.repeat(depth<<1), index, tagname, ($("*").attr('type') || '').concat(": ",urlText) );
    //if ( !pageUrls.has(urlText) ) pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
    pageUrls.set(urlText, (pageUrls.get(urlText) || 0) + 1);
  }
  else if ( tagname == 'BR' ) {
    console.log( "%s%d: ---------------------------------------", pad.repeat(depth<<1), index );
  }
  else if ( tagname.match(/(DIV|P|SPAN|LI)/) ) {
    console.log( "%s%d: %s{%s}", pad.repeat(depth<<1), index, tagname, className );
  }
  else
    console.log( "%s%d: %s", pad.repeat(depth<<1), index, tagname );

  // Recurse to a reasonable tag nesting depth
  if ( depth < 20 ) {
    $(tagname).contents().each(function (i, e) {
      detag( i, e, depth + 1 );
    });
    // Newline between containing chunks
    if ( 0 == depth ) console.log( "" );
  }
  return true;
};//}}}


// Walk through all nodes in DOM
describe('First script', function () {

  console.log( "Target: %s", parsedUrl );

  fs.readFile(targetFile, (err, data) => {

    if ( err ) {

      before(async function () {
        driver = await new Builder().forBrowser('firefox').build();
      });

      after(async () => await driver.quit());

      it('Fetch Congress.gov.ph House Bills', async function () {

        await driver.get(targetUrl);

        let title = await driver.getTitle();
        loadedUrl = await driver.getCurrentUrl();

        assert.equal("House of Representatives", title);
        await driver.manage().setTimeouts({implicit: 500});

        console.log( "Loaded URL %s", loadedUrl );

        let markup = await driver.getPageSource();

        fs.writeFile(targetFile, markup, function(err) {
          console.log( "Unable to write %s to %s: %s", loadedUrl, targetFile, err );
        });

        //let billsList = await driver.findElement(By.xpath(element_xpath));

        //billsList.getAttribute("innerHTML").then( function(content) {
        //  fs.writeFile(targetFile,  content, function(err){}); 
        //});
      });
    }
    else {
      const $ = cheerio.load( data );
      console.log("Fetched markup.  Parsing...");
      $('html')
        .children()
        .each( function(i,e) { detag(i,e,0,sampleCount); });

      if ( pageUrls.size > 0 ) {

        let pageUrlsArray = new Array;
        urlListFile = targetFile.concat(".urls.json");
        console.log("Dumping %d URLs rooted at '%s'", pageUrls.size, targetUrl ); 

        for ( const url of pageUrls.keys() ) {
          pageUrlsArray.push( url );
        }
        //pageUrls.forEach((value, key, map) => {
        //  pageUrlsArray.push({'url': key, 'n': value});
        //});
        fs.writeFile( urlListFile, JSON.stringify( pageUrlsArray ), function(err) {
          if ( err ) {
            console.log( "Failed to write URLs from page at '%s'", targetUrl );
          }
          else {
            console.log( "Wrote %d URLs from '%s' into '%s'", pageUrls.size, targetUrl, urlListFile );
          }
        });
      }
    }
  });

});
