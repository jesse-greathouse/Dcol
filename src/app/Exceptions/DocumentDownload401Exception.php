<?php

namespace App\Exceptions;

use Exception;

class DocumentDownload401Exception extends DocumentDownloadException
{
    // Document unable to be downloaded because it was unauthorized
}
