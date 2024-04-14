const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync, linkSync, unlinkSync, symlinkSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, pid, hrtime } = require("node:process");
const { spawnSync } = require("child_process");
const { inspect } = require("node:util");

const CDP = require('chrome-remote-interface');

const db_user = process.env.LEGISCOPE_USER || '';
const db_pass = process.env.LEGISCOPE_PASS || '';
const db_host = process.env.LEGISCOPE_HOST || '';
const db_name = process.env.LEGISCOPE_DB   || '';
const output_path = process.env.DEBUG_OUTPUT_PATH || '';
const targetUrl = process.env.TARGETURL || '';
const rr_timeout_s = 10; // Seconds of inactivity before flushing page metadata 
const node_request_depth = 7;

var mysql = require("mysql");

let outstanding_rr = new Map;
let latest_rr = 0;
let xhr_fetch_rr = 0;
let xhr_fetch_id = 0;
let append_buffer_to_rr_map = 0;
let rr_map = new Map;
let rr_mark = 0; // hrtime.bigint(); 
let rr_begin = 0;
let cycle_date;
let mark_steps = 0;
let rootnode_n;
let postprocessing = 0;
let exception_abort = false;

let triggerable = -1;

function sleep( millis )
{//{{{
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(true);
    },millis);
  });
}//}}}

function networkResponseReceived(params)
{//{{{
  let response = {
    status            : params.response.status,
    statusText        : params.response.statusText,
    headers           : params.response.headers,
    mimeType          : params.response.mimeType,
    charset           : params.response.charset,
    encodedDataLength : params.response.encodedDatalength,
    responseTime      : params.response.responseTime,
    protocol          : params.response.protocol
  };
  if ( rr_map.has( params.requestId ) ) {
    let m = rr_map.get( params.requestId );
    m.response = response;
    if (envSet('QA','1')) console.log("A[%s] %d %s %s", 
      params.requestId, 
      response.status,
      params.response.url, 
      response.mimeType
    );
    rr_map.set( params.requestId, m );
  }
  else {
    if (envSet('QA','1')) console.log("B[%s]", params.requestId, response );
  }
  rr_mark = hrtime.bigint();
}//}}}

function networkRequestWillBeSent(params)
{//{{{
  let markdata = {
    requestId : params.requestId,
    url       : params.request.url,
    method    : params.request.method,
    timestamp : params.timestamp,
    wallTime  : params.wallTime,
    initiator : params.initiator
  };
  latest_rr = params.requestId;
  if ( !rr_map.has( latest_rr ) ) {
    rr_map.set( latest_rr, {
      url      : markdata.url,
      request  : markdata,
      response : {}
    });
  }
  if ( !outstanding_rr.has( latest_rr ) ) {
    outstanding_rr.set( latest_rr, markdata );
  }
  if (envSet('QA','1')) console.log("Q[%s] %s %s", 
    latest_rr, 
    markdata.method,
    markdata.url
  );
  rr_mark = hrtime.bigint();
}//}}}

function networkLoadingFinished(params)
{//{{{
  if ( outstanding_rr.has( params.requestId ) ) {
    latest_rr = params.requestId; // Potentially usable for tracking XMLHTTPRequest markup fetches
    outstanding_rr.delete( params.requestId );
  }
  if (envSet('QA','1')) console.log("L[%s]", params.requestId, outstanding_rr.size );
  rr_mark = hrtime.bigint();
}//}}}

function return_sorted_map( map_obj )
{//{{{
  let sorter = new Array;
  let sorted = new Map;
  map_obj.forEach((value, key, map) => {
    sorter.push(key);
  });
  sorter.sort((a,b) => {return a - b;});
  sorter.forEach((e) => {
    sorted.set( e, map_obj.get(e) );
    map_obj.delete(e);
  });
  sorter.forEach((e) => {
    map_obj.set( e, sorted.get(e) );
    sorted.delete(e);
  });
  while ( sorter.length > 0 ) { sorter.pop(); }
  sorted.clear();
  sorted = null
  sorter = null;
  return map_obj;
}//}}}

function write_to_file( fn, file_ts, content )
{//{{{
  let ts = file_ts === undefined ? datestring( cycle_date ) : file_ts; 
  let fn_parts = [ 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$1'), 
    fn.replace(/^(.*)\.([^.]{1,})$/i,'$2')
  ];
  let fn_ts = [ [ fn_parts[0], '-',  ts ].join(''), (fn_parts[1].length > 0 && fn_parts[1] != fn_parts[0]) ? ['.', fn_parts[1]].join('') : '' ].join(''); 
  let outfile = [output_path, fn_ts].join('/')
  try {
    // Plain name is used to create a symbolic link.
    // Unlink that if it is present.
    unlinkSync( [output_path,fn].join('/') );
  } catch (e) {} 
  writeFileSync( outfile, content, {
    flag : "w+",
    flush: true
  });
  symlinkSync( fn_ts, [output_path,fn].join('/') );
}//}}}

function write_map_to_file( description, map_file, map_obj, file_ts )
{//{{{
  console.log( "Writing %s to %s", description, map_file );
  write_to_file( map_file, file_ts,  JSON.stringify( map_obj, 
    // Stringify an ES6 Map
    // https://stackoverflow.com/questions/29085197/how-do-you-json-stringify-an-es6-map
    function (key, value) {
      if(value instanceof Map) {
        return Object.fromEntries(value);
      } else {
        return value;
      }
    }, 2 )
  );
}//}}}

function envSet( v, w )
{//{{{
  return w === undefined
  ? ( process.env[v] !== undefined )
  : ( process.env[v] !== undefined && process.env[v] === w );
}//}}}

