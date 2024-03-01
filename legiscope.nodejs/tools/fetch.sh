#!/bin/bash

rm -f output.log
export SILENT_PARSE=1 
#export NOISY_PARSE=1
export RECURSIVE=1 
export REFRESH=1
#export SKIP_HEAD_FETCH=1
export TARGETURL="$1" 
# time npx --expose-gc wdio run ./wdio.conf.js 2>&1 | tee -a output.log
time node \
  --expose-gc \
  --trace-gc \
  --max-old-space-size=8192 \
  --max-heap-size=8192 \
  --huge-max-old-generation-size \
  ./node_modules/.bin/wdio run ./wdio.conf.js | tee -a output.log
cat lastrun.json | jq .  | tee -a output.log
