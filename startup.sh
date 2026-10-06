#!/bin/bash
# Azure Linux PHP 8 uses nginx. Default client_max_body_size is 1m, which
# returns HTTP 413 before PHP sees resume / ticket uploads.
set +e
SIZE="32M"

inject_body_size() {
  local file="$1"
  [ -f "$file" ] || return 0
  if grep -q 'client_max_body_size' "$file"; then
    sed -i "s/client_max_body_size[[:space:]]*[^;]*;/client_max_body_size ${SIZE};/g" "$file"
  elif grep -q 'server[[:space:]]*{' "$file"; then
    sed -i "0,/server[[:space:]]*{/s//server {\n    client_max_body_size ${SIZE};/" "$file"
  elif grep -q 'http[[:space:]]*{' "$file"; then
    sed -i "0,/http[[:space:]]*{/s//http {\n    client_max_body_size ${SIZE};/" "$file"
  fi
}

for conf in \
  /etc/nginx/sites-enabled/default \
  /etc/nginx/sites-available/default \
  /etc/nginx/conf.d/default.conf \
  /etc/nginx/nginx.conf
do
  inject_body_size "$conf"
done

if [ -d /etc/nginx/conf.d ]; then
  echo "client_max_body_size ${SIZE};" > /etc/nginx/conf.d/zpgc_upload.conf
fi

nginx -t >/dev/null 2>&1
if [ $? -eq 0 ]; then
  nginx -s reload 2>/dev/null || service nginx reload 2>/dev/null || service nginx restart 2>/dev/null
fi
exit 0
