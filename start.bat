@echo off
echo === Atelier Moto Pro - Demarrage ===
docker compose up -d --build
echo.
echo Application demarree !
echo  - Frontend : http://localhost
echo  - API docs : http://localhost/api/docs (protégé, auth requise)
echo  - Mailhog  : http://localhost:8025
pause
