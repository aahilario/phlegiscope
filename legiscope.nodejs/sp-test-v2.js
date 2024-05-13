const { readFileSync, writeFile, writeFileSync, mkdirSync, existsSync, statSync, linkSync, unlinkSync, symlinkSync, readdirSync } = require('node:fs');
const assert = require("assert");
const System = require("systemjs");
const cheerio = require("cheerio");
const { createHash } = require("node:crypto");
const url = require("node:url");
const http = require("node:http");
const https = require("node:https");
const { argv, env, pid, hrtime } = require("node:process");
const { spawnSync } = require("child_process");
const { inspect } = require("node:util");
const { Buffer } = require("node:buffer");

const CDP = require('chrome-remote-interface');

const db_user = process.env.LEGISCOPE_USER || '';
const db_pass = process.env.LEGISCOPE_PASS || '';
const db_host = process.env.LEGISCOPE_HOST || '';
const db_port = process.env.LEGISCOPE_PORT || '';
const db_name = process.env.LEGISCOPE_DB   || '';
const output_path = process.env.DEBUG_OUTPUT_PATH || '';
const targetUrl = process.env.TARGETURL || '';
const rr_timeout_s = 30; // Seconds of inactivity before flushing page metadata 
const node_request_depth = 7;
const default_insp = { showHidden: false, depth: null, colors: true };

const mysqlx = require("@mysql/xdevapi");

let db_session;
let outstanding_rr          = new Map;
let rr_map                  = new Map;
let nodes_seen              = new Map;
let tag_stack               = new Array;
let latest_rr               = 0;
let xhr_fetch_rr            = 0;
let xhr_fetch_id            = 0;
let append_buffer_to_rr_map = 0;
let rr_mark                 = 0; // hrtime.bigint();
let rr_begin                = 0;
let rr_callback             = null;
let cycle_date;
let mark_steps              = 0;
let rootnode_n;
let postprocessing          = 0;
let exception_abort         = false;
let traversal_abort         = false;
let file_ts;
let inorder_traversal_previsit = false;
let inorder_traversal_postvisit = false;
let current_url = null;
let user_agent = null;

if ( envSet("CB_PREPROCESS","0") ) inorder_traversal_previsit = true;
if ( envSet("CB_PREPROCESS","1") ) inorder_traversal_postvisit = true;

async function setup_db()
{//{{{
  try {
    // If database host, user, and password are specified in environment,
    // attempt to connect, and do not proceed if connection fails.
    if ( db_host.length > 0 && db_user.length > 0 && db_pass.length > 0 ) {
      let url_model;
      let db_config = {
        password : db_pass,
        user     : db_user,
        host     : db_host,
        port     : parseInt(db_port), 
        schema   : db_name
      };
      db_session = await mysqlx.getSession(db_config);
      if (envSet("DEBUG_DB","1")) {
        let ses = await db_session.getSchemas();
        await ses.forEach(async (schema) => {
          let schemaname = await schema.getName();
          let tables, resultset, result;
          switch ( schemaname ) {
            case 'performance_schema':
            case 'information_schema':
              console.log("-", schemaname );
              break;
            default:
              console.log("-", schemaname );
              tables = await schema.getTables();
              await tables.forEach(async (t) => {
                console.log("  -", await t.getName() );
              });
              break;
          }
        });
        url_model = await db_session
          .getSchema(db_name)
          .getTable('url_model');
        resultset = await url_model.select(['url','last_modified','last_fetch','hits'])
          .where('id = 2')
          .execute();
        result = resultset.fetchAll();
        console.log( "Result", inspect(result, default_insp) );
      }
    }
    else {
      console.log("No database");
    }
  }
  catch(e) {
    console.log("Database",inspect(e));
    process.exit(1);
  }
  return Promise.resolve(db_session);
}//}}}

async function sqlexec_resultset( db, sql, bind_params )
{//{{{
  let resultset = new Array;
  let resultmap = new Map;
  let r = await db.sql( sql )
    .bind( bind_params )
    .execute(
      (rowcursor) => {
        let lr = new Map;
        // Providing this method consumes all result rows
        resultmap.forEach((v,k) => {
          let nv = rowcursor.shift();
          lr.set( k, nv );
        });
        if (0) console.log( 
          "rowcursor:", 
          inspect(rowcursor, default_insp),
          inspect(resultmap, default_insp)
        );
        resultset.push( lr );
      },
      (metadata) => {
        metadata.forEach((c) => {
          if (0) console.log( "metadata:",
            c.getColumnName(), 
            c.getColumnLabel(), 
            inspect(c, default_insp),
            inspect(resultmap, default_insp)
          );
          resultmap.set( c.getColumnLabel(), null );
        });
      }
    );
  return Promise.resolve(resultset);
}//}}}

function sleep( millis )
{//{{{
  return new Promise((resolve) => {
    setTimeout(() => {
      resolve(true);
    },millis);
  });
}//}}}

function rr_callback_default( data, requestId, phase )
{
  // Template for callbacks assigned to rr_callback, used by
  // - Network.networkResponseReceived
  // - Network.requestWillBeSent
  // - Network.loadingFinished
}

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
    rr_mark = hrtime.bigint();
    if ( rr_callback ) rr_callback( response, params.requestId, 'A' ); 
  }
  else {
    if (envSet('QA','1')) console.log("B[%s]", params.requestId, response );
    rr_mark = hrtime.bigint();
    if ( rr_callback ) rr_callback( response, params.requestId, 'B' ); 
  }
}//}}}

function networkRequestWillBeSent(params)
{//{{{
  let markdata = {
    requestId : params.requestId,
    url       : params.request.url,
    method    : params.request.method,
    headers   : params.request.headers,
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
  if ( rr_callback ) rr_callback( markdata, params.requestId, 'Q' ); 
}//}}}

function networkLoadingFinished(params)
{//{{{
  if ( outstanding_rr.has( params.requestId ) ) {
    latest_rr = params.requestId; // FIXME: Assignments to latest_rr superseded by callback-mediated control flow
    outstanding_rr.delete( params.requestId );
  }
  if (envSet('QA','1')) console.log("L[%s]", params.requestId, outstanding_rr.size );
  rr_mark = hrtime.bigint();
  if ( rr_callback ) rr_callback( params.requestId, params.requestId, 'L' ); 
}//}}}

function return_sorted_map_ordinalkeys( map_obj )
{//{{{
  let sorter = new Array;
  let sorted = new Map;
  map_obj.forEach((value, key, map) => {
    sorter.push(key);
  });
  sorter.sort((a,b) => {return parseInt(a) - parseInt(b);});
  sorter.forEach((e) => {
    sorted.set( parseInt(e), map_obj.get(e) );
    map_obj.delete(e);
    rr_mark = hrtime.bigint();
  });
  sorter.forEach((e) => {
    map_obj.set( parseInt(e), sorted.get(parseInt(e)) );
    sorted.delete(parseInt(e));
    rr_mark = hrtime.bigint();
  });
  while ( sorter.length > 0 ) { sorter.pop(); }
  sorted.clear();
  sorted = null
  sorter = null;
  rr_mark = hrtime.bigint();
  return map_obj;
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
    rr_mark = hrtime.bigint();
  });
  sorter.forEach((e) => {
    map_obj.set( e, sorted.get(e) );
    sorted.delete(e);
    rr_mark = hrtime.bigint();
  });
  while ( sorter.length > 0 ) { sorter.pop(); }
  sorted.clear();
  sorted = null
  sorter = null;
  rr_mark = hrtime.bigint();
  return map_obj;
}//}}}

function write_to_file( fn, content, file_ts, n )
{//{{{
  let outfile;
  let fn_ts;
  if ( file_ts !== undefined ) {
    let ts = file_ts === undefined ? datestring( cycle_date ) : file_ts; 
    let fn_parts = [ 
      fn.replace(/^(.*)\.([^.]{1,})$/i,'$1'), 
      fn.replace(/^(.*)\.([^.]{1,})$/i,'$2')
    ];
    let fn_p = [ fn_parts[0], ts ];
    if ( n !== undefined ) fn_p.push( n.toString() );
    let fn_ts = [ fn_p.join('-'), (fn_parts[1].length > 0 && fn_parts[1] != fn_parts[0]) ? ['.', fn_parts[1]].join('') : '' ].join(''); 

    outfile = [output_path, fn_ts].join('/')

    try {
      // Plain name is used to create a symbolic link.
      // Unlink that if it is present.
      unlinkSync( [output_path,fn].join('/') );
    } catch (e) {} 
  }
  else {
    outfile = fn;
  }
  writeFileSync( outfile, content, {
    flag : "w+",
    flush: true
  });
  if ( file_ts !== undefined && fn_ts !== undefined ) {
    symlinkSync( fn_ts, [output_path,fn].join('/') );
  }
}//}}}

function read_map_from_file( map_file )
{//{{{
  function reviver(key, value) {
    if(typeof value === 'object' && value !== null) {
      if (value.dataType === 'Map') {
        return new Map(Object.entries(value.value));
      }
    }
    return value;
  }
  let f = readFileSync( map_file, { flags : "r" } ); 
  return JSON.parse( f, reviver );
}//}}}

function write_map_to_file( description, map_file, map_obj, file_ts, n )
{//{{{
  console.log( "Writing %s to %s", description, map_file );

  // Stringify an ES6 Map
  // https://stackoverflow.com/questions/29085197/how-do-you-json-stringify-an-es6-map
  function recoverable(key, value) {
    if(value instanceof Map) {
      return {
        dataType : 'Map',
        value    : Object.fromEntries(value)
      };
    } else {
      return value;
    }
  }

  write_to_file( 
    map_file,
    JSON.stringify( map_obj, recoverable, 2 ),
    file_ts,  
    n
  );

}//}}}

