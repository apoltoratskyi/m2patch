#!/usr/bin/env php
<?php
use Dotenv\Dotenv;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;

define('BASE_PATH',realpath(__DIR__));
require __DIR__ . '/vendor/autoload.php';
$dotEnv = Dotenv::createUnsafeImmutable(BASE_PATH);
$dotEnv->load();

$PWD = $_SERVER['PWD'];
$diffA = $diffB = false;
$script = $argv[0];
$usageHelp = "Usage: \n"
    . " php $script ACSD-666 \n"
    . " php $script ACSD-666 -v2 \n";

if (empty($argv[1])) {
    exit($usageHelp);
}

$patchFile = $PWD . '/' . $argv[1];
$patchVersion = (isset($argv[2])) ? $argv[2] : '';
$newUrls = [];
$urls = '';

try {
    $issueService = new IssueService();

    $queryParam = [
        'fields' => [  // default: '*all'
            'customfield_18505',
            'customfield_17502',
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

    $issue = $issueService->get($argv[1], $queryParam);
    $magentoVersion = $issue->fields->versions[0]->name;
    $urls = $issue->fields->customfield_13904;
    $projectPage = $issue->fields->customfield_18505;
    $projectType = strtolower($issue->fields->customfield_17502->value);
    if ($projectType != "production") {
        $projectType = "staging-5em2ouy";
    } else {
        $projectType = $projectType . "-vohbr3y";
    }

} catch (JiraRestApi\JiraException $e) {
    print('Error Occured! ' . $e->getMessage());
}

$patchGitFilename = $argv[1] . "_" . $magentoVersion . $patchVersion . ".git.patch";
$patchComposerFilename = $argv[1] . "_" . $magentoVersion . $patchVersion . ".patch";

if (strlen($urls) > 10) {
    $newUrls = convertToGitApi($urls);
    foreach ($newUrls as $newUrl) {
        file_put_contents($patchGitFilename, getPullRequestContent($newUrl), FILE_APPEND);
    }
}

$patchComposer = shell_exec( "convert-for-composer.php $patchGitFilename > $patchComposerFilename && rm $patchGitFilename");
echo "Patch file:        -----------           " . $patchComposerFilename . "         ---------------            " .  PHP_EOL;

$sshLink = sshUrl($projectPage, $projectType);
echo ("Trying to apply the patch to:  $sshLink  ---  $projectType") . PHP_EOL;
$patchApplicable = shell_exec ("cloud-patchcheck $sshLink $patchComposerFilename");
echo $patchApplicable;

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
        "Authorization: Bearer " . $_ENV['GITHUB_USER_TOKEN'],
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
