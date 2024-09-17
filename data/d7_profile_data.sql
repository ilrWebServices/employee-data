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
  replace(cv_file.uri, "public://", "https://archive.ilr.cornell.edu/sites/default/files/") as cv_uri,
  group_concat(aetd.name separator ';') as areas_of_expertise,
  oe.field_ai_other_expertise_value as other_expertise,
  l.field_ai_links_value as links_markup
from node n
  left join field_data_field_ai_overview o on n.nid = o.entity_id and n.vid = o.revision_id
  left join field_data_field_netid netid on n.nid = netid.entity_id and n.vid = netid.revision_id
  left join field_data_field_profile_image i on n.nid = i.entity_id and n.vid = i.revision_id
  left join field_data_field_ilrweb_vita_file v on n.nid = v.entity_id and n.vid = v.revision_id
  left join file_managed image_file on i.field_profile_image_fid = image_file.fid
  left join file_managed cv_file on v.field_ilrweb_vita_file_fid = cv_file.fid
  left join field_data_field_areas_of_expertise ae on n.nid = ae.entity_id and n.vid = ae.revision_id
  left join taxonomy_term_data aetd on ae.field_areas_of_expertise_tid = aetd.tid and aetd.vid = 6
  left join field_data_field_ai_other_expertise oe on n.nid = oe.entity_id and n.vid = oe.revision_id
  left join field_data_field_ai_links l on n.nid = l.entity_id and n.vid = l.revision_id
where n.type = "people_profile"
group by n.nid;
