<?php

namespace IlrProfilesDataFeed;

use League\Csv\Writer;
use Psr\Log\LoggerAwareTrait;

class FeedWriterBase {

  use LoggerAwareTrait;

  protected int $recordCount = 0;
  protected Writer $writer;

  public function __construct() {
    $tmpfile = tmpfile();
    $this->writer = Writer::createFromStream($tmpfile);
  }

  public function addRecord(array $record): int {
    $retval = $this->writer->insertOne($record);
    $this->recordCount++;
    return $retval;
  }

  public function getRecordCount(): int {
    return $this->recordCount;
  }

  public function saveFile(string $filename): void {
    copy($this->writer->getPathname(), $filename);
  }

  /**
   * Return an employee role for some given workday values.
   */
  protected function getRole(string $netid, string $job_family_group, string $job_families, string $job_profile_name): string|false {
    try {
      return match (TRUE) {
        $job_family_group === 'Faculty' => 'Faculty',
        $job_family_group === 'Faculty Modifier' => 'Emeriti faculty',
        $job_family_group === 'Post Graduate' => 'Academic staff',
        $job_family_group === 'RTE Faculty' && $job_families === 'Research' && $job_profile_name === 'Research Professor' => 'Faculty',
        $job_family_group === 'RTE Faculty' && $job_families === 'Research' && strpos($job_profile_name, 'Librarian') !== FALSE => 'Academic staff',
        $job_family_group === 'RTE Faculty' && $job_families === 'Research' && strpos($job_profile_name, 'Archivist') !== FALSE => 'Academic staff',
        $job_family_group === 'RTE Faculty' && $job_families === 'Research' && strpos($job_profile_name, 'Research Associate') !== FALSE => 'Outreach faculty',
        $job_family_group === 'RTE Faculty' && $job_families === 'Teaching' && $job_profile_name === 'Instructor' => 'Academic staff',
        $job_family_group === 'RTE Faculty' && $job_families === 'Teaching' => 'Faculty',
        $job_family_group === 'RTE Faculty' => 'Outreach faculty',
        $job_family_group === 'RTE Faculty Modifier' && $job_families === 'Rsrch' && strpos($job_profile_name, 'Fellow') !== FALSE => false,
        $job_family_group === 'RTE Faculty Modifier' && $job_families === 'Rsrch' => 'Academic staff',
        $job_family_group === 'RTE Faculty Modifier' && $job_families === 'Teach' => 'Academic staff',
        $job_family_group === 'Staff' => 'Staff',
        $job_family_group === 'Temporary' => 'Staff',
        $job_family_group === 'Union' => 'Staff',
        $job_family_group === 'Student' => false,
        $job_family_group === 'Grad Students Group' => false,
      };
    }
    catch (\UnhandledMatchError $e) {
      // Log our failure to find a role, but only if the family group is not in
      // the ignored family groups (.e.g 'Student' or 'Grad Students Group').
      $message = 'Skipped ' . $netid . ' because of missing role.';
      $this->logger->error(message: $message, context: [
        'Job_Family_Group: ' . $job_family_group,
        'Job_Families: ' . $job_families,
        'Job_Profile_Name: ' . $job_profile_name,
      ]);
    }

    return false;
  }

}
