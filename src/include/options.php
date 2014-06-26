<?php
/**
 * Copyright 2014 Bryan Selner
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License. You may obtain
 * a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

/****************************************************************************************************************/
/****                                                                                                        ****/
/****         Common Includes                                                                                ****/
/****                                                                                                        ****/
/****************************************************************************************************************/
define('__ROOT__', dirname(dirname(__FILE__)));

print (__ROOT__);

if (file_exists(__ROOT__ . '/../vendor/autoload.php')) {
    require_once(__ROOT__. '/../vendor/autoload.php');
} else {
    trigger_error("Composer required to run this app.");
}
require_once(__ROOT__ . '/scooper_common/scooper_common.php');
require_once(__ROOT__.'/include/fields_functions.php');
require_once(__ROOT__.'/include/plugin-base.php');
require_once(__ROOT__.'/plugins/plugin-basicfacts.php');
require_once(__ROOT__.'/plugins/plugin-crunchbase.php');
require_once(__ROOT__.'/plugins/plugin-quantcast.php');
require_once(__ROOT__.'/plugins/plugin-angellist.php');
require_once(__ROOT__.'/plugins/plugin-moz.php');
require_once(__ROOT__.'/lib/pharse.php');

date_default_timezone_set('America/Los_Angeles');


ini_set('auto_detect_line_endings', true);

$GLOBALS['OPTS'] = null;
const C__APPNAME__ = "Scooper";
const C__APP_VERSION_MAJOR___ = "0";
const C__APP_VERSION_MINOR___ = ".11";
const C__RECORD_CHUNK_SIZE__ = 5;
const C__FSHOWVERBOSE_APICALL__ = 0;

const C_LOOKUP_MODE_UNKNOWN = -1;
const C_LOOKUP_MODE_SINGLE = 1;
const C_LOOKUP_MODE_FILE = 2;
$GLOBALS['lookup_mode'] = C_LOOKUP_MODE_UNKNOWN;

const C__LOOKUP_DATATYPE_NAME__ = 1;
const C__LOOKUP_DATATYPE_URL__ = 2;
const C__LOOKUP_DATATYPE_BASICFACTS__ = 3;


/****************************************************************************************************************/
/****                                                                                                        ****/
/****         Common Declarations                                                                            ****/
/****                                                                                                        ****/
/****************************************************************************************************************/


const C__API_RETURN_TYPE_OBJECT__ = 33;
const C__API_RETURN_TYPE_ARRAY__ = 44;



/****************************************************************************************************************/
/****                                                                                                        ****/
/****         Helper Functions:  Information and Error Logging                                               ****/
/****                                                                                                        ****/
/****************************************************************************************************************/


/****************************************************************************************************************/
/****                                                                                                        ****/
/****         Helper Functions:  Available Options and Command Line Settings                                 ****/
/****                                                                                                        ****/
/****************************************************************************************************************/

function __startApp__()
{
    __debug__printSectionHeader("Getting settings.", C__NAPPFIRSTLEVEL__, C__SECTION_BEGIN__ );
    //
    // Gather and check that the command line arguments are valid
    //

    __initLogger__();
    __debug__printSectionHeader(C__APPNAME__, C__NAPPTOPLEVEL__, C__SECTION_BEGIN__);

    $strArgErrs = __check_args__();
    __debug__printLine("Options set:" . $GLOBALS['CONFIG']->printAllSettings(), C__DISPLAY_NORMAL__);
    __debug__printSectionHeader("Getting settings.", C__NAPPFIRSTLEVEL__, C__SECTION_END__ );

}


