@echo off
echo === Atelier Moto Pro - Demarrage ===
docker compose up -d --build
echo.
echo Application demarree !
echo  - Frontend : http://localhost
echo  - API docs : http://localhost:8000/api/docs
echo  - Mailhog  : http://localhost:8025
pause
