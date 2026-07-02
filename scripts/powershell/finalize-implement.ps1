#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$ProjectRoot = Get-Location
$ExtDir = Join-Path $ProjectRoot ".specify/extensions/drupal"
& bash (Join-Path $ExtDir "scripts/bash/finalize-implement.sh")
