#!/usr/bin/env php
<?php

function replaceCallback($matches)
{
    global $replaceMap;
    if (empty($matches[1]) || empty($matches[2])) {
        return $matches[0];
    }

    if (preg_match('/app\/design/', $matches[1])) {
        $a = 0;
    }

    $path = $matches[1];
    $module = $matches[2];

    if (empty($replaceMap[$path])) {
        return $matches[0];
    }

    preg_match_all('/[A-Z][a-z]+/', $module, $modulePartsRaw);

    if (!empty($modulePartsRaw[0])) {
        $moduleParts = $modulePartsRaw[0];
        $moduleName = implode('-', $moduleParts);
    } else {
        $moduleName = $module;
    }

    $modulePath = $replaceMap[$path] . strtolower($moduleName) . '/';

    return  $modulePath;
}

$mode = 'git';
$PWD = $_SERVER['PWD'];

$diffA = $diffB = false;
$script = $argv[0];

$usageHelp = "Usage: \n"
    . " php $script 2.1.6 MDVA-666 \n"
    . " php $script 2.1.6 MDVA-666 -v2 \n"
    . " php $script ./MAGETWO-66666.patch MDVA-666 \n"
    . " php $script ./MAGETWO-66666.patch MDVA-666 -v2 \n";

if (empty($argv[1]) || empty($argv[2])) {
    exit($usageHelp);
}

$patchFile = $PWD . '/' . $argv[1];

if (!preg_match('/\d+\.\d+\.\d+/', $argv[1]) && file_exists($patchFile)) {
    $mode = 'file';
}

$version = !empty($argv[3]) && preg_match('/\d+/', $argv[3], $matches) ? (int)$matches[0] : 1;

if ($mode == 'git') {
    $diffA = $argv[1];
    $diffB = $argv[2];

    exec("git checkout $diffA && cd ./magento2ee && git checkout $diffA");
    exec("git checkout $diffB && cd ./magento2ee && git checkout $diffB");
    $isBranchExistInCE = shell_exec("git branch | grep $diffB");
    $isBranchExistInEE = shell_exec("cd ./magento2ee && git branch | grep $diffB");

    $diffCE = $isBranchExistInCE ? shell_exec("git diff $diffA $diffB") : '';
    $diffEE = $isBranchExistInEE ? shell_exec("cd ./magento2ee && git diff $diffA $diffB") : '';
    $diff = $diffCE . $diffEE;
    $patchName = "{$diffB}_{$diffA}_v$version";
} elseif ($mode == 'file') {
    $diff = file_get_contents($patchFile);
    $withoutPrefixPatterns = array(
        '/(\+\+\+|\-\-\-)\s(app|lib)/'
    );

    $diff = preg_replace_callback($withoutPrefixPatterns, function($matches){
        $prefix = 'a/';
        if ($matches[1] == '+++') {
            $prefix = 'b/';
        }
        return "{$matches[1]} {$prefix}{$matches[2]}";
    }, $diff);
    $diffB = $argv[2];
    $patchName = "{$diffB}_v$version";
}

if ($diff) {
    $replaceMap = array(
        'app/code/Magento/' => 'vendor/magento/module-',
        'lib/internal/Magento/' => 'vendor/magento/',
        'app/design/frontend/Magento/' => 'vendor/magento/theme-frontend-',
        'app/design/adminhtml/Magento/' => 'vendor/magento/theme-adminhtml-'
    );

    $patterns = array(
        '/(app\/code\/Magento\/)([A-Za-z]+)\//',
        '/(lib\/internal\/Magento\/)([A-Za-z]+)\//',
        '/(app\/design\/frontend\/Magento\/)([A-Za-z]+)\//',
        '/(app\/design\/adminhtml\/Magento\/)([A-Za-z]+)\//',
    );

    $diffComposer = preg_replace_callback($patterns, 'replaceCallback', $diff);

    $patchGitFilename = $patchName . '.patch';
    $patchComposerFilename = $patchName . '.composer.patch';

    file_put_contents('./' . $patchGitFilename, $diff);
    file_put_contents('./' . $patchComposerFilename, $diffComposer);

    echo "\n";
    echo "######################################################################\n";
    echo "# Patches $patchGitFilename and $patchComposerFilename are generated\n";
    echo "######################################################################\n";
} else {
    echo "\n";
    echo "######################################################################\n";
    echo "# There is no available diff\n";
    echo "######################################################################\n";
}

//echo($diff);