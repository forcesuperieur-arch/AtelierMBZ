@echo off
echo === Atelier Moto Pro - Reinitialisation complete ===
echo ATTENTION : toutes les donnees seront supprimees !
set /p confirm="Continuer ? (O/N) : "
if /i not "%confirm%"=="O" goto :eof
docker compose down -v
docker compose up -d --build
timeout /t 10 /nobreak >nul
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console app:seed
echo.
echo Reinitialisation terminee !
pause
