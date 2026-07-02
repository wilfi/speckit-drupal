# install-drupal.ps1
#
# Install the latest Drupal recommended project into the current workspace.
#
# Usage: install-drupal.ps1

$ErrorActionPreference = "Stop"

$ProjectRoot = Get-Location
$ExtDir = Join-Path $ProjectRoot ".specify/extensions/drupal"
$ConfigFile = Join-Path $ExtDir "drupal-config.yml"

$ProjectTemplate = "drupal/recommended-project"
$PhpMin = "8.3"
$PreserveDirs = @(".specify", ".cursor", ".git", "specs")

function Write-Log([string]$Message) { Write-Host "drupal: $Message" }

function Fail([string]$Message) {
    Write-Error "drupal: ERROR: $Message"
    exit 1
}

if (Test-Path $ConfigFile) {
    try {
        $cfg = Get-Content $ConfigFile -Raw | ConvertFrom-Yaml
        if ($cfg.project_template) { $ProjectTemplate = $cfg.project_template }
        if ($cfg.php_min_version) { $PhpMin = $cfg.php_min_version }
        if ($cfg.preserve_dirs) { $PreserveDirs = @($cfg.preserve_dirs) }
    } catch {
        Write-Log "Could not parse config; using defaults."
    }
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Fail "composer not found. Install Composer: https://getcomposer.org/"
}
if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Fail "php not found."
}

$phpVersion = (php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;').Trim()
if ([version]$phpVersion -lt [version]$PhpMin) {
    Fail "PHP $PhpMin+ required (found $phpVersion)."
}

$composerJson = Join-Path $ProjectRoot "composer.json"
if ((Test-Path $composerJson) -and (Select-String -Path $composerJson -Pattern '"drupal/core"' -Quiet)) {
    Fail "Drupal appears already installed (drupal/core in composer.json)."
}
if (Test-Path (Join-Path $ProjectRoot "web/core")) {
    Fail "Drupal appears already installed (web/core exists)."
}

$TmpDir = Join-Path ([System.IO.Path]::GetTempPath()) ("drupal-install-" + [guid]::NewGuid().ToString())
New-Item -ItemType Directory -Path $TmpDir | Out-Null

try {
    Write-Log "Creating Drupal project from $ProjectTemplate (latest stable)..."
    & composer create-project $ProjectTemplate $TmpDir --no-interaction --no-install
    & composer install --working-dir=$TmpDir --no-interaction

    Write-Log "Merging Drupal files into $ProjectRoot ..."
    Get-ChildItem -Path $TmpDir -Force | ForEach-Object {
        $name = $_.Name
        if ($PreserveDirs -contains $name) {
            Write-Log "Preserving existing: $name"
            return
        }
        $dest = Join-Path $ProjectRoot $name
        if ($_.PSIsContainer) {
            if (-not (Test-Path $dest)) { New-Item -ItemType Directory -Path $dest | Out-Null }
            Copy-Item -Path $_.FullName -Destination $dest -Recurse -Force
        } else {
            Copy-Item -Path $_.FullName -Destination $dest -Force
        }
        Write-Log "Installed: $name"
    }

    Write-Log "Drupal installation complete."
    Write-Host @"

Next steps:
  1. Point your web server at: $ProjectRoot\web
  2. Browser install: open /core/install.php
     Or CLI install:
       composer require drush/drush
       vendor\bin\drush -r web site:install --yes
       vendor\bin\drush -r web user:login

"@
} finally {
    if (Test-Path $TmpDir) { Remove-Item -Path $TmpDir -Recurse -Force }
}
