@echo off
echo Blog sistemini senkronize ediliyor...
xcopy /E /H /C /I /Y "blog-system\*" "C:\xampp\htdocs\blog-system\"
echo Senkronizasyon tamamlandı!
pause 