function envSet( v, w )
{//{{{
  return w === undefined
  ? ( process.env[v] !== undefined )
  : ( process.env[v] !== undefined && process.env[v] === w );
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

async function flatten_container( sp, nm, p, node_id, d )
{//{{{
  if ( !p.flattened.has( node_id ) ) {
    p.flattened.set( node_id, nm );
    if ( !nm.isLeaf && nm.content instanceof Map ) {
      let lc = p.flattened.get( node_id );
      let lca = new Array;
      lc.content.forEach((v,k,m) => { lca.push(k); });
      lc.content.clear();
      while ( lca.length > 0 ) {
        let lk = lca.shift();
        lc.content.set( lk, null );
      }
      p.flattened.set( node_id, lc );
    }
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

async function graft( sourcetree, m, nodeId, depth, get_content_cb )
{//{{{
  // Recursive descent through all nodes to attach all leaves to parents.
  tag_stack.push( m.nodeName );
  if ( !m.isLeaf && m.content && m.content.size == 0 ) {
    let sr = {
      nodeName   : m.nodeName,
      parentId   : parseInt(m.parentId),
      attributes : m.attributes,
      isLeaf     : true,
      content    : await get_content_cb( m, nodeId )
      // Originally: 
      // content    : (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML
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
      if ( sourcetree.has(k) ) {
        // Append newly-fetched nodes found in linear map
        // onto this node
        let b = await graft( 
          sourcetree, 
          sourcetree.get(k), 
          k, 
          depth + 1, 
          get_content_cb
        );
        m.content.set( k, b );
        sourcetree.delete(k);
      }
    }
  }
  tag_stack.pop();
  return Promise.resolve(m);
}//}}}

async function reduce_nodes( sourcetree, nodes, get_content_cb )
{//{{{

  let st = new Array;
  let runs = 0;
  if ( envSet("REDUCE_NODES","1") ) console.log( "Populating tree buffer with %d nodes", nodes.size );
  nodes.forEach((value, key, map) => {
    process.stdout.write("+");
    st.push( key );
  });
  console.log("<");
  if ( envSet("REDUCE_NODES","1") ) console.log( "\r\nTIME: Obtained key array of length", st.length, rr_time_delta() );

  while ( nodes.size > 1 && runs < 10 ) {
    if ( envSet("REDUCE_NODES","1") ) console.log( "Run %d : %d", runs, nodes.size, rr_time_delta() );
    while ( st.length > 0 ) {
      let k = st.shift();
      if ( nodes.has( k ) ) {
        // Node[k] may have been relocated by graft(m,nodeId,depth)
        b = nodes.get( k );
        if ( b.parentId > 0 ) {
          if ( envSet("REDUCE_NODES","1") ) console.log("Next %d, remaining %d", k, nodes.size);
          nodes.set( k, await graft( sourcetree, b, k, 0, get_content_cb ) );

          b = nodes.get( k );

          if ( nodes.has( b.parentId ) ) {
            let p = nodes.get( b.parentId );
            p.content.set( k, b );
            nodes.delete( b.parentId );
            nodes.set( b.parentId, p );
            nodes.delete( k );
            process.stdout.write("\r\n");
            if ( envSet("REDUCE_NODES","1") ) console.log("Remaining nodes", nodes.size);
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
      if ( envSet("REDUCE_NODES","1") ) console.log( "Reduction of %d nodes", st.length, rr_time_delta() );
    }
  }
  if ( envSet("REDUCE_NODES","1") ) console.log( "Reduced node tree to %d root nodes", nodes.size ); 
}//}}}

async function inorder_traversal( sp, nm, d, cb, cb_param, nodeId, parentId )
{//{{{
  // Depth-first inorder traversal of .content maps in each node.
  // Parameters:
  // nm: Either an abbreviated node or a Map of such nodes
  //
  // Return value:
  // - An abbreviated node

  function reverse_content( nm )
  {//{{{
    let ka = new Array;
    let revmap = new Map;
    nm.content.forEach((v,k,m) => { 
      let val = v;
      ka.push(parseInt(k)); 
      revmap.set(k,val);
    });
    ka.forEach((e) => { nm.content.delete(e); });
    nm.content.clear();
    ka.sort((a,b) => {return b - a;});
    if (envSet("REVERSE_CONTENT_DEBUG","1")) console.log( "Reversing %d-element container %d", ka.length, nodeId );
    while ( ka.length > 0 ) {
      let k = parseInt( ka.shift() );
      let v = revmap.get(k);
      nm.content.set( k, v );
      revmap.delete(k);
    }
  }//}}}

  if ( traversal_abort ) {
    console.log( "Traversal halted" );
  }
  else if ( exception_abort ) {
    console.log( "Process Abort" );
  }
  else if ( nm === undefined || !nm ) {
    if (envSet("INORDER_TRAVERSAL","1")) console.log( "@empty node[%d]", d, nodeId, nm );
  }
  else if ( nm instanceof Map ) {
    // We were passed a node tree - either the root or (possibly) 
    // the nm.content Map of a non-root node.
    // If nodeId is undefined, then we have received the root 
    // node container; otherwise, nodeId identifies an abbreviated node instance
    // containing Map nm as .content.
    let ka = new Array;
    nm.forEach((v, k, m) => {ka.push(k);});
    while ( ka.length > 0 ) {
      let k = ka.shift();
      if (envSet("INORDER_TRAVERSAL","1")) console.log( "@root %d[%d]", k, d ); 
      if ( nm.has( k ) ) {
        nm.set(k,await inorder_traversal(sp,nm.get(k),d,cb,cb_param,k,parentId));
      }
      if ( traversal_abort || exception_abort ) break;
    }
  }
  else if ( nm.content !== undefined ) {
    // nm is an abbreviated node, which should be the case
    // whenever d > 0. We expect nodeId to be a DOM.nodeId type
    // used as a search key for nm.

    sp.branchpat.push(nm.nodeName);

    if ( cb !== undefined && inorder_traversal_previsit ) nm = await cb(sp,nm,cb_param,nodeId,d+1);
    if ( traversal_abort || exception_abort ) {
    }
    else if ( nm === undefined ) {
    }
    else if ( nm.isLeaf || nm.content.size === undefined ) {
      if (envSet("INORDER_TRAVERSAL","1")) console.log( "%sSkipping leaf %d",' '.repeat((d >= 0 ? d : 0) * 2), nodeId );
    }
    else {
      let ka = new Array;
      // Reverse order of child node Map elements
      if ( envSet("REVERSE_CONTENT") && !nm.isLeaf && nm.content instanceof Map && nm.content.size > parseInt(process.env.REVERSE_CONTENT) ) {
        reverse_content( nm );
      }
      nm.content.forEach((v,k,m)=>{ka.push(k);});

      if (envSet("INORDER_TRAVERSAL","1")) 
        console.log(
          "%sTraversing %d[%d]",
          ' '.repeat((d >= 0 ? d : 0) * 2),
          nodeId,
          d,
          ka.length
        );

      while ( ka.length > 0 ) {
        let k = ka.shift();
        let n = nm.content.get(k);
        let traversal_parent = sp.traversal_parent;
        sp.traversal_parent = nm;
        let rv = await inorder_traversal(sp,n,d+1,cb,cb_param,k,nodeId);
        sp.traversal_parent = traversal_parent;
        if ( rv === undefined ) {
          nm.content.delete(k);
        }
        else {
          nm.content.set(k,rv);
        }
        if ( traversal_abort || exception_abort ) break;
      }
    }
    if ( cb !== undefined && inorder_traversal_postvisit ) nm = await cb(sp,nm,cb_param,nodeId,d+1);
    sp.branchpat.pop();
  }
  return Promise.resolve(nm);
}//}}}

async function treeify( t )
{//{{{
  let tempmap = new Map;
  let branchpat = new Array;
  let node_ids = new Array;
  
  t = return_sorted_map_ordinalkeys( t );

  // Move one node into target tempmap
  let rootnode_id;
  let rootnode;
  t.forEach((v,k) => {
    node_ids.push(k);
  });
  node_ids.sort((a,b) => { return a - b; });
  rootnode_id = node_ids.shift();
  rootnode = t.get(rootnode_id);
  t.delete(rootnode_id);
  tempmap.set(parseInt(rootnode_id), rootnode);

  async function treeify_cb( sp, nm, p, node_id, d )
  {//{{{
    // Callback expected to be invoked before traversing .content
    let undefined_res;
    if ( nm.isLeaf ) {
      if ( nm.content instanceof Object ) nm.content = null;
    }
    //else if (nm.nodeName === 'div.modal-dialog') {
    //  nm.content.clear();
    //  nm.isLeaf = true;
    //  nm.content = null;
    //}
    else {
      let cl = new Array;
      nm.content = return_sorted_map_ordinalkeys( nm.content );
      nm.content.forEach((v,k) => { cl.push(k); });
      while ( cl.length > 0 ) {
        let k = cl.shift();
        let n = nm.content.get(k);
        if ( p.source_map.has(k) || p.source_map.has(parseInt(k)) ) {
          let r = p.source_map.get(k) || p.source_map.get(parseInt(k));
          if ( p.source_map.has(parseInt(k)) )
            p.source_map.delete(parseInt(k));
          if ( p.source_map.has(k) )
            p.source_map.delete(k);
          if ( !n ) nm.content.set(parseInt(k),r);
        }
        else if ( !n ) {
          if ( nm.content.has(k) )
            nm.content.delete(k);
          if ( nm.content.has(parseInt(k)) )
            nm.content.delete(parseInt(k));
        }
      }
      if ( nm.content.size == 0 ) {
        nm.isLeaf = true;
        nm.content = null;
        return Promise.resolve(undefined_res);
      }
    }
    return Promise.resolve(nm);
  }//}}}

  async function prune_cb( sp, nm, p, node_id, d )
  {//{{{
    let undefined_res;

    if ( !(nm.content instanceof Map) ) {
    }
    else if ( nm.content.size == 0 ) {
      if ( envSet("PRUNE_CB","1") ) console.log(
        "%sDrop[1] map[%d] %d at %d",
        ' '.repeat(d * 2),
        nm.content.size,
        node_id,
        d
      );
      nm.isLeaf = true;
      nm.content = null;
      return Promise.resolve(undefined_res);
    }
    else {
      let ks = new Array;

      if ( envSet("PRUNE_CB","1") ) console.log( "%sCheck map[%d] %d at %d",' '.repeat(d * 2), nm.content.size, node_id, d );
      nm.content.forEach((v,k) => { ks.push(k); });
      while ( ks.length > 0 ) {
        let k = ks.shift();
        let n = nm.content.get(k);
        if ( !n || ( n.content !== undefined && n.content instanceof Map && n.content.size == 0 ) ) {
          if ( envSet("PRUNE_CB","1") ) console.log( "%s- Prune[3] %d at %d",' '.repeat(d * 2), k, d );
          nm.content.delete(k);
        }
        else {
          if ( envSet("PRUNE_CB","1") ) console.log( "%s- Keep (%s)%d ",
            ' '.repeat(d * 2),
            n.content instanceof Map ? "Map" : typeof n.content,
            k, //, inspect(n.content, default_insp) //, inspect(n, default_insp)
          );
        }
      }
      if ( nm.content.size == 0 ) {
        nm.isLeaf = true;
        nm.content = null;
        if ( envSet("PRUNE_CB","1") ) console.log(
          "%sDrop[2] map %d at %d",
          ' '.repeat(d * 2),
          node_id,
          d
        );
        return Promise.resolve(undefined_res);
      }
    }

    if ( nm.isLeaf && !nm.content ) {
      if ( envSet("PRUNE_CB","1") ) console.log( "%sPrune[1] %d at %d",' '.repeat(d * 2), node_id, d );
      return Promise.resolve(undefined_res);
    }


    return Promise.resolve(nm);
  }//}}}

  if (envSet("TREEIFY","1")) console.log( "Treeify" );
  inorder_traversal_previsit = true;
  inorder_traversal_postvisit = false;
   await inorder_traversal(
    { branchpat : branchpat, traversal_parent : null },
    tempmap, -1,
    treeify_cb,
    { source_map: t }
  );

  if (envSet("TREEIFY","1")) console.log( "Prune", inspect(tempmap, default_insp) );
  inorder_traversal_previsit = false;
  inorder_traversal_postvisit = true;
  await inorder_traversal(
    { branchpat : branchpat, traversal_parent : null },
    tempmap, -1,
    prune_cb,
    { source_map: t }
  );

  while ( node_ids.length > 0 ) { node_ids.shift(); }

  t.clear();

  tempmap.forEach((v,k) => {
    node_ids.push(k);
  });

  while ( node_ids.length > 0 ) {
    let k = node_ids.shift();
    let v = tempmap.get(k);
    t.set(k,v);
    tempmap.delete(k);
  }

  while ( branchpat.length > 0 ) { branchpat.shift(); }

  return Promise.resolve(t);
}//}}}

function normalize_j_history( j )
{//{{{
  let undef_return;
  if ( j !== undefined && j.history !== undefined && j.history instanceof Map ) {
    let ka = new Array;
    let hm = new Map;
    let n = 0;
    j.history.forEach((v,k) => { ka.push(k); });
    ka.sort((a,b) => { return a - b; });
    while ( ka.length > 0 ) {
      let k = ka.shift();
      hm.set( n, j.history.get(k) );
      j.history.delete(k);
      n++;
    }
    hm.forEach((v,k) => {
      j.history.set(k,v);
    });
    return j;
  }
  return undef_return;
}//}}}

async function stack_markup( sp, nm, p, node_id, d )
{//{{{
  // Requires:
  // inorder_traversal_previsit = false;
  // inorder_traversal_postvisit = true;
 
  if ( nm.isLeaf ) {
    let $ = await cheerio.load( nm.content, null, false );
    if (0) console.log( "Element", typeof nm.content, $('td').text() || nm.content );
    if (0) $('td').children().each(function (i,e) {
      console.log("- %d", i, $(this).text, $(this).text() );
    });
    p.markup_a.set( parseInt(node_id), $('td').text() || nm.content );
  }
}//}}}

async function ingest_panels( f )
{//{{{
  let fn = f || env['TARGETURL'];
  let panel_info;

  if ( !existsSync( fn ) ) {
    console.log( "Unable to see %s", fn );
    process.exit(1);
  }

  let j = read_map_from_file( fn );

  if ( j.history !== undefined ) {

    j.history = return_sorted_map_ordinalkeys( j.history );

    panel_info = {
      url     : j.url,
      id      : j.id,
      links   : j.links,
      text    : j.text,
      history : j.history 
    };
    if (0) console.log(
      "Markup",
      inspect( panel_info, default_insp )
    );
  }
  return Promise.resolve(panel_info);
}//}}}

async function preload( f )
{//{{{
  try {
    let t = read_map_from_file( f );

    let branchpat = new Array;
    let markup_a = new Map;

    await inorder_traversal(
      { branchpat : branchpat, traversal_parent : null },
      t, -1,
      stack_markup,
      { markup_a : markup_a }
    );
    console.log( 
      f, 
      inspect( t, default_insp)
    );
  }
  catch(e) {
    console.log( "Problem loading %s", f );
    unlinkSync(f);
  }
}//}}}

async function normalize( f )
{//{{{
  let j = read_map_from_file( f );
  let r = normalize_j_history( j );
  if ( r !== undefined ) {
    write_map_to_file( f, f, r );
  }
  return Promise.resolve(true);
}//}}}

async function fetch_url_http_head( g )
{//{{{
  const head_standoff = 2000;
  let u = url.parse( g );
  let unique_entry = g;

  let result;
  let head_info;
  let client_request;
  let hr_mark, hr_now;
  let done = false;
  let head_options = {
    method: "HEAD",
    timeout: head_standoff,
    headers: {
      "User-Agent" : user_agent && user_agent !== undefined && user_agent.length > 0 ? user_agent : "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
    }
  }

  try {
    client_request = await (/https:/i.test(u.protocol) ? https : http).request( g, head_options );
    hr_mark = hrtime.bigint();
    client_request
      .on('error', (e) => {
        done = true;
        console.log("Error", g, inspect(e, default_insp) );
      })
      .on('response', (m) => {
        head_info = new Map(m.headers !== undefined ? Object.entries(m.headers) : {});
        if ( envSet("FETCH_URL_HTTP_HEAD","1") ) console.log('Headers from', g, inspect(head_info, default_insp) );
        done = true;
      }).on('timeout', () => {
        done = true;
        console.log("Timeout", g );
      }).on('data', (d) => {
        if ( envSet("FETCH_URL_HTTP_HEAD","1") ) console.log('Data B from', g, inspect(d, default_insp) );
      }).end();

    if ( !done ) do {
      hr_now = hrtime.bigint();
      if ( Number.parseInt( Number.parseFloat(Number( (hr_now - hr_mark)/BigInt(1000 * 1000) )/1.0) ) > head_standoff ) {
        console.log( "Timeout" );
        break;
      }
      if ( done ) {
        console.log( "Completed" );
        break;
      }
      await sleep(1);
    } while ( !done && head_standoff > 0 );

    if ( head_info instanceof Map && head_info.size > 0 ) {
      let h = new Map;
      head_info.forEach((v,k,m) => {
        let lc_k = k.toLowerCase();
        if ( /^(date|last-modified)$/.test( lc_k ) ) {
          let dateval = new Date( v ).valueOf();
          h.set( lc_k, isNaN(dateval) ? null : dateval / 1000 );
        }
        else
        h.set( lc_k, v );
        m.delete(k);
      });
      head_info = h;
    }
    else {
      head_info = new Map;
    }

  } catch (e) {
    console.log("fetch_url_http_head", g, inspect(e, default_insp) );
  }

  return Promise.resolve(head_info)
  ;
}//}}}

function congress_panel_text_hdrfix( panel_info_text, text_headers )
{//{{{
  // Fix keys in .text collected using naive token pairing.
  // Serialize elements, and then tokenize + restructure.
  let tokens = new Array;
  let revised = new Map;
  let pairkey, pairval, e_undef;

  if (0) console.log( "Fixing headers in this", inspect( panel_info_text ) );

  panel_info_text.forEach((v,k) => {
    tokens.push(k);
    tokens.push(v);
  });
  while ( tokens.length > 0 ) {
    let t = (tokens.shift() || '').replace(/[: ]$/g,'');
    if ( text_headers.has( t ) ) { 
      pairkey = t;
      t = (tokens.shift() || '').replace(/[: ]$/g,'') || '';
      if ( text_headers.has( t ) ) {
        revised.set( pairkey, '' );
        pairkey = t;
        pairval = e_undef;
      }
      else {
        pairval = t;
      }
    }
    else {
      pairval = t;
    }
    if ( pairkey.length > 0 && pairval !== undefined ) {
      revised.set( pairkey, pairval );
      pairkey = '';
      pairval = e_undef;
    }
    else { 
      if (0) console.log( "Floof", inspect( { r: tokens.length, k : pairkey, v: pairval, t : t}, default_insp ) );
      if ( tokens.length == 0 && t.length > 0 ) {
        let kv = t.split(":");
        let k = (kv[0] || '').trim();
        let v = (kv[1] || '').trim();
        if ( text_headers.has(k) ) {
          revised.set(k,v);
        }
      }
    }
  }
  if ( revised.size > 0 ) {
    panel_info_text.clear();
    text_headers.forEach((v,k) => {
      if ( revised.has(k) )
        panel_info_text.set( k, revised.get(k) );
    });
  }
  if (0) console.log( "Repaired as", inspect( revised, default_insp ) );
}//}}}

function panel_info_text_check( panel_info, text_headers, text_headers_master )
{//{{{
  if ( panel_info.text !== undefined ) {
    // Keep unique document .text map keys
    let broken_headers = false;
    panel_info.text.forEach((v,k) => {
      let th;
      if ( !text_headers.has( k ) ) {
        if ( text_headers_master ) {
          broken_headers = true;
        }
        else {
          text_headers.set( k, 0 );
        }
      }
      if ( !text_headers_master ) {
        th = text_headers.get( k );
        th++;
        text_headers.set( k, th );
      }
    });

    if ( broken_headers ) {
      congress_panel_text_hdrfix( panel_info.text, text_headers )
    }
  }
}//}}}

function full_title_map_filter( m, f )
{//{{{
  let title_full_raw = Buffer.from(m.get(f) || '', 'utf8');
  let title_full_hex = title_full_raw.toString('hex').replace(/([a-f0-9]{2})/g,"$1 ");
  let title_full_buf = Buffer.from(title_full_hex
    .replace(/e2 82 b1/g,'50') // Philippine peso
    .replace(/ea 9e 8c/g,'27') // apostrophe
    .replace(/c4 94/g,'c389') // EACCENT in ATTACH[E]
    .replace(/c5 87/g,'c391') // NTILDE
    .replace(/c5 83/g,'c391') // NTILDE
    .replace(/cc 83/g,'c391') // NTILDE
    .replace(/ef bf bd/g,'c391') // NTILDE
    .replace(/e2 80 b2 e2 80 b2/g,'22') // Two apostrophes to double quote 
    .replace(/e2 80 b3/,'22')
    .replace(/e2 80 9c/,'22')
    .replace(/e2 88 92/g,'2d') // en dash to hyphen 
    .replace(/e2 80 92/g,'2d') // em dash to hyphen 
    .replace(/c8 98/,'c59e') // S with cedilla
    .replace(/\\n/g,' ')
    .replace(/ */g,'')
    ,
    'hex'
  );
  return title_full_buf.toString('utf8');
}//}}}

function sanitize_congress_billres_sn( sn )
{//{{{
  return sn.replace(/[^A-Z0-9#-]/g,'')
    .replace(/([A-Z0-9#-]{1,32})/,'$1')
    .replace(/^#([A-Z0-9]{1,30})-([0-9]{1,4}).*/,'$1-$2')
    .trim();
}//}}}

async function select_congress_basedoc_url( db, congress_basedoc_id )
{//{{{
  // Select full base document + url joins + urls 
  let sql = [
    "SELECT",
    "d.id,",
    "d.create_time d_ct,",
    "d.congress_n,",
    "d.sn,",
    "d.title_full,",
    "j.id dju,",
    "j.create_time dju_ct,",
    "j.update_time dju_ut,",
    "j.url_raw,",
    "u.id url_id,",
    "u.create_time u_ct,",
    "u.last_modified u_lm,",
    "u.url,",
    "u.urltext,",
    "u.urlhash",
    "FROM congress_basedoc d",
    "LEFT JOIN congress_basedoc_url_raw_join j ON d.id = j.congress_basedoc",
    "LEFT JOIN url_raw u ON j.url_raw = u.id",
    "WHERE d.sn = ?" 
  ].join(' '); 
  return sqlexec_resultset( db, sql, congress_basedoc_id );
}//}}}

async function congress_record_panelinfo( panel_info, p )
{//{{{
  let undefined_res;
  let title_full;

  panel_info_text_check( panel_info, p.text_headers, true );

  try {
    // REVIEW: Sanitize page inputs, there are human-encoded entries which contain unexpected, non-alphanumerics.
    let congress_basedoc_id = sanitize_congress_billres_sn( panel_info.id );
    let history_present_flag = panel_info.history.size > 1 ? 1 : 0;
    let congress_basedoc = await p.congress_basedoc
      .select(['id','create_time','update_time','history','mark','congress_n','sn','title_full'])
      .where("sn = :sn")
      .bind("sn",congress_basedoc_id)
      .execute();
    let r = await congress_basedoc.fetchAll();
    title_full = full_title_map_filter( panel_info.text, 'Full Title, As Filed' );

    // Create missing document record
    if ( r.length == 0 ) {//{{{

      if ( title_full.length == 0 )
        title_full = congress_basedoc_id;

      if ( title_full.length > 0 ) {
        let congress_n = parseInt(congress_basedoc_id.replace(/^([^-]{1,})-([0-9]*)/,"$2") || 0);
        r = await p.congress_basedoc
          .insert(['history', 'congress_n','sn','title_full'])
          .values([history_present_flag, congress_n, congress_basedoc_id, title_full])
          .execute()
          .then(() => {
            return p.congress_basedoc
              .select(['id','create_time','update_time','history','mark','congress_n','sn','title_full'])
              .where("sn = :sn")
              .bind("sn",congress_basedoc_id)
              .execute()
          });
        r = r.fetchAll();
        if ( r.length > 0 ) {
          r.forEach((b) => {
            console.log( "Res %s", congress_basedoc_id, inspect(b, default_insp) );
          });
        }
      }
      else {
        console.log( 
          "Empty full title for %s",
          congress_basedoc_id,
          inspect(panel_info, default_insp)
        );
      }
      await sleep(1);
    }//}}}
    else {
      if (0) r.forEach((e) => {
        console.log( "Already have %s", congress_basedoc_id, inspect(e, default_insp) );
      });
      let update_history_mark = [
        'UPDATE congress_basedoc SET',
        'update_time = CURRENT_TIMESTAMP,',
        'history = ?',
        'WHERE sn = ?'
      ].join(' ');
      let congress_basedoc_update_result = await p.db
        .sql( update_history_mark )
        .bind([history_present_flag, congress_basedoc_id])
        .execute();
      await sleep(1);
    }

    let resultset = await select_congress_basedoc_url( p.db, congress_basedoc_id );

    if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") ) console.log("Results [%d]",
      resultset.length,
      inspect(panel_info, default_insp),
      inspect(resultset, default_insp)
    );

    let urls_db = new Map;
    let urls = new Map;

    if ( panel_info.links !== undefined && panel_info.links.size > 0 ) {
      panel_info.links.forEach((v,k,m) => {
        urls.set( v.url, {
          text: k,
          id: null
        });
      });
      if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") ) console.log( "Iter",  inspect(urls, default_insp) );
    }

    // Create raw URL records and through table rows linking each to {congress_basedoc}
    if ( resultset.length > 0 && urls.size > 0 ) {//{{{
      // Find and match URLs returned from database.
      // Check, update, and remove URLs from {urls} already stored in DB
      let congress_basedoc_db_id;
      resultset.forEach((r) => {
        let db_url = r.get('url');
        if ( congress_basedoc_db_id === undefined ) {
          congress_basedoc_db_id = r.get('id');
        }

        if ( urls.has( db_url ) ) {
          if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") ) console.log( "- Already have URL %s", db_url );
          urls.delete( db_url );
        }
        else {
          if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") ) console.log( "- Record %s", db_url ); 
        }
      });

      if ( urls.size > 0 ) {

        let urls_a = new Array;
        urls.forEach(async (v,k) => { urls_a.push(k); }); 

        while ( urls_a.length > 0 ) 
        {
          let k = urls_a.shift();
          let v = urls.get( k );
          let url_insert_result;
          let hash = createHash('sha256');
          let hashval;
          let modified = (await fetch_url_http_head( k )).get('last-modified') || null;
          let inserted_u;

          hash.update(k);
          hashval = hash.digest('hex');

          // Create raw URL records
          try {//{{{

            let url_raw_select = [
              'SELECT id, UNIX_TIMESTAMP(last_modified) AS last_modified, url',
              'FROM url_raw',
              'WHERE urlhash = ?'
            ].join(' ');
            let extant_urls = await sqlexec_resultset( p.db, url_raw_select, hashval );

            if ( extant_urls !== undefined && extant_urls.length > 0 ) {
              v.id = 0;
              while ( extant_urls.length > 0 ) {
                let extant_url = extant_urls.shift();
                // Update the {url_raw} record if the Last-Modified header value has changed
                if ( v.id > 0 ) {
                  console.log( "WARNING: Multiple records found for %s",
                    k
                  );
                }
                else if ( extant_url.get('last_modified') !== modified ) {
                  let url_raw_update_sql = [
                    'UPDATE url_raw SET',
                    'update_time = CURRENT_TIMESTAMP,',
                    'prev_modified = last_modified,',
                    'last_modified = FROM_UNIXTIME(?)',
                    'WHERE urlhash = ?'
                  ].join(' ');
                  let url_update_result = p.db
                    .sql( url_raw_update_sql )
                    .bind([ modified, hashval ])
                    .execute();
                  v.id = extant_url.get('id');
                }
                extant_url.clear();
              }
            }
            else {
              url_insert_sql = [
                'INSERT INTO url_raw ( last_modified, url, urltext, urlhash )',
                'VALUES ( FROM_UNIXTIME(?), ?, ?, ? )'
              ].join(' ');
              url_insert_result = await p.db
                .sql( url_insert_sql )
                .bind([modified, k, v.text, hashval])
                .execute();

              v.id = await url_insert_result.getAutoIncrementValue();
              urls.set( k, v );
              if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") )
                console.log( "Result of insert[%d]",
                  v.id,
                  await url_insert_result.getWarnings()
                );
            }
          }//}}}
          catch(e) {
            console.log( "URL record %s insert error",
              k,
              inspect(e.info ? e.info : e, default_insp),
              inspect(url_insert_result, default_insp)
            );
            process.exit(0);
          }

          // Create through table join record
          try {//{{{
            let join_select = [
              'SELECT id, url_raw, congress_basedoc',
              'FROM congress_basedoc_url_raw_join',
              'WHERE url_raw = ? AND congress_basedoc = ?'
            ].join(' ');
            let extant_joins = await sqlexec_resultset( 
              p.db, join_select, 
              [ v.id, congress_basedoc_db_id ]
            );

            if ( extant_joins !== undefined && extant_joins.length > 0 ) {
              console.log( "Found %d extant join records for URL %s",
                extant_joins.length,
                k
              );
              while ( extant_joins.length > 0 ) {
                let extant_join = extant_joins.shift();
                console.log( "- %d", extant_join.get('id') );
              }
            }
            else {
              let join_insert_result = await p.joins
                .insert(['url_raw','congress_basedoc','edgeinfo'])
                .values([v.id,congress_basedoc_db_id,'-'])
                .execute();
              if ( envSet("DEBUG_BASEDOC_URL_JOINS","1") ) console.log( "Inserted join #%d", await join_insert_result.getAutoIncrementValue() );
            }
          }//}}}
          catch(e) {
            // TODO: Transaction per {url_raw} record
            console.log( "Join record for %s insert error", 
              k,
              inspect(e.info ? e.info : e, default_insp)
            );
            process.exit(0);
          }
        }
        console.log("URLs recorded (%d): ", urls.size, inspect(urls, default_insp) );
      }
      else {
        console.log("No new URLs to record out of %d", resultset.length );
      }
    }//}}}

  }
  catch (e) {
    console.log( "------------" );
    console.log( "Unparseable %s", 
      panel_info.id,
      e.info ? e.info.msg : e,
      inspect(
        {
          title_raw  : panel_info.text.get('Full Title, As Filed'),
          title_full : title_full
        },
        default_insp
      )
    );
  }
  return Promise.resolve(panel_info);
}//}}}

async function congress_bills_panel_to_db( j, p )
{//{{{
  // Accepts JSON intended to be written to cache files
  // and writes information directly to database
  let panel_info;
  if ( j.history !== undefined ) {
    j.history = return_sorted_map_ordinalkeys( j.history );
    panel_info = {
      url     : j.url,
      id      : j.id,
      links   : j.links,
      //network : j.network || false,
      text    : j.text,
      history : j.history 
    };
    panel_info = await congress_record_panelinfo( panel_info, p );
  }
  return panel_info;
}//}}}

async function congress_record_fe_panelinfo( f, p )
{//{{{
  let panel_info = read_map_from_file( f );

  panel_info = await congress_record_panelinfo( panel_info, p );

  return Promise.resolve(panel_info);
}//}}}

async function examine_ingest_json( f, p )
{//{{{
  // let panel_info;
  let j = read_map_from_file( f );
  let hashval;
  if ( j.history instanceof Map && j.history.size > 0 ) {
    let congress_basedoc_id = sanitize_congress_billres_sn( j.id );
    let c = congress_basedoc_id.replace(/^([^-]*)-([0-9]{1,}).*/g,"$2");
    let h_map = new Array;
    let hash = createHash('sha256');
    
    j.history.forEach((v,k) => { h_map.push( [k,v].join('|') ); });
    hash.update(h_map.join(';'));
    hashval = hash.digest('hex');

    if ( p.work.has( hashval ) )
      p.work.set( hashval, p.work.get( hashval ) + 1 );
    else
      p.work.set( hashval, 1 );

    //if ( j.history.size == 0 && j.links.size == 0 ) {
    //  let rs = await select_congress_basedoc_url( p.db, congress_basedoc_id );
    //  if ( rs.length == 1 ) {
    //    let result;
    //    rs = rs.shift();
    //    if ( rs.has('dju') && !rs.get('dju') ) {
    //      result = await p.congress_basedoc
    //        .delete()
    //        .where('`id` = :id')
    //        .bind('id', rs.get('id'))
    //        .execute();
    //      await sleep(1);
    //    }
    //    console.log( "Removable %s", 
    //      congress_basedoc_id,
    //      inspect( rs, default_insp ),
    //      inspect( result, default_insp )
    //    );
    //  }
    //}
    //else {
    //  panel_info = j; 
    //  if (0) { 
    //    console.log( "Keep %s %s", panel_info.id ? panel_info.id : f, f );
    //    await congress_record_panelinfo( panel_info, p );
    //  }
    //}
  }
  return Promise.resolve(hashval);
}//}}}

async function monitor()
{//{{{

  let client;
  let nodes_tree  = new Map;
  let lookup_tree = new Map;
  let parents_pending_children = new Array; 
  let waiting_parent = 0;
  let depth          = 0;
  let rootnode       = 0;
  let completed      = false;

  rr_callback = rr_callback_default;

  client = await CDP();

  const { Browser, Network, Page, DOM, Input } = client;
  
  function document_reset()
  {//{{{
    console.log( "RESET" );
    nodes_seen.clear();
    nodes_tree.clear();
    lookup_tree.clear();
    outstanding_rr.clear();
    cycle_date = null;
    rr_map.clear();

    waiting_parent = 0;
    depth          = 0;
    rootnode       = 0;
    completed      = false;
    traversal_abort = false;
    exception_abort = false;
    rr_callback = rr_callback_default;

    while ( parents_pending_children.length > 0 )
      parents_pending_children.shift();
    while ( tag_stack.length > 0 )
      tag_stack.shift();
  }//}}}

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
    parents_pending_children.push( parseInt(nodeId) );
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
    if ( !nodes_seen.has( parseInt(parent_nodeId) ) ) {
      console.log( "MISSING PARENT %d for node", parent_nodeId, inspect( m, default_insp ) );
      return Promise.resolve(true);
    }

    let parentNode = nodes_seen.get( parseInt(parent_nodeId) ); 
    let has_child_array = (m.children !== undefined) && (m.children.length !== undefined);
    let enqueue_m_nodeid = (m.childNodeCount !== undefined) && !has_child_array && m.childNodeCount > 0;
    if ( !nodes_seen.has(parseInt(m.nodeId)) ) {
      let attrmap = mapify_attributes( m.attributes );
      let isLeaf = !((m.childNodeCount && m.childNodeCount > 0) || has_child_array); 
      nodes_seen.set( parseInt(m.nodeId), {
        nodeName   : m.nodeName ? m.nodeName : '---',
        parentId   : parseInt(parent_nodeId),
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
    if ( parentNode.content === undefined || !(parentNode.content instanceof Map ) ) {
      console.log( "Overriding parent node .content, originally", inspect( parentNode, default_insp ) );
      parentNode.content = new Map;
    }
    parentNode.content.set( parseInt(m.nodeId), null );
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
      //,inspect((await DOM.describeNode({ nodeId: m.nodeId })).node, default_insp)
      //,inspect(m.children ? m.children : [], default_insp)
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
      nodes_seen.set( parseInt(parentId), {
        nodeName   : R.nodeName,
        parentId   : parseInt(waiting_parent),
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
          parent_node.content.set( parseInt(n.nodeId), null );
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
        nodes_seen.set( parseInt(parentId), parent_node );
      }
    }

    await nodes.forEach(async (n,nn,node) => {
      await recursively_add_and_register( n, parentId, 0 );
      return true;
    });
    rr_mark = hrtime.bigint();
    return true;
  }//}}}

  async function trigger_dom_fetch()
  {//{{{
    if ( parents_pending_children.length > 0 ) {
      waiting_parent = parents_pending_children.shift();
      // The DOM.requestChildNodes call should result in
      // invocation of domSetChildNodes(p) within milliseconds.
      try {
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
      }
      catch (e) {
        console.log( "trigger_dom_fetch", inspect(e, default_insp) );
      }
      rr_mark = hrtime.bigint();
    }
  }//}}}

  async function flatten_dialog_container( sp, nm, p, node_id, d )
  {//{{{
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
  }//}}}

  async function traverse_to( node_id, nm )
  {//{{{
    let result = true;
    try {
      await DOM.scrollIntoViewIfNeeded({nodeId: node_id});
      let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
      let cx = (content[0] + content[2])/2;
      let cy = (content[1] + content[5])/2;
      // Move mouse pointer to object on page, send mouse click and release
      if ( envSet("TRAVERSE_TO","1") ) console.log( "Box['%s'] (%d,%d)", nm.content, cx, cy, content );
    }
    catch(e) {
      console.log( "Non-traversable %d", node_id, inspect(nm, default_insp) );
      result = false;
    }
    return Promise.resolve(result);
  }//}}}

  async function congress_prune_panel_cb( sp, nm, p, node_id, d )
  {//{{{
    // Invoked by: 
    // - congress_extract_history_panel
    if ( nm.isLeaf ) {
      try {
        if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "- Removing %d", node_id );
        DOM.removeNode({ nodeId: node_id });
      }
      catch (e) {
        // No-op
      }
    }
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  async function congress_extract_history_panel( sp, nm, p, node_id, d )
  {//{{{
    // Extract bill information from markup AFTER returning 'up' the
    // DOM tree, and after finding a child node that contains [History] links.
    if ( p.hit_depth > d && ( p.hit_depth == ( 3 + d ) ) ) {

      let extracted_info = {
        id       : null,
        links    : new Map,
        text     : new Map,
        skipthis : new Map
      };
      // Extract information from branch and leaves
      let local_sp = new Array;
      if ( envSet("CONGRESS_BILLRES_CB","1") ) {
        console.log( "---- MARK ----",
          inspect(nm, default_insp)
        );
      }
      await inorder_traversal(
        { branchpat : local_sp, traversal_parent : null },
        nm,
        -1,
        congress_billres_extract_cb,
        extracted_info
      );
      // Clean up extracted key-value pairs
      let ka = new Array;
      let document_id = extracted_info.id.replace(/^\#/,'');
      extracted_info.text.forEach((v,k,m) => { ka.push(k); });

      if (envSet("CONGRESS_BILLRES_SAVE_PANELTEXTS","1")) {
        // Cannot use mapify_attributes owing to inconsistencies in formatting
        write_map_to_file(
          extracted_info.id, 
          "paneltexts.json",
          extracted_info.text,
          file_ts,
          document_id
        );
      }

      // Construct .text section of bill panel markup data
      while ( ka.length > 0 ) {
        let k = ka.shift();
        let k_str = extracted_info.text.get(k).replace(/[:]*$/g,'');
        let k_arr = k_str.split(':');
        let v;
        let v_str;
        if ( k_arr.length == 2 ) {
          k_str = k_arr[0];
          v_str = k_arr[1];
        }
        else {
          v = ka.shift();
          v_str = extracted_info.text.get(v);
          extracted_info.text.delete(v);
        }
        extracted_info.text.set( k_str, v_str );
        extracted_info.text.delete(k);
      }
      if ( p.text_headers !== undefined && p.text_headers.size > 1 ) {
        congress_panel_text_hdrfix( extracted_info.text, p.text_headers );
      }

      extracted_info.text.forEach((v,k,m) => {
        if ( !p.textkeys.has(k) )
          p.textkeys.set(k,1);
        else {
          let tk = p.textkeys.get(k);
          tk++;
          p.textkeys.set(k,tk);
        }
      });
      ka.sort((a,b) => {return a - b;});

      // Remove branch and all leaves from frontend DOM
      await inorder_traversal(
        { branchpat : local_sp, traversal_parent : null },
        nm,
        -1,
        congress_prune_panel_cb,
        extracted_info
      );
      let final_data_raw = {
        url     : current_url,
        id      : extracted_info.id,
        links   : extracted_info.links,
        text    : extracted_info.text,
        network : rr_map,
        history : null 
      };
      if ( p.flattened instanceof Map && p.flattened.size > 0 ) {
        let previsit = inorder_traversal_previsit;
        let postvisit = inorder_traversal_postvisit;
        let history_p = return_sorted_map_ordinalkeys( p.flattened );

        let branchpat = new Array;
        let markup_a = new Map;

        history_p = await treeify( history_p );

        inorder_traversal_previsit = false;
        inorder_traversal_postvisit = true;
        await inorder_traversal(
          { branchpat : branchpat, traversal_parent : null },
          history_p, -1,
          stack_markup,
          { markup_a : markup_a }
        );
        final_data_raw.history = markup_a;
        inorder_traversal_previsit = previsit;
        inorder_traversal_postvisit = postvisit;
      }
      else {
        final_data_raw.history = p.flattened;
      }
      extracted_info.links.delete('[History]');
      if ( p.found_data_id ) {
        console.log( "Omitting panels.json write for %s", p.found_data_id );
      }
      else {
        let final_data = normalize_j_history( final_data_raw );

        congress_bills_panel_to_db( final_data, p );
        // Write to cache file since we do not yet (4 May 2024) record all extracted data
        write_map_to_file(
          extracted_info.id, 
          "panels.json",
          final_data,
          file_ts,
          document_id
        );
      }

      if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log(
        inspect(final_data, default_insp)
      );
      console.log( "---- CYCLE DONE ----" );
      p.child_hits = 0;
      p.hit_depth = 0;
      p.flattened.clear();
      rr_map.clear();
    }
  }//}}}

  async function congress_billres_extract_cb( sp, nm, p, node_id, d )
  {//{{{
    // Invoked by:
    // - inorder_traversal: congress_extract_history_panel 
    if ( nm.nodeName == 'A' ) {
      let link = nm.attributes.has('href') 
        ? nm.attributes.get('href')
        : null
      ;
      let linkid = nm.attributes.has('data-id')
        ? nm.attributes.get('data-id')
        : null
      ;
      if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "Extract A", inspect(nm) );
      if ( nm.content instanceof Map && nm.content.size == 1 ) {
        let eset = new Array;
        nm.content.forEach((v,k,map) => {
          eset.push(k);
        });
        while ( eset.length > 0 ) {
          let e = eset.shift();
          let child_node = nm.content.get(e);
          if ( child_node.isLeaf && child_node.nodeName == '#text' ) {
            let label = child_node.content.trim();
            p.links.set( label, { url : link, data_id : linkid } /* link */ );
            if ( linkid ) {
              p.id = linkid;
            }
            if ( !p.text.has( e ) )
              p.skipthis.set( e, label );
            else
              p.text.delete( e );
            if ( envSet("CONGRESS_BILLRES_CB","1") ) console.log( "extract[%d] %s: %s",
              e,
              child_node.content,
              { url : link, id : linkid }
            );
          }
        }
      }
    }
    if ( nm.isLeaf ) {
      if ( nm.nodeName == '#text' ) {
        let content = nm.content.trim();
        if ( content.length > 0 && !p.skipthis.has(node_id) ) {
          p.text.set( node_id, content );
        }
      }
    }
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  function trigger_page_fetch_common_cb( sp, nm, p, node_id, d )
  {//{{{
    let nr;

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
  }//}}}

  async function trigger_page_traverse_cb( sp, nm, p, node_id, d )
  {//{{{
    if ( traversal_abort || exception_abort )
      return Promise.resolve(nm);

    trigger_page_fetch_common_cb( sp, nm, p, node_id, d );

    if ( p.triggerable != 0 ) {

      if ( nm.nodeName == '#text' && nm.content == '[History]' ) {

        // Indicates that we've found a [History] trigger in
        // a child node, so that the triggering tag and surrounding
        // siblings can be captured post-traversal.
        p.child_hits++;
        p.hit_depth = d;

        try {

          p.triggerable--;
          await traverse_to( node_id, nm );

        }
        catch(e) {
          console.log("Exception at depth %d", 
            d, 
            nm.nodeName, 
            e && e.request !== undefined ? e.request : e, 
            e && e.response !== undefined ? e.response : e, 
            inspect(nm, default_insp)
          );
          exception_abort = true;
        }
      }
      else if ( p.child_hits > 0 ) {
        await congress_extract_history_panel( sp, nm, p, node_id, d );
      }
    }

    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}

  async function clickon_node( node_id, nm, cb )
  {//{{{

    let result;
    let traversable = await traverse_to( node_id, nm );
    
    if ( traversable ) while ( result === undefined ) {

      try {
        let {model:{content,width,height}} = await DOM.getBoxModel({nodeId: node_id});
        let cx = (content[0] + content[2])/2;
        let cy = (content[1] + content[5])/2;

        await Input.dispatchMouseEvent({
          type: "mouseMoved",
          x: parseFloat(cx),
          y: parseFloat(cy)
        });
        await sleep(300);

        console.log( "Click on %d at (%d,%d)", node_id, cx, cy );
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
        result = cb === undefined
        ? true
        : await cb( node_id, nm );
      }
      catch (e) {
        console.log( "clickon_node", e );
        result = false;
      }
    }
    await sleep(10);
    return Promise.resolve(result);
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

    // Two callbacks are in effect:
    // - xhr_response_callback provides request/response state to this
    //   application.  A failed POST XHR must cause end of traversal.
    // - clickon_callback defers return from the XHR trigger click action
    //   until the matching networkLoadingFinished event occurs, signaling
    //   the browser has received renderable content.
    // 
    // Fetch state is maintained in this scope.  
    // - xhr_response_callback provides state;
    // - clickon_callback consumes this state, and holds execution
    //   until data is returned, or response status indicates
    //   processing must halt.
    //
    const rq_dur_ms = 30000;
    const rq_retry_max = 3;
    let traversal_request_id;
    let traversal_rq_start_tm;
    let traversal_rq_retry = 0;
    let traversal_rq_aborted = false;
    let traversal_rq_success = false;

    function xhr_response_callback( data, requestId, phase )
    {//{{{
      switch ( phase ) {
        case 'Q':
          // Request about to be sent.  Register the requestId only if
          // it is a POST request to the Congress server backend, directed at
          // the URL https://congress.gov.ph/members/fetch_history.php
          if ( data.method === 'POST' && /fetch_history.php$/ig.test( data.url ) ) {
            traversal_request_id = data.requestId;
            traversal_rq_start_tm = hrtime.bigint();
            console.log( "XHR [%s] Mark, duration %dms", requestId, rq_dur_ms );
          }
          break;
        case 'A':
          // Response received.  We expect Content-Type text/html 
          // and HTTP response code 200
          // ANY OTHER http response code (including a 3xx redirect)
          // is handled as a traversal abort condition.
          // In the event of response.status !== 200, set traversal_abort = true
          if ( traversal_request_id !== undefined && traversal_request_id.length > 0 ) {
            if ( traversal_request_id == requestId && /text\/html$/ig.test( data.mimeType ) ) {
              let rq_status = parseInt(data.status);
              if ( rq_status == 200 ) {
                // Successful request
                console.log( "XHR [%s] OK", requestId );
              }
              else {
                // Unconditional traversal abort
                traversal_rq_aborted = true;
                console.log( "Traversal aborted on XHR fetch error", inspect( data, default_insp ) );
              }
              traversal_rq_start_tm = hrtime.bigint();
            }
          }
          break;
        case 'B':
          // Untracked response with unexpected responseId
          break;
        case 'L':
          if ( traversal_request_id !== undefined && traversal_request_id.length > 0 ) {
            if ( traversal_request_id == requestId ) {
              // Set flag indicating we should proceed, and terminate
              // our wait loop.
              traversal_rq_success = true;
              console.log( "XHR [%s] Done", requestId );
              rr_callback = rr_callback_default;
            }
          }
          // Request loading finished
          break;
      }
    }//}}}

    async function clickon_callback( node_id, nm )
    {//{{{
      // Invoked AFTER a sequence of Input events { mouseMoved, mousePressed, mouseReleased }
      // has been executed. Here we simply wait for network events (or
      // timeout) to occur.
      //
      // In the event of timeout, set traversal_abort = true.
      //
      let success_result = false;
      let traversal_wait_mark = hrtime.bigint();
      while ( success_result !== undefined ) {
        if ( traversal_rq_start_tm !== undefined ) {
          let traversal_dur_ms = Number.parseFloat(
            Number((hrtime.bigint() - traversal_rq_start_tm)/BigInt(1000 * 1000))/1.0
          );
          if ( Number.parseInt(traversal_dur_ms) > Number.parseInt(rq_dur_ms) ) {
            console.log( "Timeout waiting for XHR response", traversal_dur_ms );
            traversal_abort = true;
            break;
          }
        }
        else {
          let traversal_wait_dur = Number.parseFloat(
            Number((hrtime.bigint() - traversal_wait_mark)/BigInt(1000 * 1000))/1.0
          );
          if ( Number.parseInt(traversal_wait_dur) > Number.parseInt(rq_dur_ms) ) {
            traversal_rq_retry++;
            if ( traversal_rq_retry < rq_retry_max ) {
              console.log( "Retry %d/%d XHR setup", traversal_rq_retry, rq_retry_max, traversal_wait_dur );
              traversal_wait_mark = hrtime.bigint();
              success_result = undefined;
            }
            else {
              console.log( "Timeout exceeded for XHR setup", traversal_wait_dur );
              traversal_abort = true;
            }
            break;
          }
        }
        if ( traversal_rq_aborted ) {
          traversal_abort = true;
          break;
        }
        if ( traversal_rq_success ) {
          success_result = true;
          break;
        }
        await sleep(10);
        rr_mark = hrtime.bigint();
      }
      return Promise.resolve(success_result);
    }//}}}

    if ( traversal_abort )
      return Promise.resolve(nm);

    if ( exception_abort )
      return Promise.resolve(nm);

    trigger_page_fetch_common_cb( sp, nm, p, node_id, d );

    if ( p.triggerable != 0 ) {//{{{

      if ( nm.nodeName == 'A' && nm.attributes !== undefined && nm.attributes instanceof Map && nm.attributes.size > 0 ) 
      {//{{{
        // Perform database lookup done here, before 
        // an XHR for document history markup is triggered.
        // Referencing p.id works where, as in the case of 
        // The Philippines Congress, this #text node is a 
        // DOM child of the anchor tag.

        let href = nm.attributes.get('href') || '';
        if ( href == '#HistoryModal' ) {
          let linkid = nm.attributes.has('data-id')
            ? (nm.attributes.get('data-id') || '')
            .replace(/[^A-Z0-9#-]/g,'')
            .replace(/([A-Z0-9#-]{1,32})/,'$1')
            .replace(/^#([A-Z0-9]{1,30})-([0-9]{1,4}).*/,'$1-$2')
            .trim()
            : null
          ;
          p.found_data_id = null;
          p.unfetched_data_id = null;
          if ( linkid ) {
            let congress_basedoc = await p.congress_basedoc
              .select(['id','create_time','congress_n','sn','title_full'])
              .where("sn = :sn")
              .bind("sn",linkid)
              .execute();
            let r = await congress_basedoc.fetchAll();
            if ( r.length > 0 ) {
              console.log( "Found stored %s", linkid );
              p.found_data_id = linkid;
            }
            else {
              console.log( "Pending retrieval of %s", linkid );
              p.unfetched_data_id = linkid;
            }
            await sleep(10);
          }
        }
      }//}}}

      if ( nm.nodeName == '#text' && nm.content == '[History]' )
      {//{{{

        // Indicates that we've found a [History] trigger in
        // a child node, so that the triggering tag and surrounding
        // siblings can be captured post-traversal.

        // Method congress_extract_history_panel uses p.hit_depth to
        // 'gate' further markup processing (including deletion of DOM nodes) 

        p.child_hits++;
        p.hit_depth = d;

        if ( p.found_data_id ) {

          console.log( "Skipping live fetch of %s", p.found_data_id );

        }
        else {

          console.log( 'Live fetch %s', p.unfetched_data_id );

          try {

            p.triggerable--;
            append_buffer_to_rr_map = 0;

            // Set up network callback, 
            rr_callback = xhr_response_callback;
            await clickon_node( node_id, nm, clickon_callback );

            if ( append_buffer_to_rr_map > 0 ) {
              let R = (await DOM.describeNode({nodeId: append_buffer_to_rr_map})).node;
              let markup;
              await setup_dom_fetch( append_buffer_to_rr_map );
              await sleep(1500);

              // Climb the popup container tree
              if ( p.lookup_tree.has( append_buffer_to_rr_map ) ) {//{{{
                let dp = p.lookup_tree.get( append_buffer_to_rr_map );
                let cka = new Array;
                if ( envSet("VERBOSE","1") ) console.log("Populating container %d",
                  append_buffer_to_rr_map
                );
                dp.content.forEach((v,ck,map) => {
                  cka.push(ck);
                });
                while ( cka.length > 0 ) {
                  let ck = cka.shift();
                  if ( envSet("VERBOSE","1") ) console.log("- %d", ck);
                  await setup_dom_fetch( ck, append_buffer_to_rr_map );
                }
                // Decompose the dialog container to get at 
                // the good bits: The Close button and link text.
                let dialog_motif = new Array;
                await inorder_traversal(
                  { branchpat  : dialog_motif, traversal_parent : null },
                  dp,
                  -1,
                  flatten_dialog_container,
                  p
                );
                if ( envSet("VERBOSE","1") ) console.log("Dialog container %d",
                  append_buffer_to_rr_map,
                  inspect(dp,default_insp),
                );
              }//}}}
              else {
                console.log("MISSING: Container %d not in lookup tree", append_buffer_to_rr_map);
              }
              await sleep(500); // Delay to allow browser to populate XHR container modal
              markup = (await DOM.getOuterHTML({nodeId: append_buffer_to_rr_map})).outerHTML;
              console.log( "FETCHED %s INTO %s %d", 
                xhr_fetch_rr,
                p.lookup_tree.has( append_buffer_to_rr_map ) ? "in-tree" : "missing",
                append_buffer_to_rr_map,
                envSet("VERBOSE","1") ? markup : '',
                envSet("VERBOSE","1") ? inspect(R,default_insp) : '',
                nodes_seen.size
              );
              await sleep(500);
              await reduce_nodes( nodes_seen, nodes_seen, get_outerhtml );

              if ( envSet("VERBOSE","1") ) console.log( "Reduced", inspect(nodes_seen,default_insp) );

              if ( envSet("SAMPLE_TRIE","1") ) write_map_to_file( "Fragment", "trie.txt", 
                nodes_seen,
                file_ts,
                p.n
              );
              p.n++;

              p.flattened.clear();
              await inorder_traversal(
                { branchpat : new Array, traversal_parent : nm },
                nodes_seen,
                -1,
                flatten_container,
                p
              );
              p.flattened = return_sorted_map( p.flattened );

              if ( p.closer_node > 0 ) {
                console.log( "Closing modal using event on %d", p.closer_node );
                await clickon_node( p.closer_node, nm );
              }

              if ( rr_map.has( xhr_fetch_rr ) ) {
                let rr_entry = rr_map.get( xhr_fetch_rr );
                rr_entry.markup = p.flattened;
                rr_map.set( xhr_fetch_rr, rr_entry );
                if ( !user_agent ) {
                  let ua = rr_entry.request !== undefined 
                    && rr_entry.request.headers !== undefined 
                    && rr_entry.request.headers['User-Agent'] !== undefined 
                    ? rr_entry.request.headers['User-Agent']
                    : null
                  ;
                  if ( ua && ua.length > 0 ) {
                    user_agent = ua;
                    console.log( "UA: %s", user_agent );
                  }
                }
              }

              if ( p.closer_node == 0 ) {
                console.log( "Input needed: Close dialog in 10s" );
              }
              await sleep(2500);
            }
            nodes_seen.clear();
          }
          catch(e) {
            console.log("Exception at depth %d", 
              d, 
              nm.nodeName, 
              e && e.request !== undefined ? e.request : e, 
              e && e.response !== undefined ? e.response : e, 
              inspect(nm, default_insp)
            );
            exception_abort = true;
          }
        }
        // if ( nm.nodeName == '#text' && nm.content == '[History]' )
      }//}}}
      else if ( p.child_hits > 0 ) {
        await congress_extract_history_panel( sp, nm, p, node_id, d );
      }
      // p.triggerable != 0
    }//}}}
    rr_mark = hrtime.bigint();
    return Promise.resolve(nm);
  }//}}}
  
  async function setup_dom_fetch( nodeId, parentId )
  {//{{{
    let setup_result = false;
    rootnode   = nodeId;
    rr_mark    = hrtime.bigint();
    cycle_date = new Date();
    rr_begin   = rr_mark;
    waiting_parent = nodeId;
    try {
      rootnode_n = (await DOM.resolveNode({nodeId: nodeId}));
      nodes_seen.set( waiting_parent, {
        nodeName   : rootnode_n.object.description,
        parentId   : parentId === undefined ? 0 : parseInt(parentId),
        attributes : new Map,
        isLeaf     : false,
        content    : new Map
      });
      setup_result = true;
    }
    catch(e) {
      console.log( "setup_dom_fetch", inspect( e, default_insp ) );
    }
    if (envSet("SETUP_DOM_FETCH","1") || !setup_result ) console.log("setup_dom_fetch( %d )", 
      nodeId, 
      inspect(rootnode_n,default_insp)
    );
    if ( setup_result ) {
      parents_pending_children.unshift( nodeId );
      await trigger_dom_fetch();
    }
    return Promise.resolve(setup_result);
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
          envSet("VERBOSE","1") ? markup : '',
          envSet("VERBOSE","1") ? inspect(R,default_insp) : ''
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
        parentId   : parseInt(parentId),
        attributes : mapify_attributes( params.node.attributes ),
        isLeaf     : params.node.childNodeCount == 0,
        content    : new Map
      };
      // Ensure that parent exists and refers to this child node
      if ( parentId !== undefined && parentId > 0 ) {
        if ( lookup_tree.has( parentId ) ) {
          let pn = lookup_tree.get( parentId );
          if ( !pn.content instanceof Map ) {
            if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Replacing .content %s with Map", pn.content );
            pn.content = new Map;
          }
          if ( pn.content !== undefined  && pn.content instanceof Map ) {
            if ( pn.content.has( nodeId ) ) {
              if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Preexisting child %d in %d",
                nodeId,
                parentId
              );
            }
            else {
              pn.content.set( nodeId, null );
              if ( envSet("DOM","1") ) console.log( "DOM::childNodeInserted: Adding child %d to %d", nodeId, parentId );
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
        if ( envSet("DOM","1") ) console.log(
          "DOM::childNodeInserted: Added node %d to tree",
          nodeId
        );
      }
    }
    else {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeInserted', params );
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
        if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d]',
          nodeId,
          inspect(n,default_insp) 
        );
      }
      else {
        if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d] not in tree', nodeId );
      }
      if ( lookup_tree.has( parentId ) ) {
        let pn = lookup_tree.get( parentId );
        if ( pn.content !== undefined && pn.content instanceof Map ) {
          if ( pn.content.has( nodeId ) ) {
            if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved[%d] from %d',
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

  async function get_outerhtml( nm, nodeId )
  {//{{{
    return (await DOM.getOuterHTML({ nodeId : nodeId })).outerHTML;
  }//}}}

  async function finalize_metadata( step )
  {//{{{
    // Chew up, digest, dump, and clear captured nodes.
    if ( step == 0 ) {

      file_ts = datestring( cycle_date );
      let text_headers;

      if ( nodes_seen.size > 0 ) {
        // First, sort nodes - just because we can.
        console.log( "TIME: finalize_metadata", rr_time_delta() );
        nodes_seen = return_sorted_map( nodes_seen );
        if ( envSet('FINALIZE_METADATA','1') ) console.log(
          "Pre-update",
          inspect(nodes_seen, default_insp)
        );
        write_map_to_file("Pre-transform", "pre-transform.json", nodes_seen, file_ts);

        let markupfile = "index.html";
        try {
          console.log( "Writing markup %s [%d]", markupfile, rootnode );
          write_to_file( markupfile,  
            (await DOM.getOuterHTML({nodeId: rootnode})).outerHTML, 
            file_ts
          );
        }
        catch(e) {
          console.log( "Unable to write markup file", markupfile );
        }

        console.log( "TIME: TRANSFORM", rr_time_delta() );

        // Load any existing text section table headers
        if ( existsSync( "textsections.json" ) ) {
          text_headers = read_map_from_file( "textsections.json" );
          text_headers.forEach((v,k) => { v = 0; });
          console.log( "Loaded text sections master", inspect( text_headers, default_insp ) );
        }

        // Copy into lookup_tree before reducing to tree
        nodes_seen.forEach((v,key,map) => {
          lookup_tree.set( key, {
            nodeName : v.nodeName,
            parentId : parseInt(v.parentId),
            content  : v.content,
            isLeaf   : v.isLeaf
          });
        });

        // Reduce nodes_seen to traversable tree
        await reduce_nodes( nodes_seen, nodes_seen, get_outerhtml );

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

        let textkeys = new Map;
        let s = await setup_db();
        let traversal_paramset = { // 'p' traversal data passed to inorder_traversal callback
          tagstack     : tagstack, // Retains tag counts at depth d
          motifs       : motifs, // A 'fast list' of DOM tree branch tag patterns ending in leaf nodes
          lookup_tree  : lookup_tree, // Flat Map of document nodes 
          dialog_nodes : new Map,
          closer_node  : 0, // nodeId
          child_hits   : 0, // Count of 'interesting' nodes in children
          hit_depth    : 0, // Viz. congress_extract_history_panel: Tunable to set depth of panel traversal
          triggerable  : -1, // DEBUG: Limit number of elements traversed
          flattened    : new Map, // Stores History markup tree
          textkeys     : textkeys, // Stores bill history dictionary 
          text_headers : text_headers, // .text map lookup table, if available
          n            : 0,

          unfetched_data_id: null,
          found_data_id    : null,
          db               : s,
          congress_basedoc : await s.getSchema( db_name ).getTable('congress_basedoc'),
          url_raw          : await s.getSchema( db_name ).getTable('url_raw'),
          joins            : await s.getSchema( db_name ).getTable('congress_basedoc_url_raw_join')
        };

        await inorder_traversal( 
          { branchpat  : tagmotif, traversal_parent : null },
          nodes_tree, 
          -1, 
          envSet("MODE","TRAVERSE") 
          ? trigger_page_traverse_cb
          : trigger_page_fetch_cb, 
          traversal_paramset 
        );
         
        postprocessing = 0;

        rr_time = hrtime.bigint();
        console.log( "Built %d nodes", nodes_tree.size, rr_time_delta(),
          (envSet("DUMP_PRODUCT","1")) 
          ? inspect(lookup_tree, default_insp)
          : nodes_tree.size 
        );
        write_map_to_file( "Tag stack",
          "tagstack.txt", 
          tagstack,
          file_ts
        );
        if ( envSet("SAMPLE_TRIE","1") ) write_map_to_file( "Lookup tree",
          "trie.txt", 
          lookup_tree,
          file_ts
        );
        write_map_to_file( "Tag motifs",
          "motifs.txt", 
          motifs,
          file_ts
        );
        write_map_to_file( "Panel key frequency", "panelkeys.txt",
          textkeys,
          file_ts
        );

        console.log( "Everything", 
          rr_time_delta(), 
          (envSet("DUMP_PRODUCT","1"))
          ? inspect(nodes_tree, default_insp)
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
      }

      if ( exception_abort || traversal_abort ) {
        console.log( "Process Abort" );
        if ( envSet("ABORT_CLOSES_BROWSER","1") ) await Browser.close();
        process.exit(1);
      }

      document_reset();

    }
    else {
      // Trigger requestChildNodes
      await trigger_dom_fetch();
    }
    return Promise.resolve(true);
  }//}}}

  try
  {//{{{

    Network.requestWillBeSent(networkRequestWillBeSent);
    Network.responseReceived(networkResponseReceived);
    Network.loadingFinished(networkLoadingFinished);

    DOM.setChildNodes(domSetChildNodes);

    await DOM.attributeModified(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::attributeModified', params );
      await domAttributeModified(params);
    });

    await DOM.attributeRemoved(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::attributeRemoved', params );
    });

    await DOM.characterDataModified(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::characterDataModified', params );
    });

    await DOM.childNodeCountUpdated(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeCountUpdated', params );
    });

    await DOM.childNodeInserted(async (params) => {
      await domChildNodeInserted(params);
    });

    await DOM.childNodeRemoved(async (params) => {
      if ( envSet("DOM","1") ) console.log( 'DOM::childNodeRemoved', params );
      await domChildNodeRemoved(params);
    });

    await DOM.documentUpdated(async (params) => {
      console.log( 'DOM::documentUpdated', params );
      document_reset();
    });

    await Page.loadEventFired(async (ts) => {

      try {
        const { currentIndex, entries } = await Page.getNavigationHistory();
        const {root:{nodeId}} = await DOM.getDocument({ pierce: true });

        current_url = entries && entries.length > 0 && entries[currentIndex] && entries[currentIndex].url 
          ? entries[currentIndex].url 
          : '---';

        await setup_dom_fetch( nodeId );

        console.log("LOAD EVENT OK root[%d]", 
          nodeId, 
          ts,
          datestring( cycle_date ),
          current_url
        );
      }
      catch(e) {
        console.log("LOAD EVENT EXCEPTION root[%d]",
          nodeId,
          ts,
          datestring( cycle_date ),
          current_url,
          inspect( e, default_insp )
        );
      }

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
      console.log("Page::fileChooserOpened", p);
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

    document_reset();

    // Trigger a page fetch, else stand by for browser interaction or timeout
    if ( targetUrl.length > 0 ) {
      await Page.navigate({url: targetUrl});
    }

    while ( !completed ) {
      await watchdog( finalize_metadata );
    }

  }//}}}
  catch (err)
  {//{{{
    console.error("Main exception", inspect(err, default_insp));
  }//}}}
  finally
  {//{{{
    if (client) {
      console.log( "Close opportunity ended" );
      // client.close();
    }
  }//}}}
  return Promise.resolve(true);

}//}}}

async function traverse()
{//{{{
  let fn = env['TARGETURL'];
  let ss = statSync( fn, { throwIfNoEntry: false } );
  let panel_ids = new Map;
  let uniques = new Array;
  let text_headers = new Map;
  let text_headers_master = false;
  let history_headers = new Map;
  let files_processed = 0;

  if ( ss === undefined ) {
    console.log( "No such file or directory '%s'", fn );
    process.exit(1);
  }

  if ( existsSync( "textsections.json" ) ) {
    text_headers_master = true;
    text_headers = read_map_from_file( "textsections.json" );
    text_headers.forEach((v,k) => { v = 0; });
    console.log( "Loaded text sections master", inspect( text_headers, default_insp ) );
  }

  let s = await setup_db();
  let ingest_paramset = {
    text_headers     : text_headers,
    db               : s,
    congress_basedoc : await s.getSchema( db_name ).getTable('congress_basedoc'),
    url_raw          : await s.getSchema( db_name ).getTable('url_raw'),
    joins            : await s.getSchema( db_name ).getTable('congress_basedoc_url_raw_join'),
    work             : new Map
  }

  if ( ss.isFile() ) {
    console.log( "Parse file '%s'", fn );
    //let panel_raw = await ingest_panels( fn );
    //let panel = normalize_j_history( panel_raw );
    //console.log( "Finalized panel", inspect( panel, default_insp ));
    //result = await examine_ingest_json( fn, ingest_paramset );
  }

  if ( ss.isDirectory() ) {
    let dh = readdirSync( fn, { withFileTypes: true, recursive: true } ); 
    if (0) console.log( "Check", typeof ingest_paramset, inspect(ingest_paramset, default_insp) );
    console.log( "Locate %d files in '%s'", dh.length, fn );
    while ( dh.length > 0 ) {
      let dirent = dh.shift();
      if ( !dirent.isFile() )
        continue;
      if ( /^EXTRACTED/.test( dirent.parentPath ) )
        continue;
      if ( /^NORMALIZE/.test( dirent.parentPath ) )
        continue;

      let f = [ dirent.parentPath, dirent.name ].join('/');

      if ( ( /^INGEST/.test( dirent.parentPath ) || /^HOLDING/.test( dirent.parentPath )) && /\.json$/.test( dirent.name ) ) {//{{{
        let result;
        try { 
          switch ( process.env['PARSEMODE'] || '' ) { 
            case "ingest":
              result = await congress_record_fe_panelinfo( f, ingest_paramset );
              if ( result !== undefined )
                files_processed++;
              break;
            case "examine_history":
              result = await examine_ingest_json( f, ingest_paramset );
              if ( result !== undefined ) {
                console.log( "%s|%s", result, f );
                files_processed++;
              }
              break;
            default:
              break;
          }
        }
        catch (e) {
          console.log( "Skip %s", f, e );
        }
      }//}}}
      else if ( /panels-(.*)\.json/.test( dirent.name ) ) {//{{{
        // Live DOM page traversal will dump these panels-*.json files
        // if the database is not accessible, or when an error occurs
        // (e.g. database connection timeout, or [TODO] first database 
        // INSERT error).
        let panel_info = await ingest_panels( f );
        if ( panel_info !== undefined ) {
          let panel_id = panel_info.id.replace(/^#/,'');
          let panel_filename;
          let suffix;

          files_processed++;

          // Check for duplicates in this batch
          if ( !panel_ids.has( panel_id ) )
            panel_ids.set( panel_id, [ f ] );
          else {
            let panel_files = panel_ids.get( panel_id );
            panel_files.push( f );
            panel_ids.set( panel_id, panel_files );
            console.log( "DUPLICATE PANEL: %d for %s", 
              panel_files.length, 
              panel_id
            );
            suffix = panel_files.length;
          }

          panel_info_text_check( panel_info, text_headers, text_headers_master );

          // Write to EXTRACTED/ and INGEST/ directories
          panel_filename = [ panel_id ];
          if ( suffix !== undefined ) panel_filename.push( suffix );
          panel_filename = [ panel_filename.join('-'), 'json' ].join('.');
          console.log(
            "Parsed file '%s' into %s", f, panel_filename,
            inspect(panel_info, default_insp)
          );

          // Insert newly-fetched document information 
          if ( panel_info.links.size > 0 || panel_info.history.size > 0 ) {
            await congress_record_panelinfo( panel_info, ingest_paramset );
          }

          if (0) if ( !existsSync(['EXTRACTED', panel_filename ].join('/')) || 
            envSet("INGEST_OVERWRITE","1") ) {
            write_map_to_file( 
              panel_filename, 
              ['EXTRACTED', panel_filename ].join('/'),
              panel_info
            );
          }
          else {
            console.log("Not writing %s", panel_filename);
          }

          if (1) if ( existsSync('INGEST') ) {
            if ( !existsSync(['INGEST', panel_filename ].join('/')) || 
              envSet("INGEST_OVERWRITE","1") ) {
              write_map_to_file( 
                panel_filename, 
                ['INGEST', panel_filename ].join('/'),
                panel_info
              );
            }
            else {
              console.log("Not writing %s", panel_filename);
            }
          }

        }
        else if ( existsSync(f) ) {
          let rawjson;
          rawjson = read_map_from_file( f ); 
          console.log( "Unable to parse %s", f,
            inspect( rawjson, default_insp )
          );
        }
        else {
          console.log( "File '%s' no longer available", f );
        }
        console.log('-----------');
      }//}}}
      else if ( /trie-(.*)\./.test( dirent.name ) ) {//{{{
        if (envSet("TRIE","1")) {
          console.log( "Parse file '%s'", f );
          await preload( f );
          console.log('-----------');
          files_processed++;
        }
      }//}}}
      else if ( /([^-]{1,})-([0-9-]{1,}).json/.test( dirent.name ) ) {//{{{
        if (envSet("NORMALIZE","1"))
          await normalize( f );
          files_processed++;
      }//}}}

      await sleep(20);
    }

    // Clean up text headers
    let th_a = new Array;
    text_headers.forEach((v,k) => { th_a.push(k); });
    while ( th_a.length > 0 ) {
      let th = th_a.shift();
      let n = text_headers.get(th);
      // Prune panel text headers used less than x times.
      if ( n < 10 ) text_headers.delete(th);
    }

    console.log( "%d Text headers found", text_headers.size,
      inspect( text_headers, default_insp )
    );

    if ( envSet("CONGRESS_RETAIN_RAWHDR","1") ) write_map_to_file( "Text section headers", 
      "EXTRACTED/textsections.json",
      text_headers
    );

    if ( panel_ids.size > 0 ) {
      // Collect non-duplicated panel sources for removal
      panel_ids.forEach((v,k) => {
        if ( v.length == 1 ) {
          uniques.push(k)
        };
      });

      if (envSet("PERMIT_UNLINK","1")) while ( uniques.length > 0 ) {
        let unique_e = uniques.shift();
        let entries = panel_ids.get(unique_e);
        entries.forEach((f) => {
          try {
            if ( existsSync( f ) )
              unlinkSync( f );
            console.log("x", f);
          }
          catch (e) {}
        });
        panel_ids.delete(unique_e);
      }

      if (envSet("PERMIT_UNLINK","1")) panel_ids.forEach((v,k) => {
        v.forEach((f) => {
          try {
            if ( existsSync( f ) )
              unlinkSync( f );
            console.log("*", f);
          }
          catch (e) {}
        });
      });

      // Retain registry of duplicate sources
      if ( envSet("CONGRESS_RETAIN_DUPLIST","1") ) write_map_to_file( 
        "Panel IDs", 
        "EXTRACTED/panels.json", 
        panel_ids
      );
    }
    else {
      console.log( "Skipping file removals" );
    }

    console.log( "Processed %d", files_processed );

    ingest_paramset.work.forEach((v,k,m) => {
      if ( v <= 1 ) m.delete(k);
    });

    write_map_to_file(
      "History hashes",
      "history.sha256.json",  
      ingest_paramset.work
    );
  }
  process.exit(0);
}//}}}

if ( envSet("ACTIVE_MONITOR","1") ) {
  monitor();
}

if ( envSet("PARSE","1") ) {
  traverse();
}