function __check_args__()
{
    $strErrOptions = "";
    $fHadFatalError = false;

    if(!$GLOBALS['OPTS']) {  __reset_args__(); }


    /****************************************************************************************************************/
    /****                                                                                                        ****/
    /****    Initialize the app and setup the options based on the command line variables                        ****/
    /****                                                                                                        ****/
    /****************************************************************************************************************/

    if($GLOBALS['OPTS']['verbose_given']) {  $GLOBALS['OPTS']['VERBOSE'] = true; } else { $GLOBALS['OPTS']['VERBOSE'] = false; }
    if($GLOBALS['OPTS']['verbose_api_calls_given']) {  define(C__FSHOWVERBOSE_APICALL__, true); } else { define(C__FSHOWVERBOSE_APICALL__, false); }
    if($GLOBALS['OPTS']['VERBOSE'] == true) { __log__ ('Options set: '.var_export($GLOBALS['OPTS'], true), LOG_INFO); }


    set_FileDetails_fromPharseSetting("use_config_ini_for_ids", 'config_file_details', true);

    /****************************************************************************************************************/
    /****                                                                                                        ****/
    /****    Get the INI file settings                                                                           ****/
    /****                                                                                                        ****/
    /****************************************************************************************************************/
    __debug__printLine("Parsing ini file ". $GLOBALS['OPTS']['config_file_details']['full_file_path']."...", C__DISPLAY_ITEM_START__);
    $GLOBALS['CONFIG'] = new ClassScooperConfigFile($GLOBALS['OPTS']['config_file_details']['full_file_path']);



    /****************************************************************************************************************/
    /****                                                                                                        ****/
    /****    Get the input and output file settings                                                              ****/
    /****                                                                                                        ****/
    /****************************************************************************************************************/
    $GLOBALS['input_file_details'] = null;
    $GLOBALS['output_file_details'] = null;


    $GLOBALS['input_file_details'] = $GLOBALS['CONFIG']->getInputFilesDetails();
    if(is_array($GLOBALS['input_file_details'])) $GLOBALS['input_file_details'] = $GLOBALS['input_file_details'][0];

    if($GLOBALS['OPTS']['inputfile_given'])
    {
        $GLOBALS['input_file_details'] = parseFilePath($GLOBALS['OPTS']['inputfile'], $GLOBALS['OPTS']['inputfile_given']);
    }
    $GLOBALS['output_file_details'] = $GLOBALS['CONFIG']->getOutputFileDetails();
    if($GLOBALS['OPTS']['outputfile_given'])
    {
        $GLOBALS['output_file_details'] = parseFilePath($GLOBALS['OPTS']['outputfile'], false);
    }
    if(strlen($GLOBALS['output_file_details']['full_file_path']) <= 0)
    {
        $strDefaultOutFileName = getDefaultFileName("_output_",$GLOBALS['input_file_details']['file_name_base'],"csv");
        $GLOBALS['output_file_details'] = parseFilePath($GLOBALS['output_file_details']['directory'] . $strDefaultOutFileName);
    }



    /****************************************************************************************************************/
    /****                                                                                                        ****/
    /****    Determine whether we're doing a single lookup or processing an input file                           ****/
    /****                                                                                                        ****/
    /****************************************************************************************************************/

    if($GLOBALS['OPTS']['lookup_name_given'] || $GLOBALS['OPTS']['lookup_url_given'])
    {
        $GLOBALS['lookup_mode'] = C_LOOKUP_MODE_SINGLE;

        if($GLOBALS['OPTS']['lookup_url_given'] && strlen($GLOBALS['OPTS']['lookup_url']) == 0 )
        {
            addToErrs($strErrOptions, "Company website URL required with --lookup_url/-lu .");
            $fHadFatalError = true;
        }
        else if($GLOBALS['OPTS']['lookup_name_given'] && strlen($GLOBALS['OPTS']['lookup_name']) == 0 )
        {
            addToErrs($strErrOptions, "Company name required with --lookup_name/-ln .");
            $fHadFatalError = true;
        }
        if(strlen($GLOBALS['output_file_details']['full_file_path']) == 0)
        {
            if(strlen($GLOBALS['output_file_details']['directory']) > 0 && strcasecmp("./", $GLOBALS['output_file_details']['directory']) != 0)
            {
                $GLOBALS['output_file_details'] = parseFilePath($GLOBALS['output_file_details']['directory'] . "/" . $strDefaultOutFileName , false);
            }
        }

        if(strlen($GLOBALS['output_file_details']['full_file_path']) == 0) // if it's still zero after all that, error
        {
            addToErrs($strErrOptions, 'Output file path required (--outputfile / -o) when using single lookup mode.');
            $fHadFatalError = true;
        }


    }
    else
    {
        $GLOBALS['lookup_mode'] = C_LOOKUP_MODE_FILE ;

        if(strlen($GLOBALS['output_file_details']['full_file_path']) == 0)
        {
            $strDefaultOutFileName = getDefaultFileName("_output_",$GLOBALS['input_file_details']['file_name_base'],"csv");
            $GLOBALS['output_file_details'] = parseFilePath($GLOBALS['output_file_details']['directory'] . $strDefaultOutFileName  );
        }

    }


    if($GLOBALS['lookup_mode'] == C_LOOKUP_MODE_FILE  && strlen($GLOBALS['input_file_details']['full_file_path']) == 0)
    {
        addToErrs($strErrOptions, 'You must specify a valid input CSV file.');

    }



    /****************************************************************************************************************/
    /****                                                                                                        ****/
    /****    get the settings for the plugins                                                                    ****/
    /****                                                                                                        ****/
    /****************************************************************************************************************/



    if($GLOBALS['OPTS']['exclude_quantcast_given'] ) {  $GLOBALS['OPTS']['exclude_quantcast'] = 1;  } else { $GLOBALS['OPTS']['exclude_quantcast'] = 0; }
    if($GLOBALS['OPTS']['exclude_angellist_given'] ) {  $GLOBALS['OPTS']['exclude_angellist'] = 1;  } else { $GLOBALS['OPTS']['exclude_angellist'] = 0; }

    if( $GLOBALS['OPTS']['exclude_moz'] != 1)
    {
        $GLOBALS['OPTS']['moz_access_id'] = $GLOBALS['CONFIG']->keys("moz_access_id");
        $GLOBALS['OPTS']['moz_secret_key'] = $GLOBALS['CONFIG']->keys("moz_secret_key");

        if(!$GLOBALS['OPTS']['exclude_moz_given'] && (strlen($GLOBALS['OPTS']['moz_access_id']) == 0 && $GLOBALS['OPTS']['moz_secret_key'] == 0)  )
        {
            if(!$GLOBALS['OPTS']['exclude_moz_given']) { __debug__printLine("Moz API access ID and secret key were not both set.  Excluding Moz.com data. ", C__DISPLAY_ITEM_DETAIL__); }
            $GLOBALS['OPTS']['exclude_moz'] = 1;
        }
        else
        {
            $GLOBALS['OPTS']['exclude_moz'] = 0;
        }
    }

    if($GLOBALS['OPTS']['exclude_crunchbase'] != 1)
    {
        $GLOBALS['OPTS']['crunchbase_api_id'] = $GLOBALS['CONFIG']->keys("crunchbase_v2_api_id");
        $GLOBALS['OPTS']['crunchbase_v1_api_id'] = $GLOBALS['CONFIG']->keys("crunchbase_v1_api_id");
        if($GLOBALS['OPTS']['exclude_crunchbase_given'] )
        {
            $GLOBALS['OPTS']['exclude_crunchbase'] = 1;
        }
        else
        {
            $GLOBALS['OPTS']['exclude_crunchbase'] = 0;
            if(!$GLOBALS['OPTS']['crunchbase_api_id_given'] && (strlen($GLOBALS['OPTS']['crunchbase_api_id']) == 0)  )
            {
                $GLOBALS['OPTS']['exclude_crunchbase'] = 1;
                __debug__printLine("No Crunchbase API Key given by the the user. Excluding Crunchbase." , C__DISPLAY_ERROR__);
            }

        }

    }

    if($fHadFatalError == true)
    {
        __log__($strErrOptions, LOG_CRIT);

        exit(PHP_EOL."Unable to run with the settings specified: ".PHP_EOL.var_export($GLOBALS['OPTS'], true).PHP_EOL."Run --help option to view the required settings.".PHP_EOL);
    }


    return $strErrOptions;

}

