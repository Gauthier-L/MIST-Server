<?php
/**
 * MulticastSnapinWrapper class
 *
 * Generates client-side wrapper scripts for multicast snapin reception
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */

/**
 * MulticastSnapinWrapper class
 *
 * This class generates platform-specific wrapper scripts that:
 * 1. Download and install udp-receiver if not present
 * 2. Join the multicast session
 * 3. Receive the snapin file
 * 4. Execute the snapin with proper arguments
 * 5. Report results back to the server
 *
 * @category Plugin
 * @package  FOGProject
 * @author   MIST Team
 * @license  http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link     https://fogproject.org
 */
class MulticastSnapinWrapper extends FOGBase
{
    /**
     * Generate Windows PowerShell wrapper script
     *
     * @param object $session The multicast session
     * @param object $snapin  The snapin object
     *
     * @return string The PowerShell script content
     */
    public static function generateWindowsScript($session, $snapin)
    {
        $serverIP = self::getSetting('FOG_TFTP_HOST');
        $port = $session->get('port');
        $interface = $session->get('interface');
        $multicastAddress = self::getSetting('FOG_MULTICAST_ADDRESS') ?: '224.0.0.1';

        $snapinName = $snapin->get('name');
        $snapinFile = $snapin->get('file');
        $snapinArgs = $snapin->get('args');
        $snapinRunWith = $snapin->get('runWith') ?: 'cmd.exe';
        $snapinRunWithArgs = $snapin->get('runWithArgs') ?: '/c';

        $script = <<<'POWERSHELL'
# FOG Multicast Snapin Wrapper - Windows
# Generated automatically by MIST MulticastSnapin plugin

$ErrorActionPreference = "Stop"
$LogFile = "$env:TEMP\fog-multicast-snapin.log"
$SnapinFile = "$env:TEMP\{SNAPIN_FILE}"
$UdpReceiverPath = "$env:ProgramFiles\udpcast\udp-receiver.exe"

function Write-Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    "$timestamp - $Message" | Tee-Object -FilePath $LogFile -Append
}

function Install-UdpReceiver {
    Write-Log "Checking for udp-receiver..."

    if (Test-Path $UdpReceiverPath) {
        Write-Log "udp-receiver already installed"
        return $true
    }

    Write-Log "Installing udpcast..."

    try {
        # Download udpcast for Windows
        $udpcastUrl = "http://{SERVER_IP}/fog/service/udpcast/udpcast-windows.zip"
        $downloadPath = "$env:TEMP\udpcast.zip"

        Write-Log "Downloading from $udpcastUrl..."
        Invoke-WebRequest -Uri $udpcastUrl -OutFile $downloadPath -UseBasicParsing

        # Extract
        $extractPath = "$env:ProgramFiles\udpcast"
        New-Item -ItemType Directory -Path $extractPath -Force | Out-Null
        Expand-Archive -Path $downloadPath -DestinationPath $extractPath -Force

        Write-Log "udpcast installed successfully"
        return $true
    }
    catch {
        Write-Log "ERROR: Failed to install udpcast: $_"
        return $false
    }
}

function Receive-MulticastSnapin {
    Write-Log "Starting multicast reception..."
    Write-Log "Server: {SERVER_IP}, Port: {PORT}, Address: {MULTICAST_ADDRESS}"

    try {
        $receiverArgs = @(
            "--portbase", "{PORT}",
            "--mcast-rdv-address", "{SERVER_IP}",
            "--file", $SnapinFile,
            "--nokbd"
        )

        Write-Log "Running: $UdpReceiverPath $receiverArgs"

        $process = Start-Process -FilePath $UdpReceiverPath `
            -ArgumentList $receiverArgs `
            -NoNewWindow `
            -Wait `
            -PassThru

        if ($process.ExitCode -ne 0) {
            throw "udp-receiver exited with code $($process.ExitCode)"
        }

        if (-not (Test-Path $SnapinFile)) {
            throw "Snapin file not received: $SnapinFile"
        }

        $fileSize = (Get-Item $SnapinFile).Length
        Write-Log "Snapin received successfully ($fileSize bytes)"

        return $true
    }
    catch {
        Write-Log "ERROR: Multicast reception failed: $_"
        return $false
    }
}

function Execute-Snapin {
    Write-Log "Executing snapin: {SNAPIN_NAME}"

    try {
        $executor = "{SNAPIN_RUNWITH}"
        $executorArgs = "{SNAPIN_RUNWITHARGS}"
        $snapinArgs = "{SNAPIN_ARGS}"

        # Build full argument list
        if ($snapinArgs) {
            $fullArgs = "$executorArgs `"$SnapinFile`" $snapinArgs"
        } else {
            $fullArgs = "$executorArgs `"$SnapinFile`""
        }

        Write-Log "Running: $executor $fullArgs"

        $process = Start-Process -FilePath $executor `
            -ArgumentList $fullArgs `
            -NoNewWindow `
            -Wait `
            -PassThru

        $exitCode = $process.ExitCode
        Write-Log "Snapin execution completed with exit code: $exitCode"

        return $exitCode
    }
    catch {
        Write-Log "ERROR: Snapin execution failed: $_"
        return 1
    }
}

# Main execution
try {
    Write-Log "=== FOG Multicast Snapin Wrapper Started ==="
    Write-Log "Snapin: {SNAPIN_NAME}"

    # Step 1: Ensure udp-receiver is available
    if (-not (Install-UdpReceiver)) {
        Write-Log "FATAL: Cannot install udp-receiver"
        exit 1
    }

    # Step 2: Receive snapin via multicast
    if (-not (Receive-MulticastSnapin)) {
        Write-Log "FATAL: Failed to receive snapin"
        exit 2
    }

    # Step 3: Execute the snapin
    $exitCode = Execute-Snapin

    # Step 4: Cleanup
    if (Test-Path $SnapinFile) {
        Remove-Item $SnapinFile -Force -ErrorAction SilentlyContinue
    }

    Write-Log "=== Wrapper completed with exit code: $exitCode ==="
    exit $exitCode
}
catch {
    Write-Log "FATAL ERROR: $_"
    exit 99
}
POWERSHELL;

