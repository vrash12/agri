<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third-party assets served from a CDN
    |--------------------------------------------------------------------------
    |
    | Every script and stylesheet this application loads from someone else's server
    | is listed here beside the hash of the exact file that was reviewed. A browser
    | refuses to run a resource whose hash does not match, so a compromised CDN, a
    | hijacked package or a silent upstream republish stops at the door rather than
    | executing inside a session that can read the farmer register.
    |
    | A pinned version and a hash are only useful together. An unversioned URL serves
    | whatever is current, and a pinned URL with no hash still trusts whoever happens
    | to be serving it today.
    |
    | To change one: update the URL, download that exact file, and record
    |
    |     openssl dgst -sha384 -binary <file> | openssl base64 -A
    |
    | prefixed with `sha384-`. tests/Feature/CdnIntegrityTest.php fails if any view
    | loads a CDN URL directly instead of going through this table.
    |
    */

    'assets' => [

        'handsontable_css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.css',
            'integrity' => 'sha384-9Mg4InPFPHKJtIpcs57KDmCPzCAAO3TwKzIoq7RMATzPh1K8iwXr9alCv3k0EBZI',
        ],

        'handsontable_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/handsontable@14.3.0/dist/handsontable.full.min.js',
            'integrity' => 'sha384-0ZIe+4MtpCrX3ZK+zB2iEvnsCoDbtMEST6waJMYqryNaNrMprptcu7RqOfxplaGj',
        ],

        'codemirror_css' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css',
            'integrity' => 'sha384-zaeBlB/vwYsDRSlFajnDd7OydJ0cWk+c2OWybl3eSUf6hW2EbhlCsQPqKr3gkznT',
        ],

        'codemirror_theme_eclipse_css' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/eclipse.min.css',
            'integrity' => 'sha384-dq10nYMe5EzlTlySS4PL3lt13I+hSWRGzpsgTbwcrbXrI3hXw2NpiZIi4vAnxjI3',
        ],

        'codemirror_js' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js',
            'integrity' => 'sha384-ZYmwuq4n2gOcNxMSiJ6jyTj+BbIrilr7p6dlq6q5nmSWKmsH9UU4K1qqjycMkfmR',
        ],

        'codemirror_mode_sql' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js',
            'integrity' => 'sha384-HxXmA1hLc56V6Ja4yfcCwAprmbnS4tuvKYS0qKG3t6oxOFMflcnYq5fOnt6wVCda',
        ],

        'codemirror_mode_javascript' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js',
            'integrity' => 'sha384-g0o+WW9mdIxA7LaaCKTkRm0M5TVT+Bb4s9eocxPsI2G0Xm0POG9iD6G6qP1IIsfS',
        ],

        'codemirror_mode_xml' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js',
            'integrity' => 'sha384-xPpkMo5nDgD98fIcuRVYhxkZV6/9Y4L8s3p0J5c4MxgJkyKJ8BJr+xfRkq7kn6Tw',
        ],

        'codemirror_mode_markdown' => [
            'url' => 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js',
            'integrity' => 'sha384-nPWqI+4+e+SwcpI6LVYBpaQd+zIZIQgC6lN7Gep8MJQr5DdmMGtWV8qJQgcyODcF',
        ],

        'xlsx_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js',
            'integrity' => 'sha384-vtjasyidUo0kW94K5MXDXntzOJpQgBKXmE7e2Ga4LG0skTTLeBi97eFAXsqewJjw',
        ],

        'docx_preview_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/docx-preview@0.1.15/dist/docx-preview.min.js',
            'integrity' => 'sha384-SEqLelR8gDRu3+XNDK4h4Kg9ie7PYMsIjiFuEt/xtLqa5j8o8R5Szx4O/WaNHvxs',
        ],

        'chart_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
            'integrity' => 'sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF',
        ],

        'tom_select_css' => [
            'url' => 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css',
            'integrity' => 'sha384-dC18+guBm9Lk5IBQfkKIOw2YSxXWMsiuPfIOZNPilutZQn6MgBvcx+vejNQYlrbc',
        ],

        'tom_select_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
            'integrity' => 'sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf',
        ],

        'jszip_js' => [
            'url' => 'https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js',
            'integrity' => 'sha384-+mbV2IY1Zk/X1p/nWllGySJSUN8uMs+gUAN10Or95UBH0fpj6GfKgPmgC5EXieXG',
        ],

        'jquery_js' => [
            'url' => 'https://code.jquery.com/jquery-3.7.1.min.js',
            'integrity' => 'sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs',
        ],

        'datatables_css' => [
            'url' => 'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
            'integrity' => 'sha384-cya1SKeBBe5YTpHgx4WoICmzqkoHTxoGqjPySUq2nLksbJ/JiKXX2RmhyLz6jwST',
        ],

        'datatables_js' => [
            'url' => 'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
            'integrity' => 'sha384-Udt767MMeKelGRBxaCfxX88YDLbViYdQ7T/gkRoB197Jf+OviZ+lsaRAOpS/MIjf',
        ],

    ],

];
