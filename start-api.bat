@echo off
rem Starts the McRelish API on http://127.0.0.1:8080 using XAMPP's PHP (no Apache setup needed).
rem Keep this window open while you work. Press Ctrl+C to stop.
cd /d "%~dp0"
if exist "C:\xampp\php\php.exe" (
  "C:\xampp\php\php.exe" -S 127.0.0.1:8080 -t public router.php
) else (
  php -S 127.0.0.1:8080 -t public router.php
)
