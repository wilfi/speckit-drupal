#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$ProjectRoot = Get-Location
$ExtDir = Join-Path $ProjectRoot ".specify/extensions/drupal"
$FeatureDir = $args[0]
& bash (Join-Path $ExtDir "scripts/bash/verify-foundational.sh") @($FeatureDir)
