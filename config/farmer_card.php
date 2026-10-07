<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Farmer registry card signatories
    |--------------------------------------------------------------------------
    |
    | The back of a farmer registry card prints a signature line, with the
    | signatory's name and title beneath it, only when the farmer's municipality
    | matches an entry here. Matching uses the municipality name together with
    | its supervising province record, never the legacy province string or a
    | database ID, so the same entry resolves identically on every deployment.
    | Municipalities without an entry print no signature line.
    |
    | Cards are signed by hand after printing; no signature image is stored.
    | Update an entry when the office's head changes.
    |
    */

    'signatories' => [
        [
            'municipalities' => ['Ramos'],
            'province' => 'Tarlac',
            'name' => 'Engr. Dennish C. Pascua',
            'title' => 'Head Agriculturist',
        ],
    ],

];
