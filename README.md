# ILR People Profile Feed Generator

## Requirements

- PHP xsl extension (D7 feed only)
- [dotenv](https://github.com/ohmyzsh/ohmyzsh/tree/master/plugins/dotenv) for local development (D7 feed only)
- PHP CLI >=8.1 (CSV feed only)
- Composer

## Developer Setup

This is only required for local development. In production, environment variables will be set via the hosting configuration.

- Copy `.env.example` to `.env` and update values.
- `composer install`

## Usage

```
php pull_people_data.php  # The old source for D7.
php build_people_feed.php # The new source for Drupal persona migration.
```

## Notes

This should run every night. It will place the files `output/ilr_profiles_feed.xml` and `output/employee-feed.csv` in a location where it can be retrieved by Drupal migration processes.

### D7 data

Some of the D7 profile data has been dumped into `data/d7_profile_data.csv` using the following query on the D7 production database:

```sql
select 
--   *,
  n.nid,
  n.title,
  n.status,
  n.created,
  n.changed,
  netid.field_netid_value as netid,
  o.field_ai_overview_value as overview,
  o.field_ai_overview_format as overview_format,
  i.field_profile_image_fid as profile_image_fid,
  i.field_profile_image_title as profile_image_title,
  image_file.filename as image_filename,
  replace(image_file.uri, "public://", "https://www.ilr.cornell.edu/sites/default/files/") as image_uri,
  v.field_ilrweb_vita_file_fid as vita_file_fid,
  v.field_ilrweb_vita_file_description as vita_file_desc,
  cv_file.filename as cv_filename,
  replace(cv_file.uri, "public://", "https://archive.ilr.cornell.edu/sites/default/files/") as cv_uri
from node n
  left join field_data_field_ai_overview o on n.nid = o.entity_id and n.vid = o.revision_id
  left join field_data_field_netid netid on n.nid = netid.entity_id and n.vid = netid.revision_id
  left join field_data_field_profile_image i on n.nid = i.entity_id and n.vid = i.revision_id
  left join field_data_field_ilrweb_vita_file v on n.nid = v.entity_id and n.vid = v.revision_id
  left join file_managed image_file on i.field_profile_image_fid = image_file.fid
  left join file_managed cv_file on v.field_ilrweb_vita_file_fid = cv_file.fid
where n.type = "people_profile";
```

This is merged into the Workday source data by matching on the `netid`.
