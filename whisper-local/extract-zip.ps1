param(
    [Parameter(Mandatory=$true)][string]$ZipPath,
    [Parameter(Mandatory=$true)][string]$DestPath
)

# Couleurs
$Host.UI.RawUI.WindowTitle = "SpocSpace - Extraction en cours..."

Write-Host ""
Write-Host "  ============================================" -ForegroundColor Cyan
Write-Host "   Extraction du fichier ZIP" -ForegroundColor Cyan
Write-Host "  ============================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path $ZipPath)) {
    Write-Host "  [ERREUR] Fichier ZIP introuvable : $ZipPath" -ForegroundColor Red
    exit 1
}

# Taille du ZIP pour estimation
$zipSize = (Get-Item $ZipPath).Length
$zipSizeMB = [math]::Round($zipSize / 1MB, 0)
Write-Host "  [*] Fichier : $(Split-Path $ZipPath -Leaf) ($zipSizeMB Mo)" -ForegroundColor Gray

# Creer le dossier destination
if (-not (Test-Path $DestPath)) {
    New-Item -ItemType Directory -Path $DestPath -Force | Out-Null
}

# Charger l'assembly .NET pour ZIP
Add-Type -AssemblyName System.IO.Compression.FileSystem

try {
    $zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
    $totalFiles = $zip.Entries.Count
    $totalSize = ($zip.Entries | Measure-Object -Property Length -Sum).Sum
    $totalSizeMB = [math]::Round($totalSize / 1MB, 0)

    Write-Host "  [*] $totalFiles fichiers a extraire ($totalSizeMB Mo)" -ForegroundColor Gray
    Write-Host ""

    $extracted = 0
    $extractedSize = 0
    $startTime = Get-Date
    $lastUpdate = Get-Date

    foreach ($entry in $zip.Entries) {
        $destFile = Join-Path $DestPath $entry.FullName

        # Creer les dossiers
        if ($entry.FullName.EndsWith('/') -or $entry.FullName.EndsWith('\')) {
            if (-not (Test-Path $destFile)) {
                New-Item -ItemType Directory -Path $destFile -Force | Out-Null
            }
            continue
        }

        # Creer le dossier parent si necessaire
        $parentDir = Split-Path $destFile -Parent
        if (-not (Test-Path $parentDir)) {
            New-Item -ItemType Directory -Path $parentDir -Force | Out-Null
        }

        # Extraire le fichier
        [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destFile, $true)

        $extracted++
        $extractedSize += $entry.Length

        # Mettre a jour l'affichage toutes les 200ms max (eviter le flickering)
        $now = Get-Date
        if (($now - $lastUpdate).TotalMilliseconds -ge 200 -or $extracted -eq $totalFiles) {
            $lastUpdate = $now

            # Pourcentage
            if ($totalSize -gt 0) {
                $pct = [math]::Round(($extractedSize / $totalSize) * 100, 1)
            } else {
                $pct = [math]::Round(($extracted / $totalFiles) * 100, 1)
            }

            # Barre de progression visuelle
            $barWidth = 30
            $filled = [math]::Floor($pct / 100 * $barWidth)
            $empty = $barWidth - $filled
            $bar = ([char]0x2588).ToString() * $filled + ([char]0x2591).ToString() * $empty

            # Temps ecoule et estimation restant
            $elapsed = ($now - $startTime).TotalSeconds
            if ($pct -gt 0) {
                $totalEst = $elapsed / ($pct / 100)
                $remaining = [math]::Max(0, $totalEst - $elapsed)
                if ($remaining -ge 60) {
                    $timeLeft = "{0}min {1}s" -f [math]::Floor($remaining / 60), [math]::Floor($remaining % 60)
                } else {
                    $timeLeft = "{0}s" -f [math]::Floor($remaining)
                }
            } else {
                $timeLeft = "..."
            }

            $extractedMB = [math]::Round($extractedSize / 1MB, 0)

            # Nom du fichier courant (tronque)
            $fileName = $entry.Name
            if ($fileName.Length -gt 30) {
                $fileName = $fileName.Substring(0, 27) + "..."
            }

            # Ecrire la ligne de progression (ecrase la precedente)
            $line = "  $bar  $pct%  |  $extractedMB / $totalSizeMB Mo  |  ~$timeLeft restant"
            Write-Host "`r$line" -NoNewline -ForegroundColor Yellow

            # Write-Progress pour la barre PowerShell native aussi
            Write-Progress -Activity "Extraction SpocSpace" -Status "$pct% - $extracted/$totalFiles fichiers ($extractedMB Mo)" -PercentComplete ([math]::Min($pct, 100)) -CurrentOperation $entry.FullName
        }
    }

    Write-Progress -Activity "Extraction SpocSpace" -Completed
    Write-Host ""
    Write-Host ""

    $totalTime = (Get-Date) - $startTime
    if ($totalTime.TotalMinutes -ge 1) {
        $timeStr = "{0}min {1}s" -f [math]::Floor($totalTime.TotalMinutes), [math]::Floor($totalTime.Seconds)
    } else {
        $timeStr = "{0}s" -f [math]::Floor($totalTime.TotalSeconds)
    }

    Write-Host "  [OK] Extraction terminee !" -ForegroundColor Green
    Write-Host "  [*] $extracted fichiers extraits en $timeStr" -ForegroundColor Gray
    Write-Host ""

    $zip.Dispose()
    exit 0

} catch {
    Write-Host ""
    Write-Host "  [ERREUR] $($_.Exception.Message)" -ForegroundColor Red
    if ($zip) { $zip.Dispose() }
    exit 1
}
