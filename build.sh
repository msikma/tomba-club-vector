#!/usr/bin/env bash

SCRIPT_DIR=$(cd -- "$( dirname -- "${BASH_SOURCE[0]}" )" &> /dev/null && pwd)

if ! command -v sass 2>&1 >/dev/null; then
  echo "error: Install SassC 1.43.1 or up to build."
  exit 1
fi

if [[ "$1" == "--watch" ]]; then
  WATCH_OPTION="--watch"
else
  WATCH_OPTION=""
fi

sass $WATCH_OPTION "$SCRIPT_DIR/scss/tc-vector.scss":"$SCRIPT_DIR/TombaClub/tc-vector.css"
