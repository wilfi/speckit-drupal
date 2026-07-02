#Requires -Version 5.1
$ErrorActionPreference = "Stop"
& bash "$PSScriptRoot/../bash/verify-quality.sh" @args
