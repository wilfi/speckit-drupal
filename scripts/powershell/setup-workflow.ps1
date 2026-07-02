#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$ProjectRoot = Get-Location
$ExtDir = Join-Path $ProjectRoot ".specify/extensions/drupal"
& (Join-Path $ExtDir "scripts/bash/setup-workflow.sh")
