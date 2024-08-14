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
   * Return an employee roll for some given workday values.
   */
  protected function getRole(string $netid, string $job_family_group, string $job_families, string $job_profile_name): string|false {
    if ($job_family_group === 'Faculty') {
      return 'faculty';
    }
    elseif ($job_family_group === 'Faculty Modifier') {
      return 'faculty';
    }
    elseif ($job_family_group === 'Post Graduate') {
      return 'academic';
    }
    elseif ($job_family_group === 'RTE Faculty') {
      return 'academic';
    }
    elseif ($job_family_group === 'RTE Faculty Modifier') {
      return 'academic';
    }
    elseif ($job_family_group === 'Staff') {
      return 'staff';
    }
    elseif ($job_family_group === 'Temporary') {
      return 'staff';
    }
    elseif ($job_family_group === 'Union') {
      $academic_profiles = [
        'Extension Supp Spec C06',
        'Research Support Spec C06',
        'Research Support Spec C07'
      ];

      if ($job_families === 'Communication Wkrs of America' && in_array($job_profile_name, $academic_profiles)) {
        return 'academic';
      }

      return 'staff';
    }

    // Log our failure to find a role, but only if the family group is not
    // 'Student' or 'Grad Students Group'.
    switch ($job_family_group) {
      case 'Student':
      case 'Grad Students Group':
        break;
      default:
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
