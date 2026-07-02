# setup-feature-artifacts.ps1 — delegate to bash implementation
$ExtDir = Join-Path (Get-Location) ".specify/extensions/drupal"
& bash (Join-Path $ExtDir "scripts/bash/setup-feature-artifacts.sh") @args
