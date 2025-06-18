function Sync-BlogSystem {
    Write-Host "Blog sistemini senkronize ediliyor..." -ForegroundColor Green
    Copy-Item -Path "blog-system\*" -Destination "C:\xampp\htdocs\blog-system\" -Recurse -Force
    Write-Host "Senkronizasyon tamamlandı!" -ForegroundColor Green
}

Set-Alias -Name syncblog -Value Sync-BlogSystem 