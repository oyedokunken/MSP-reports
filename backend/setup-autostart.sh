#!/bin/bash

echo "Setting up supervisor auto-start for reporting platform..."
echo ""

SCRIPT_PATH="/home/kristian/reporting/backend/ensure-supervisor-running.sh"

# Option 1: Add to crontab (runs every minute to check)
echo "Option 1: Crontab (checks every minute)"
echo "Run this command:"
echo ""
echo "(crontab -l 2>/dev/null; echo \"* * * * * $SCRIPT_PATH > /dev/null 2>&1\") | crontab -"
echo ""

# Option 2: Add to user's crontab to run at reboot
echo "Option 2: Run at system reboot"
echo "Run this command:"
echo ""
echo "(crontab -l 2>/dev/null; echo \"@reboot sleep 30 && $SCRIPT_PATH\") | crontab -"
echo ""

# Option 3: Create systemd service (recommended)
echo "Option 3: Systemd service (recommended)"
echo ""
cat > /tmp/reporting-supervisor.service << 'EOF'
[Unit]
Description=Reporting Platform Supervisor
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/home/kristian/reporting/backend/ensure-supervisor-running.sh
User=kristian

[Install]
WantedBy=multi-user.target
EOF

echo "Created systemd service file at /tmp/reporting-supervisor.service"
echo ""
echo "To install it, run these commands as root:"
echo ""
echo "  sudo cp /tmp/reporting-supervisor.service /etc/systemd/system/"
echo "  sudo systemctl daemon-reload"
echo "  sudo systemctl enable reporting-supervisor.service"
echo "  sudo systemctl start reporting-supervisor.service"
echo ""
echo "To check status:"
echo "  sudo systemctl status reporting-supervisor.service"
