const {By, Builder, WebElement} = require('selenium-webdriver');
const fs = require('fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const chalk = require("chalk");

//const targetUrl = 'https://congress.gov.ph/legisdocs/?v=bills#HistoryModal';
//const element_xpath = '/html/body/div[2]/div/div[1]/div[2]';
//const targetFile = 'bills-text.html'; 
//const sampleCount = 100;

const targetUrl     = 'https://congress.gov.ph';
const element_xpath = '/html/body';
const targetFile    = 'congress.gov.ph.html';
const sampleCount   = 0;

const pad = " ";

// Globally-scoped Selenium instance
let driver;

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
  else if ( tagname == 'A' ) {
    console.log( "%s%d: %s{%s} %s", pad.repeat(depth<<1), index, tagname, className, $("*").attr('href') );
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

  fs.readFile(targetFile, (err, data) => {
    if ( err ) {

      before(async function () {
        driver = await new Builder().forBrowser('firefox').build();
      });

      //after(async () => await driver.quit());

      it('Fetch Congress.gov.ph House Bills', async function () {

        await driver.get(targetUrl);

        let title = await driver.getTitle();

        assert.equal("House of Representatives", title);
        await driver.manage().setTimeouts({implicit: 500});

        let billsList = await driver.findElement(By.xpath(element_xpath));

        billsList.getAttribute("innerHTML").then( function(content) {
          fs.writeFile(targetFile,  content, function(err){}); 
        });
      });
    }
    else {
      const $ = cheerio.load( data );
      console.log("Fetched markup.  Parsing...");
      $('html').find('body').children().each( function(i,e) { detag(i,e,0,sampleCount); });
    }
  });

});
