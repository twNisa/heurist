<?php
require_once(dirname(__FILE__)."/../config/initialise.php");

print "if (!top.HEURIST) top.HEURIST = {};\n";
print "if (!top.HEURIST.magicNumbers) { \n top.HEURIST.magicNumbers = {";

$rtDefines = getRTDefineKeys();
$const = get_defined_constants(true);
$userDefines = $const['user'];

foreach ($rtDefines as $magicRTName) {
	print " '$magicRTName' : ".(array_key_exists($magicRTName,$userDefines)?$userDefines[$magicRTName]:"''").",\n";
}

/*
print " 'RT_INTERNET_BOOKMARK' : ".(defined('RT_INTERNET_BOOKMARK')?RT_INTERNET_BOOKMARK:"''").",\n";
print " 'RT_NOTE' : ".(defined('RT_NOTE')?RT_NOTE:"''").",\n";
print " 'RT_JOURNAL_ARTICLE' : ".(defined('RT_JOURNAL_ARTICLE')?RT_JOURNAL_ARTICLE:"''").",\n";
print " 'RT_BOOK' : ".(defined('RT_BOOK')?RT_BOOK:"''").",\n";
print " 'RT_JOURNAL_VOLUME' : ".(defined('RT_JOURNAL_VOLUME')?RT_JOURNAL_VOLUME:"''").",\n";
print " 'RT_RELATION' : ".(defined('RT_RELATION')?RT_RELATION:"''").",\n";
print " 'RT_PERSON' : ".(defined('RT_PERSON')?RT_PERSON:"''").",\n";
print " 'RT_MEDIA_RECORD' : ".(defined('RT_MEDIA_RECORD')?RT_MEDIA_RECORD:"''").",\n";
print " 'RT_AUTHOR_EDITOR' : ".(defined('RT_AUTHOR_EDITOR')?RT_AUTHOR_EDITOR:"''").",\n";
print " 'RT_BLOG_ENTRY' : ".(defined('RT_BLOG_ENTRY')?RT_BLOG_ENTRY:"''").",\n";
print " 'RT_INTERPRETATION' : ".(defined('RT_INTERPRETATION')?RT_INTERPRETATION:"''").",\n";
print " 'RT_FACTOID' : ".(defined('RT_FACTOID')?RT_FACTOID:"''").",\n";
*/

$dtDefines = getDTDefineKeys();

foreach ($dtDefines as $magicDTName) {
	print " '$magicDTName' : ".(array_key_exists($magicDTName,$userDefines)?$userDefines[$magicDTName]:"''").",\n";
}

