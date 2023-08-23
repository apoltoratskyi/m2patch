#!/usr/bin/env php
<?php

define('BASE_PATH',realpath(__DIR__));

const GIT_TOKEN = '';
const JIRA_HOST="";
const JIRA_USER="";
const JIRA_PASS="";
const TEST_ON_CLOUD = "NO";

$PWD = $_SERVER['PWD'];
$diffA = $diffB = false;
$script = $argv[0];
$usageHelp = "Usage: \n"
    . " php $script ACSD-6661 \n"
    . " php $script ACSD-6661 _v2 \n"
    . " php $script ACSD-6661 _DEBUG \n";

if (empty($argv[1])) {
    exit($usageHelp);
}

$patchFile = $PWD . '/' . $argv[1];
$patchVersion = (isset($argv[2])) ? $argv[2] : '';
$newUrls = [];
$urls = '';

// get ticket info from jira
function getTicket($ticketId) {
// cURL initialization
    $ch = curl_init(JIRA_HOST.'/rest/api/2/issue/'.$ticketId);

// Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_USERPWD, JIRA_USER.":".JIRA_PASS);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

// Execute cURL request
    $response = curl_exec($ch);

// Check for errors
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

// Close cURL session
    curl_close($ch);

    return json_decode($response, true);
}

// get magento version from Jira ticket
function getVersion($response) {

    return $response['fields']['versions'][0]['name'];
}

// get $urls of pull requests

function getGitUrl($response) {

    return $response['fields']['customfield_13904'];
}

// get project url
function getProjectUrl($response) {

    return $response['fields']['customfield_18505'];
}

// get project env type (stg/prd)
function getProjectType($response) {
    return $response['fields']['customfield_17502']['value'];
}

function convertToGitApi($pulls) {

    preg_match_all('#(https://)(github.com)(/magento-sparta/)(.+?)(/pull)(/\d+)#', $pulls, $match);
    foreach ($match[0] as $url) {
        if($url && strlen($url) > 10){
            echo $url . '       - included'. PHP_EOL;
            $newUrl = preg_replace_callback('#(https://)(github.com)(/magento-sparta/)(.+?)(/pull)(/\d+)#', function($match) {
                return $match[1] . 'api.' . $match[2] . '/repos' . $match[3] . $match[4] . $match[5] . 's' . $match[6];
            }, $url);
            $newUrls[] = $newUrl;
        }
    }
    return $newUrls;
}

function getPullRequestContent($pullRequests) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pullRequests);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_HTTPHEADER, array(
        "Accept: application/vnd.github.v3.diff",
        "Authorization: Bearer " . GIT_TOKEN,
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36"
    ));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function sshUrl($projectPage, $projectType) {
    if($projectPage) {
        preg_match('#(https://)(\w+-\d)(\.magento.cloud)(/projects/)(\w+)#', $projectPage, $match);
        return '1.ent-' . $match[5] . '-' . $projectType . '@ssh.' . $match[2] . '.magento.cloud';
    }
}

// execute

$response = getTicket($argv[1]);
$patchGitFilename = $argv[1] . "_" . getVersion($response) . $patchVersion . ".git.patch";
$patchComposerFilename = $argv[1] . "_" . getVersion($response) . $patchVersion . ".patch";

if (strlen(getGitUrl($response)) > 10) {
    $newUrls = convertToGitApi(getGitUrl($response));
    foreach ($newUrls as $newUrl) {
        file_put_contents($patchGitFilename, getPullRequestContent($newUrl), FILE_APPEND);
    }
}

$patchComposer = shell_exec( "m2-convert-for-composer $patchGitFilename > $patchComposerFilename && rm $patchGitFilename");
echo "Patch file:        -----------           " . $patchComposerFilename . "         ---------------            " .  PHP_EOL;

if (TEST_ON_CLOUD === "YES") {
    $sshLink = sshUrl(getProjectUrl($response), getProjectType($response));
    echo ("Trying to apply the patch to:  $sshLink  ---  ".getProjectType($response)) . PHP_EOL;
    $patchApplicable = shell_exec ("cloud-patchcheck $sshLink $patchComposerFilename");
    echo $patchApplicable;
}