async function monitor() {

  let client;
  let nodes_seen  = new Map;
  let nodes_tree  = new Map;
  let lookup_tree = new Map;
  let tag_stack   = new Array;
  let parents_pending_children = new Array; 
  let waiting_parent = 0;
  let depth = 0;
  let rootnode = 0;
  let completed = false;
  let db;

  try {
    // If database host, user, and password are specified in environment,
    // attempt to connect, and do not proceed if connection fails.
    if ( db_host.length > 0 && db_user.length > 0 && db_pass.length > 0 ) {
      db = mysql.createConnection({
        host     : db_host,
        user     : db_user,
        password : db_pass,
        database : db_name
      });
      db.connect(function(err) {
        if ( err ) {
          console.log("Database:", err.sqlMessage ? err.sqlMessage : err );
          process.exit(1);
        }
      });
    }
  }
  catch(e) {
    process.exit(1);
  }

  client = await CDP();

  const { Network, Page, DOM, Input } = client;

  async function watchdog(cb)
  {//{{{
    return new Promise((resolve) => {
      setTimeout(async () => {
        if ( rr_mark > 0 ) {
          let rr_current = hrtime.bigint();
          let delta = Number((rr_current - rr_mark)/BigInt(1000 * 1000));
          if ( delta > 1000 * rr_timeout_s ) { 
            rr_mark = 0;
            mark_steps = 0;
            console.log("\r\n--CLEAR--\r\n");
            if ( cb ) await cb( mark_steps );
          }
          else {
            mark_steps++;
            if ( envSet('WATCHDOG','1') ) console.log("--MARK[%d]--", mark_steps, parents_pending_children.length);
            if ( cb ) await cb( mark_steps );
          }
        }
        resolve(true);
      },1);
    });
  }//}}}

  function register_parent_in_waiting( nodeId )
  {//{{{
    // Only call this method 
    // if node.childNodeCount > 0 AND node.children === undefined
    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('?');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log("ENQUEUE [%d]", nodeId);
    parents_pending_children.push( nodeId );
  }//}}}

  function mapify_attributes( ma )
  {//{{{
    let attrset = ma ? ma : [];
    let attrmap = new Map;
    while ( attrset.length > 0 ) {
      let attr = attrset.shift();
      let attrval = attrset.shift();
      attrmap.set( attr, attrval );
    }
    return attrmap;
  }//}}}

  async function recursively_add_and_register( m, parent_nodeId, depth )
  {//{{{
    // Regular inorder tree traversal to populate nodes from 
    // array nodes_seen. Fully populating the tree requires a
    // second traversal to pick up newly-fetched nodes acquired 
    // by triggering requestChildNodes (in trigger_dom_fetch) 
    //
    // Parameters:
    // m: CDP DOM.Node
    // parentNode: Abbreviated node record ID
    // depth: Current recursive call nesting depth
    let parentNode = nodes_seen.get( parent_nodeId ); 
    let has_child_array = (m.children !== undefined) && (m.children.length !== undefined);
    let enqueue_m_nodeid = (m.childNodeCount !== undefined) && !has_child_array && m.childNodeCount > 0;
    if ( !nodes_seen.has(m.nodeId) ) {
      let attrmap = mapify_attributes( m.attributes );
      let isLeaf = !((m.childNodeCount && m.childNodeCount > 0) || has_child_array); 
      nodes_seen.set( m.nodeId, {
        nodeName   : m.nodeName ? m.nodeName : '---',
        parentId   : parent_nodeId,
        attributes : attrmap.size > 0 ? attrmap : null,
        isLeaf     : isLeaf,
        content    : /*new Map */ isLeaf
        ? m.nodeValue
        : new Map
      });
      //attrmap.clear();
      //attrmap = null;
    }

    // Create placeholder flat Map node entry
    parentNode.content.set(m.nodeId, null);
    nodes_seen.set( parent_nodeId, parentNode );

    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log("Sub[%d] %s %d <- parent %d children %d",
      depth,
      m.nodeName ? m.nodeName : '---',
      m.nodeId,
      parent_nodeId,
      m.childNodeCount ? m.childNodeCount : 0
      //(await DOM.resolveNode({nodeId: m.nodeId})).object, 
      //(await DOM.getOuterHTML({nodeId: m.nodeId})).outerHTML
      //,m
      //,inspect((await DOM.describeNode({ nodeId: m.nodeId })).node, {showHidden: false, depth: null, colors: true})
      //,inspect(m.children ? m.children : [], {showHidden: false, depth: null, colors: true})
    );

    // Only enqueue requestChildNodes for parents missing .children array
    if ( enqueue_m_nodeid ) {
      register_parent_in_waiting( m.nodeId );
    }

    if ( has_child_array && m.children.length > 0 ) {
      await m.children.forEach(async (c) => { // DOM.Node.children is an array
        return await recursively_add_and_register( c, m.nodeId, depth + 1 );
      });
    }
    return Promise.resolve(true);
  }//}}}

  async function domSetChildNodes(params) 
  {//{{{
    // Populate nodes_seen array with all DOM nodes.
    // Inorder traversal takes care of appending already-fetched nodes.
    const {parentId, nodes} = params;
    const descriptor = (await DOM.resolveNode({nodeId: parentId})).object;

    if ( envSet('DOMSETCHILDNODES','2') || postprocessing == 2 ) process.stdout.write('.');
    if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) console.log( 
      "NodeDSC[%d] %s %d <== parent %d children %d { %s }", 
      nodes_seen.size,
      descriptor.description,
      parentId, 

      waiting_parent,
      nodes.length,
      nodes.map((e) => e.nodeId).join(',')
      //,descriptor
      //,params
    );
    let parent_node;
    if ( nodes_seen.has( waiting_parent ) ) {
      // Avoid need for fixup by fixing waiting_parent content map
      parent_node = nodes_seen.get( waiting_parent );
      if ( !parent_node.content.has( parentId ) && (waiting_parent != parentId) ) {
        parent_node.content.set( parentId, null );
        nodes_seen.set( waiting_parent, parent_node );
        if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) 
          console.log( "Fixup %d.%d", parentId, waiting_parent );
      }
    }
    if ( !nodes_seen.has( parentId ) ) {
      let R = (await DOM.describeNode({nodeId: parentId})).node;
      let attrset = R.attributes ? R.attributes : [];
      let attrmap = new Map;
      while ( attrset.length > 0 ) {
        let attr = attrset.shift();
        let attrval = attrset.shift();
        attrmap.set( attr, attrval );
      }
      nodes_seen.set( parentId, {
        nodeName   : R.nodeName,
        parentId   : waiting_parent,
        attributes : attrmap,
        isLeaf     : !(R.childNodeCount && R.childNodeCount > 0),
        content    : R.childNodeCount && R.childNodeCount > 0
          ? new Map 
          : R.nodeValue 
      });
    }
    parent_node = nodes_seen.get( parentId );
    if ( 1/*parent_node.content.size == 0 && !parent_node.isLeaf*/ ) {
      let modified = 0;
      nodes.forEach((n,nn,node) => {
        if ( n.nodeId != parentId && !parent_node.content.has( n.nodeId ) ) {
          parent_node.content.set( n.nodeId, null );
          modified++;
        }
      });
      if ( envSet('DOMSETCHILDNODES','1') || postprocessing == 1 ) {
        console.log( "- Preregistered %d/%d nodes with parent[%d]",
          modified,
          nodes.length,
          parentId
        );
      }
      if ( modified > 0 ) {
        parent_node.isLeaf = false;
        nodes_seen.set( parentId, parent_node );
      }
    }

    await nodes.forEach(async (n,nn,node) => {
      await recursively_add_and_register( n, parentId, 0 );
      return true;
    });
    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  async function graft( m, nodeId, depth )
  {//{{{
    // Recursive descent through all nodes to attach all leaves to parents.
    tag_stack.push( m.nodeName );
    if ( !m.isLeaf && m.content && m.content.size == 0 ) {
      let sr = {
        nodeName   : m.nodeName,
        parentId   : m.parentId,
        attributes : m.attributes,
        isLeaf     : true,
        content    : (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML
      };
      if ( envSet('GRAFT','1') ) console.log("Leafify %d",
        depth,
        nodeId,
        m.content
      );
      m = sr;
    }
    if ( m.isLeaf ) {
      if (envSet("GRAFT","2")) process.stdout.write('g');
      if ( envSet('GRAFT','1') ) console.log("   Leaf %d %d { %s }", 
        depth, 
        nodeId,
        tag_stack.join(' '), 
        m.content );
    }
    else if ( m.content && m.content.size && m.content.size > 0 ) {
      let tstk = new Array;
      let attrinfo;
      if ( m.nodeName == 'A' ) {
        let attrarr = new Array();
        m.attributes.forEach((value, key, map) => {
          attrarr.push( [key,'="',value,'"'].join('') );
        });
        attrarr.sort();
        attrinfo = attrarr.join(' ');
        while ( attrarr.length > 0 ) attrarr.shift();
        attrarr = null;
      }
      if (envSet("GRAFT","2")) process.stdout.write('G');
      if ( envSet('GRAFT','1') ) console.log("Grafted %d %d | %s >", 
        depth,
        nodeId,
        tag_stack.join(' '),
        attrinfo ? attrinfo : ''
      );
      m.content.forEach((value, key, map) => {
        tstk.push( key );
      });
      tstk.sort((a,b) => {return a - b;});
      
      while ( tstk.length > 0 ) {
        let k = tstk.shift();
        if ( nodes_seen.has(k) ) {
          // Append newly-fetched nodes found in linear map
          // onto this node
          let b = await graft( nodes_seen.get(k), k, depth + 1 );
          m.content.set( k, b );
          nodes_seen.delete(k);
        }
      }
    }
    tag_stack.pop();
    return Promise.resolve(m);
  }//}}}

  async function trigger_dom_fetch()
  {//{{{
    if ( parents_pending_children.length > 0 ) {
      waiting_parent = parents_pending_children.shift();
      // The DOM.requestChildNodes call should result in
      // invocation of domSetChildNodes(p) within milliseconds.
      let rq_result = await DOM.requestChildNodes({
        nodeId : waiting_parent,
        depth  : node_request_depth,
        pierce : true
      });
      if ( envSet('TRIGGER_DOM_FETCH','1') || postprocessing == 1 ) {
        console.log("requestChildNodes %d", 
          waiting_parent, 
          nodes_seen.has(waiting_parent), 
          parents_pending_children.length,
          rq_result
        ); 
      }
      rr_mark = hrtime.bigint();
    }
  }//}}}

  async function flatten_dialog_container( sp, nm, p, node_id, d )
  {
    if ( !p.dialog_nodes.has( node_id ) ) {
      p.dialog_nodes.set( node_id, nm );
      if ( 
        nm.nodeName == '#text' && 
        nm.isLeaf && 
        !(nm.content instanceof Map) &&
        nm.content == '×' // 'Close'
      ) {
        p.closer_node = node_id;
        console.log(
          "Found Modal Closer %d",
          node_id
        );
      }
    }
    return Promise.resolve(nm);
  }

  async function traverse_to( node_id, nm )
  {
    await DOM.scrollIntoViewIfNeeded({nodeId: node_id});
    let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
    let cx = (content[0] + content[2])/2;
    let cy = (content[1] + content[5])/2;

    // Move mouse pointer to object on page, send mouse click and release
    console.log( "Box['%s'] (%d,%d)", nm.content, cx, cy, content );
    return Promise.resolve(true);
  }


  async function trigger_page_traverse_cb( sp, nm, p, node_id, d )
  {
    let nr;

    if ( exception_abort )
      return Promise.resolve(nm);

    if ( !p.tagstack.has(d) )
      p.tagstack.set(d, new Map);

    nr = p.tagstack.get(d);

    if ( !nr.has( nm.nodeName ) ) {
      nr.set( nm.nodeName, -1 );
    }

    let nrn = nr.get( nm.nodeName ) + 1;
    let altname = [ nm.nodeName,'[', nrn, ']', ].join('');

    nr.set(nm.nodeName, nrn);

    p.tagstack.set(d, nr);

    // Update our branch motifs list:
    // Update or store the HTML tag pattern leading to this node.
    let branchpat = sp.branchpat.join('|');
    let motif = {
      n : 0
    };
    if ( p.motifs.has( branchpat ) ) {
      motif = p.motifs.get( branchpat );
    }
    motif.n++;
    p.motifs.set( branchpat, motif );

    let attrinfo = '';
    if ( nm.nodeName == 'A' ) {
      if ( nm.attributes.has('href') )
        attrinfo = nm.attributes.get('href');
    }

    if ( envSet('PAGE_FETCH_CB','1') ) console.log(
      "%s[%d] %s", 
      ' '.repeat(d * 2),
      d,
      altname,
      nm.isLeaf 
      ? nm.content.replace(/[\r\n]/g,' ').replace(/[ \t]{1,}/,' ')
      : attrinfo 
    );

    if ( triggerable != 0 ) {

      if ( nm.nodeName == '#text' && nm.content == '[History]' ) {
        try {

          triggerable--;
          await traverse_to( node_id, nm );

        }
        catch(e) {
          console.log("Exception at depth %d", 
            d, 
            nm.nodeName, 
            e && e.request !== undefined ? e.request : e, 
            e && e.response !== undefined ? e.response : e, 
            inspect(nm, {showHidden: false, depth: null, colors: true})
          );
          exception_abort = true;
        }
      }
    }

    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);

  }

  async function clickon_node( node_id, nm )
  {//{{{
    await DOM.scrollIntoViewIfNeeded({nodeId: node_id});
    let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
    let cx = (content[0] + content[2])/2;
    let cy = (content[1] + content[5])/2;

    // Move mouse pointer to object on page, send mouse click and release
    console.log( "Box['%s'] (%d,%d)", nm.content, cx, cy, content );
    await Input.dispatchMouseEvent({
      type: "mouseMoved",
      x: parseFloat(cx),
      y: parseFloat(cy)
    });
    await sleep(300);

    console.log( "Click at (%d,%d)", cx, cy );
    await Input.dispatchMouseEvent({
      type: "mousePressed",
      x: parseFloat(cx),
      y: parseFloat(cy),
      button: "left",
      clickCount: 1
    });
    await sleep(10);

    console.log( "Release (%d,%d)", cx, cy );
    await Input.dispatchMouseEvent({
      type: "mouseReleased",
      x: parseFloat(cx),
      y: parseFloat(cy),
      button: "left"
    });
    return sleep(200);
  }//}}}

  async function trigger_page_fetch_cb( sp, nm, p, node_id, d )
  {//{{{
    // This callback performs two functions:
    // 1. It takes each node nm passed to it by inorder_traversal(cb)
    //    and assembles these into a simplified DOM tree
    // 2. It traverses the (presumably still-loaded page) DOM, moves
    //    those text links into focus, and executes an Input mouse click on
    //    those selected nodes.
    //
    // Parameters
    // nm           : Abbreviated node
    // node_id      : node_id for {nm}
    // p            : Callback parameters passed to inorder_traversal
    // Return value : nm

    let nr;

    if ( exception_abort )
      return Promise.resolve(nm);

    if ( !p.tagstack.has(d) )
      p.tagstack.set(d, new Map);

    nr = p.tagstack.get(d);

    if ( !nr.has( nm.nodeName ) ) {
      nr.set( nm.nodeName, -1 );
    }

    let nrn = nr.get( nm.nodeName ) + 1;
    let altname = [ nm.nodeName,'[', nrn, ']', ].join('');

    nr.set(nm.nodeName, nrn);

    p.tagstack.set(d, nr);

    // Update our branch motifs list:
    // Update or store the HTML tag pattern leading to this node.
    let branchpat = sp.branchpat.join('|');
    let motif = {
      n : 0
    };
    if ( p.motifs.has( branchpat ) ) {
      motif = p.motifs.get( branchpat );
    }
    motif.n++;
    p.motifs.set( branchpat, motif );

    let attrinfo = '';
    if ( nm.nodeName == 'A' ) {
      if ( nm.attributes.has('href') )
        attrinfo = nm.attributes.get('href');
    }

    if ( envSet('PAGE_FETCH_CB','1') ) console.log(
      "%s[%d] %s", 
      ' '.repeat(d * 2),
      d,
      altname,
      nm.isLeaf 
      ? nm.content.replace(/[\r\n]/g,' ').replace(/[ \t]{1,}/,' ')
      : attrinfo 
    );

    if ( triggerable != 0 ) {

      if ( nm.nodeName == '#text' && nm.content == '[History]' ) {
        try {

          triggerable--;
          append_buffer_to_rr_map = 0;

          await clickon_node( node_id, nm );

          if ( append_buffer_to_rr_map > 0 ) {
            let R = (await DOM.describeNode({nodeId: append_buffer_to_rr_map})).node;
            let markup;
            await setup_dom_fetch( append_buffer_to_rr_map );
            await sleep(1000);
            if ( p.lookup_tree.has( append_buffer_to_rr_map ) ) {
              let dp = p.lookup_tree.get( append_buffer_to_rr_map );
              let cka = new Array;
              console.log("Populating container %d",
                append_buffer_to_rr_map
              );
              dp.content.forEach((v,ck,map) => {
                cka.push(ck);
              });
              while ( cka.length > 0 ) {
                let ck = cka.shift();
                console.log("- %d", ck);
                await setup_dom_fetch( ck, append_buffer_to_rr_map );
              }
              // Decompose the dialog container to get at 
              // the good bits: The Close button and link text.
              let dialog_motif = new Array;
              await inorder_traversal(
                {
                  branchpat : dialog_motif 
                },
                dp,
                -1,
                flatten_dialog_container,
                p
              );
              if ( envSet("VERBOSE","1") ) console.log("Dialog container %d",
                append_buffer_to_rr_map,
                inspect(dp,{showHidden: false, depth: null, colors: true}),
              );
            }
            else {
              console.log("MISSING: Container %d not in lookup tree", append_buffer_to_rr_map);
            }
            await sleep(500);
            markup = (await DOM.getOuterHTML({nodeId: append_buffer_to_rr_map})).outerHTML;
            console.log( "TRIGGERED FETCH %s INTO %s %d", 
              xhr_fetch_rr,
              p.lookup_tree.has( append_buffer_to_rr_map ) ? "in-tree" : "missing",
              append_buffer_to_rr_map,
              markup,
              inspect(R,{showHidden: false, depth: null, colors: true}),
              nodes_seen.size
              //inspect(nodes_seen,{showHidden: false, depth: null, colors: true}),
            );
            await sleep(500);
            await reduce_nodes( nodes_seen );
            if ( envSet("VERBOSE","1") ) console.log( "Reduced", inspect(nodes_seen,{showHidden: false, depth: null, colors: true}) );

            if ( p.closer_node > 0 ) {
              await clickon_node( p.closer_node, nm );
            }

            // TODO: Dispatch dialog close mousePressed Input HERE
            if ( rr_map.has( xhr_fetch_rr ) ) {
              let rr_entry = rr_map.get( xhr_fetch_rr );
              rr_entry.markup = nodes_seen;
              rr_map.set( xhr_fetch_rr, rr_entry );
            }

            if ( p.closer_node == 0 ) {
              console.log( "Input needed: Close dialog in 10s" );
            }
            await sleep(5000);
          }
          nodes_seen.clear();
        }
        catch(e) {
          console.log("Exception at depth %d", 
            d, 
            nm.nodeName, 
            e && e.request !== undefined ? e.request : e, 
            e && e.response !== undefined ? e.response : e, 
            inspect(nm, {showHidden: false, depth: null, colors: true})
          );
          exception_abort = true;
        }
      }
    }
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}
  
  async function inorder_traversal( sp, nm, d, cb, cb_param, nodeId, parentId )
  {//{{{
    // Depth-first inorder traversal of .content maps in each node.

    // Parameters:
    // nm: Either a Map or an abbreviated node
    //
    // Return value:
    // - An abbreviated node
    if ( exception_abort ) {
    }
    else if ( nm === undefined || !nm ) {
      if (envSet("INORDER_TRAVERSAL","1")) console.log( "@empty node[%d]", d, nodeId, nm );
    }
    else if ( nm instanceof Map ) {
      // We were passed a node tree - either the root or (possibly) 
      // the nm.content Map of a non-root node.
      // If nodeId is undefined, then we have received the root 
      // node container; otherwise, nodeId identifies an instance
      // containing Map nm as .content.
      let ka = new Array;
      nm.forEach((v, k, m) => {ka.push(k);});
      while ( ka.length > 0 ) {
        let k = ka.shift();
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "@root %d[%d]", k, d ); 
        if ( nm.has( k ) ) {
          nm.set(k,await inorder_traversal(sp,nm.get(k),d,cb,cb_param,k));
        }
        if ( exception_abort ) break;
      }
    }
    else if ( nm.content !== undefined ) {
      // nm is an abbreviated node, which should be the case
      // whenever d > 0. We expect nodeId to be a DOM.nodeId type
      // used as a search key for nm.

      sp.branchpat.push(nm.nodeName);

      if ( cb !== undefined && !envSet("CB_PREPROCESS") ) nm = await cb(sp,nm,cb_param,nodeId,d+1);
      if ( nm.isLeaf || nm.content.size === undefined ) {
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "- Skipping leaf %d", nodeId );
      }
      else {
        let ka = new Array;
        // Reverse order of child node Map elements
        if ( envSet("REVERSE_CONTENT") && !nm.isLeaf && nm.content instanceof Map && nm.content.size > parseInt(process.env.REVERSE_CONTENT) ) {
          let revmap = new Map;
          nm.content.forEach((v,k,m) => { 
            let val = v;
            ka.push(k); 
            revmap.set(k,val);
          });
          ka.forEach((e) => { nm.content.delete(e); });
          nm.content.clear();
          ka.sort((a,b) => {return b - a;});
          console.log( "Reversing %d-element container %d", ka.length, nodeId );
          while ( ka.length > 0 ) {
            let k = ka.shift();
            let v = revmap.get(k);
            nm.content.set( k, v );
            revmap.delete(k);
          }
        }
        nm.content.forEach((v,k,m)=>{ka.push(k);});
        if (envSet("INORDER_TRAVERSAL","1")) console.log( "- Traversing %d[%d]", nodeId, d, ka.length );
        while ( ka.length > 0 ) {
          let k = ka.shift();
          let n = nm.content.get(k);
          let rv = await inorder_traversal(sp,n,d+1,cb,cb_param,k,nodeId);
          if ( rv === undefined ) {
            nm.content.delete(k);
          }
          else {
            nm.content.set(k,rv);
          }
          if ( exception_abort ) break;
        }
      }
      if ( cb !== undefined  && envSet("CB_PREPROCESS") ) nm = await cb(sp,nm,cb_param,nodeId,d+1);

      sp.branchpat.pop();

    }
    return Promise.resolve(nm);
  }//}}}

  function rr_time_delta()
  {//{{{
    let rr_now = hrtime.bigint();
    if ( rr_begin == 0 ) rr_begin = hrtime.bigint();
    let sec = Number.parseFloat(
      Number((rr_now - rr_begin)/BigInt(1000 * 1000))/1000.0
    );
    let min = Number.parseInt(Number.parseInt(sec) / 60);
    let s = Number.parseInt(sec) - (min*60);
    return [ min, 'm ', s, 's', ' (', sec , 's)' ].join('');
  }//}}}

  function datestring( d, fmt )
  {//{{{
    if ( d === undefined || !d ) d = new Date;
    if ( fmt === undefined ) fmt = "%Y%M%D-%H%i%s-%u";
    return fmt
      .replace(/%Y/g, d.getUTCFullYear())
      .replace(/%M/g, (d.getUTCMonth()+1).toString().padStart(2,'0'))
      .replace(/%D/g, d.getUTCDate().toString().padStart(2,'0'))
      .replace(/%H/g, d.getUTCHours().toString().padStart(2,'0'))
      .replace(/%i/g, d.getUTCMinutes().toString().padStart(2,'0'))
      .replace(/%s/g, d.getUTCSeconds().toString().padStart(2,'0'))
      .replace(/%u/g, d.getUTCMilliseconds());
  }//}}}

  async function reduce_nodes( nodes )
  {//{{{

    let st = new Array;
    let runs = 0;

    console.log( "Populating tree buffer with %d nodes", nodes.size );
    nodes.forEach((value, key, map) => {
      process.stdout.write("+");
      st.push( key );
    });
    console.log( "\r\nTIME: Obtained key array of length", st.length, rr_time_delta() );

    while ( nodes.size > 1 && runs < 10 ) {
      console.log( "Run %d : %d", runs, nodes.size, rr_time_delta() );
      while ( st.length > 0 ) {
        let k = st.shift();
        if ( nodes.has( k ) ) {
          // Node[k] may have been relocated by graft(m,nodeId,depth)
          b = nodes.get( k );
          if ( b.parentId > 0 ) {
            console.log("Next %d, remaining %d", k, nodes.size);
            nodes.set( k, await graft( b, k, 0 ) );

            b = nodes.get( k );

            if ( nodes.has( b.parentId ) ) {
              let p = nodes.get( b.parentId );
              p.content.set( k, b );
              nodes.delete( b.parentId );
              nodes.set( b.parentId, p );
              nodes.delete( k );
              process.stdout.write("\r\n");
              console.log("Remaining nodes", nodes.size);
            }
          } // b.parentId > 0
        } // nodes.has( k )
      } // st.length > 0
      runs++;
      if ( nodes.size > 1 ) {
        nodes.forEach((value, key, map) => {
          st.push( key );
        });
        st.sort((a,b) => {return b - a;});
        console.log( "Reduction of %d nodes", st.length, rr_time_delta() );
      }
    }
    console.log( "Reduced node tree to %d root nodes", nodes.size ); 
  }//}}}

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    let file_ts = datestring( cycle_date );
    if ( step == 0 ) {

      if ( nodes_seen.size > 0 ) {
        // First, sort nodes - just because we can.
        console.log( "TIME: finalize_metadata", rr_time_delta() );
        nodes_seen = return_sorted_map( nodes_seen );
        if ( envSet('FINALIZE_METADATA','1') ) console.log(
          "Pre-update",
          inspect(nodes_seen, {showHidden: false, depth: null, colors: true})
        );
        write_map_to_file("Pre-transform", "pre-transform.json", nodes_seen, file_ts);

        let markupfile = "index.html";
        try {
          console.log( "Writing markup %s [%d]", markupfile, rootnode );
          write_to_file( markupfile, file_ts,  
            (await DOM.getOuterHTML({nodeId: rootnode})).outerHTML
          );
        }
        catch(e) {
          console.log( "Unable to write markup file", markupfile );
        }

        console.log( "TIME: TRANSFORM", rr_time_delta() );

        // Stash tree before post-processing, traversing
        console.log( "Sorting %d nodes", nodes_seen.size );
        nodes_seen = return_sorted_map( nodes_seen );
        write_to_file( "pre-processed.json", file_ts, inspect( nodes_seen, { showHidden: true, depth: null, colors: false } ));

        // Copy into lookup_tree before reducing to tree
        nodes_seen.forEach((v,key,map) => {
          lookup_tree.set( key, {
            nodeName : v.nodeName,
            parentId : v.parentId,
            content  : v.content,
            isLeaf   : v.isLeaf
          });
        });

        // Reduce nodes_seen to traversable tree
        await reduce_nodes( nodes_seen );

        rr_time = hrtime.bigint();
        console.log( "\r\nDOM tree structure finalized with %d nodes", lookup_tree.size, rr_time_delta() );

        // Inorder traversal of "clean" HTML
        console.log( "Building %d nodes", nodes_seen.size );

        let tagstack = new Map; // At each recursive step up the tree (from root node d = 0), we use this Map of array elements to track unique HTML tags found
        let motifs   = new Map;
        let tagmotif = new Array; // A simple array of all nodes reached at a point in tree traversal

        postprocessing = 3;

        // Transfer working tree to nodes_tree; nodes_seen is reused
        // to refetch modal popups filled with XHR-fetched HTML fragments.
        nodes_seen.forEach((value,key,map) => {
          let v = value;
          nodes_tree.set( key, v );
          nodes_seen.delete( key );
        });

        await inorder_traversal( 
          {
            branchpat   : tagmotif
          },
          nodes_tree, 
          -1, 
          envSet("MODE","TRAVERSE") 
          ? trigger_page_traverse_cb
          : trigger_page_fetch_cb, 
          {
            tagstack    : tagstack, // Retains tag counts at depth d
            motifs      : motifs, // A 'fast list' of DOM tree branch tag patterns ending in leaf nodes
            lookup_tree : lookup_tree, // Target tree containing nodes { "HTMLTAG" => Map(n) { "HTMLTAG" => ... { "HTMLTAG" => "<leaf node content>" } ... } }
            dialog_nodes : new Map,
            closer_node : 0
          }
        );
         
        postprocessing = 0;

        rr_time = hrtime.bigint();
        console.log( "Built %d nodes", nodes_tree.size, rr_time_delta(),
          (envSet("DUMP_PRODUCT","1")) 
          ? inspect(lookup_tree, {showHidden: false, depth: null, colors: true})
          : nodes_tree.size 
        );
        write_to_file( "tagstack.txt", file_ts, 
          inspect(tagstack, {showHidden: false, depth: null, colors: true})
        );
        write_to_file( "trie.txt", file_ts, 
          inspect(lookup_tree, {showHidden: false, depth: null, colors: true})
        );
        write_to_file( "motifs.txt", file_ts, 
          inspect(motifs, {showHidden: false, depth: null, colors: true})
        );


        console.log( "Everything", 
          rr_time_delta(), 
          (envSet("DUMP_PRODUCT","1"))
          ? inspect(nodes_tree, {showHidden: false, depth: null, colors: true})
          : nodes_tree.size
        );
        // Clear metadata storage

        rr_time = hrtime.bigint();
        console.log( "DONE", rr_time_delta() );

        write_map_to_file("Everything",
          "everything.json",
          nodes_tree,
          file_ts 
        );

        console.log( "Currently", Date() );
      }

      if ( rr_map.size > 0 ) {
        write_map_to_file( "Network exchanges",
          "network.json",
          rr_map, 
          file_ts
        );
        rr_map.clear();
      }

      nodes_seen.clear();
      nodes_tree.clear();
      lookup_tree.clear();
      cycle_date = null;

      if ( exception_abort ) process.exit(1);
    }
    else {
      // Trigger requestChildNodes
      await trigger_dom_fetch();
    }
    return Promise.resolve(true);
  }//}}}

  async function setup_dom_fetch( nodeId, parentId )
  {//{{{
    rootnode   = nodeId;
    rr_mark    = hrtime.bigint();
    cycle_date = new Date();
    rr_begin   = rr_mark;
    rootnode_n = (await DOM.resolveNode({nodeId: nodeId}));
    waiting_parent = nodeId;
    nodes_seen.set( waiting_parent, {
      nodeName   : rootnode_n.object.description,
      parentId   : parentId !== undefined ? parentId : 0,
      attributes : new Map,
      isLeaf     : false,
      content    : new Map
    });
    console.log("setup_dom_fetch( %d )", 
      nodeId, 
      inspect(rootnode_n,{showHidden: false, depth: null, colors: true})
    );
    parents_pending_children.unshift( nodeId );
    await trigger_dom_fetch();
    return Promise.resolve(true);
  }//}}}
  
  async function domAttributeModified(params)
  {//{{{
    // This event is triggered by clicking on [History] links on https://congress.gov.ph/legisdocs/?v=bills 
    if ( params.value == 'modal fade in' ) {
      append_buffer_to_rr_map = params.nodeId;
      let markup = (await DOM.getOuterHTML({nodeId: append_buffer_to_rr_map})).outerHTML;
      let R = (await DOM.describeNode({nodeId: append_buffer_to_rr_map})).node;
      if ( latest_rr !== 0 && rr_map.has( latest_rr ) ) {
        let rr_entry = rr_map.get( latest_rr );
        rr_entry.markup = markup;
        rr_map.set( latest_rr, rr_entry );
        console.log( "Markup from nodeId[%d] recorded to R/R[%s]",
          append_buffer_to_rr_map,
          latest_rr,
          markup,
          inspect(R,{showHidden: false, depth: null, colors: true}),
        ); 
        xhr_fetch_rr = latest_rr;
        latest_rr = 0;
        return setup_dom_fetch( append_buffer_to_rr_map );
      }
    }
    return Promise.resolve(false);
  }//}}}

  async function domChildNodeInserted(params)
  {//{{{
    if ( postprocessing > 0 ) {
      // Update lookup_tree
      let nodeId = params.node.nodeId;
      let parentId = params.parentNodeId;
      let n = {
        nodeName   : params.node.nodeName,
        parentId   : parentId,
        attributes : mapify_attributes( params.node.attributes ),
        isLeaf     : params.node.childNodeCount == 0,
        content    : new Map
      };
      // Ensure that parent exists and refers to this child node
      if ( parentId !== undefined && parentId > 0 ) {
        if ( lookup_tree.has( parentId ) ) {
          let pn = lookup_tree.get( parentId );
          if ( !pn.content instanceof Map ) {
            console.log( "DOM::childNodeInserted: Replacing .content %s with Map", pn.content );
            pn.content = new Map;
          }
          if ( pn.content !== undefined  && pn.content instanceof Map ) {
            if ( pn.content.has( nodeId ) ) {
              console.log( "DOM::childNodeInserted: Preexisting child %d in %d",
                nodeId,
                parentId
              );
            }
            else {
              pn.content.set( nodeId, null );
              console.log( "DOM::childNodeInserted: Adding child %d to %d", nodeId, parentId );
            }
          }
          lookup_tree.set( parentId, pn );
        }
        else {
          console.log( "DOM::childNodeInserted: Parent %d missing", parentId );
        }
      }
      // Insert the node if not yet present in lookup_tree
      if ( lookup_tree.has( nodeId ) ) {
        let dn = lookup_tree.get( nodeId );
        console.log( "DOM::childNodeInserted",
          "Node %d already present in tree", 
          nodeId,
          dn,
          n
        );
      }
      else {
        lookup_tree.set( nodeId, n );
        console.log(
          "DOM::childNodeInserted: Added node %d to tree",
          nodeId
        );
      }
    }
    else {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::childNodeInserted', params );
    }
    return Promise.resolve(true);
  }//}}}

  async function domChildNodeRemoved(params)
  {//{{{
    if ( postprocessing > 0 ) {
      let nodeId   = params.nodeId;
      let parentId = params.parentNodeId;
      if ( lookup_tree.has( nodeId ) ) {
        let n = lookup_tree.get( nodeId );
        lookup_tree.delete( nodeId );
        console.log( 'DOM::childNodeRemoved[%d]',
          nodeId,
          inspect(n,{showHidden: false, depth: null, colors: true}) 
        );
      }
      else {
        console.log( 'DOM::childNodeRemoved[%d] not in tree', nodeId );
      }
      if ( lookup_tree.has( parentId ) ) {
        let pn = lookup_tree.get( parentId );
        if ( pn.content !== undefined && pn.content instanceof Map ) {
          if ( pn.content.has( nodeId ) ) {
            console.log( 'DOM::childNodeRemoved[%d] from %d',
              nodeId, parentId
            );
            pn.content.delete( nodeId );
          }
        }
        lookup_tree.set( parentId, pn );
      }
    }
    return Promise.resolve(true);
  }//}}}

  try {

    Network.requestWillBeSent(networkRequestWillBeSent);
    Network.responseReceived(networkResponseReceived);
    Network.loadingFinished(networkLoadingFinished);
    DOM.setChildNodes(domSetChildNodes);

    await DOM.attributeModified(async (params) => {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::attributeModified', params );
      await domAttributeModified(params);
    });

    await DOM.attributeRemoved(async (params) => {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::attributeRemoved', params );
    });

    await DOM.characterDataModified(async (params) => {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::characterDataModified', params );
    });

    await DOM.childNodeCountUpdated(async (params) => {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::childNodeCountUpdated', params );
    });

    await DOM.childNodeInserted(async (params) => {
      await domChildNodeInserted(params);
    });

    await DOM.childNodeRemoved(async (params) => {
      if ( envSet("VERBOSE","1") ) console.log( 'DOM::childNodeRemoved', params );
      await domChildNodeRemoved(params);
    });

    await DOM.documentUpdated(async (params) => {
      console.log( 'DOM::documentUpdated', params );
    });

    await Page.loadEventFired(async (ts) => {
      const { currentIndex, entries } = await Page.getNavigationHistory();
      const {root:{nodeId}} = await DOM.getDocument({ pierce: true });

      await setup_dom_fetch( nodeId );

      console.log("LOAD EVENT root[%d]", 
        nodeId, 
        ts,
        datestring( cycle_date ),
        entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
        ? entries[currentIndex].url 
        : '---'
      );
    });

    await Page.windowOpen(async (wo) => {
      console.log("windowOpen", wo);
    });

    await Page.domContentEventFired(async (ts) => {
      console.log("DOM Content Event", ts );
    });

    await Page.lifecycleEvent(async (p) => {
      console.log("Lifecycle", p);
    });

    await Page.setLifecycleEventsEnabled({enabled: true});

    await Page.setInterceptFileChooserDialog({enabled : true});
    
    await Page.fileChooserOpened(async (p) => {
      console.log("Page::fileChooseOpened", p);
    });

    await Page.frameAttached(async (p) => {
      console.log("Page::frameAttached",p);
    });

    await Page.frameDetached(async (p) => {
      console.log("Page::frameDetached",p);
    });
    
    await Page.frameNavigated(async (p) => {
      console.log("Page::frameNavigated",p);
    });
    
    await Page.interstitialHidden(async (p) => {
      console.log("Page::interstitialHidden",p);
    });
    
    await Page.interstitialShown(async (p) => {
      console.log("Page::interstitialShown",p);
    });
    
    await Page.javascriptDialogClosed(async (p) => {
      console.log("Page::javascriptDialogClosed",p);
    });
    
    await Page.javascriptDialogOpening(async (p) => {
      console.log("Page::javascriptDialogOpening",p);
    });

    await Input.setIgnoreInputEvents({ignore: false});

    await Network.enable();
    await DOM.enable({ includeWhitespace: "none" });

    await Page.enable();

    nodes_seen.clear();

    // Trigger a page fetch, else stand by for browser interaction or timeout
    if ( targetUrl.length > 0 ) {
      await Page.navigate({url: targetUrl});
    }

    while ( !completed ) {
      await watchdog( finalize_metadata );
    }

  } catch (err) {
    console.error(err);
  } finally {
    if (client) {
      console.log( "Close opportunity ended" );
      // client.close();
    }
  }
  return Promise.resolve(true);
}

monitor();
