@echo off
echo === Atelier Moto Pro - Chargement Donnees Demo ===
docker compose exec php php bin/console app:analytics:demo-seed
echo.
echo Donnees de demonstration chargees !
pause
