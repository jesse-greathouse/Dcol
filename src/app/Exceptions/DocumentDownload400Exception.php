<?php

namespace App\Exceptions;

use Exception;

class DocumentDownload400Exception extends DocumentDownloadException
{
    // Document unable to be downloaded because it was a bad request
}
