param([ValidateRange(1024,65535)][int]$Port = 8082)
$ErrorActionPreference = 'Stop'
$portable = Join-Path $PSScriptRoot '.local/php/php.exe'
if (Test-Path $portable) {
    $php = $portable
} else {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if (-not $command) { throw 'PHP 8.2+ belum ditemukan. Pasang PHP atau letakkan PHP portabel di .local/php.' }
    $php = $command.Source
}
Write-Host "Rentique PHP native: http://127.0.0.1:$Port"
Write-Host 'Data tersimpan di storage/data.json. Tanpa database, npm, atau deploy.'
Write-Host 'Tekan Ctrl+C untuk menghentikan server.'
Push-Location $PSScriptRoot
try {
    & $php -n -d date.timezone=Asia/Jakarta -S "127.0.0.1:$Port" -t public router.php
    if ($LASTEXITCODE -ne 0) { throw "Server PHP berhenti dengan kode $LASTEXITCODE." }
} finally {
    Pop-Location
}
