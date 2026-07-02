#Requires -Version 5.1
$ErrorActionPreference = "Stop"
$ProjectRoot = Get-Location
$ExtDir = Join-Path $ProjectRoot ".specify/extensions/drupal"
$ArgsList = @()
if ($args) { $ArgsList = $args }
& bash (Join-Path $ExtDir "scripts/bash/setup-mcp-tools.sh") @ArgsList
