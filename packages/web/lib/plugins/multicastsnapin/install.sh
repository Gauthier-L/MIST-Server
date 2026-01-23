#!/bin/bash
#
# Installation script for MulticastSnapin plugin
#
# This script installs and activates the FOGMulticastSnapinManager service
#

set -e

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="FOGMulticastSnapinManager"
SYSTEMD_DIR="/lib/systemd/system"
SERVICE_DIR="/opt/fog/service/$SERVICE_NAME"

echo "=== MulticastSnapin Plugin Installation ==="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: This script must be run as root"
    echo "Please run: sudo $0"
    exit 1
fi

# Check for udp-sender
if [ ! -f "/usr/local/sbin/udp-sender" ]; then
    echo "WARNING: udp-sender not found at /usr/local/sbin/udp-sender"
    echo "Please ensure udpcast is installed before using this plugin"
fi

# Create service directory
echo "Creating service directory: $SERVICE_DIR"
mkdir -p "$SERVICE_DIR"

# Copy service executable
echo "Installing service executable..."
cp "$PLUGIN_DIR/../../service/$SERVICE_NAME/$SERVICE_NAME" "$SERVICE_DIR/"
chmod +x "$SERVICE_DIR/$SERVICE_NAME"

# Copy systemd unit file
echo "Installing systemd unit file..."
cp "$PLUGIN_DIR/systemd/$SERVICE_NAME.service" "$SYSTEMD_DIR/"

# Reload systemd
echo "Reloading systemd daemon..."
systemctl daemon-reload

# Enable service
echo "Enabling $SERVICE_NAME service..."
systemctl enable "$SERVICE_NAME"

# Start service
echo "Starting $SERVICE_NAME service..."
systemctl start "$SERVICE_NAME"

# Check status
echo ""
echo "=== Service Status ==="
systemctl status "$SERVICE_NAME" --no-pager

echo ""
echo "=== Installation Complete ==="
echo ""
echo "Next steps:"
echo "1. Go to MIST web interface → Plugin Management"
echo "2. Activate the 'multicastsnapin' plugin"
echo "3. Navigate to 'Multicast Snapin' in the menu to create sessions"
echo ""
echo "To view service logs, run:"
echo "  sudo journalctl -u $SERVICE_NAME -f"
echo ""
echo "To restart the service, run:"
echo "  sudo systemctl restart $SERVICE_NAME"
echo ""
