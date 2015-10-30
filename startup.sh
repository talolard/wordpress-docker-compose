#!/bin/bash
hhvm  --mode daemon  -vServer.Type=fastcgi -vServer.Port=9000 &
nginx