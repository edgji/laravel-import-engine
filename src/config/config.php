<?php

return array(

    /*
	|--------------------------------------------------------------------------
	| default routing
	|--------------------------------------------------------------------------
	|
    | This package can register default import routes. If you're implementing
    | your own simply set this to false
    |
    */
    'enable_default_routing' => true,

    /*
	|--------------------------------------------------------------------------
	| default http method
	|--------------------------------------------------------------------------
	|
    | Set the default route http method for fallback if a method is an http
    | method is not defined per importer
    |
    */
    'default_http_method' => 'get',

    /*
	|--------------------------------------------------------------------------
	| routing
	|--------------------------------------------------------------------------
	|
    | This is passed to the Route::group and allows us to group and filter the
    | routes for our package
    |
    */
    'routing' => array(
        'prefix' => '/imports'
    ),

    /*
	|--------------------------------------------------------------------------
	| configure storageproviders, that are used in all importers
	|--------------------------------------------------------------------------
	|
    | //
    |
    */
    'storageprovider' => array(
        'default' => array(
            'type' => 'directory',                    #[upload, service, array, doctrine, file]
            'path' => "{app_storage}/import",
        ),
    ),

    /*
	|--------------------------------------------------------------------------
	| configure your Importers
	|--------------------------------------------------------------------------
	|
    | //
    |
    */
    'importers' => array(
        'your_importer_name' => array(
            'http_method' => 'get',

            #automaticly recognize this importer by meeting of the conditions below
            'preconditions' => array(
                'format' => 'csv',               #format of data must be [csv, excel, xml]
                //'fieldcount' => 135,                 #must have this number of fields
                //'fields' => array(                      #these fields must exist (order is irrelevant)
                //),
                'filename' => 'test.csv',      #filename must match one of these regular expression(s) (can be a list)
            ),

            #use an object-factory to convert raw row-arrays to target objects
            'object_factory' => array(
                //'type' => 'jms_serializer',        #[jms_serializer, ~]
                'class' => '\StdClass',
            ),

            #validate imported data
            // TODO implement validation
            //'validation' => array(
            //    'source' => array(                      #add constraints to source fields
            //        'header1' => 'email',
            //        'header2' => 'notempty'
            //    ),
            //    'target' => '~',                   #activate validation against generated object from object-factory (via annotations, xml)
            //),

            #target of import
            'target' => array(
                'type' => 'service',               #[service, array, doctrine, file]
                'service' => 'edgji.datadoor.importcallback',     #service name in DIC
                'method' => 'importRow',    #method to invoke on service
            ),
        ),
    )

);