/*
print " 'DT_TITLE' : ".(defined('DT_TITLE')?DT_TITLE:"''").",\n";
print " 'DT_GIVEN_NAMES' : ".(defined('DT_GIVEN_NAMES')?DT_GIVEN_NAMES:"''").",\n";
print " 'DT_ALTERNATE_NAME' : ".(defined('DT_ALTERNATE_NAME')?DT_ALTERNATE_NAME:"''").",\n";
print " 'DT_CREATOR' : ".(defined('DT_CREATOR')?DT_CREATOR:"''").",\n";
print " 'DT_EXTENDED_DESCRIPTION' : ".(defined('DT_EXTENDED_DESCRIPTION')?DT_EXTENDED_DESCRIPTION:"''").",\n";
print " 'DT_LINKED_RESOURCE' : ".(defined('DT_LINKED_RESOURCE')?DT_LINKED_RESOURCE:"''").",\n";
print " 'DT_RELATION_TYPE' : ".(defined('DT_RELATION_TYPE')?DT_RELATION_TYPE:"''").",\n";
print " 'DT_NOTES' : ".(defined('DT_NOTES')?DT_NOTES:"''").",\n";
print " 'DT_PRIMARY_RESOURCE' : ".(defined('DT_PRIMARY_RESOURCE')?DT_PRIMARY_RESOURCE:"''").",\n";
print " 'DT_FULL_IMAG_URL' : ".(defined('DT_FULL_IMAG_URL')?DT_FULL_IMAG_URL:"''").",\n";
print " 'DT_THUMB_IMAGE_URL' : ".(defined('DT_THUMB_IMAGE_URL')?DT_THUMB_IMAGE_URL:"''").",\n";
print " 'DT_ASSOCIATED_FILE' : ".(defined('DT_ASSOCIATED_FILE')?DT_ASSOCIATED_FILE:"''").",\n";
print " 'DT_GEO_OBJECT' : ".(defined('DT_GEO_OBJECT')?DT_GEO_OBJECT:"''").",\n";
print " 'DT_OTHER_FILE' : ".(defined('DT_OTHER_FILE')?DT_OTHER_FILE:"''").",\n";
print " 'DT_LOGO_IMAGE' : ".(defined('DT_LOGO_IMAGE')?DT_LOGO_IMAGE:"''").",\n";
print " 'DT_THUMBNAIL' : ".(defined('DT_THUMBNAIL')?DT_THUMBNAIL:"''").",\n";
print " 'DT_IMAGES' : ".(defined('DT_IMAGES')?DT_IMAGES:"''").",\n";
print " 'DT_DATE' : ".(defined('DT_DATE')?DT_DATE:"''").",\n";
print " 'DT_START_DATE' : ".(defined('DT_START_DATE')?DT_START_DATE:"''").",\n";
print " 'DT_END_DATE' : ".(defined('DT_END_DATE')?DT_END_DATE:"''").",\n";
print " 'DT_INTERPRETATION_REFERENCE' : ".(defined('DT_INTERPRETATION_REFERENCE')?DT_INTERPRETATION_REFERENCE:"''").",\n";
print " 'DT_DOI' : ".(defined('DT_DOI')?DT_DOI:"''").",\n";
print " 'DT_WEBSITE_ICON' : ".(defined('DT_WEBSITE_ICON')?DT_WEBSITE_ICON:"''").",\n";
print " 'DT_ISBN' : ".(defined('DT_ISBN')?DT_ISBN:"''").",\n";
print " 'DT_ISSN' : ".(defined('DT_ISSN')?DT_ISSN:"''").",\n";
print " 'DT_JOURNAL_REFERENCE' : ".(defined('DT_JOURNAL_REFERENCE')?DT_JOURNAL_REFERENCE:"''").",\n";
print " 'DT_SHORT_SUMMARY' : ".(defined('DT_SHORT_SUMMARY')?DT_SHORT_SUMMARY:"''").",\n";
print " 'DT_MEDIA_REFERENCE' : ".(defined('DT_MEDIA_REFERENCE')?DT_MEDIA_REFERENCE:"''").",\n";
print " 'DT_TEI_DOCUMENT_REFERENCE' : ".(defined('DT_TEI_DOCUMENT_REFERENCE')?DT_TEI_DOCUMENT_REFERENCE:"''").",\n";
print " 'DT_START_ELEMENT' : ".(defined('DT_START_ELEMENT')?DT_START_ELEMENT:"''").",\n";
print " 'DT_END_ELEMENT' : ".(defined('DT_END_ELEMENT')?DT_END_ELEMENT:"''").",\n";
print " 'DT_START_WORD' : ".(defined('DT_START_WORD')?DT_START_WORD:"''").",\n";
print " 'DT_MIME_TYPE' : ".(defined('DT_MIME_TYPE')?DT_MIME_TYPE:"''").",\n";
print " 'DT_SERVICE_URL' : ".(defined('DT_SERVICE_URL')?DT_SERVICE_URL:"''").",\n";
print " 'DT_MAP_IMAGE_LAYER_SCHEMA' : ".(defined('DT_MAP_IMAGE_LAYER_SCHEMA')?DT_MAP_IMAGE_LAYER_SCHEMA:"''").",\n";
print " 'DT_KML_FILE' : ".(defined('DT_KML_FILE')?DT_KML_FILE:"''").",\n";
print " 'DT_TITLE_SHORT' : ".(defined('DT_TITLE_SHORT')?DT_TITLE_SHORT:"''").",\n";
print " 'DT_KML' : ".(defined('DT_KML')?DT_KML:"''").",\n";
print " 'DT_MINMUM_ZOOM_LEVEL' : ".(defined('DT_MINMUM_ZOOM_LEVEL')?DT_MINMUM_ZOOM_LEVEL:"''").",\n";
print " 'DT_MAP_IMAGE_LAYER_REFERENCE' : ".(defined('DT_MAP_IMAGE_LAYER_REFERENCE')?DT_MAP_IMAGE_LAYER_REFERENCE:"''").",\n";
print " 'DT_MAXIMUM_ZOOM_LEVEL' : ".(defined('DT_MAXIMUM_ZOOM_LEVEL')?DT_MAXIMUM_ZOOM_LEVEL:"''")."};};\n";
*/

print "if (top != window && !window.HEURIST) window.HEURIST = {};\n";
print "if (!window.HEURIST.magicNumbers) { \n window.HEURIST.magicNumbers = top.HEURIST.magicNumbers;\n};";

?>