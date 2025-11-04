#!/bin/bash

# Start supervisord in the background
/usr/bin/supervisord -c /etc/supervisor/supervisord.conf

# Keep the script running so Docker doesn't exit
tail -f /dev/null