        // Replace placeholders
        $script = str_replace('{SERVER_IP}', $serverIP, $script);
        $script = str_replace('{PORT}', $port, $script);
        $script = str_replace('{MULTICAST_ADDRESS}', $multicastAddress, $script);
        $script = str_replace('{SNAPIN_NAME}', addslashes($snapinName), $script);
        $script = str_replace('{SNAPIN_FILE}', $snapinFile, $script);
        $script = str_replace('{SNAPIN_ARGS}', addslashes($snapinArgs), $script);
        $script = str_replace('{SNAPIN_RUNWITH}', addslashes($snapinRunWith), $script);
        $script = str_replace('{SNAPIN_RUNWITHARGS}', addslashes($snapinRunWithArgs), $script);

        return $script;
    }

    /**
     * Generate Linux bash wrapper script
     *
     * @param object $session The multicast session
     * @param object $snapin  The snapin object
     *
     * @return string The bash script content
     */
    public static function generateLinuxScript($session, $snapin)
    {
        $serverIP = self::getSetting('FOG_TFTP_HOST');
        $port = $session->get('port');
        $interface = $session->get('interface');
        $multicastAddress = self::getSetting('FOG_MULTICAST_ADDRESS') ?: '224.0.0.1';

        $snapinName = $snapin->get('name');
        $snapinFile = $snapin->get('file');
        $snapinArgs = $snapin->get('args');
        $snapinRunWith = $snapin->get('runWith') ?: '/bin/bash';
        $snapinRunWithArgs = $snapin->get('runWithArgs') ?: '';

        $script = <<<'BASH'
#!/bin/bash
# FOG Multicast Snapin Wrapper - Linux
# Generated automatically by MIST MulticastSnapin plugin

set -e

LOG_FILE="/tmp/fog-multicast-snapin.log"
SNAPIN_FILE="/tmp/{SNAPIN_FILE}"
UDP_RECEIVER="/usr/local/bin/udp-receiver"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

install_udp_receiver() {
    log "Checking for udp-receiver..."

    if [ -f "$UDP_RECEIVER" ]; then
        log "udp-receiver already installed"
        return 0
    fi

    log "Installing udpcast..."

    # Try to install via package manager
    if command -v apt-get &> /dev/null; then
        apt-get update -qq
        apt-get install -y udpcast
    elif command -v yum &> /dev/null; then
        yum install -y udpcast
    elif command -v dnf &> /dev/null; then
        dnf install -y udpcast
    else
        log "ERROR: No supported package manager found"
        return 1
    fi

    if [ ! -f "$UDP_RECEIVER" ]; then
        log "ERROR: udp-receiver not found after installation"
        return 1
    fi

    log "udpcast installed successfully"
    return 0
}

receive_multicast_snapin() {
    log "Starting multicast reception..."
    log "Server: {SERVER_IP}, Port: {PORT}, Address: {MULTICAST_ADDRESS}"

    "$UDP_RECEIVER" \
        --portbase {PORT} \
        --mcast-rdv-address {SERVER_IP} \
        --file "$SNAPIN_FILE" \
        --nokbd 2>&1 | tee -a "$LOG_FILE"

    local exit_code=${PIPESTATUS[0]}

    if [ $exit_code -ne 0 ]; then
        log "ERROR: udp-receiver exited with code $exit_code"
        return 1
    fi

    if [ ! -f "$SNAPIN_FILE" ]; then
        log "ERROR: Snapin file not received: $SNAPIN_FILE"
        return 1
    fi

    local file_size=$(stat -f%z "$SNAPIN_FILE" 2>/dev/null || stat -c%s "$SNAPIN_FILE")
    log "Snapin received successfully ($file_size bytes)"

    return 0
}

execute_snapin() {
    log "Executing snapin: {SNAPIN_NAME}"

    # Make executable if needed
    chmod +x "$SNAPIN_FILE" 2>/dev/null || true

    local executor="{SNAPIN_RUNWITH}"
    local executor_args="{SNAPIN_RUNWITHARGS}"
    local snapin_args="{SNAPIN_ARGS}"

    log "Running: $executor $executor_args $SNAPIN_FILE $snapin_args"

    $executor $executor_args "$SNAPIN_FILE" $snapin_args 2>&1 | tee -a "$LOG_FILE"

    local exit_code=${PIPESTATUS[0]}
    log "Snapin execution completed with exit code: $exit_code"

    return $exit_code
}

# Main execution
main() {
    log "=== FOG Multicast Snapin Wrapper Started ==="
    log "Snapin: {SNAPIN_NAME}"

    # Step 1: Ensure udp-receiver is available
    if ! install_udp_receiver; then
        log "FATAL: Cannot install udp-receiver"
        exit 1
    fi

    # Step 2: Receive snapin via multicast
    if ! receive_multicast_snapin; then
        log "FATAL: Failed to receive snapin"
        exit 2
    fi

    # Step 3: Execute the snapin
    execute_snapin
    local exit_code=$?

    # Step 4: Cleanup
    rm -f "$SNAPIN_FILE"

    log "=== Wrapper completed with exit code: $exit_code ==="
    exit $exit_code
}

main "$@"
BASH;

        // Replace placeholders
        $script = str_replace('{SERVER_IP}', $serverIP, $script);
        $script = str_replace('{PORT}', $port, $script);
        $script = str_replace('{MULTICAST_ADDRESS}', $multicastAddress, $script);
        $script = str_replace('{SNAPIN_NAME}', addslashes($snapinName), $script);
        $script = str_replace('{SNAPIN_FILE}', $snapinFile, $script);
        $script = str_replace('{SNAPIN_ARGS}', addslashes($snapinArgs), $script);
        $script = str_replace('{SNAPIN_RUNWITH}', addslashes($snapinRunWith), $script);
        $script = str_replace('{SNAPIN_RUNWITHARGS}', addslashes($snapinRunWithArgs), $script);

        return $script;
    }

    /**
     * Save wrapper script to storage
     *
     * @param object $session     The multicast session
     * @param object $snapin      The snapin object
     * @param string $platform    Platform (windows|linux)
     * @param object $storageNode The storage node
     *
     * @return string|bool The saved file path or false
     */
    public static function saveWrapperScript($session, $snapin, $platform, $storageNode)
    {
        // Generate script content
        if ($platform === 'windows') {
            $content = self::generateWindowsScript($session, $snapin);
            $extension = 'ps1';
        } else {
            $content = self::generateLinuxScript($session, $snapin);
            $extension = 'sh';
        }

        // Generate filename
        $filename = sprintf(
            'multicast-snapin-%d-%s.%s',
            $session->get('id'),
            $platform,
            $extension
        );

        // Get snapin path on storage node
        $snapinPath = $storageNode->get('snapinpath');
        if (!$snapinPath) {
            return false;
        }

        $fullPath = sprintf(
            '%s/%s',
            rtrim($snapinPath, '/'),
            $filename
        );

        // Save file
        if (file_put_contents($fullPath, $content) === false) {
            return false;
        }

        // Make executable for Linux scripts
        if ($platform === 'linux') {
            chmod($fullPath, 0755);
        }

        return $fullPath;
    }
}
