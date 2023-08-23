#!/usr/bin/env php
<?php

// Load environment variables from .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $envVariables = parse_ini_file($envPath);
} else {
    echo "No .env file found. Make sure you have set up your environment variables.";
    exit(1);
}

// Jira custom fields
$gitPullRequestField = 'customfield_13904';
$projectPageField = 'customfield_18505';
$environmentTypeField = 'customfield_17502';
$gitRepo = 'magento-sparta';

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
function getTicket($ticketId, $envVariables) {
// cURL initialization
    $ch = curl_init($envVariables['JIRA_HOST'].'/rest/api/2/issue/'.$ticketId);

// Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_USERPWD, $envVariables['JIRA_USER'].":".$envVariables['JIRA_PASS']);
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

function getGitUrl($response, $gitPullRequestField) {

    return $response['fields'][$gitPullRequestField];
}

// get project url
function getProjectUrl($response, $projectPageField) {

    return $response['fields'][$projectPageField];
}

// get project env type (stg/prd)
function getProjectType($response, $environmentTypeField) {
    return $response['fields'][$environmentTypeField]['value'];
}

function convertToGitApi($pulls, $gitRepo) {

    preg_match_all('#(https://)(github.com)(/'.$gitRepo.'/)(.+?)(/pull)(/\d+)#', $pulls, $match);
    foreach ($match[0] as $url) {
        if($url && strlen($url) > 10){
            echo $url . '       - included'. PHP_EOL;
            $newUrl = preg_replace_callback('#(https://)(github.com)(/'.$gitRepo.'/)(.+?)(/pull)(/\d+)#', function($match) {
                return $match[1] . 'api.' . $match[2] . '/repos' . $match[3] . $match[4] . $match[5] . 's' . $match[6];
            }, $url);
            $newUrls[] = $newUrl;
        }
    }
    return $newUrls;
}

function getPullRequestContent($pullRequests, $envVariables) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $pullRequests);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch,CURLOPT_HTTPHEADER, array(
        "Accept: application/vnd.github.v3.diff",
        "Authorization: Bearer " . $envVariables['GIT_TOKEN'],
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
$response = getTicket($argv[1], $envVariables);
$patchGitFilename = $argv[1] . "_" . getVersion($response) . $patchVersion . ".git.patch";
$patchComposerFilename = $argv[1] . "_" . getVersion($response) . $patchVersion . ".patch";

if (strlen(getGitUrl($response,$gitPullRequestField)) > 10) {
    $newUrls = convertToGitApi(getGitUrl($response, $gitPullRequestField), $gitRepo);
    foreach ($newUrls as $newUrl) {
        file_put_contents($patchGitFilename, getPullRequestContent($newUrl, $envVariables), FILE_APPEND);
    }
}

$patchComposer = shell_exec( "m2-convert-for-composer $patchGitFilename > $patchComposerFilename && rm $patchGitFilename");
echo "Patch file:        -----------           " . $patchComposerFilename . "         ---------------            " .  PHP_EOL;

if ($envVariables['TEST_ON_CLOUD'] === "YES") {
    $sshLink = sshUrl(getProjectUrl($response, $projectPageField), getProjectType($response, $environmentTypeField));
    echo ("Trying to apply the patch to:  $sshLink  ---  ".getProjectType($response, $environmentTypeField)) . PHP_EOL;
    $patchApplicable = shell_exec ("cloud-patchcheck $sshLink $patchComposerFilename");
    echo $patchApplicable;
}
