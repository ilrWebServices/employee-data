<?php

namespace IlrProfilesDataFeed;

use GuzzleHttp\Client;
use League\Csv\Reader;
use League\Csv\ResultSet;
use League\Csv\Statement;
use Psr\Log\LoggerAwareTrait;

class WorkdayFetcher {

  use LoggerAwareTrait;

  public function __construct(
    protected ?Client $guzzle = null,
    protected string $outputDir = __DIR__ . '/../output/'
  ) {
    if (!$guzzle) {
      $this->guzzle = new Client([
        'base_uri' => 'https://services1.myworkday.com/ccx/service/',
        'timeout' => 0,
        'allow_redirects' => true,
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode(getenv('WORKDAY_USER') . ':' . getenv('WORKDAY_PASS')),
        ],
      ]);
    }

    $this->setLogger(new SlackLogger(getenv('PROFILE_DATA_SLACK_WEBHOOK_URL'), 'Employee feed generator (workday fetcher)'));
  }

  public function getData(): ResultSet|false {
    $file_path = $this->outputDir . 'workday.csv';
    $cache_file_info = new \SplFileInfo($file_path);

    if ($cache_file_info->getRealPath() && $cache_file_info->getCTime() > time() - 1800) {
      $reader = Reader::createFromPath($file_path)->setHeaderOffset(0);
      return Statement::create()->orderByDesc('Primary_Job')->process($reader);
    }

    // Empty the cache file if it exists.
    if (file_exists($file_path)) {
      file_put_contents($file_path, '');
    }

    // Ensure that the cache file exists.
    touch($file_path);

    try {
      // Send a GET request and save the response directly to a file.
      $response = $this->guzzle->request('GET', 'customreport2/cornell/intsys-HRIS/CRINT256A_ILR_Job_Person?Supervisory_Organization%21WID=6af4aaa760da4e4eb0ece6722159d167!b98cf3c3a7c24b1890f7beebaf1c64e3&Include_Subordinate_Organizations=1&format=csv', [
        'sink' => $file_path,
      ]);
    }
    catch (\Exception $e) {
      $this->logger->error($e->getMessage(), [
        'Code: ' . $e->getCode()
      ]);

      unlink($file_path);
      return false;
    }

    $reader = Reader::createFromPath($file_path)->setHeaderOffset(0);
    return Statement::create()->orderByDesc('Primary_Job')->process($reader);
  }

}
