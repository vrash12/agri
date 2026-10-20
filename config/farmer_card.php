<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Farmer registry card signatories
    |--------------------------------------------------------------------------
    |
    | For offices without a saved LGU profile in Settings, the back of a farmer
    | registry card prints a signature line, with the
    | signatory's name and title beneath it, only when the farmer's municipality
    | matches an entry here. Matching uses the municipality name together with
    | its supervising province record, never the legacy province string or a
    | database ID, so the same entry resolves identically on every deployment.
    | Municipalities without an entry print no signature line.
    |
    | Cards are signed by hand after printing; no signature image is stored.
    | Saved profiles take precedence, including an explicitly blank signatory.
    | Maintain the office's current head in Settings once its profile is saved.
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
