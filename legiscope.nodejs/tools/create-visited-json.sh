#!/bin/bash
rm -f r.json
for U in $(cat q.json | jq -r .[] | sed -r -e 's@/../@/@g' | sort -u); do
  cat <<EOF >> r.json
"$U",
EOF
  cat <<EOF
  "$U": {
    "hits": 1
  },
EOF
done

