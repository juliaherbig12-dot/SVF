@echo off
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (echo PHP wurde nicht gefunden.&pause&exit /b 1)
php storage\init.php
if errorlevel 1 (echo. & echo FEHLER: Die Datenbank konnte nicht initialisiert werden. Bitte die Meldung oben pruefen.&pause&exit /b 1)
echo Datenbank: OK
echo Website: http://127.0.0.1:8081
echo Redaktion: http://127.0.0.1:8081/login/
start "" http://127.0.0.1:8081
php -S 127.0.0.1:8081 -t public
pause
