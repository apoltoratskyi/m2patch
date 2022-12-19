#!/usr/bin/env php
<?php
require 'vendor/autoload.php';
use Dotenv\Dotenv;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;

$PWD = $_SERVER['PWD'];
//$dotenv = Dotenv::createImmutable(__DIR__);
//$dotenv->load();

$diffA = $diffB = false;
$script = $argv[0];

$usageHelp = "Usage: \n"
    . " php $script ACSD-666 \n"
    . " php $script 2.1.6 ACSD-666 -v2 \n";

if (empty($argv[1])) {
    exit($usageHelp);
}

$patchFile = $PWD . '/' . $argv[1];
$patchVersion = !empty($argv[3]) && preg_match('/\d+/', $argv[3], $matches) ? (int)$matches[0] : 0;
$newUrls = [];
$urls = '';

try {
    $issueService = new IssueService();

    $queryParam = [
        'fields' => [  // default: '*all'
            'customfield_13904',
            'versions'
        ],
        'expand' => [
            'renderedFields',
            'names',
            'schema',
            'transitions',
            'operations',
            'editmeta',
            'changelog',
        ]
    ];

    $issue = $issueService->get('ACSD-48293', $queryParam);
    $magentoVersion = $issue->fields->versions[0]->name;
    $urls = $issue->fields->customfield_13904;

} catch (JiraRestApi\JiraException $e) {
    print('Error Occured! ' . $e->getMessage());
}

if (strlen($urls) > 10) {
    $newUrls = convertToGitApi($urls);
    foreach ($newUrls as $newUrl) {
        $trr = $newUrl;
        file_put_contents($argv[1] . "-" . $magentoVersion . ".patch", getPullRequestContent($newUrl), FILE_APPEND);
    }
}


function convertToGitApi($pulls) {

    preg_match_all('#(https://)(github.com)(/magento-sparta/)(\w+)(/pull)(/\d+)#', $pulls, $match);
    foreach ($match[0] as $url) {
        if($url && strlen($url) > 10){
            $newUrl = preg_replace_callback('#(https://)(github.com)(/magento-sparta/)(\w+)(/pull)(/\d+)#', function($match) {
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
        "Authorization: Bearer " . $_ENV['GITHUB_USER_TOKEN'],
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36"
    ));
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}