<?php

return [
    // Runtime follows the explicitly configured application connection. The
    // M1 importer remains isolated on the `wms_migration` connection.
    'connection' => env('WMS_RUNTIME_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
    'database' => env('WMS_RUNTIME_DB_DATABASE', 'wms_mysql'),
    // No default is intentional: production multimedia migrations must name
    // the exact owner-approved target and fail closed when it is absent.
    'quiz_multimedia_migration_database' => env('WMS_QUIZ_MULTIMEDIA_MIGRATION_DATABASE'),
    'sequence_table' => 'WMS_ID_SEQUENCE',
    'allow_test_truncate' => env('WMS_ALLOW_TEST_TRUNCATE', false),
    'allow_missing_test_schema' => true,

    // Columns required by the active attendance workflows that were not
    // present in the historical Sheets header. They are additive runtime
    // metadata and are deliberately kept out of the M1 source manifest.
    'runtime_columns' => [
        'MASTER_ANNOUNCEMENT' => [
            'Audience_Type',
            'Target_Batch_ID',
            'Target_Class_ID',
            'Student_Scope',
        ],
        'MASTER_ATTENDANCE' => [
            'Employee_ID',
            'User_ID',
            'Batch_ID',
            'Session_ID',
            'Late_Minutes',
            'Verification_Method',
            'Device_Info',
        ],
        'MASTER_ATTENDANCE_REQUEST' => [
            'Class_ID',
            'Attendance_Type',
        ],
        'MASTER_PERMANENT_QR' => [
            'ATTENDANCE_START_TIME',
            'ATTENDANCE_END_TIME',
        ],
        'QUIZ_QUESTION' => [
            'Image_Path',
            'Image_Mime',
            'Image_Size_Bytes',
            'Audio_Path',
            'Audio_Mime',
            'Audio_Size_Bytes',
            'Audio_Duration_Milliseconds',
        ],
        'QUIZ_ATTEMPT' => [
            'Class_ID',
        ],
    ],
];