function __reset_args__()
{

    # specify some options
    $options = array(
        'lookup_name' => array(
            'description'   => 'The name of the company to lookup. (Requires --outputfile.)',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'ln',
        ),
        'lookup_url' => array(
            'description'   => 'The website URL for the company to lookup. (Requires --outputfile.)',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'lu',
        ),
        'crunchbase_api_url' => array(
            'description'   => 'Export a Crunchbase API call to a CSV file.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'cb',
        ),
        'inputfile' => array(
            'description'   => 'Full file path of the CSV file to use as the input data.',
            'default'       => '',
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'i'
        ),
        'outputfile' => array(
            'description'   => '(optional) Output path or full file path and name for writing the results.',
            'default'       => '',
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'o'
        ),
        'exclude_moz' => array(
            'description'   => 'Include moz.com data in the final result set.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'em',
        ),
        'exclude_quantcast' => array(
            'description'   => 'Include quantcast.com uniq visitors data in the final result set.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'eq',
        ),
        'exclude_crunchbase' => array(
            'description'   => 'Include TechCrunch\'s Crunchbase data in the final result set.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'ec',
        ),
        'exclude_angellist' => array(
            'description'   => 'Include TechCrunch\'s Crunchbase data in the final result set.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'ea',
        ),
        'moz_access_id' => array(
            'description'   => 'Your Moz.com API access ID value.  If you do not have one, Moz data will be excluded.  Learn more about Moz.com access IDs at http://moz.com/products/api.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'mozid',
        ),
        'moz_secret_key' => array(
            'description'   => 'Your Moz.com API secret key value.  If you do not have one, Moz data will be excluded.  Learn more about Moz.com access IDs at http://moz.com/products/api.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'mozkey',
        ),
        'use_config_ini_for_ids' => array(
            'description'   => 'Use this INI config file for the service ID settings',
            'default'       => 1,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'ini',
        ),
        'crunchbase_api_id' => array(
            'description'   => 'Your Crunchbase API key value.  If you do not have one, Crunchbase data will be excluded.  Learn more about Moz.com access IDs at http://developer.crunchbase.com.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_STRING,
            'required'      => false,
            'short'      => 'cbid',
        ),
        'verbose' => array(
            'description'   => 'Show debug statements and other information.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'v',
        ),
        'verbose_api_calls' => array(
            'description'   => 'Show API calls in verbose mode.',
            'default'       => 0,
            'type'          => Pharse::PHARSE_INTEGER,
            'required'      => false,
            'short'      => 'va',
        ),


    );

    # You may specify a program banner thusly:
    $banner = "Find and export basic website, Moz.com, Crunchbase and Quantcast data for any company name or URL.";
    Pharse::setBanner($banner);

    # After you've configured Pharse, run it like so:
    $GLOBALS['OPTS'] = Pharse::options($options);


    return $GLOBALS['OPTS'];
}
/*
function checkMozKey()
{

    $ret = false;

    if($GLOBALS['OPTS']['exclude_moz_given'] )
    {
        $GLOBALS['OPTS']['exclude_moz'] = 1;
        $ret = false;

    }

    if($GLOBALS['CONFIG'] == null && strlen($GLOBALS['CONFIG']["moz_access_id"] > 1) && strlen($GLOBALS['CONFIG']["moz_secret_key"] > 1))
    {
        $GLOBALS['OPTS']['moz_secret_key'] = $GLOBALS['CONFIG']['moz_secret_key'];
        $GLOBALS['OPTS']['moz_access_id'] = $GLOBALS['CONFIG']['moz_access_id'];
        $ret = true;
    }
    elseif($GLOBALS['OPTS']['moz_access_id_given'] && $GLOBALS['OPTS']['moz_secret_key_given'] )
    {
        $ret = true;

    }

    if(($GLOBALS['OPTS']['exclude_moz_given'] || $GLOBALS['OPTS']['exclude_moz'] == 1) || $ret == false)
    {
        __debug__printLine("Excluding Moz.com data: either by user request or due to missing key values. ", C__DISPLAY_ITEM_DETAIL__);
        $GLOBALS['OPTS']['exclude_moz'] = 1;
    }

    return $ret;

}

function checkCrunchbaseKey()
{
    $ret = false;
    if($GLOBALS['OPTS']['exclude_crunchbase_given'] )
    {
        $GLOBALS['OPTS']['exclude_crunchbase'] = 1;
    }

    if($GLOBALS['CONFIG'] == null && strlen($GLOBALS['CONFIG']["crunchbase_v2_api_id"] > 1))
    {
        $GLOBALS['OPTS']['crunchbase_api_id'] = $GLOBALS['CONFIG']["crunchbase_v2_api_id"];
        $ret = true;
    }
    elseif($GLOBALS['OPTS']['crunchbase_api_id_given'] && (strlen($GLOBALS['OPTS']['crunchbase_api_id']) >= 0))
    {
        $ret = true;
//            define( 'API_KEY', $GLOBALS['CONFIG']["CRUNCHBASE_V2_API_KEY"] );
//            define( 'USER_KEY', "?user_key=" . API_KEY );
    }



    if(($GLOBALS['OPTS']['exclude_crunchbase_given'] || $GLOBALS['OPTS']['exclude_crunchbase'] == 1) || $ret == false)
    {
        __debug__printLine("Excluding Crunchbase data: either by user request or due to missing key values. ", C__DISPLAY_ITEM_DETAIL__);        $GLOBALS['OPTS']['exclude_crunchbase'] = 1;
    }

    return $ret;
}


*/
