<?php

return array(

    #configure storageproviders, that are used in all importers
    'storageprovider' => [
        'default' => [
            'type' => 'upload',                    #[upload, service, array, doctrine, file]
            'path' => "%kernel.root_dir%/Resources/import",
        ],
    ],

    #configure your Importers
    'importers' => [
        'your_importer_name' => [

            #automaticly recognize this importer by meeting of the conditions below
            'preconditions' => [
                'format' => 'excel',               #format of data must be [csv, excel, xml]
                'fieldcount' => 2,                 #must have this number of fields
                'fields' => [                      #these fields must exist (order is irrelevant)
                    'header2',
                    'header1',
                ],
                'fieldset' => [                    #all fields must exist exactly this order
                    'header1',
                    'header2',
                ],
                'filename' => 'somefile.xls',      #filename must match one of these regular expression(s) (can be a list)
            ],

            #use an object-factory to convert raw row-arrays to target objects
            'object_factory' => [
                'type' => 'jms_serializer',        #[jms_serializer, ~]
                'class' => 'Acme\DemoBundle\ValueObject\MyImportedRow',
            ],

            #validate imported data
            'validation' => [
                'source' => [                      #add constraints to source fields
                    'header1' => 'email',
                    'header2' => 'notempty'
                ],
                'target' => '~',                   #activate validation against generated object from object-factory (via annotations, xml)
            ],                                     #or supply list of constraints like in source

    #target of import
            'target' => [
                'type' => 'service',               #[service, array, doctrine, file]
                'service' => 'import_service',     #service name in DIC
                'method' => 'processImportRow',    #method to invoke on service
            ],
        ],
    ]

);