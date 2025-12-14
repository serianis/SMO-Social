@echo off
echo ===========================================
echo  XAMPP WebSocket - Final Configuration
echo ===========================================
echo.

echo 🔍 Checking XAMPP WebSocket setup...

REM Έλεγχος αν τα αρχεία υπάρχουν
set "HTDOCS=C:\xampp\htdocs"

if not exist "%HTDOCS%\smo-websocket-server.php" (
    echo ❌ WebSocket server not found!
    echo Run setup-xampp-websocket.php first
    pause
    exit /b 1
)

if not exist "%HTDOCS%\start-smo-websocket.bat" (
    echo ❌ Startup script not found!
    pause
    exit /b 1
)

if not exist "%HTDOCS%\smo-websocket-test.html" (
    echo ❌ Test page not found!
    pause
    exit /b 1
)

echo ✅ All required files are present

REM Έλεγχος PHP sockets extension
echo.
echo 🔧 Checking PHP Sockets Extension...
php -m | findstr sockets >nul
if errorlevel 1 (
    echo ❌ Sockets extension NOT enabled
    echo.
    echo To enable it:
    echo 1. Open C:\xampp\php\php.ini
    echo 2. Find: ;extension=sockets
    echo 3. Remove the semicolon (;) to make it: extension=sockets
    echo 4. Save the file
    echo 5. Restart Apache in XAMPP Control Panel
    echo.
    pause
    exit /b 1
) else (
    echo ✅ Sockets extension is enabled
)

REM Έλεγχος Apache
echo.
echo 🌐 Checking Apache...
curl -s http://localhost >nul 2>&1
if errorlevel 1 (
    echo ❌ Apache is not running!
    echo.
    echo Please:
    echo 1. Open XAMPP Control Panel
    echo 2. Start Apache
    echo 3. Try again
    echo.
    pause
    exit /b 1
) else (
    echo ✅ Apache is running
)

REM Τελικές οδηγίες
echo.
echo ===========================================
echo  🚀 XAMPP WebSocket Setup Complete!
echo ===========================================
echo.
echo NEXT STEPS:
echo.
echo 1. START WebSocket Server:
echo    Double-click: %HTDOCS%\start-smo-websocket.bat
echo.
echo 2. TEST Connection:
echo    Open browser: http://localhost/smo-websocket-test.html
echo.
echo 3. WordPress Plugin:
echo    Will auto-connect to: ws://127.0.0.1:8080
echo.
echo 4. Monitor:
echo    Keep WebSocket server window open
echo    Press Ctrl+C to stop
echo.
echo ⚠️  IMPORTANT:
echo • Don't close the WebSocket server window
echo • Restart Apache after enabling sockets
echo • Test page shows connection status
echo.
echo Ready to start? Press any key to launch WebSocket server...
pause >nul

echo.
echo 🚀 Starting WebSocket Server...
echo Server will run on: ws://127.0.0.1:8080
echo Press Ctrl+C to stop the server
echo.

cd /d "%HTDOCS%"
echo Starting WebSocket server...
echo This will automatically detect available ports if 8080 is busy
echo.
php smo-websocket-server.php

echo.
echo WebSocket server stopped.
pause