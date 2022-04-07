#!/usr/bin/env php
<?php

$repoPath = '/Users/poltorat/PhpstormProjects/m2p';
$repositories = [
    ['ce',               'git@github.com:magento-sparta/magento2ce.git'],
    ['ee',               'git@github.com:magento-sparta/magento2ee.git'],
    ['b2b',               'git@github.com:magento-sparta/magento2b2b.git'],
    ['inventory',         'git@github.com:magento-sparta/inventory.git'],
    ['pb',                'git@github.com:magento-sparta/magento2-page-builder.git'],
    ['security',          'git@github.com:magento-sparta/security-package.git']
];
$mapping =
    ['b2b'=>
        ['2.3.1'=>    '1.1.1',
            '2.3.2'=>    '1.1.2',
            '2.3.2-p2'=> '1.1.2-p2',
            '2.3.3'=>    '1.1.3',
            '2.3.3-p1'=> '1.1.3-p1',
            '2.3.4'=>    '1.1.4',
            '2.3.4-p1'=> '1.1.4-p1',
            '2.3.4-p2'=> '1.1.4-p2',
            '2.3.5'=>    '1.1.5',
            '2.3.5-p1'=> '1.1.5-p1',
            '2.3.5-p2'=> '1.1.5-p2',
            '2.3.6'=>    '1.1.6',
            '2.3.6-p1'=> '1.1.6-p1',
            '2.3.7'=>    '1.1.7',
            '2.3.7-p1'=> '1.1.7-p1',
            '2.3.7-p2'=> '1.1.7-p2',
            '2.4.0'=>    '1.2.0',
            '2.4.0-p1'=> '1.2.0-p1',
            '2.4.1'=>    '1.3.0',
            '2.4.1-p1'=> '1.3.0-p1',
            '2.4.2'=>    '1.3.1',
            '2.4.2-p1'=> '1.3.1-p1',
            '2.4.2-p2'=> '1.3.1-p2',
            '2.4.3'=>    '1.3.2',
            '2.4.3-p1'=> '1.3.2-p1'],
        'inventory' =>
            ['2.3.1'=>   '1.0.3',
                '2.3.2'=>    '1.1.2',
                '2.3.2-p2'=> '1.1.2',
                '2.3.3'=>    '1.1.3',
                '2.3.3-p1'=> '1.1.3',
                '2.3.4'=>    '1.1.4',
                '2.3.4-p1'=> '1.1.4',
                '2.3.4-p2'=> '1.1.4',
                '2.3.5'=>    '1.1.5',
                '2.3.5-p1'=> '1.1.5',
                '2.3.5-p2'=> '1.1.5-p1',
                '2.3.6'=>    '1.1.6',
                '2.3.6-p1'=> '1.1.6',
                '2.3.7'=>    '1.1.7',
                '2.3.7-p1'=> '1.1.7',
                '2.3.7-p2'=> '1.1.7',
                '2.4.0'=>    '1.2.0',
                '2.4.0-p1'=> '1.2.0-p1',
                '2.4.1'=>    '1.2.1',
                '2.4.1-p1'=> '1.2.1',
                '2.4.2'=>    '1.2.2',
                '2.4.2-p1'=> '1.2.2',
                '2.4.2-p2'=> '1.2.2',
                '2.4.3'=>    '1.2.3',
                '2.4.3-p1'=> '1.2.3-p1'],
        'pb'=>
            ['2.3.1'=>   '1.0.0',
                '2.3.2'=>    '1.0.1',
                '2.3.2-p2'=> '1.0.3',
                '2.3.3'=>    '1.1.0',
                '2.3.3-p1'=> '1.1.1',
                '2.3.4'=>    '1.2.0',
                '2.3.4-p1'=> '1.2.1',
                '2.3.4-p2'=> '1.2.2',
                '2.3.5'=>    '1.3.0',
                '2.3.5-p1'=> '1.3.1',
                '2.3.5-p2'=> '1.3.2',
                '2.3.6'=>    '1.3.3',
                '2.3.6-p1'=> '1.3.3-p1',
                '2.3.7'=>    '1.3.4',
                '2.3.7-p1'=> '1.3.4',
                '2.3.7-p2'=> '1.3.4-p1',
                '2.4.0'=>    '1.4.0',
                '2.4.0-p1'=> '1.4.1',
                '2.4.1'=>    '1.5.0',
                '2.4.1-p1'=> '1.5.1',
                '2.4.2'=>    '1.6.0',
                '2.4.2-p1'=> '1.6.0',
                '2.4.2-p2'=> '1.6.0',
                '2.4.3'=>    '1.7.0',
                '2.4.3-p1'=> '1.7.0-p1'],
        'security'=>
                ['2.4.0'=>    '1.0.0',
                '2.4.0-p1'=> '1.0.1',
                '2.4.1'=>    '1.1.0',
                '2.4.1-p1'=> '1.1.1',
                '2.4.2'=>    '1.1.1',
                '2.4.2-p1'=> '1.1.1',
                '2.4.2-p2'=> '1.1.1',
                '2.4.3'=>    '1.1.2',
                '2.4.3-p1'=> '1.1.2']
    ];

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


//create repo dir
if (!file_exists($repoPath)) {
    mkdir($repoPath, 0777, true);
}

foreach ($repositories as [$repo, $path]){
    if (!file_exists("$repoPath/$repo")) {
        mkdir("$repoPath/$repo", 0777, true);
        exec("git clone $path $repoPath/$repo");
    }
}

$diffAll = NULL;
if ($mode == 'git') {
    $diffA = $argv[1];
    $diffB = $argv[2];

    foreach ($repositories as [$repo, $path]) {

        if (in_array($repo, array('ce','ee'))){
            $mVersion = $argv[1];
        }
        else{
            $mVersion = $mapping[$repo][$diffA];
        }
        echo $mVersion;
        exec("cd $repoPath'/'$repo && git pull origin && git checkout $mVersion");
        exec("cd $repoPath'/'$repo && git checkout $diffB");
        $isBranchExist = shell_exec("cd $repoPath'/'$repo && git branch | grep $diffB");
        $diffRepo = $isBranchExist ? shell_exec("cd $repoPath'/'$repo && git diff $mVersion $diffB") : '';
        $diffAll .= $diffRepo;
    }
    $isBranchExistInCE = shell_exec("git branch | grep $diffB");
    $isBranchExistInEE = shell_exec("cd ./magento2ee && git branch | grep $diffB");

    $diffCE = $isBranchExistInCE ? shell_exec("git diff $diffA $diffB") : '';
    $diffEE = $isBranchExistInEE ? shell_exec("cd ./magento2ee && git diff $diffA $diffB") : '';
    $diff = $diffCE . $diffEE . $diffAll;
    $patchName = "{$diffB}_EE_{$diffA}_v$version";

    //remove 'AUTO_xxx' suffix
    $patchName = preg_replace('/_AUTO_\d{1,}/', '', $patchName);

    $patchGitFilename = $patchName . '.patch';
    $patchComposerFilename = $patchName . '.composer.patch';

    file_put_contents('./' . $patchGitFilename, $diff);
    $diffComposer = shell_exec( "convert-for-composer.php $patchGitFilename > $patchComposerFilename");

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