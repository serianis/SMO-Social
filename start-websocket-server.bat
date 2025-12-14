@echo off
echo === XAMPP WebSocket Server Launcher ===
echo.

REM Check if XAMPP is running
echo Checking XAMPP Apache...
curl -s http://localhost >nul 2>&1
if errorlevel 1 (
    echo ⚠️  WARNING: Apache doesn't seem to be running!
    echo Please start Apache in XAMPP Control Panel first
    echo.
)

REM Check PHP
echo Checking PHP installation...
php --version >nul 2>&1
if errorlevel 1 (
    echo ❌ ERROR: PHP not found in PATH
    echo Please add XAMPP PHP to your PATH or run this from XAMPP directory
    echo.
    echo To add XAMPP PHP to PATH:
    echo 1. Right-click "This PC" → Properties → Advanced system settings
    echo 2. Click "Environment Variables"
    echo 3. Edit "Path" and add: C:\xampp\php
    echo 4. Restart command prompt
    echo.
    pause
    exit /b 1
)

echo ✓ PHP found
echo.

REM Start WebSocket Server
echo 🚀 Starting WebSocket Server...
echo Server will run on: ws://127.0.0.1:8080
echo Press Ctrl+C to stop the server
echo.

cd /d "%~dp0"
php xampp-websocket-server.php

echo.
echo WebSocket Server stopped.
pause