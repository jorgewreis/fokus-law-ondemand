# Puxa a caixa de saída do Law por SFTP e guarda três meses.
# Jorge executa este script no PC. Ele não guarda senha nem chave.
param(
    [Parameter(Mandatory = $true)]
    [string] $SftpHost,
    [Parameter(Mandatory = $true)]
    [string] $SftpUser,
    [int] $SftpPort = 22,
    [string] $RemoteDir = '/outbox',
    [Parameter(Mandatory = $true)]
    [string] $LocalRoot,
    [Parameter(Mandatory = $true)]
    [string] $KnownHostsFile
)

$ErrorActionPreference = 'Stop'
if (-not (Get-Command sftp -ErrorAction SilentlyContinue)) {
    throw 'O cliente sftp do OpenSSH não está disponível neste PC.'
}
if (-not (Test-Path -LiteralPath $KnownHostsFile)) {
    throw 'Informe um known_hosts já fixado. O script não aceita host novo sozinho.'
}

New-Item -ItemType Directory -Force -Path $LocalRoot | Out-Null
$month = Get-Date -Format 'yyyy-MM'
$destination = Join-Path $LocalRoot $month
New-Item -ItemType Directory -Force -Path $destination | Out-Null
$batch = Join-Path $destination 'sftp-batch.txt'
$remoteManifest = ($RemoteDir.TrimEnd('/')) + '/MANIFEST.txt'
$localManifest = Join-Path $destination 'MANIFEST.txt'

@(
    "get $remoteManifest $localManifest"
) | Set-Content -LiteralPath $batch -Encoding ascii

& sftp -o BatchMode=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$KnownHostsFile" -P $SftpPort -b $batch "$SftpUser@$SftpHost"
if ($LASTEXITCODE -ne 0) {
    throw 'A leitura do manifesto SFTP falhou.'
}

$lines = Get-Content -LiteralPath $localManifest
foreach ($line in $lines) {
    $parts = $line -split '\s+'
    if ($parts.Length -lt 4) { continue }
    $fileName = $parts[2]
    $expected = $parts[3]
    $remoteFile = ($RemoteDir.TrimEnd('/')) + '/' + $fileName
    $localFile = Join-Path $destination $fileName
    "get $remoteFile $localFile" | Set-Content -LiteralPath $batch -Encoding ascii
    & sftp -o BatchMode=yes -o StrictHostKeyChecking=yes -o "UserKnownHostsFile=$KnownHostsFile" -P $SftpPort -b $batch "$SftpUser@$SftpHost"
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao baixar $fileName."
    }
    $actual = (Get-FileHash -LiteralPath $localFile -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($actual -ne $expected.ToLowerInvariant()) {
        Remove-Item -LiteralPath $localFile -Force
        throw "SHA-256 divergente em $fileName. O arquivo foi apagado."
    }
}

Get-ChildItem -LiteralPath $LocalRoot -Directory |
    Sort-Object Name -Descending |
    Select-Object -Skip 3 |
    ForEach-Object { Remove-Item -LiteralPath $_.FullName -Recurse -Force }

Remove-Item -LiteralPath $batch -Force -ErrorAction SilentlyContinue
Write-Output "Cópias mensais em $LocalRoot. Mantidas as três pastas mais recentes."
