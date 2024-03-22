const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, pid } = require("node:process");
const { spawnSync } = require("child_process");

const CDP = require('chrome-remote-interface');

const targetUrl     = process.env.TARGETURL || 'https://congress.gov.ph/';

async function monitor() {
  let client;
  let nodes_seen = new Map;
  let nodes_tree = new Map;
  let depth = 0;
  try {
    // connect to endpoint
    client = await CDP();
    // extract domains
    const {Network, Page, DOM} = client;
    // setup handlers
    Network.requestWillBeSent((params) => {
      console.log("Q[%s] %s", params.requestId, params.request.url);
    });
    Network.responseReceived((params) => {
      console.log("A[%s]", params.requestId, params.response);
    });
    DOM.setChildNodes(async ({parentId, nodes}) => {
      console.log( "Parent %d children %d", 
        parentId, 
        nodes.length,
        await DOM.getOuterHTML({ nodeId: parentId }) 
      );
      await nodes.forEach(async (n,nn,node) => {
        console.log("Node[%d] %d <- %d %s children %d", 
          nn,
          parentId,
          n.nodeId, 
          n.nodeName ? n.nodeName : '---',
          n.childNodeCount ? n.childNodeCount : 0, 
          await DOM.getOuterHTML({ nodeId: n.nodeId }),
          n.children && n.children.length > 0 ? n : "below"
        );
        try {
          if ( n.children && n.children.length > 0 ) {
            n.children.forEach(async function(m,nm,n_) {
              if ( m && m.nodeId ) {
                console.log("Sub[%d] %d <- %d %s parent %d children %d",
                  nm,
                  parentId,
                  m.nodeId,
                  m.nodeName ? m.nodeName : '---',
                  m.parentId ? m.parentId : '---',
                  m.childNodeCount ? m.childNodeCount : 0,
                  await DOM.getOuterHTML({nodeId: m.nodeId})
                );
                DOM.requestChildNodes({nodeId: m.nodeId, depth: 6});
              }
              return Promise.resolve(true);
            });
          }
        }
        catch(e) {
          console.log('Err', e);
        }
        return Promise.resolve(true);
      });
      return Promise.resolve(true);
    });
    // enable events then start!
    await Network.enable();
    await DOM.enable({ includeWhitespace: "none" });
    await Page.enable();

    await Page.navigate({url: targetUrl});
    await Page.loadEventFired(async ({ts}) => {
      const {root:{nodeId}} = await DOM.getDocument({ pierce: true });
      await DOM.requestChildNodes({nodeId: nodeId, depth: 6});
    });
  } catch (err) {
    console.error(err);
  } finally {
    if (client) {
      // client.close();
    }
  }
  return Promise.resolve(true);
}

monitor();
