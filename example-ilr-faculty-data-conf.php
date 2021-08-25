<?php
define('SETTINGS', array(
	'ai_api_url' => 'https://webservices.digitalmeasures.com/login/service/v4',
	'ai_userid' => '',
	'ai_pwd' => '',
	'ldap_start' => 'ou=people,o=cornell university,c=us',
	'ldap_filter' => '(|(uid=cl672)(uid=mlc13)(uid=ldd3)(uid=mb2693)(uid=lrt4)(uid=sdm39)(uid=dca58)(uid=ajc22)(uid=kfh7)(uid=vmb2)(uid=hck2)(uid=cjm267)(uid=rss14)(uid=mfl55)(uid=bdd28)(cornelledudeptname1=LIBR - Catherwood*)(&(|(cornelledudeptname1=LIBR - Hospitality, Labor*)(cornelledudeptname1=LIBR - Management Library)(cornelledudeptname1=LIBR - ILR Catherwood Library))(cornelleducampusaddress=Ives Hall*))(cornelledudeptname1=IL-*)(cornelledudeptname1=E-*)(cornelledudeptname1=ILR*)(cornelledudeptname1=CAHRS))',
	'ldap_server' => 'directory.cornell.edu',
	'ldap_port' => '389',
	'ldap_user' => '',
	'ldap_pwd' => '',
));