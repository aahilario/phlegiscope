#!/bin/bash
LOGFILE=ingest
YMDPATH=$(date +"%Y-%m-%d").d
rm -f ${YMDPATH}/output.log
rm -f ${YMDPATH}/pre-transform.json
rm -f ${YMDPATH}/everything.json
[ -d ${YMDPATH} ] || mkdir -p ${YMDPATH}
OUTPUT_LOG=${YMDPATH}/${LOGFILE}-$(date +"%Y-%m-%d-%H%M%S").log
export ACTIVE_MONITOR=0
export CB_PREPROCESS=0
export DEBUG_OUTPUT_PATH=$YMDPATH
export DOM=0
export DOMSETCHILDNODES=0
export DUMP_PRODUCT=0
export FETCH_URL_HTTP_HEAD=0
export FINALIZE_METADATA=0
export GRAFT=2
export INORDER_TRAVERSAL=0
export INGEST_OVERWRITE=1
export MODE=XTRAVERSE
export NORMALIZE=0
export PAGE_FETCH_CB=0
export PARSE=1
#export PARSEMODE=ingest
export PARSEMODE=examine_history
export PERMIT_UNLINK=1
export PRUNE_CB=0
export QA=1
export XREVERSE_CONTENT=10
export REDUCE_NODES=0
export SN_INORDER_TRAVERSAL=2
export TRIE=0
export TRIGGER_DOM_FETCH=0
export TREEIFY=0
export VERBOSE=0
export WATCHDOG=0

rm -f ${YMDPATH}/${LOGFILE}*.log
touch ${OUTPUT_LOG}
ln -sf $(basename ${OUTPUT_LOG}) ${YMDPATH}/${LOGFILE}.log

export TARGETURL=$1
shift
clear
node \
  --max-old-space-size=8192 \
  --max-heap-size=8192 \
  --huge-max-old-generation-size \
  sp-test-v2.js $@ 2>&1 | tee -a ${OUTPUT_LOG}


